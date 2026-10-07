<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LecturerController extends Controller
{
    /**
     * GET /api/lecturer/dashboard
     * Answers: "What is happening with my classes and attendance?"
     */
    public function getDashboardStats(Request $request)
    {
        try {
            $lecturer = $request->user();
            $lecturerId = $lecturer->id;

            // Summary
            $courseIds = $lecturer->courses()->pluck('courses.id');
            $totalStudents = DB::table('course_student')->whereIn('course_id', $courseIds)->count();

            $activeSession = AttendanceSession::where('lecturer_id', $lecturerId)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->with('course')
                ->first();

            $activeSessionsCount = $activeSession ? 1 : 0;

            $totalAttendancesToday = Attendance::whereHas('session', function($q) use ($lecturerId) {
                    $q->where('lecturer_id', $lecturerId);
                })
                ->whereDate('attended_at', now()->toDateString())
                ->count();

            $totalExpectedToday = DB::table('course_student')
                ->whereIn('course_id', AttendanceSession::where('lecturer_id', $lecturerId)
                    ->whereDate('start_time', now()->toDateString())
                    ->pluck('course_id'))
                ->count();

            $percentageToday = $totalExpectedToday > 0 ? ($totalAttendancesToday / $totalExpectedToday) * 100 : 0;

            $flaggedCount = Attendance::whereHas('session', function($q) use ($lecturerId) {
                    $q->where('lecturer_id', $lecturerId);
                })
                ->where('status', 'flagged')
                ->count();

            // Today's Breakdown
            $todayPresent = Attendance::whereHas('session', function($q) use ($lecturerId) {
                    $q->where('lecturer_id', $lecturerId);
                })
                ->whereDate('attended_at', now()->toDateString())
                ->whereIn('status', ['present', 'verified'])
                ->count();

            $todayLate = Attendance::whereHas('session', function($q) use ($lecturerId) {
                    $q->where('lecturer_id', $lecturerId);
                })
                ->whereDate('attended_at', now()->toDateString())
                ->where('status', 'late')
                ->count();

            $todayFlagged = Attendance::whereHas('session', function($q) use ($lecturerId) {
                    $q->where('lecturer_id', $lecturerId);
                })
                ->whereDate('attended_at', now()->toDateString())
                ->where('status', 'flagged')
                ->count();

            $todayAbsent = max(0, $totalExpectedToday - ($todayPresent + $todayLate));

            return response()->json([
                'lecturer' => [
                    'name' => $lecturer->name
                ],
                'summary' => [
                    'assigned_courses' => $courseIds->count(),
                    'total_students' => $totalStudents,
                    'active_sessions' => $activeSessionsCount,
                    'today_attendance' => $totalAttendancesToday,
                    'today_attendance_percentage' => round($percentageToday, 1),
                    'flagged_records' => $flaggedCount,
                ],
                'today' => [
                    'present' => $todayPresent,
                    'late' => $todayLate,
                    'absent' => $todayAbsent,
                    'flagged' => $todayFlagged,
                ],
                'active_session' => $activeSession ? [
                    'id' => $activeSession->id,
                    'course_name' => $activeSession->course->name,
                    'course_code' => $activeSession->course->course_code,
                    'venue' => $activeSession->room_name,
                    'start_time' => $activeSession->start_time->format('H:i'),
                    'end_time' => $activeSession->end_time->format('H:i'),
                    'present' => $activeSession->attendances()->whereIn('status', ['present', 'verified'])->count(),
                    'pending' => $activeSession->attendances()->where('status', 'pending')->count(),
                    'flagged' => $activeSession->attendances()->where('status', 'flagged')->count(),
                ] : null,
                'recent_activity' => [] // Optional audit log integration
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/lecturer/attendance-sessions
     */
    public function getSessions(Request $request)
    {
        $sessions = AttendanceSession::where('lecturer_id', $request->user()->id)
            ->with('course')
            ->orderBy('start_time', 'desc')
            ->paginate(15);

        return response()->json($sessions);
    }

    /**
     * POST /api/lecturer/attendance-sessions
     */
    public function createSession(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'room_name' => 'required|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'radius' => 'integer|min:10',
            'wifi_bssid' => 'nullable|string',
        ]);

        $lecturer = $request->user();

        // Verify course belongs to lecturer
        if (!$lecturer->courses()->where('courses.id', $request->course_id)->exists()) {
            return response()->json(['message' => 'You are not assigned to this course.'], 403);
        }

        $session = AttendanceSession::create([
            'course_id' => $request->course_id,
            'lecturer_id' => $lecturer->id,
            'room_name' => $request->room_name,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'expires_at' => $request->end_time,
            'radius' => $request->radius ?? 100,
            'wifi_bssid' => $request->wifi_bssid,
            'status' => 'scheduled',
            'is_active' => false
        ]);

        return response()->json($session->load('course'), 201);
    }

    public function openSession(Request $request, $id)
    {
        $session = AttendanceSession::where('lecturer_id', $request->user()->id)->findOrFail($id);
        $session->update(['status' => 'active', 'is_active' => true]);
        return response()->json(['message' => 'Session opened', 'session' => $session]);
    }

    public function pauseSession(Request $request, $id)
    {
        $session = AttendanceSession::where('lecturer_id', $request->user()->id)->findOrFail($id);
        $session->update(['status' => 'paused', 'is_active' => false]);
        return response()->json(['message' => 'Session paused', 'session' => $session]);
    }

    public function resumeSession(Request $request, $id)
    {
        $session = AttendanceSession::where('lecturer_id', $request->user()->id)->findOrFail($id);
        $session->update(['status' => 'active', 'is_active' => true]);
        return response()->json(['message' => 'Session resumed', 'session' => $session]);
    }

    public function closeSession(Request $request, $id)
    {
        $session = AttendanceSession::where('lecturer_id', $request->user()->id)->findOrFail($id);
        $session->update(['status' => 'closed', 'is_active' => false, 'expires_at' => now()]);
        return response()->json(['message' => 'Session closed', 'session' => $session]);
    }

    public function cancelSession(Request $request, $id)
    {
        $session = AttendanceSession::where('lecturer_id', $request->user()->id)->findOrFail($id);
        $session->update(['status' => 'cancelled', 'is_active' => false]);
        return response()->json(['message' => 'Session cancelled', 'session' => $session]);
    }

    /**
     * GET /api/lecturer/attendance-sessions/{id}/monitor
     */
    public function monitorAttendance(Request $request, $id)
    {
        $session = AttendanceSession::where('lecturer_id', $request->user()->id)
            ->with('course')
            ->findOrFail($id);

        $attendances = $session->attendances()
            ->with('student')
            ->get()
            ->map(function($a) {
                return [
                    'student_name' => $a->student->full_name,
                    'registration_number' => $a->student->registration_number,
                    'status' => $a->status,
                    'time' => $a->attended_at ? $a->attended_at->format('H:i') : '--:--',
                    'verification' => [
                        'device' => $a->device_verified,
                        'wifi' => $a->wifi_verified,
                        'location' => $a->gps_verified
                    ],
                    'is_flagged' => $a->status === 'flagged'
                ];
            });

        return response()->json([
            'session' => [
                'course' => $session->course->name,
                'code' => $session->course->course_code,
            ],
            'summary' => [
                'total' => $attendances->count(),
                'present' => $attendances->where('status', 'verified')->count() + $attendances->where('status', 'present')->count(),
                'late' => $attendances->where('status', 'late')->count(),
                'pending' => $attendances->where('status', 'pending')->count(),
                'flagged' => $attendances->where('status', 'flagged')->count(),
                'absent' => 0 // Logic to find enrolled but not present if needed
            ],
            'students' => $attendances
        ]);
    }

    /**
     * GET /api/lecturer/students
     */
    public function getStudents(Request $request)
    {
        $lecturer = $request->user();
        $courseIds = $lecturer->courses()->pluck('courses.id');

        $query = Student::whereHas('courses', function($q) use ($courseIds) {
            $q->whereIn('courses.id', $courseIds);
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'ILIKE', "%$search%")
                  ->orWhere('registration_number', 'ILIKE', "%$search%");
            });
        }

        $students = $query->paginate(15);

        // Add attendance metrics for each student
        $students->getCollection()->transform(function($student) use ($courseIds) {
            $attendances = $student->attendances()
                ->whereHas('session', function($q) use ($courseIds) {
                    $q->whereIn('course_id', $courseIds);
                })->get();

            $student->attendance_percentage = $attendances->count() > 0 ? round(($attendances->whereIn('status', ['present', 'verified'])->count() / $attendances->count()) * 100, 1) : 0;
            $student->classes_attended = $attendances->whereIn('status', ['present', 'verified'])->count();
            $student->classes_missed = $attendances->where('status', 'missed')->count();
            $student->flagged_records = $attendances->where('status', 'flagged')->count();
            return $student;
        });

        return response()->json($students);
    }

    public function getStudentAttendance(Request $request, $id)
    {
        $lecturer = $request->user();
        $student = Student::findOrFail($id);

        $courseIds = $lecturer->courses()->pluck('courses.id');
        $attendances = Attendance::where('student_id', $id)
            ->whereHas('session', function($q) use ($courseIds) {
                $q->whereIn('course_id', $courseIds);
            })
            ->with('session.course')
            ->orderBy('attended_at', 'desc')
            ->get();

        return response()->json($attendances);
    }

    /**
     * GET /api/lecturer/attendance-records
     */
    public function getAttendanceRecords(Request $request)
    {
        $lecturer = $request->user();
        $courseIds = $lecturer->courses()->pluck('courses.id');

        $query = Attendance::whereHas('session', function($q) use ($courseIds) {
                $q->whereIn('course_id', $courseIds);
            })
            ->with(['student', 'session.course']);

        if ($request->filled('course_id')) $query->whereHas('session', fn($q) => $q->where('course_id', $request->course_id));
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('date_from')) $query->whereDate('attended_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('attended_at', '<=', $request->date_to);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function($q) use ($search) {
                $q->where('full_name', 'ILIKE', "%$search%")
                  ->orWhere('registration_number', 'ILIKE', "%$search%");
            });
        }

        return response()->json($query->orderBy('attended_at', 'desc')->paginate(20));
    }

    public function generateAttendanceReport(Request $request)
    {
        $request->validate([
            'format' => 'required|in:pdf,excel,csv',
            'course_id' => 'nullable|exists:courses,id',
        ]);

        // Authorization check
        if ($request->course_id && !$request->user()->courses()->where('courses.id', $request->course_id)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'message' => 'Report generation started.',
            'download_url' => url('/storage/reports/sample_report.' . $request->format)
        ]);
    }

    public function updateAttendance(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:verified,rejected,flagged',
            'remarks' => 'nullable|string'
        ]);

        $attendance = Attendance::whereHas('session', function($q) use ($request) {
            $q->where('lecturer_id', $request->user()->id);
        })->findOrFail($id);

        $attendance->update([
            'status' => $request->status,
            'remarks' => $request->remarks
        ]);

        return response()->json(['message' => 'Attendance updated', 'attendance' => $attendance]);
    }
}
