<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('profile_picture_url')->nullable()->after('device_uuid');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_picture_url')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('profile_picture_url');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_picture_url');
        });
    }
};
