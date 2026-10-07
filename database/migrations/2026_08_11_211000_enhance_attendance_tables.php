<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update attendance_sessions
        Schema::table('attendance_sessions', function (Blueprint $table) {
            // Fix foreign key if needed (depends on database state, safer to just add fields for now)
            $table->string('status')->default('active')->after('is_active'); // active, paused, closed, cancelled
            $table->timestamp('start_time')->nullable()->after('status');
            $table->timestamp('end_time')->nullable()->after('start_time');
            $table->integer('radius')->default(100)->after('end_time'); // GPS radius in meters
            $table->string('wifi_bssid')->nullable()->after('radius');
            $table->boolean('allow_late')->default(false)->after('wifi_bssid');
            $table->boolean('allow_manual')->default(false)->after('allow_late');
            $table->integer('grace_period')->default(0)->after('allow_manual'); // in minutes
            $table->integer('duration')->default(60)->after('grace_period'); // expected duration in minutes
        });

        // 2. Update attendances
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('gps_verified')->default(false)->after('status');
            $table->boolean('wifi_verified')->default(false)->after('gps_verified');
            $table->boolean('device_verified')->default(false)->after('wifi_verified');
            $table->text('remarks')->nullable()->after('device_verified');
            $table->timestamp('attended_at')->useCurrent()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['gps_verified', 'wifi_verified', 'device_verified', 'remarks', 'attended_at']);
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn(['status', 'start_time', 'end_time', 'radius', 'wifi_bssid', 'allow_late', 'allow_manual', 'grace_period', 'duration']);
        });
    }
};
