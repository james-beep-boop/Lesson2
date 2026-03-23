<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    protected $fillable = [
        'username',
        'name',
        'email',
        'email_verified_at',
        'password',
        'is_system',
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

        // app panel — all non-system verified users
        return ! $this->is_system && $this->hasVerifiedEmail();
    }

    public function subjectGrades(): BelongsToMany
    {
        return $this->belongsToMany(SubjectGrade::class, 'subject_grade_user')
            ->withPivot('role')
            ->withTimestamps();
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

    public function isSubjectAdminFor(SubjectGrade $subjectGrade): bool
    {
        return (int) $subjectGrade->subject_admin_user_id === $this->id;
    }

    public function isEditorFor(SubjectGrade $subjectGrade): bool
    {
        return $this->subjectGrades()
            ->where('subject_grade_id', $subjectGrade->id)
            ->where('role', 'editor')
            ->exists();
    }

    public function canEditSubjectGrade(SubjectGrade $subjectGrade): bool
    {
        return $this->isSiteAdmin()
            || $this->isSubjectAdminFor($subjectGrade)
            || $this->isEditorFor($subjectGrade);
    }

    /**
     * Single-word role label for the user avatar dropdown.
     * Priority: Site Admin → Subject Admin → Editor → Teacher (default / no role).
     */
    public function getRoleLabel(): string
    {
        if ($this->isSiteAdmin()) {
            return 'Administrator';
        }

        if (SubjectGrade::where('subject_admin_user_id', $this->id)->exists()) {
            return 'Subject Admin';
        }

        if ($this->subjectGrades()->wherePivot('role', 'editor')->exists()) {
            return 'Editor';
        }

        return 'Teacher';
    }
}
