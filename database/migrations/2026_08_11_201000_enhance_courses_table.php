<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'semester')) {
                $table->string('semester')->nullable()->after('department');
            }
            if (!Schema::hasColumn('courses', 'credit_hours')) {
                $table->integer('credit_hours')->default(3)->after('semester');
            }
            if (!Schema::hasColumn('courses', 'venue')) {
                $table->string('venue')->nullable()->after('credit_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['semester', 'credit_hours', 'venue']);
        });
    }
};
