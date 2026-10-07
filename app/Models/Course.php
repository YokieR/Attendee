<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use SoftDeletes;

    protected $fillable = ['course_code', 'name', 'department', 'programme', 'semester', 'credit_hours', 'venue', 'status'];

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
