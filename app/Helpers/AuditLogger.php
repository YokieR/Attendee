<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public static function log($action, $description, $model = null)
    {
        DB::table('audit_logs')->insert([
            'action' => $action,
            'description' => $description,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->id : null,
            'user_id' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
