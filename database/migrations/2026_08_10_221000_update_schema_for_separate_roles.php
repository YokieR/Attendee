<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update attendances table
        Schema::table('attendances', function (Blueprint $table) {
            $table->renameColumn('user_id', 'student_id');
        });

        // Create course_student pivot table
        Schema::create('course_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Create course_lecturer pivot table
        Schema::create('course_lecturer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('lecturer_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Drop old pivot table
        Schema::dropIfExists('course_user');
    }

    public function down(): void
    {
        Schema::create('course_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type')->default('student');
            $table->timestamps();
        });

        Schema::dropIfExists('course_student');
        Schema::dropIfExists('course_lecturer');

        Schema::table('attendances', function (Blueprint $table) {
            $table->renameColumn('student_id', 'user_id');
        });
    }
};
