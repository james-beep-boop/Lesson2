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
     * Format: SUBJ_Grade_StrandNum_SubstrandNum_REV_Major.Minor.Patch.md
     * Example: ENGL_10_1_1_REV_1.0.0.md
     * Requires family.subjectGrade.subject to be loaded.
     */
    public function getFilename(): string
    {
        $subject = strtoupper(substr($this->family->subjectGrade->subject->name, 0, 4));
        $grade = $this->family->subjectGrade->grade;
        $strandNum = $this->family->strand_number ?? 0;
        $substrandNum = $this->family->substrand_number ?? 0;

        return "{$subject}_{$grade}_{$strandNum}_{$substrandNum}_REV_{$this->version}.md";
    }
}
