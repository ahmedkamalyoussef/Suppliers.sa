<?php

namespace App\Http\Controllers;

use App\Models\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function getMaintenanceStatus(Request $request): JsonResponse
    {
        try {
            $settings = SystemSettings::first();
            
            if (! $settings) {
                // Return default maintenance mode if no settings exist
                $maintenanceMode = false;
            } else {
                $maintenanceMode = $settings->maintenance_mode;
            }

            return response()->json([
                'success' => true,
                'maintenance_mode' => (bool) $maintenanceMode
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get maintenance status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
