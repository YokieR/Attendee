<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    protected $fillable = [
        'course_id', 'lecturer_id', 'room_name', 'expires_at', 'is_active', 'geofence',
        'status', 'start_time', 'end_time', 'radius', 'wifi_bssid', 'allow_late',
        'allow_manual', 'grace_period', 'duration'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_active' => 'boolean',
        'allow_late' => 'boolean',
        'allow_manual' => 'boolean',
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
