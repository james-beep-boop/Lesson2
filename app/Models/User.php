<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_system' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->hasRole('site_administrator') && $this->hasVerifiedEmail();
        }

        // app panel — all non-system users may access the auth / verification flow.
        // Protected panel pages are still gated by EnsureEmailIsVerified middleware.
        return ! $this->is_system;
    }

    public function subjectGrades(): BelongsToMany
    {
        return $this->belongsToMany(SubjectGrade::class, 'subject_grade_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function subjectGradesAsAdmin(): HasMany
    {
        return $this->hasMany(SubjectGrade::class, 'subject_admin_user_id');
    }

    /** Backwards-compatible alias; prefer subjectGradesAsAdmin(). */
    public function subjectGradeAsAdmin(): HasMany
    {
        return $this->subjectGradesAsAdmin();
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'from_user_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'to_user_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function lessonPlanVersions(): HasMany
    {
        return $this->hasMany(LessonPlanVersion::class, 'contributor_id');
    }

    public function roleInSubjectGrade(SubjectGrade $subjectGrade): ?string
    {
        if ((int) $subjectGrade->subject_admin_user_id === $this->id) {
            return 'subject_admin';
        }

        $pivot = $this->subjectGrades()
            ->where('subject_grade_id', $subjectGrade->id)
            ->first();

        return $pivot?->pivot?->role;
    }

    public function isSiteAdmin(): bool
    {
        return $this->hasRole('site_administrator');
    }

    /**
     * Returns true if removing this user as site admin would leave zero
     * non-system site administrators. Used to guard against lockout in both
     * UserResource (admin panel) and UsersWidget (app panel).
     */
    public static function isLastSiteAdmin(self $user): bool
    {
        return ! static::role('site_administrator')
            ->where('is_system', false)
            ->where('id', '!=', $user->id)
            ->exists();
    }

    public function isSubjectAdminFor(SubjectGrade $subjectGrade): bool
    {
        return (int) $subjectGrade->subject_admin_user_id === $this->id;
    }

    public function isEditorFor(SubjectGrade $subjectGrade): bool
    {
        if ($this->relationLoaded('subjectGrades')) {
            return $this->subjectGrades
                ->where('id', $subjectGrade->id)
                ->where('pivot.role', 'editor')
                ->isNotEmpty();
        }

        return $this->subjectGrades()
            ->where('subject_grade_id', $subjectGrade->id)
            ->wherePivot('role', 'editor')
            ->exists();
    }

    public function canEditSubjectGrade(SubjectGrade $subjectGrade): bool
    {
        return $this->isSiteAdmin()
            || $this->isSubjectAdminFor($subjectGrade)
            || $this->isEditorFor($subjectGrade);
    }

    /**
     * Role label for the user avatar dropdown.
     * Priority: Site Administrator → Subject Administrator → Editor → Teacher (default / no role).
     * Cached by Eloquent's Attribute caching (shouldCache) to avoid repeated DB hits.
     */
    protected function roleLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->isSiteAdmin()) {
                    return 'Site Administrator';
                }

                $isSubjectAdmin = $this->relationLoaded('subjectGradesAsAdmin')
                    ? $this->subjectGradesAsAdmin->isNotEmpty()
                    : SubjectGrade::where('subject_admin_user_id', $this->id)->exists();

                if ($isSubjectAdmin) {
                    return 'Subject Administrator';
                }

                $isEditor = $this->relationLoaded('subjectGrades')
                    ? $this->subjectGrades->where('pivot.role', 'editor')->isNotEmpty()
                    : $this->subjectGrades()->wherePivot('role', 'editor')->exists();

                return $isEditor ? 'Editor' : 'Teacher';
            }
        )->shouldCache();
    }

    /** @var array<int, string>|null */
    private ?array $detailedRoleLabelsCache = null;

    /**
     * @return array<int, string>
     */
    public function detailedRoleLabels(): array
    {
        if ($this->detailedRoleLabelsCache !== null) {
            return $this->detailedRoleLabelsCache;
        }

        $labels = [];

        if ($this->isSiteAdmin()) {
            $labels[] = 'Site Administrator';
        }

        $subjectAdminGrades = $this->relationLoaded('subjectGradesAsAdmin')
            ? $this->subjectGradesAsAdmin->loadMissing('subject')
            : $this->subjectGradesAsAdmin()->with('subject')->get();

        if ($subjectAdminGrades->isNotEmpty()) {
            $labels[] = 'Subject Admin — '.$subjectAdminGrades
                ->map(fn (SubjectGrade $subjectGrade): string => $subjectGrade->subject->name.' Grade '.$subjectGrade->grade)
                ->join(', ');
        }

        $editorGrades = $this->relationLoaded('subjectGrades')
            ? $this->subjectGrades->loadMissing('subject')
            : $this->subjectGrades()->with('subject')->get();

        $editorGrades = $editorGrades
            ->where('pivot.role', 'editor')
            ->reject(fn (SubjectGrade $subjectGrade): bool => (int) $subjectGrade->subject_admin_user_id === $this->id);

        if ($editorGrades->isNotEmpty()) {
            $labels[] = 'Editor — '.$editorGrades
                ->map(fn (SubjectGrade $subjectGrade): string => $subjectGrade->subject->name.' Grade '.$subjectGrade->grade)
                ->join(', ');
        }

        return $this->detailedRoleLabelsCache = $labels === [] ? ['Teacher'] : $labels;
    }

    protected function detailedRoleLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => implode(' | ', $this->detailedRoleLabels())
        )->shouldCache();
    }
}
