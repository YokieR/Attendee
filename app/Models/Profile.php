<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $fillable = [
        'user_id', 'registration_number', 'programme', 'department', 'current_semester', 'faculty', 'courses_assigned'
    ];

    protected $casts = [
        'courses_assigned' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
