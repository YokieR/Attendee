# Implementation Plan - Laravel Backend for Attendee

Implement the backend logic for student dashboard, attendance verification, and data management in the Laravel project located at `C:\Users\ReyR\Desktop\himp\attendee-repository\attendee-repository\laravel`.

## User Review Required

> [!IMPORTANT]
> A new migration will be created to add `device_uuid` to the `students` table to support device registration and verification.
> The `check-in` process will strictly enforce Device ID and Wi-Fi BSSID checks if configured in the session.

## Proposed Changes

### Database & Models

#### [NEW] [2026_08_15_080000_add_device_uuid_to_students_table.php](file:///C:/Users/ReyR/Desktop/himp/attendee-repository/attendee-repository/laravel/database/migrations/2026_08_15_080000_add_device_uuid_to_students_table.php)
- Add `device_uuid` column to the `students` table.

#### [MODIFY] [Student.php](file:///C:/Users/ReyR/Desktop/himp/attendee-repository/attendee-repository/laravel/app/Models/Student.php)
- Add `device_uuid` to the `fillable` array.

### Controllers

#### [MODIFY] [StudentController.php](file:///C:/Users/ReyR/Desktop/himp/attendee-repository/attendee-repository/laravel/app/Http/Controllers/StudentController.php)
- Implement `getTimetable`: Join `timetables` with `course_student` to return the student's scheduled classes.
- Implement `getNotifications`: Fetch morph-linked notifications for the authenticated student.

#### [MODIFY] [AttendanceController.php](file:///C:/Users/ReyR/Desktop/himp/attendee-repository/attendee-repository/laravel/app/Http/Controllers/AttendanceController.php)
- Enhance `checkIn` with multi-step verification:
    - **Step 3 (Device):** Check if `device_uuid` matches the student's registered device. Register the device on the first successful check-in if not already set.
    - **Step 4 (Wi-Fi):** Validate `bssid` against the session's `wifi_bssid` if configured.
    - **Step 5 (GPS):** Ensure the student is within the PostGIS geofence.
    - **Response:** Provide detailed failure messages (e.g., "Outside permitted area", "Wrong Wi-Fi network").

### Seeding (Optional for Verification)

#### [MODIFY] [DatabaseSeeder.php](file:///C:/Users/ReyR/Desktop/himp/attendee-repository/attendee-repository/laravel/database/seeders/DatabaseSeeder.php)
- Add sample timetable entries and notifications for testing.

## Verification Plan

### Automated Tests
- Run `php artisan test` (if tests are configured).
- Manually test API endpoints using Postman or `curl` to verify verification logic.

### Manual Verification
- **Authentication:** Test login with registration number and institutional email.
- **Dashboard:** Verify `student/timetable` and `student/notifications` return correct data.
- **Attendance:**
    - Test check-in with correct location/Wi-Fi/Device.
    - Test check-in with incorrect location (should fail with specific message).
    - Test check-in with incorrect Wi-Fi BSSID (should fail with specific message).
    - Test check-in from a different device after registration (should fail).
