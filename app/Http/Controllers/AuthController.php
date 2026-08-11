<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Lecturer;
use App\Models\User;
use App\Mail\ResetPasswordMail;
use App\Mail\VerificationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        Log::info('Register request data:', $request->all());
        $request->validate([
            'name' => 'required|string|max:255',
            'registration_number' => 'required|string|unique:students,registration_number',
            'programme' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^[a-zA-Z]+\.[a-zA-Z]+[0-9]+@students\.dkut\.ac\.ke$/', $value)) {
                        $fail('Registration is restricted to students with valid institutional emails.');
                    }
                },
            ],
            'password' => 'required|string|min:8|confirmed',
        ]);

        $student = Student::create([
            'full_name' => $request->name,
            'registration_number' => $request->registration_number,
            'email' => $request->email,
            'programme' => $request->programme,
            'department' => $request->department,
            'password' => Hash::make($request->password),
            'is_verified' => false,
        ]);

        $token = $student->createToken('auth_token', ['role:student'])->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $student,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'registration_number' => 'required|string',
            'password' => 'required',
        ]);

        $regNumber = trim($request->registration_number);
        $password = $request->password;
        $user = null;
        $role = null;

        Log::info("Login attempt for: '{$regNumber}'");

        // Smart Role Detection
        if (str_contains($regNumber, '@')) {
            // Check if it's a lecturer email or student email
            if (str_ends_with($regNumber, '@dkut.ac.ke') && !str_contains($regNumber, '@students.dkut.ac.ke')) {
                $user = Lecturer::where('email', $regNumber)->first();
                $role = 'lecturer';
            } else {
                $user = Student::where('email', $regNumber)->first();
                $role = 'student';
            }
        } elseif (str_starts_with($regNumber, 'LEC')) {
            $id = (int) filter_var($regNumber, FILTER_SANITIZE_NUMBER_INT);
            $user = Lecturer::find($id);
            $role = 'lecturer';
        } else {
            $user = Student::where('registration_number', $regNumber)->first();
            $role = 'student';
        }

        if (!$user) {
            Log::warning("User not found in table for: '{$regNumber}'");
            throw ValidationException::withMessages([
                'registration_number' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!Hash::check($password, $user->password)) {
            Log::warning("Password check failed for: '{$regNumber}'. Hash in DB: " . substr($user->password, 0, 10) . "...");
            throw ValidationException::withMessages([
                'registration_number' => ['The provided credentials are incorrect.'],
            ]);
        }

        Log::info("Login successful for: {$regNumber} as {$role}");

        // Check if user is verified
        if (!$user->is_verified) {
            $code = rand(100000, 999999);
            $user->update(['verification_code' => $code]);

            try {
                Mail::to($user->email)->send(new VerificationMail($code));
                Log::info("Verification code sent to {$user->email}");
            } catch (\Exception $e) {
                Log::error("Failed to send verification email: " . $e->getMessage());
            }

            return response()->json([
                'message' => 'First-time login verification required. Code sent to your email.',
                'registration_number' => $regNumber,
                'role' => $role
            ], 403);
        }

        $token = $user->createToken('auth_token', ["role:$role"])->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'role' => $role
        ]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'registration_number' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $regNumber = $request->registration_number;
        $user = null;
        $role = null;

        if (str_starts_with($regNumber, 'LEC')) {
            $user = Lecturer::where('email', $regNumber)->first();
            $role = 'lecturer';
        } else {
            $user = Student::where('registration_number', $regNumber)->first();
            $role = 'student';
        }

        if (!$user || $user->verification_code !== $request->code) {
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }

        $user->update([
            'is_verified' => true,
            'verification_code' => null
        ]);

        $token = $user->createToken('auth_token', ["role:$role"])->plainTextToken;

        return response()->json([
            'message' => 'Verification successful.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'role' => $role
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['registration_number' => 'required|string']);

        $regNumber = $request->registration_number;
        $user = null;

        if (str_starts_with($regNumber, 'LEC')) {
            $user = Lecturer::where('email', $regNumber)->first();
        } else {
            $user = Student::where('registration_number', $regNumber)->first();
        }

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $code = rand(100000, 999999);
        $user->update(['verification_code' => $code]);

        try {
            Mail::to($user->email)->send(new ResetPasswordMail($code));
            Log::info("Password reset code sent to {$user->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send reset email: " . $e->getMessage());
            return response()->json(['message' => 'Failed to send reset code.'], 500);
        }

        return response()->json(['message' => 'Reset code sent to your email.']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'registration_number' => 'required|string',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $regNumber = $request->registration_number;
        $user = null;

        if (str_starts_with($regNumber, 'LEC')) {
            $user = Lecturer::where('email', $regNumber)->first();
        } else {
            $user = Student::where('registration_number', $regNumber)->first();
        }

        if (!$user || $user->verification_code !== $request->code) {
            return response()->json(['message' => 'Invalid or expired reset code.'], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'verification_code' => null
        ]);

        return response()->json(['message' => 'Password reset successful.']);
    }
}
