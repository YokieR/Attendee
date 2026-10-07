<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('max_radius')->default(100);
            $table->decimal('gps_accuracy_threshold', 8, 2)->default(20.00);
            $table->integer('jwt_expiry')->default(1440); // in minutes (24h)
            $table->integer('session_timeout')->default(60); // in minutes
            $table->decimal('ml_sensitivity', 3, 2)->default(0.70);
            $table->timestamps();
        });

        // Seed default settings
        DB::table('system_settings')->insert([
            'max_radius' => 100,
            'gps_accuracy_threshold' => 20.00,
            'jwt_expiry' => 1440,
            'session_timeout' => 60,
            'ml_sensitivity' => 0.70,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
