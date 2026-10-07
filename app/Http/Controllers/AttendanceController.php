<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function checkIn(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:attendance_sessions,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'device_uuid' => 'required|string',
            'mac_address' => 'required|string',
            'bssid' => 'nullable|string',
        ]);

        $user = $request->user();

        if ($user->account_status === 'suspended') {
            return response()->json(['message' => 'Your account is suspended.'], 403);
        }

        if (!$user->is_verified) {
            return response()->json(['message' => 'Your account is not verified.'], 403);
        }

        $session = AttendanceSession::findOrFail($request->session_id);

        if (!$session->is_active || ($session->expires_at && $session->expires_at < now())) {
            return response()->json(['message' => 'Attendance session has closed.'], 403);
        }

        // Check if student is enrolled in the course
        if (!$user->courses()->where('courses.id', $session->course_id)->exists()) {
            return response()->json(['message' => 'You are not enrolled in this course.'], 403);
        }

        // Check if already checked in
        $exists = Attendance::where('student_id', $user->id)
            ->where('session_id', $session->id)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'You have already marked attendance for this session.'], 422);
        }

        // Step 3: Verify registered device
        if (empty($user->device_uuid)) {
            // First time registration
            $user->update(['device_uuid' => $request->device_uuid]);
        } elseif ($user->device_uuid !== $request->device_uuid) {
            return response()->json(['message' => 'This device is not registered to your account.'], 403);
        }

        // Step 4: Verify Wi-Fi
        $wifiVerified = true;
        if (!empty($session->wifi_bssid)) {
            if (empty($request->bssid) || strtolower($request->bssid) !== strtolower($session->wifi_bssid)) {
                $wifiVerified = false;
                return response()->json(['message' => 'You are not connected to the authorized university Wi-Fi.'], 403);
            }
        }

        // Step 5: Verify GPS location
        $point = "ST_GeomFromText('POINT(" . $request->longitude . " " . $request->latitude . ")', 4326)";
        $result = DB::selectOne("SELECT ST_Contains(geofence, $point) as inside FROM attendance_sessions WHERE id = ?", [$session->id]);

        if (!$result || !$result->inside) {
            return response()->json(['message' => 'You are outside the permitted attendance location.'], 403);
        }

        // Record attendance
        $attendance = Attendance::create([
            'student_id' => $user->id,
            'session_id' => $session->id,
            'device_uuid' => $request->device_uuid,
            'mac_address' => $request->mac_address,
            'bssid' => $request->bssid,
            'status' => 'verified',
            'gps_verified' => true,
            'wifi_verified' => $wifiVerified,
            'device_verified' => true,
            'attended_at' => now(),
        ]);

        // Update location using raw SQL for geometry
        DB::statement("UPDATE attendances SET location = $point WHERE id = ?", [$attendance->id]);

        return response()->json([
            'message' => 'Attendance Recorded Successfully',
            'course' => $session->course->name,
            'time' => now()->format('h:i A'),
            'venue' => $session->room_name,
            'status' => 'Present'
        ]);
    }
}
