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

        $session = AttendanceSession::findOrFail($request->session_id);

        if (!$session->is_active || ($session->expires_at && $session->expires_at < now())) {
            return response()->json(['message' => 'This session is no longer active.'], 403);
        }

        // Check if already checked in
        $exists = Attendance::where('student_id', $request->user()->id)
            ->where('session_id', $session->id)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'You have already checked in for this session.'], 422);
        }

        // PostGIS ST_Contains check
        $point = "ST_GeomFromText('POINT(" . $request->longitude . " " . $request->latitude . ")', 4326)";
        $result = DB::selectOne("SELECT ST_Contains(geofence, $point) as inside FROM attendance_sessions WHERE id = ?", [$session->id]);

        if (!$result || !$result->inside) {
            return response()->json(['message' => 'Location verification failed. You are outside the designated area.'], 403);
        }

        // Record attendance
        $attendance = Attendance::create([
            'student_id' => $request->user()->id,
            'session_id' => $session->id,
            'device_uuid' => $request->device_uuid,
            'mac_address' => $request->mac_address,
            'bssid' => $request->bssid,
            'status' => 'verified',
        ]);

        // Update location using raw SQL for geometry
        DB::statement("UPDATE attendances SET location = $point WHERE id = ?", [$attendance->id]);

        return response()->json([
            'message' => 'Attendance verified successfully',
            'attendance' => $attendance
        ]);
    }
}
