<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('email');
            $table->string('faculty')->nullable()->after('department');
            $table->string('profile_picture_url')->nullable()->after('faculty');
        });
    }

    public function down(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'faculty', 'profile_picture_url']);
        });
    }
};
