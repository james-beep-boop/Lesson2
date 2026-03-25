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
}
