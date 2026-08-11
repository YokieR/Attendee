<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('session_id')->constrained('attendance_sessions')->onDelete('cascade');
            $table->string('device_uuid')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('bssid')->nullable();
            $table->string('status')->default('verified');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE attendances ADD COLUMN location GEOMETRY(POINT, 4326)");
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
