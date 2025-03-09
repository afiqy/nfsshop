<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // 🔹 Show Login Form
    public function showLoginForm()
    {
        return view('auth.login');
    }

    // 🔹 Handle Login
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->filled('remember'))) {
            return redirect()->route('dashboard')->with('success', 'Logged in successfully');
        }

        return back()->withErrors(['email' => 'Invalid credentials']);
    }

    // 🔹 Show Registration Form
    public function showRegisterForm()
    {
        return view('auth.register');
    }

    // 🔹 Handle Registration
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('login')->with('success', 'Account created successfully, please login.');
    }

    // 🔹 Handle Logout
    public function logout(Request $request)
    {
        Auth::logout();
        return redirect()->route('login')->with('success', 'Logged out successfully');
    }
}

