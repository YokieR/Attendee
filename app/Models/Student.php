<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'registration_number',
        'full_name',
        'email',
        'department',
        'programme',
        'current_semester',
        'faculty',
        'password',
        'device_uuid',
        'profile_picture_url',
        'is_verified',
        'verification_code',
        'account_status',
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
