<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function getProfile(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'User not found'], 401);

        $totalAttended = $user->attendances()->where('status', 'verified')->count();
        $enrolledCourseIds = $user->courses()->pluck('id');
        $totalSessions = AttendanceSession::whereIn('course_id', $enrolledCourseIds)->count();
        $totalMissed = max(0, $totalSessions - $totalAttended);
        $percentage = $totalSessions > 0 ? ($totalAttended / $totalSessions) * 100 : 0;

        return response()->json([
            'name' => $user->full_name,
            'registration_number' => $user->registration_number,
            'programme' => $user->programme,
            'department' => $user->department,
            'current_semester' => $user->current_semester,
            'total_attended' => $totalAttended,
            'total_missed' => $totalMissed,
            'attendance_percentage' => round($percentage, 2),
        ]);
    }

    public function getActiveSession(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'User not found'], 401);

        $programme = $user->programme;

        $session = AttendanceSession::whereHas('course', function($q) use ($programme) {
                $q->where('name', 'ILIKE', "%$programme%")
                  ->orWhere('course_code', 'ILIKE', "%$programme%");
            })
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->with('course', 'lecturer')
            ->first();

        return response()->json($session);
    }

    public function getAttendanceStats(Request $request)
    {
        $user = $request->user();
        // Load courses with session counts
        $stats = $user->courses()->withCount('sessions')->get();

        // Add attendance count for each course manually or via a clever query
        foreach ($stats as $course) {
            $course->total_attended = Attendance::where('student_id', $user->id)
                ->whereHas('session', function($q) use ($course) {
                    $q->where('course_id', $course->id);
                })
                ->where('status', 'verified')
                ->count();
        }

        return response()->json($stats);
    }

    public function getTimetable(Request $request)
    {
        return response()->json([]);
    }

    public function getNotifications(Request $request)
    {
        return response()->json([]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $user->update($request->only([
            'registration_number', 'programme', 'department', 'current_semester', 'faculty', 'full_name', 'email'
        ]));
        return response()->json($user);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|min:8',
        ]);

        $user = $request->user();
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json(['message' => 'Old password incorrect'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);
        return response()->json(['message' => 'Password updated successfully']);
    }

    public function getEnrolledCourses(Request $request)
    {
        return response()->json($request->user()->courses);
    }
}
