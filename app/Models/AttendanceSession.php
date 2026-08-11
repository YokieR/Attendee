<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    protected $fillable = [
        'course_id', 'lecturer_id', 'room_name', 'expires_at', 'is_active', 'geofence'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class, 'lecturer_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'session_id');
    }
}
