<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonPlanVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_plan_family_id',
        'content',
        'revision_note',
    ];

    protected function casts(): array
    {
        return [
            'lesson_plan_family_id' => 'integer',
            'contributor_id' => 'integer',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(LessonPlanFamily::class, 'lesson_plan_family_id');
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributor_id');
    }

    public function deletionRequests(): HasMany
    {
        return $this->hasMany(DeletionRequest::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'lesson_plan_version_id');
    }

    public function isOfficial(): bool
    {
        return $this->family && (int) $this->family->official_version_id === $this->id;
    }

    /**
     * Generate the canonical filename for this version.
     * Format: SUBJ_Grade_Day_REV_Major.Minor.Patch.md
     * Example: ENGL_10_1_REV_1.1.1.md
     *
     * Relations are eager-loaded automatically if not already present.
     */
    public function getFilename(): string
    {
        if (! $this->relationLoaded('family')) {
            $this->load('family.subjectGrade.subject');
        } elseif (! $this->family->relationLoaded('subjectGrade')) {
            $this->family->load('subjectGrade.subject');
        } elseif (! $this->family->subjectGrade->relationLoaded('subject')) {
            $this->family->subjectGrade->load('subject');
        }

        $subject = strtoupper(substr($this->family->subjectGrade->subject->name, 0, 4));
        $grade = $this->family->subjectGrade->grade;
        $day = $this->family->day;

        return "{$subject}_{$grade}_{$day}_REV_{$this->version}.md";
    }
}
