<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;use Illuminate\Support\Facades\Auth;
class AuthController extends Controller{
 public function create(){return view('auth.login');}
 public function store(Request $r){$credentials=$r->validate(['username'=>['required','string'],'password'=>['required','string']]);if(!Auth::attempt([...$credentials,'status'=>1],$r->boolean('remember')))return back()->withErrors(['username'=>'Invalid credentials or inactive account.'])->onlyInput('username');$r->session()->regenerate();return redirect()->intended(route('admin.dashboard'));}
 public function destroy(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect()->route('login');}
}
