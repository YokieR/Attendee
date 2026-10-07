<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'max_radius', 'gps_accuracy_threshold', 'jwt_expiry', 'session_timeout', 'ml_sensitivity'
    ];
}
