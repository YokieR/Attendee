<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function addLecturer(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:lecturers',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^[a-zA-Z]+\.[a-zA-Z]+@dkut\.ac\.ke$/', $value)) {
                        $fail('Lecturer email must be in the format: firstname.lastname@dkut.ac.ke');
                    }
                },
            ],
            'password' => 'required|string|min:8',
        ]);

        $lecturer = Lecturer::create([
            'name' => $request->name,
            'department' => $request->department,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_verified' => false,
        ]);

        return response()->json([
            'message' => 'Lecturer added successfully.',
            'user' => $lecturer
        ], 201);
    }

    public function getStats()
    {
        return response()->json([
            'total_students' => Student::count(),
            'total_lecturers' => Lecturer::count(),
            'total_admins' => User::where('role', 'admin')->count(),
        ]);
    }
}
