<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IndiaLocationController extends Controller
{
    public function states(): JsonResponse
    {
        return response()->json($this->values('state'));
    }

    public function districts(Request $request): JsonResponse
    {
        $request->validate(['state' => ['required', 'string', 'max:100']]);
        return response()->json($this->values('district', ['state' => $request->state]));
    }

    public function cities(Request $request): JsonResponse
    {
        $request->validate(['state' => ['required', 'string', 'max:100'], 'district' => ['required', 'string', 'max:100']]);
        $cities = collect($this->values('city', ['state' => $request->state, 'district' => $request->district]))
            ->map(fn ($city) => $this->normalizePostalPlace($city))
            ->filter()
            ->unique(fn ($city) => mb_strtolower($city))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return response()->json($cities);
    }

    public function postalCodes(Request $request): JsonResponse
    {
        $request->validate(['state' => ['required', 'string', 'max:100'], 'district' => ['required', 'string', 'max:100'], 'city' => ['required', 'string', 'max:180']]);
        $postalCodes = DB::table('tbl_india_postal_codes')
            ->where('state', $request->state)
            ->where('district', $request->district)
            ->where(function ($query) use ($request): void {
                $query->where('city', $request->city)
                    ->orWhere('city', 'like', $request->city.' %');
            })
            ->distinct()
            ->orderBy('postal_code')
            ->pluck('postal_code')
            ->values()
            ->all();

        return response()->json($postalCodes);
    }

    private function values(string $column, array $where = []): array
    {
        return DB::table('tbl_india_postal_codes')->where($where)->distinct()->orderBy($column)->pluck($column)->values()->all();
    }

    private function normalizePostalPlace(?string $place): string
    {
        return trim((string) preg_replace('/\s+(H\.?O\.?|S\.?O\.?|B\.?O\.?)$/i', '', trim((string) $place)));
    }
}
