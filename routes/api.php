<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\LecturerController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-admin', [AuthController::class, 'verifyCode']);
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Admin Routes
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::post('/lecturers', [AdminController::class, 'addLecturer']);
        Route::get('/stats', [AdminController::class, 'getStats']);
    });

    // Student Routes
    Route::prefix('student')->middleware('role:student')->group(function () {
        Route::get('/profile', [StudentController::class, 'getProfile']);
        Route::get('/active-session', [StudentController::class, 'getActiveSession']);
        Route::get('/stats', [StudentController::class, 'getAttendanceStats']);
        Route::get('/timetable', [StudentController::class, 'getTimetable']);
        Route::get('/notifications', [StudentController::class, 'getNotifications']);
        Route::post('/profile/update', [StudentController::class, 'updateProfile']);
        Route::post('/password/change', [StudentController::class, 'changePassword']);
        Route::get('/courses', [StudentController::class, 'getEnrolledCourses']);
    });

    // Attendance Routes
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);

    // Lecturer Routes
    Route::prefix('lecturer')->middleware('role:lecturer')->group(function () {
        Route::get('/profile', [LecturerController::class, 'getProfile']);
        Route::get('/dashboard', [LecturerController::class, 'getDashboardStats']);
        Route::post('/sessions', [LecturerController::class, 'createSession']);
        Route::post('/sessions/{id}/close', [LecturerController::class, 'closeSession']);
        Route::get('/sessions/{id}/live', [LecturerController::class, 'getLiveStudents']);
        Route::get('/courses', [LecturerController::class, 'getAssignedCourses']);
        Route::get('/history', [LecturerController::class, 'getAttendanceHistory']);
        Route::get('/anomalies', [LecturerController::class, 'getAnomalyAlerts']);
        Route::get('/report/{courseId}', [LecturerController::class, 'generateReport']);
    });
});
