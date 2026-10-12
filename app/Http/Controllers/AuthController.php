<?php

namespace App\Http\Controllers;

use App\Models\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::validate($credentials)) {
            return back()->withErrors([
                'username' => 'Invalid username or password.',
            ])->onlyInput('username');
        }

        $login = Login::query()->with('profile')->where('username', $credentials['username'])->firstOrFail();
        $profileInactive = $login->profile && (string) $login->profile->status !== '1';

        if (! $login->status || $profileInactive) {
            return back()->withErrors([
                'username' => 'Your account is inactive. Please contact the administrator.',
            ])->onlyInput('username');
        }

        Auth::login($login, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
