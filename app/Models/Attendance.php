<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'student_id', 'session_id', 'device_uuid', 'mac_address', 'bssid', 'status',
        'location', 'gps_verified', 'wifi_verified', 'device_verified', 'remarks', 'attended_at'
    ];

    protected $casts = [
        'gps_verified' => 'boolean',
        'wifi_verified' => 'boolean',
        'device_verified' => 'boolean',
        'attended_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function session()
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }
}
