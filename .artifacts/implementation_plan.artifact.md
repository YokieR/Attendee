# Implementation Plan - Laravel Admin Backend

Implement a comprehensive administrative backend in Laravel to support the System Administrator dashboard in the Android app. This includes user management, course management, system monitoring, anomaly detection, and settings configuration.

## Proposed Changes

### 1. Database & Models
- **[NEW] Migration**: Create `system_settings` table to store `max_radius`, `gps_accuracy_threshold`, `jwt_expiry`, `session_timeout`, and `ml_sensitivity`.
- **[NEW] Model**: `app/Models/SystemSetting.php`.
- **[NEW] Model**: `app/Models/AuditLog.php` for better querying of system logs.

### 2. Admin Controller (`app/Http/Controllers/AdminController.php`)
Expand the controller with the following methods:
- **`getStats()`**: Update to return all fields required by the Android dashboard (Flagged records, Active devices, etc.).
- **`getStudents()`**: Searchable and filterable list of students.
- **`resetStudentDevice(int $id)`**: Clear the `device_uuid` for a student.
- **`toggleStudentStatus(int $id)`**: Suspend/Activate student accounts.
- **`getLecturers()`**: List of lecturers.
- **`assignLecturerCourse(int $id)`**: Pivot table management for lecturer-course assignment.
- **`getCourses()`**: List of all courses.
- **`createCourse(Request $request)`**: Validation and creation of new courses.
- **`getActiveSessions()`**: Monitor currently active attendance sessions.
- **`getAttendanceRecords()`**: Global searchable attendance logs with date filtering.
- **`getDevices()`**: List of registered device UUIDs and their associated students.
- **`getAnomalies()`**: List of attendances with `status = 'flagged'`.
- **`resolveAnomaly(int $id)`**: Method to manually approve or reject flagged attendance.
- **`getSettings()`**: Retrieve current system configuration.
- **`updateSettings(Request $request)`**: Update system configuration parameters.
- **`getLogs()`**: Paginated list of audit logs.

### 3. Auth Controller (`app/Http/Controllers/AuthController.php`)
- **[MODIFY] `login()`**: Update the `last_login_at` timestamp for the authenticated user.

### 4. API Routes (`routes/api.php`)
Register all new endpoints under the `admin` prefix:
- `GET /admin/stats`
- `GET /admin/students`
- `POST /admin/students/{id}/reset-device`
- `POST /admin/students/{id}/toggle-status`
- `GET /admin/lecturers`
- `POST /admin/lecturers/{id}/assign-course`
- `GET /admin/courses`
- `POST /admin/courses`
- `GET /admin/sessions/active`
- `GET /admin/attendance/records`
- `GET /admin/devices`
- `GET /admin/anomalies`
- `POST /admin/anomalies/{id}/resolve`
- `GET /admin/settings`
- `POST /admin/settings/update`
- `GET /admin/logs`

## Verification Plan

### Automated Testing
- Use Postman to verify each endpoint returns the expected JSON structure.
- Verify that `AuditLogger` correctly records administrative actions.

### Manual Verification
- Confirm that the Android app's Admin Dashboard stats are correctly populated.
- Test the "Reset Device" functionality in the app and verify the database is updated.
- Verify that "Flagged" records appear in the Anomaly Detection module.
