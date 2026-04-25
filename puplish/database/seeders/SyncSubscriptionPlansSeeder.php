<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncSubscriptionPlansSeeder extends Seeder
{
    /**
     * Sync subscription plans without deleting existing data.
     * Uses INSERT IGNORE to skip duplicates based on unique name.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'basic',
                'display_name' => 'Basic',
                'description' => 'Perfect for businesses just getting started',
                'price' => 0.00,
                'currency' => 'SAR',
                'billing_cycle' => 'monthly',
                'duration_months' => 1,
                'features' => json_encode([
                    'en' => [
                        'Basic business profile listing',
                        'Contact information display',
                        'Business hours and location',
                        'Standard business description',
                        'Basic category selection',
                        'Public reviews and ratings',
                        'Standard search visibility',
                        'Basic contact form'
                    ],
                    'ar' => [
                        'إدراج ملف نشاط تجاري أساسي',
                        'عرض معلومات التواصل',
                        'ساعات العمل على الموقع',
                        'وصف نشاط قياسي',
                        'اختيار فئات أساسي',
                        'مراجعات وتقييمات عامة',
                        'ظهور قياسي في البحث',
                        'نموذج تواصل أساسي'
                    ]
                ]),
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'premium_monthly',
                'display_name' => 'Premium Business',
                'description' => 'Complete solution for serious business growth',
                'price' => 199.00,
                'currency' => 'SAR',
                'billing_cycle' => 'monthly',
                'duration_months' => 1,
                'features' => json_encode([
                    'en' => [
                        'Everything in Free, plus:',
                        'Core Business Features',
                        '📍 Pin and manage multiple locations on map',
                        '✅ Special verified business badge and certification',
                        '📞 Procurement and sales team contact numbers',
                        'Communication & Lead Generation',
                        '🤝 Send requests to be contacted by customers',
                        '💬 Direct messaging system with real-time notifications',
                        '🔔 Real-time alerts for new leads and inquiries',
                        '🎯 Dedicated customer service for lead generation',
                        '📋 Professional quotation generation tools',
                        'Premium Marketing & Analytics',
                        '⭐ Featured placement on homepage businesses section',
                        '🏆 Recommended supplier status in search results',
                        '📊 Comprehensive profile analytics and insights'
                    ],
                    'ar' => [
                        'كل ما في المجاني، بالإضافة إلى:',
                        'مزايا الأعمال الأساسية',
                        '📍 تثبيت وإدارة مواقع متعددة على الخريطة',
                        '✅ شارة توثيق خاصة وشهادة اعتماد',
                        '📞 أرقام تواصل فرق المشتريات والمبيعات',
                        'التواصل وتوليد العملاء المحتملين',
                        '🤝 إرسال طلبات للتواصل معك من العملاء',
                        '💬 رسائل مباشرة مع تنبيهات فورية',
                        '🔔 تنبيهات فورية للعملاء المحتملين و الاستفسارات',
                        '🎯 خدمة عملاء مخصصة لتوليد العملاء المحتملين',
                        '📋 أدوات احترافية لإصدار العروض',
                        'تسويق مميز وتحليلات',
                        '⭐ ظهور مميز في قسم الشركات بالصفحة الرئيسية',
                        '🏆 مورد موصى به في نتائج البحث',
                        '📊 تحليلات شاملة لأداء الملف'
                    ]
                ]),
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'premium_yearly',
                'display_name' => 'Premium Business',
                'description' => 'Complete solution for serious business growth - Save 25%',
                'price' => 1799.00,
                'currency' => 'SAR',
                'billing_cycle' => 'yearly',
                'duration_months' => 12,
                'features' => json_encode([
                    'en' => [
                        'Everything in Monthly Premium, plus:',
                        '💰 Save 589 SAR (25% off)',
                        '🎯 That\'s just 150 SAR/month billed annually',
                        '🛡️ 30-day money-back guarantee',
                        '🎯 Trusted by 5000+ Saudi businesses',
                        'All Premium Monthly Features',
                        '📍 Pin and manage multiple locations on map',
                        '✅ Special verified business badge and certification',
                        '📞 Procurement and sales team contact numbers',
                        '🤝 Send requests to be contacted by customers',
                        '💬 Direct messaging system with real-time notifications',
                        '🔔 Real-time alerts for new leads and inquiries',
                        '🎯 Dedicated customer service for lead generation',
                        '📋 Professional quotation generation tools',
                        '⭐ Featured placement on homepage businesses section',
                        '🏆 Recommended supplier status in search results',
                        '📊 Comprehensive profile analytics and insights'
                    ],
                    'ar' => [
                        'كل ما في الباقة الشهرية، بالإضافة إلى:',
                        '💰 وفّر 589 ريال (خصم 25%)',
                        '🎯 أي ما يعادل 150 ريال/شهري عند الفوترة السنوية',
                        '🛡️ ضمان استرداد 30 يوماً',
                        '🎯 موثوق من +5000 نشاط سعودي',
                        'جميع مميزات الباقة الشهرية المميزة',
                        '📍 تثبيت وإدارة مواقع متعددة على الخريطة',
                        '✅ شارة توثيق خاصة وشهادة اعتماد',
                        '📞 أرقام تواصل فرق المشتريات والمبيعات',
                        '🤝 إرسال طلبات للتواصل معك من العملاء',
                        '💬 رسائل مباشرة مع تنبيهات فورية',
                        '🔔 تنبيهات فورية للعملاء المحتملين و الاستفسارات',
                        '🎯 خدمة عملاء مخصصة لتوليد العملاء المحتملين',
                        '📋 أدوات احترافية لإصدار العروض',
                        '⭐ ظهور مميز في قسم الشركات بالصفحة الرئيسية',
                        '🏆 مورد موصى به في نتائج البحث',
                        '📊 تحليلات شاملة لأداء الملف'
                    ]
                ]),
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $inserted = 0;
        $skipped = 0;

        foreach ($plans as $plan) {
            // Check if plan already exists
            $exists = DB::table('subscription_plans')->where('name', $plan['name'])->exists();
            
            if ($exists) {
                // Update existing plan (keep ID, update other fields)
                DB::table('subscription_plans')
                    ->where('name', $plan['name'])
                    ->update([
                        'display_name' => $plan['display_name'],
                        'description' => $plan['description'],
                        'price' => $plan['price'],
                        'currency' => $plan['currency'],
                        'billing_cycle' => $plan['billing_cycle'],
                        'duration_months' => $plan['duration_months'],
                        'features' => $plan['features'],
                        'is_active' => $plan['is_active'],
                        'sort_order' => $plan['sort_order'],
                        'updated_at' => now(),
                    ]);
                Log::info('Updated subscription plan: ' . $plan['name']);
                $skipped++;
            } else {
                // Insert new plan
                DB::table('subscription_plans')->insert($plan);
                Log::info('Inserted subscription plan: ' . $plan['name']);
                $inserted++;
            }
        }

        $this->command->info("Subscription plans synced: {$inserted} inserted, {$skipped} updated.");
        Log::info('Subscription plans sync completed', ['inserted' => $inserted, 'updated' => $skipped]);
    }
}
