<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'notifiable_id', 'notifiable_type', 'type', 'title', 'message', 'is_read'
    ];

    public function notifiable()
    {
        return $this->morphTo();
    }
}
