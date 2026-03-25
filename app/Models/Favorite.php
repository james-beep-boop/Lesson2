<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_plan_family_id',
        'lesson_plan_version_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'lesson_plan_family_id' => 'integer',
            'lesson_plan_version_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(LessonPlanFamily::class, 'lesson_plan_family_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LessonPlanVersion::class, 'lesson_plan_version_id');
    }
}
