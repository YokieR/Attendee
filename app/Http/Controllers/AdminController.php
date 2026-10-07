<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use App\Models\Student;
use App\Models\User;
use App\Models\Course;
use App\Models\Timetable;
use App\Models\Semester;
use App\Models\Room;
use App\Helpers\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function getStats()
    {
        try {
            $admin = request()->user();

            $currentSemester = Semester::where('status', 'active')->first();

            // 1.5 Today's Attendance Percentage logic: Present / Expected * 100
            $today = now()->toDateString();
            $activeSessionsToday = \App\Models\AttendanceSession::whereDate('created_at', $today)->get();

            $expectedStudents = 0;
            foreach ($activeSessionsToday as $session) {
                $expectedStudents += DB::table('course_student')
                    ->where('course_id', $session->course_id)
                    ->count();
            }

            $presentToday = \App\Models\Attendance::whereDate('created_at', $today)
                ->whereIn('status', ['verified', 'late'])
                ->count();

            $attendancePercentage = $expectedStudents > 0 ? ($presentToday / $expectedStudents) * 100 : 0;

            $stats = [
                'total_students' => \App\Models\Student::count(),
                'total_lecturers' => Lecturer::count(),
                'total_courses' => Course::count(),
                'total_rooms' => Room::count(),
                'active_sessions' => \App\Models\AttendanceSession::where('is_active', true)->count(),
                'attendance_today' => $presentToday,
                'attendance_percentage' => round($attendancePercentage, 1),
                'flagged_records' => \App\Models\Attendance::where('status', 'flagged')->count(),
                'unresolved_anomalies' => \App\Models\Attendance::where('status', 'flagged')->count(),
                'active_devices' => \App\Models\Student::whereNotNull('device_uuid')->count(),
                'unregistered_students' => \App\Models\Student::whereNull('device_uuid')->count(),
                'system_status' => 'Online',
                'active_users' => DB::table('personal_access_tokens')->where('last_used_at', '>', now()->subMinutes(15))->count(),
                'students_online' => DB::table('personal_access_tokens')
                    ->where('tokenable_type', 'App\Models\Student')
                    ->where('last_used_at', '>', now()->subMinutes(15))->count(),
                'lecturers_online' => DB::table('personal_access_tokens')
                    ->where('tokenable_type', 'App\Models\Lecturer')
                    ->where('last_used_at', '>', now()->subMinutes(15))->count(),
            ];

            $activities = DB::table('audit_logs')
                ->latest()
                ->take(15)
                ->get()
                ->map(function($a) {
                    return [
                        'id' => $a->id,
                        'type' => $a->action,
                        'description' => $a->description,
                        'timestamp' => \Carbon\Carbon::parse($a->created_at)->diffForHumans(),
                    ];
                });

            return response()->json([
                'profile' => [
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'staff_id' => $admin->staff_id ?? 'ADM-' . $admin->id,
                    'last_login' => $admin->last_login_at ? $admin->last_login_at->toDateTimeString() : now()->toDateTimeString(),
                    'profile_picture' => $admin->profile_picture_url
                ],
                'stats' => $stats,
                'current_semester' => $currentSemester,
                'activities' => $activities
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    // ─── Semester Management ──────────────────────────────────────────────────

    public function getSemesters()
    {
        return response()->json(Semester::orderBy('start_date', 'desc')->get());
    }

    public function createSemester(Request $request)
    {
        $data = $request->validate([
            'academic_year' => 'required|string',
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $semester = Semester::create($data);
        AuditLogger::log('semester_created', "Created semester: {$semester->name} ({$semester->academic_year})", $semester);
        return response()->json($semester, 201);
    }

    public function getSemesterDetails($id)
    {
        return response()->json(Semester::findOrFail($id));
    }

    public function updateSemester(Request $request, $id)
    {
        $semester = Semester::findOrFail($id);
        $data = $request->validate([
            'academic_year' => 'string',
            'name' => 'string',
            'start_date' => 'date',
            'end_date' => 'date|after:start_date',
            'status' => 'string|in:upcoming,active,closed'
        ]);

        $semester->update($data);
        AuditLogger::log('semester_updated', "Updated semester: {$semester->name}", $semester);
        return response()->json($semester);
    }

    public function activateSemester($id)
    {
        DB::beginTransaction();
        try {
            // Deactivate others
            Semester::where('status', 'active')->update(['status' => 'closed']);

            $semester = Semester::findOrFail($id);
            $semester->update(['status' => 'active']);

            DB::commit();
            AuditLogger::log('semester_activated', "Activated semester: {$semester->name}", $semester);
            return response()->json(['message' => 'Semester activated successfully', 'semester' => $semester]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to activate semester'], 500);
        }
    }

    public function closeSemester($id)
    {
        $semester = Semester::findOrFail($id);
        $semester->update(['status' => 'closed']);
        AuditLogger::log('semester_closed', "Closed semester: {$semester->name}", $semester);
        return response()->json(['message' => 'Semester closed successfully']);
    }

    // ─── Room Management ─────────────────────────────────────────────────────

    public function getRooms()
    {
        return response()->json(Room::latest()->get());
    }

    public function createRoom(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:rooms,name',
            'building' => 'required|string',
            'floor' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'description' => 'nullable|string',
        ]);

        $room = Room::create($data);
        AuditLogger::log('room_created', "Created room: {$room->name} in {$room->building}", $room);
        return response()->json($room, 201);
    }

    public function getRoomDetails($id)
    {
        return response()->json(Room::findOrFail($id));
    }

    public function updateRoom(Request $request, $id)
    {
        $room = Room::findOrFail($id);
        $data = $request->validate([
            'name' => 'string|unique:rooms,name,' . $id,
            'building' => 'string',
            'floor' => 'nullable|string',
            'latitude' => 'numeric|between:-90,90',
            'longitude' => 'numeric|between:-180,180',
            'description' => 'nullable|string',
            'status' => 'string|in:active,inactive'
        ]);

        $room->update($data);
        AuditLogger::log('room_updated', "Updated room: {$room->name}", $room);
        return response()->json($room);
    }

    public function activateRoom($id)
    {
        $room = Room::findOrFail($id);
        $room->update(['status' => 'active']);
        AuditLogger::log('room_activated', "Activated room: {$room->name}", $room);
        return response()->json(['message' => 'Room activated successfully']);
    }

    public function deactivateRoom($id)
    {
        $room = Room::findOrFail($id);
        $room->update(['status' => 'inactive']);
        AuditLogger::log('room_deactivated', "Deactivated room: {$room->name}", $room);
        return response()->json(['message' => 'Room deactivated successfully']);
    }

    // ─── Health & Other methods... ────────────────────────────────────────────

    public function getHealth()
    {
        $status = [
            'postgresql' => 'Online',
            'api' => 'Online',
            'ml_service' => 'Online',
        ];

        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $status['postgresql'] = 'Offline';
        }

        return response()->json($status);
    }

    public function getStudents(Request $request)
    {
        $query = Student::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'ILIKE', "%$search%")
                  ->orWhere('registration_number', 'ILIKE', "%$search%");
            });
        }

        if ($request->filled('status')) {
            $query->where('account_status', $request->status);
        }

        return response()->json($query->latest()->get());
    }

    public function addStudent(Request $request)
    {
        $data = $request->validate([
            'registration_number' => 'required|string|unique:students',
            'full_name' => 'required|string',
            'email' => 'required|email|unique:students',
            'department' => 'required|string',
            'programme' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        $data['password'] = Hash::make($data['password']);
        $student = Student::create($data);

        AuditLogger::log('student_added', "Added new student: {$student->full_name}", $student);
        return response()->json($student, 201);
    }

    public function updateStudent(Request $request, $id)
    {
        $student = Student::findOrFail($id);
        $data = $request->validate([
            'full_name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:students,email,' . $id,
            'department' => 'string',
            'programme' => 'string',
        ]);

        $student->update($data);
        AuditLogger::log('student_updated', "Updated student: {$student->full_name}", $student);
        return response()->json($student);
    }

    public function deleteStudent($id)
    {
        $student = Student::findOrFail($id);
        $student->update(['account_status' => 'deleted']);
        $student->delete();

        AuditLogger::log('student_deleted', "Soft deleted student: {$student->full_name}", $student);
        return response()->json(['message' => 'Student deleted successfully']);
    }

    public function resetStudentDevice($id)
    {
        $student = Student::findOrFail($id);
        $student->update(['device_uuid' => null]);

        AuditLogger::log('device_reset', "Reset device binding for student: {$student->full_name}", $student);

        return response()->json(['message' => 'Device binding reset successfully']);
    }

    public function toggleStudentStatus($id)
    {
        $student = Student::findOrFail($id);
        $student->update(['account_status' => ($student->account_status === 'active' ? 'suspended' : 'active')]);

        AuditLogger::log('student_status_toggle', "Student account status changed to {$student->account_status} for: {$student->full_name}", $student);
        return response()->json(['message' => "Student account {$student->account_status} successfully"]);
    }

    public function getLecturers(Request $request)
    {
        $query = Lecturer::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'ILIKE', "%$search%")
                  ->orWhere('email', 'ILIKE', "%$search%")
                  ->orWhere('staff_number', 'ILIKE', "%$search%");
            });
        }

        return response()->json($query->latest()->get());
    }

    public function addLecturer(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'staff_number' => 'nullable|string|unique:lecturers,staff_number',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:lecturers',
                function ($attribute, $value, $fail) {
                    if (!str_ends_with($value, '@dkut.ac.ke')) {
                        $fail('Lecturer email must be a valid institutional email (@dkut.ac.ke).');
                    }
                },
            ],
            'password' => 'required|string|min:8',
            'phone_number' => 'nullable|string',
            'faculty' => 'nullable|string',
            'office_location' => 'nullable|string',
            'current_semester' => 'nullable|string',
        ]);

        $lecturer = Lecturer::create([
            'name' => $request->name,
            'department' => $request->department,
            'staff_number' => $request->staff_number,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'faculty' => $request->faculty,
            'office_location' => $request->office_location,
            'current_semester' => $request->current_semester,
            'password' => Hash::make($request->password),
            'is_verified' => true, // Admin-added lecturers are verified by default
            'account_status' => 'active'
        ]);

        AuditLogger::log('lecturer_added', "Added new lecturer: {$lecturer->name}", $lecturer);

        return response()->json($lecturer, 201);
    }

    public function updateLecturer(Request $request, $id)
    {
        $lecturer = Lecturer::findOrFail($id);
        $data = $request->validate([
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:lecturers,email,' . $id,
            'department' => 'string',
        ]);

        $lecturer->update($data);
        AuditLogger::log('lecturer_updated', "Updated lecturer: {$lecturer->name}", $lecturer);
        return response()->json($lecturer);
    }

    public function deleteLecturer($id)
    {
        $lecturer = Lecturer::findOrFail($id);
        $lecturer->update(['account_status' => 'deleted']);
        $lecturer->delete();

        AuditLogger::log('lecturer_deleted', "Soft deleted lecturer: {$lecturer->name}", $lecturer);
        return response()->json(['message' => 'Lecturer deleted successfully']);
    }

    public function assignLecturerCourse(Request $request, $id)
    {
        $request->validate(['course_id' => 'required|exists:courses,id']);
        $lecturer = Lecturer::findOrFail($id);

        if (!$lecturer->courses()->where('course_id', $request->course_id)->exists()) {
            $lecturer->courses()->attach($request->course_id);
        }

        AuditLogger::log('course_assignment', "Assigned course ID {$request->course_id} to lecturer: {$lecturer->name}", $lecturer);
        return response()->json(['message' => 'Course assigned successfully']);
    }

    public function removeCourseAssignment(Request $request, $id)
    {
        $request->validate(['course_id' => 'required|exists:courses,id']);
        $lecturer = Lecturer::findOrFail($id);
        $lecturer->courses()->detach($request->course_id);

        AuditLogger::log('course_removal', "Removed course ID {$request->course_id} from lecturer: {$lecturer->name}", $lecturer);
        return response()->json(['message' => 'Course assignment removed']);
    }

    public function getCourses()
    {
        return response()->json(Course::with('lecturers')->latest()->get());
    }

    public function createCourse(Request $request)
    {
        $data = $request->validate([
            'course_code' => 'required|string|unique:courses',
            'name' => 'required|string',
            'department' => 'required|string',
            'programme' => 'required|string',
            'semester' => 'required|string',
            'credit_hours' => 'required|integer',
        ]);

        $course = Course::create($data);
        AuditLogger::log('course_created', "Created new course: {$course->name}", $course);
        return response()->json($course, 201);
    }

    public function updateCourse(Request $request, $id)
    {
        $course = Course::findOrFail($id);
        $data = $request->validate([
            'name' => 'string',
            'department' => 'string',
            'programme' => 'string',
            'semester' => 'string',
            'credit_hours' => 'integer',
            'status' => 'string|in:active,inactive'
        ]);

        $course->update($data);
        AuditLogger::log('course_updated', "Updated course: {$course->name}", $course);
        return response()->json($course);
    }

    public function deleteCourse($id)
    {
        $course = Course::findOrFail($id);
        $course->update(['status' => 'inactive']);
        $course->delete();

        AuditLogger::log('course_deleted', "Soft deleted course: {$course->name}", $course);
        return response()->json(['message' => 'Course deleted successfully']);
    }

    public function getActiveSessions()
    {
        return response()->json(\App\Models\AttendanceSession::where('is_active', true)
            ->with(['course', 'lecturer'])
            ->get());
    }

    public function getAttendanceRecords(Request $request)
    {
        $query = \App\Models\Attendance::with(['student', 'session.course']);

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->has('course_id')) {
            $query->whereHas('session', function($q) use ($request) {
                $q->where('course_id', $request->course_id);
            });
        }

        return response()->json($query->latest()->take(100)->get());
    }

    public function getDevices()
    {
        return response()->json(Student::whereNotNull('device_uuid')
            ->select('id', 'full_name', 'registration_number', 'device_uuid')
            ->get());
    }

    public function getAnomalies()
    {
        return response()->json(\App\Models\Attendance::where('status', 'flagged')
            ->with(['student', 'session.course'])
            ->get());
    }

    public function resolveAnomaly(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:verified,rejected']);
        $attendance = \App\Models\Attendance::findOrFail($id);
        $attendance->update(['status' => $request->status]);

        AuditLogger::log('anomaly_resolved', "Resolved anomaly for attendance ID {$id} as {$request->status}", $attendance);
        return response()->json(['message' => 'Anomaly resolved successfully']);
    }

    public function getSettings()
    {
        return response()->json(\App\Models\SystemSetting::first());
    }

    public function updateSettings(Request $request)
    {
        $settings = \App\Models\SystemSetting::first();
        $settings->update($request->all());

        AuditLogger::log('settings_updated', "Updated system configuration settings");
        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function getAnalytics()
    {
        $trend = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $trend[] = [
                'date' => $date,
                'count' => \App\Models\Attendance::whereDate('created_at', $date)->count()
            ];
        }

        $courseComparison = Course::withCount('attendances')->get()->map(function($c) {
            return ['name' => $c->name, 'count' => $c->attendances_count];
        });

        $dist = [
            '90-100%' => Student::where('attendance_percentage', '>=', 90)->count(),
            '80-89%' => Student::where('attendance_percentage', '>=', 80)->where('attendance_percentage', '<', 90)->count(),
            '70-79%' => Student::where('attendance_percentage', '>=', 70)->where('attendance_percentage', '<', 80)->count(),
            'below_70%' => Student::where('attendance_percentage', '<', 70)->count(),
        ];

        return response()->json([
            'attendance_trend' => $trend,
            'course_comparison' => $courseComparison,
            'attendance_distribution' => $dist,
            'device_risk' => [
                'unique_devices' => Student::whereNotNull('device_uuid')->distinct('device_uuid')->count(),
                'duplicate_devices' => DB::table('students')
                    ->select('device_uuid', DB::raw('count(*) as count'))
                    ->whereNotNull('device_uuid')
                    ->groupBy('device_uuid')
                    ->having('count', '>', 1)
                    ->count(),
            ]
        ]);
    }

    public function getLogs()
    {
        return response()->json(DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->select('audit_logs.*', 'users.name as admin_name')
            ->latest()
            ->paginate(50));
    }

    public function importTimetable(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $file = $request->file('file');
        $data = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_shift($data);
        $importResults = ['success' => 0, 'errors' => [], 'lecturer_not_found' => []];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                if (count($row) < count($header)) continue;
                $rowData = array_combine($header, $row);
                $course = Course::firstOrCreate(['course_code' => trim($rowData['course_code'])], ['name' => trim($rowData['course_name']), 'department' => trim($rowData['department'] ?? 'IT')]);
                $lecturer = Lecturer::where('name', 'ILIKE', '%' . trim($rowData['lecturer_name']) . '%')->first();
                if ($lecturer) {
                    if (!$lecturer->courses()->where('course_id', $course->id)->exists()) $lecturer->courses()->attach($course->id);
                    Timetable::updateOrCreate(['course_id' => $course->id, 'lecturer_id' => $lecturer->id, 'day_of_week' => trim($rowData['day']), 'start_time' => trim($rowData['start_time'])], ['end_time' => trim($rowData['end_time']), 'room_name' => trim($rowData['room'])]);
                    $importResults['success']++;
                } else {
                    $importResults['lecturer_not_found'][] = "Row " . ($index + 2) . ": Lecturer '{$rowData['lecturer_name']}' not found.";
                }
            }
            DB::commit();
            return response()->json(['message' => 'Timetable import completed.', 'results' => $importResults]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to import timetable.', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateProfilePicture(Request $request)
    {
        $request->validate(['image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120']);
        $admin = $request->user();
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('profile_pictures', 'public');
            $admin->update(['profile_picture_url' => asset('storage/' . $path)]);
        }
        return response()->json(['message' => 'Profile picture updated', 'profile_picture_url' => $admin->profile_picture_url]);
    }

    public function deleteProfilePicture(Request $request)
    {
        $admin = $request->user();
        $admin->update(['profile_picture_url' => null]);
        return response()->json(['message' => 'Profile picture removed']);
    }

    public function changePassword(Request $request)
    {
        $request->validate(['old_password' => 'required', 'new_password' => 'required|min:8|confirmed']);
        $user = $request->user();
        if (!Hash::check($request->old_password, $user->password)) return response()->json(['message' => 'Old password incorrect'], 422);
        $user->update(['password' => Hash::make($request->new_password)]);
        return response()->json(['message' => 'Password updated successfully']);
    }
}
