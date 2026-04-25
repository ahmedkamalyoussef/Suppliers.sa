<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class DefaultSubscriptionPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'basic',
                'display_name' => 'Basic',
                'description' => 'Perfect for small businesses and startups',
                'price' => 99.00,
                'currency' => 'SAR',
                'billing_cycle' => 'monthly',
                'duration_months' => 1,
                'features' => json_encode([
                    'up_to_5_products' => 'Up to 5 Products',
                    'basic_analytics' => 'Basic Analytics',
                    'email_support' => 'Email Support',
                ]),
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'professional',
                'display_name' => 'Professional',
                'description' => 'Ideal for growing businesses with more needs',
                'price' => 299.00,
                'currency' => 'SAR',
                'billing_cycle' => 'monthly',
                'duration_months' => 1,
                'features' => json_encode([
                    'up_to_25_products' => 'Up to 25 Products',
                    'advanced_analytics' => 'Advanced Analytics',
                    'priority_support' => 'Priority Support',
                    'featured_listings' => 'Featured Listings',
                ]),
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'enterprise',
                'display_name' => 'Enterprise',
                'description' => 'Complete solution for large organizations',
                'price' => 799.00,
                'currency' => 'SAR',
                'billing_cycle' => 'monthly',
                'duration_months' => 1,
                'features' => json_encode([
                    'unlimited_products' => 'Unlimited Products',
                    'premium_analytics' => 'Premium Analytics',
                    'dedicated_support' => 'Dedicated Support',
                    'api_access' => 'API Access',
                    'custom_branding' => 'Custom Branding',
                ]),
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }

        $this->command->info('Default subscription plans seeded successfully.');
    }
}
