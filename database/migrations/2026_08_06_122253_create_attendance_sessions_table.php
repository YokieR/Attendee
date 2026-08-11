<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('lecturer_id')->constrained('users')->onDelete('cascade');
            $table->string('room_name');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE attendance_sessions ADD COLUMN geofence GEOMETRY(POLYGON, 4326)");
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
