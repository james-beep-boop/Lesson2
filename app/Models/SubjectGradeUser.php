<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SubjectGradeUser extends Pivot
{
    use HasFactory;

    public $incrementing = true;

    protected $table = 'subject_grade_user';

    protected $fillable = [
        'subject_grade_id',
        'user_id',
        'role',
    ];

    public function subjectGrade(): BelongsTo
    {
        return $this->belongsTo(SubjectGrade::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
