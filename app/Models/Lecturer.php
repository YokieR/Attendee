<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Lecturer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'department',
        'faculty',
        'profile_picture_url',
        'password',
        'is_verified',
        'verification_code',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_verified' => 'boolean',
    ];

    protected $appends = ['role', 'phoneNumber', 'coursesAssigned', 'registration_number'];

    public function getRoleAttribute()
    {
        return 'lecturer';
    }

    public function getRegistrationNumberAttribute()
    {
        return 'LEC/' . $this->id;
    }

    public function getPhoneNumberAttribute()
    {
        return $this->attributes['phone_number'] ?? null;
    }

    public function getCoursesAssignedAttribute()
    {
        return $this->courses->pluck('name')->implode(', ');
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_lecturer', 'lecturer_id', 'course_id');
    }

    public function sessions()
    {
        return $this->hasMany(AttendanceSession::class, 'lecturer_id');
    }
}
