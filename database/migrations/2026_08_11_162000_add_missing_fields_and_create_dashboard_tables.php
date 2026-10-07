<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add fields to lecturers
        Schema::table('lecturers', function (Blueprint $table) {
            if (!Schema::hasColumn('lecturers', 'staff_number')) {
                $table->string('staff_number')->nullable()->after('id');
            }
            if (!Schema::hasColumn('lecturers', 'current_semester')) {
                $table->string('current_semester')->nullable()->after('faculty');
            }
        });

        // Create timetable table if it doesn't exist
        if (!Schema::hasTable('timetables')) {
            Schema::create('timetables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained()->onDelete('cascade');
                $table->foreignId('lecturer_id')->constrained('lecturers')->onDelete('cascade');
                $table->string('room_name');
                $table->string('day_of_week'); // Monday, Tuesday, etc.
                $table->time('start_time');
                $table->time('end_time');
                $table->timestamps();
            });
        }

        // Create notifications table if it doesn't exist
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->morphs('notifiable'); // Lecturer or Student
                $table->string('type'); // attendance_opened, ml_alert, etc.
                $table->string('title');
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('timetables');
        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropColumn(['staff_number', 'current_semester']);
        });
    }
};
