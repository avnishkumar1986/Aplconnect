<?php

namespace App\Http\Controllers;

use App\Models\{Address, ApiIntegration, Company, Contact, Department, Designation, DesignationLevel, DistributorTier, DistributorType, Login, UserProfile, UserType};
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class StatusController extends Controller
{
    private const RESOURCES = [
        'addresses' => Address::class,
        'contacts' => Contact::class,
        'accounts' => Login::class,
        'users' => UserProfile::class,
        'user-types' => UserType::class,
        'distributor-types' => DistributorType::class,
        'distributor-tiers' => DistributorTier::class,
        'companies' => Company::class,
        'departments' => Department::class,
        'designations' => Designation::class,
        'designation-levels' => DesignationLevel::class,
        'api-integrations' => ApiIntegration::class,
    ];

    public function __invoke(string $resource, int $id): JsonResponse
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);
        $record = self::RESOURCES[$resource]::findOrFail($id);
        $permission = in_array($resource, ['departments', 'designations', 'designation-levels'], true)
            ? 'users.edit'
            : $resource.'.edit';
        if ($resource === 'users' && $record->userType?->type_name && str_contains(strtolower($record->userType->type_name), 'distributor')) {
            $permission = 'distributors.edit';
        }
        Gate::authorize($permission);
        $statusColumn = $resource === 'companies' ? 'Status' : 'status';
        $updatedByColumn = match ($resource) {
            'companies' => 'Updated_by',
            'designation-levels', 'api-integrations' => null,
            default => 'updated_by',
        };
        $active = (string) $record->{$statusColumn} === '1';
        abort_if($resource === 'accounts' && $record->is(auth()->user()) && $active, 422, 'You cannot deactivate your current account.');

        $changes = [$statusColumn => $active ? '0' : '1'];
        if ($updatedByColumn) {
            $changes[$updatedByColumn] = auth()->id();
        }
        $record->update($changes);
        if ($record instanceof UserProfile && $record->login) {
            $record->login->update([
                'status' => ! $active,
                'updated_by' => auth()->id(),
            ]);
        }

        return response()->json([
            'active' => ! $active,
            'label' => $active ? 'Inactive' : 'Active',
            'message' => 'Status updated successfully.',
        ]);
    }
}
