<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'student_id', 'session_id', 'device_uuid', 'mac_address', 'bssid', 'status', 'location'
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
