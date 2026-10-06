<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeSetting extends Model
{
    protected $table = 'tbl_theme_settings';

    protected $guarded = ['id'];

    public static function current(): self
    {
        return static::first() ?? static::create([
            'preset' => 'teal-cyan',
            'default_mode' => 'light',
            'primary_color' => '#159aa6',
            'secondary_color' => '#31b7c3',
            'light_navbar_bg' => '#ffffff',
            'light_sidebar_bg' => '#111b2e',
            'light_navbar_text' => '#24334a',
            'light_sidebar_text' => '#b5c0d3',
            'dark_navbar_bg' => '#101827',
            'dark_sidebar_bg' => '#0b1220',
            'dark_navbar_text' => '#e5edf7',
            'dark_sidebar_text' => '#9daac0',
            'canvas_bg' => '#f3f6fa',
            'card_bg' => '#ffffff',
        ]);
    }
}
