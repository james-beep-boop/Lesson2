<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LessonPlanFamily extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_grade_id',
        'day',
        'strand_number',
        'strand_name',
        'substrand_number',
        'substrand_name',
    ];

    protected function casts(): array
    {
        return [
            'subject_grade_id' => 'integer',
            'official_version_id' => 'integer',
            'strand_number' => 'integer',
            'substrand_number' => 'integer',
        ];
    }

    public function subjectGrade(): BelongsTo
    {
        return $this->belongsTo(SubjectGrade::class);
    }

    public function officialVersion(): BelongsTo
    {
        return $this->belongsTo(LessonPlanVersion::class, 'official_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LessonPlanVersion::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(LessonPlanVersion::class)->latestOfMany();
    }
}
