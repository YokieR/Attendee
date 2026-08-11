<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['course_code', 'name', 'department'];

    public function students()
    {
        return $this->belongsToMany(Student::class, 'course_student');
    }

    public function lecturers()
    {
        return $this->belongsToMany(Lecturer::class, 'course_lecturer');
    }

    public function sessions()
    {
        return $this->hasMany(AttendanceSession::class);
    }
}
