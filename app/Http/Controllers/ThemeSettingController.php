<?php

namespace App\Http\Controllers;

use App\Models\ThemeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ThemeSettingController extends Controller
{
    public function edit()
    {
        Gate::authorize('theme-settings.view');
        return view('theme-settings.edit', ['theme' => ThemeSetting::current()]);
    }

    public function update(Request $request)
    {
        Gate::authorize('theme-settings.edit');
        $data = $request->validate([
            'preset' => ['required', 'string', 'max:40'],
            'default_mode' => ['required', 'in:light,dark'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'light_navbar_bg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'light_sidebar_bg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'light_navbar_text' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'light_sidebar_text' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'dark_navbar_bg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'dark_sidebar_bg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'dark_navbar_text' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'dark_sidebar_text' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'canvas_bg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'card_bg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        ThemeSetting::current()->update($data + ['updated_by' => auth()->id()]);

        return back()->with('success', 'Theme settings updated.');
    }
}
