<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = BusinessAvailability::query();

        // Optional filtering by year and month
        if ($request->filled('year')) {
            $query->whereYear('date', $request->input('year'));
        }

        if ($request->filled('month')) {
            $query->whereMonth('date', $request->input('month'));
        }

        $availabilities = $query->where('date', '>=', Carbon::now()->subDays(10))->orderBy('date', 'asc')->get();

        return response()->json([
            'data' => $availabilities->map(function ($availability) {
                return [
                    'date' => $availability->date->format('Y-m-d'),
                    'is_fully_unavailable' => $availability->is_fully_unavailable,
                    'unavailable_start_time' => $availability->unavailable_start_time,
                    'unavailable_end_time' => $availability->unavailable_end_time,
                ];
            }),
        ]);
    }
}
