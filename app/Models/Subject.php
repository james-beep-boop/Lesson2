<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function subjectGrades(): HasMany
    {
        return $this->hasMany(SubjectGrade::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
