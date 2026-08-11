<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Lecturer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LecturerController extends Controller
{
    public function getProfile(Request $request)
    {
        return response()->json($request->user());
    }

    public function getDashboardStats(Request $request)
    {
        $lecturer = $request->user();
        $activeSessionsCount = AttendanceSession::where('lecturer_id', $lecturer->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->count();

        $courseIds = $lecturer->courses()->pluck('course_id');
        $totalCourses = $courseIds->count();

        $totalStudents = 0;
        if ($totalCourses > 0) {
            $totalStudents = DB::table('course_student')
                ->whereIn('course_id', $courseIds)
                ->count();
        }

        $totalAttendancesToday = Attendance::whereHas('session', function($q) use ($lecturer) {
                $q->where('lecturer_id', $lecturer->id);
            })
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return response()->json([
            'today_classes_count' => $totalCourses,
            'total_students_enrolled' => $totalStudents,
            'active_session_status' => $activeSessionsCount > 0 ? 'Active' : 'None',
            'average_attendance_rate' => 85.5,
            'flagged_cases_count' => 0,
        ]);
    }

    public function createSession(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'room_name' => 'required|string',
            'duration_minutes' => 'required|integer',
            'polygon_coordinates' => 'required|array|min:3',
        ]);

        $coords = $request->polygon_coordinates;
        $wktPoints = [];
        foreach ($coords as $c) {
            $wktPoints[] = $c['longitude'] . " " . $c['latitude'];
        }
        // Ensure polygon is closed
        if ($wktPoints[0] !== end($wktPoints)) {
            $wktPoints[] = $wktPoints[0];
        }
        $wkt = "POLYGON((" . implode(', ', $wktPoints) . "))";

        $session = AttendanceSession::create([
            'course_id' => $request->course_id,
            'lecturer_id' => $request->user()->id,
            'room_name' => $request->room_name,
            'expires_at' => now()->addMinutes($request->duration_minutes),
            'is_active' => true,
        ]);

        DB::statement("UPDATE attendance_sessions SET geofence = ST_GeomFromText(?, 4326) WHERE id = ?", [$wkt, $session->id]);

        return response()->json($session->load('course'));
    }

    public function closeSession(Request $request, $id)
    {
        $session = AttendanceSession::where('lecturer_id', $request->user()->id)->findOrFail($id);
        $session->update(['is_active' => false]);
        return response()->json(['message' => 'Session closed']);
    }

    public function getLiveStudents(Request $request, $id)
    {
        $session = AttendanceSession::where('lecturer_id', $request->user()->id)->findOrFail($id);
        $attendances = $session->attendances()->with('student')->get();
        return response()->json($attendances);
    }

    public function getAssignedCourses(Request $request)
    {
        return response()->json($request->user()->courses);
    }

    public function getAttendanceHistory(Request $request)
    {
        $sessions = AttendanceSession::where('lecturer_id', $request->user()->id)
            ->with('course')
            ->withCount('attendances')
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($sessions);
    }

    public function getAnomalyAlerts(Request $request)
    {
        return response()->json([]);
    }

    public function generateReport(Request $request, $courseId)
    {
        $sessions = AttendanceSession::where('course_id', $courseId)->pluck('id');
        $report = Attendance::whereIn('session_id', $sessions)
            ->with('student', 'session')
            ->get();
        return response()->json($report);
    }
}
