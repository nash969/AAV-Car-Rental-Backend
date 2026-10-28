<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            $request->user()->role === 'admin',
            403,
            'Only administrators can manage employees.'
        );

        $employees = User::where('role', 'employee')
            ->latest()
            ->get(['id', 'name', 'email', 'phone', 'role', 'created_at']);

        return response()->json($employees);
    }

    public function store(Request $request)
    {
        abort_unless(
            $request->user()->role === 'admin',
            403,
            'Only administrators can add employees.'
        );

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $employee = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => 'employee',
        ]);

        return response()->json([
            'message' => 'Employee added successfully.',
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'role' => $employee->role,
            ],
        ], 201);
    }
}