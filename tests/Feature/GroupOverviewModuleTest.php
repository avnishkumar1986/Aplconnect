<?php

use App\Models\ModulePackage;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authorized users can view the active group overview module', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('group_overview.dashboard.view', 'web'));

    $this->actingAs($user)
        ->get(route('admin.group-overview.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('modules/groupoverview/dashboard'));
});

test('group overview returns the inactive module page when disabled', function () {
    ModulePackage::query()->where('slug', 'group-overview')->update(['status' => false]);
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('group_overview.dashboard.view', 'web'));

    $this->actingAs($user)
        ->get(route('admin.group-overview.index'))
        ->assertStatus(503)
        ->assertInertia(fn ($page) => $page->component('modules/coming-soon'));
});

test('users without the group overview permission are forbidden', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.group-overview.index'))
        ->assertForbidden();
});
