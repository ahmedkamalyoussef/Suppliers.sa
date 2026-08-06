<?php

namespace App\Http\Controllers;

use App\Models\PublicBusinessesStatistics;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicBusinessesStatisticsController extends Controller
{
    /**
     * Get public businesses statistics (accessible to everyone)
     */
    public function index()
    {
        $statistics = PublicBusinessesStatistics::first();
        
        if (!$statistics) {
            // Create default statistics if none exist
            $statistics = PublicBusinessesStatistics::create([
                'verified_businesses' => 0,
                'successful_connections' => 0,
                'average_rating' => 0.00,
            ]);
        }

        return response()->json([
            'verified_businesses' => $statistics->verified_businesses,
            'successful_connections' => $statistics->successful_connections,
            'average_rating' => $statistics->average_rating,
        ]);
    }

    /**
     * Update or create statistics (admin only)
     */
    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'verified_businesses' => 'required|integer|min:0',
                'successful_connections' => 'required|integer|min:0',
                'average_rating' => 'required|numeric|min:0|max:5',
            ]);

            $statistics = PublicBusinessesStatistics::first();
            
            if (!$statistics) {
                $statistics = PublicBusinessesStatistics::create($validated);
            } else {
                $statistics->update($validated);
            }

            return response()->json([
                'message' => 'data updated successfuly',
                'data' => $statistics,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
