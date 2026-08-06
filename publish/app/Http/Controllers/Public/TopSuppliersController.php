<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopSuppliersController extends Controller
{
    public function topRated(Request $request): JsonResponse
    {
        $limit = min($request->query('limit', 10), 50); // Default 10, max 50
        
        $topSuppliers = Supplier::with(['profile', 'approvedRatings'])
            ->whereHas('approvedRatings')
            ->withAvg(['approvedRatings as average_rating'], 'score')
            ->orderByDesc('average_rating')
            ->limit($limit)
            ->get()
            ->map(function ($supplier) {
                $ratings = $supplier->approvedRatings;
                $averageRating = $ratings->avg('score');
                $totalRatings = $ratings->count();
                
                // Get actual profile views from analytics
                $profileViews = \DB::table('analytics_views_history')
                    ->where('supplier_id', $supplier->id)
                    ->sum('views_count');
                
                // Get business image with fallback to supplier image and full URL
                $businessImage = $supplier->profile?->business_image ?? $supplier->image ?? null;
                if ($businessImage) {
                    $businessImage = url($businessImage);
                }
                
                // Get certifications - first 3 names and total count
                $certifications = $supplier->certifications()
                    ->select('certification_name')
                    ->distinct()
                    ->limit(3)
                    ->pluck('certification_name')
                    ->toArray();
                
                $totalCertifications = $supplier->certifications()->count();
                
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'email' => $supplier->email,
                    'phone' => $supplier->phone,
                    'business_name' => $supplier->profile?->business_name,
                    'business_type' => $supplier->profile?->business_type,
                    'category' => $supplier->profile?->category,
                    'business_image' => $businessImage,
                    'profile_visibility' => $supplier->profile_visibility,
                    'allow_direct_contact' => $supplier->allow_direct_contact,
                    'average_rating' => round($averageRating, 2),
                    'total_ratings' => $totalRatings,
                    'profile_views' => $profileViews,
                    'certifications' => $certifications,
                    'total_certifications' => $totalCertifications,
                    'status' => $supplier->status,
                    'plan' => $supplier->plan,
                    'created_at' => $supplier->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'suppliers' => $topSuppliers,
            'count' => $topSuppliers->count(),
            'limit' => $limit,
        ]);
    }
    
    public function mostActive(Request $request): JsonResponse
    {
        $limit = min($request->query('limit', 10), 50); // Default 10, max 50
        
        $activeSuppliers = Supplier::with(['profile'])
            ->withCount(['inquiries', 'ratings'])
            ->orderBy('inquiries_count', 'desc')
            ->orderBy('ratings_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($supplier) {
                $ratings = $supplier->ratings()->where('is_approved', true)->get();
                $averageRating = $ratings->count() > 0 ? $ratings->avg('score') : 0;
                
                // Get actual profile views from analytics
                $profileViews = \DB::table('analytics_views_history')
                    ->where('supplier_id', $supplier->id)
                    ->sum('views_count');
                
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'email' => $supplier->email,
                    'business_name' => $supplier->profile?->business_name,
                    'category' => $supplier->profile?->category,
                    'inquiries_count' => $supplier->inquiries_count,
                    'ratings_count' => $supplier->ratings_count,
                    'approved_ratings_count' => $ratings->count(),
                    'average_rating' => round($averageRating, 2),
                    'profile_views' => $profileViews,
                    'status' => $supplier->status,
                    'plan' => $supplier->plan,
                    'created_at' => $supplier->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'suppliers' => $activeSuppliers,
            'count' => $activeSuppliers->count(),
            'limit' => $limit,
        ]);
    }
    
    public function mostViewed(Request $request): JsonResponse
    {
        $limit = min($request->query('limit', 10), 50); // Default 10, max 50
        
        $viewedSuppliers = Supplier::with(['profile', 'approvedRatings'])
            ->whereHas('profile')
            ->limit($limit)
            ->get()
            ->map(function ($supplier) {
                $ratings = $supplier->approvedRatings;
                $averageRating = $ratings->count() > 0 ? $ratings->avg('score') : 0;
                
                // Get actual profile views from analytics
                $profileViews = \DB::table('analytics_views_history')
                    ->where('supplier_id', $supplier->id)
                    ->sum('views_count');
                
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'email' => $supplier->email,
                    'business_name' => $supplier->profile?->business_name,
                    'category' => $supplier->profile?->category,
                    'profile_views' => $profileViews,
                    'average_rating' => round($averageRating, 2),
                    'total_ratings' => $ratings->count(),
                    'inquiries_count' => $supplier->inquiries()->count(),
                    'status' => $supplier->status,
                    'plan' => $supplier->plan,
                    'created_at' => $supplier->created_at->format('Y-m-d H:i:s'),
                ];
            })
            ->sortByDesc('profile_views')
            ->values();

        return response()->json([
            'suppliers' => $viewedSuppliers,
            'count' => $viewedSuppliers->count(),
            'limit' => $limit,
        ]);
    }
}
