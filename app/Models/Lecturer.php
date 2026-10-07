<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Database\Eloquent\SoftDeletes;

class Lecturer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'staff_number',
        'email',
        'phone_number',
        'department',
        'faculty',
        'office_location',
        'current_semester',
        'profile_picture_url',
        'password',
        'is_verified',
        'verification_code',
        'account_status',
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

    protected $appends = ['role', 'phoneNumber', 'coursesAssigned', 'registration_number', 'staffNumber', 'currentSemester', 'officeLocation', 'full_name'];

    public function getRoleAttribute()
    {
        return 'lecturer';
    }

    public function getFullNameAttribute()
    {
        return $this->name;
    }

    public function getRegistrationNumberAttribute()
    {
        return ($this->attributes['staff_number'] ?? null) ?? ('LEC/' . $this->id);
    }

    public function getStaffNumberAttribute()
    {
        return $this->attributes['staff_number'] ?? null;
    }

    public function getCurrentSemesterAttribute()
    {
        return $this->attributes['current_semester'] ?? null;
    }

    public function getOfficeLocationAttribute()
    {
        return $this->attributes['office_location'] ?? null;
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

    public function timetables()
    {
        return $this->hasMany(Timetable::class);
    }

    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }
}
