<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('account_status')->default('active')->after('is_verified'); // active, suspended, deleted
            $table->softDeletes();
        });

        Schema::table('lecturers', function (Blueprint $table) {
            $table->string('account_status')->default('active')->after('is_verified');
            $table->softDeletes();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('status')->default('active')->after('credit_hours');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('status');
        });

        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('account_status');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('account_status');
        });
    }
};
