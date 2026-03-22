<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'grade',
        'subject_admin_user_id',
    ];

    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'grade' => 'integer',
            'subject_admin_user_id' => 'integer',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function subjectAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_admin_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_grade_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function lessonPlanFamilies(): HasMany
    {
        return $this->hasMany(LessonPlanFamily::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->subject->name . ' — Grade ' . $this->grade;
    }
}
