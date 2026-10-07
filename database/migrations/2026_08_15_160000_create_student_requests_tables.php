<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->string('old_uuid')->nullable();
            $table->string('new_uuid');
            $table->text('reason');
            $table->string('status')->default('submitted'); // submitted, under_review, approved, rejected
            $table->text('admin_remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('session_id')->constrained('attendance_sessions')->onDelete('cascade');
            $table->string('problem_type'); // gps_error, wifi_error, device_error, other
            $table->text('description');
            $table->string('attachment_url')->nullable();
            $table->string('status')->default('submitted'); // submitted, under_review, resolved, rejected
            $table->text('admin_remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_disputes');
        Schema::dropIfExists('device_change_requests');
    }
};
