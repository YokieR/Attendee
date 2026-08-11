<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Student extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'registration_number',
        'full_name',
        'email',
        'department',
        'programme',
        'current_semester',
        'faculty',
        'password',
        'is_verified',
        'verification_code',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_verified' => 'boolean',
    ];

    protected $appends = ['role', 'name'];

    public function getRoleAttribute()
    {
        return 'student';
    }

    public function getNameAttribute()
    {
        return $this->full_name;
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_student', 'student_id', 'course_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }
}
