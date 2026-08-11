<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->string('verification_code')->nullable()->after('password');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('verification_code')->nullable()->after('password');
            $table->boolean('is_verified')->default(false)->after('verification_code');
        });
    }

    public function down(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropColumn('verification_code');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['verification_code', 'is_verified']);
        });
    }
};
