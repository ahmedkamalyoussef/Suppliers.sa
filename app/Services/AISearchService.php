<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AISearchService
{
    public function analyzeQuery(string $query): array
    {
        try {
            Log::info('AI Search: Analyzing query with Groq', ['query' => $query]);

            $apiKey = env('GROQ_API_KEY');
            if (!$apiKey) {
                Log::error('AI Search: No Groq API key found');
                return $this->parseQueryManually($query);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.1-8b-instant',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'أنت مساعد بحث ذكي لنظام دليل الموردين والأعمال. مهمتك فهم أي استفسار (عامي، مكسر، إنجليزي، عربي، مخلوط) واستخراج كلمات البحث المناسبة.

قاعدة البيانات اللي بتبحث فيها:
- suppliers: name, email, phone, status, plan
- supplier_profiles: business_name, description, business_address, business_type, category, keywords, working_hours
- supplier_services: service_name (خدمات بيقدمها الموردين)
- supplier_products: product_name (منتجات بيبيعوها)
- supplier_ratings: score, comment

أمثلة على المجالات الموجودة:
الزراعة، النجارة، السباكة، الكهرباء، المحاماة، المحاسبة، التصميم، البرمجة، التسويق، التجارة، الأثاث، الإلكترونيات، السيارات، المطاعم، الكافيهات، البقالات، الصيدليات، العقارات، البناء، التشييد، التعليم، الصحة، الجمال، الرياضة

القواعد الأساسية:
1. افهم المعنى من الكلام العامي أو المكسر
2. استخرج الكلمات المفتاحية المتعلقة بنوع العمل أو الخدمة
3. حافظ على الكلمات العربية كما هي
4. حافظ على الكلمات الإنجليزية كما هي
5. اربط المرادفات والكلمات القريبة (مثال: "حد شغال في الزراعة" = "زراعة، مزارع، نباتات، محاصيل")
6. لو الكلام فيه موقع أو مكان، استخرجه
7. لو فيه تقييم مطلوب (مثلا "محل كويس" أو "5 نجوم")، استخرجه

أمثلة توضيحية:

المثال 1:
Input: "عايز حد شغال في الزراعة"
فهم المعنى: يبحث عن شخص/شركة تعمل في مجال الزراعة
Output: {"query": "زراعة مزارع نباتات محاصيل"}

المثال 2:
Input: "محتاج نجار كويس في الرياض"
فهم المعنى: يبحث عن نجار بتقييم جيد في الرياض
Output: {"query": "نجار الرياض", "location": "الرياض", "minRating": 4}

المثال 3:
Input: "محل اثاث رخيص"
فهم المعنى: يبحث عن متجر أثاث بأسعار منخفضة
Output: {"query": "اثاث أثاث furniture محل متجر"}

المثال 4:
Input: "مطعم فتح دلوقتي"
فهم المعنى: يبحث عن مطعم مفتوح الآن
Output: {"query": "مطعم restaurant", "isOpenNow": true}

المثال 5:
Input: "lawyer محامي في جدة"
فهم المعنى: يبحث عن محامي في جدة (كلام مخلوط)
Output: {"query": "محامي lawyer قانون", "location": "جدة"}

المثال 6:
Input: "بدور على supplier electronics"
فهم المعنى: يبحث عن مورد إلكترونيات
Output: {"query": "electronics الكترونيات supplier مورد"}

المثال 7:
Input: "محل كمبيوتر 5 نجوم"
فهم المعنى: يبحث عن محل كمبيوتر بتقييم 5 نجوم
Output: {"query": "كمبيوتر computer محل", "minRating": 5}

المثال 8:
Input: "عايز حد يصلح السباكة"
فهم المعنى: يبحث عن سباك أو خدمة إصلاح سباكة
Output: {"query": "سباكة سباك اصلاح plumbing"}

المثال 9:
Input: "بقالة قريبة مني"
فهم المعنى: يبحث عن بقالة قريبة (محتاج location من user)
Output: {"query": "بقالة grocery store"}

المثال 10:
Input: "مصمم جرافيك شاطر"
فهم المعنى: يبحث عن مصمم جرافيك محترف
Output: {"query": "تصميم جرافيك graphic design مصمم designer", "minRating": 4}

استراتيجية التحليل:
1. حدد نوع العمل أو الخدمة المطلوبة
2. استخرج المرادفات والكلمات ذات الصلة
3. حدد الموقع إن وجد
4. حدد التقييم المطلوب (كلمات مثل: كويس، شاطر، ممتاز، 5 نجوم = تقييم عالي)
5. حدد إذا كان يريد مكان مفتوح الآن (كلمات مثل: مفتوح، فاتح، دلوقتي، الحين)

صيغة الرد (JSON):
{
  "query": "الكلمات المفتاحية للبحث (مع المرادفات)",
  "location": "المدينة أو المنطقة (إن وجدت)",
  "minRating": رقم من 1-5 (إن وجد),
  "isOpenNow": true/false (إن وجد)
}

ملاحظات مهمة:
- query يجب يحتوي على كلمات متعددة ومرادفات (مش كلمة واحدة)
- لو المستخدم كاتب كلام عامي، ترجمه لكلمات بحث واضحة
- لو الكلام مكسر أو فيه أخطاء، صححه
- ما تسيبش query فاضي أبداً'
                    ],
                    [
                        'role' => 'user',
                        'content' => "استفسار المستخدم: \"{$query}\"\n\nحلل الاستفسار واستخرج معلومات البحث بصيغة JSON"
                    ]
                ],
                'response_format' => [
                    'type' => 'json_object'
                ],
                'temperature' => 0.3,
                'max_tokens' => 200
            ]);

            Log::info('AI Search: Groq response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if (!$response->successful()) {
                Log::error('AI Search: Groq API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return $this->parseQueryManually($query);
            }

            $result = $response->json();
            
            if (!isset($result['choices'][0]['message']['content'])) {
                Log::error('AI Search: Invalid Groq response', ['response' => $result]);
                return $this->parseQueryManually($query);
            }

            $processed = json_decode($result['choices'][0]['message']['content'], true);
            
            if (!$processed || !isset($processed['query'])) {
                Log::error('AI Search: Invalid JSON in Groq response', ['content' => $result['choices'][0]['message']['content']]);
                return $this->parseQueryManually($query);
            }

            Log::info('AI Search: Successfully processed query', [
                'original' => $query,
                'processed' => $processed
            ]);
            
            return $processed;

        } catch (\Exception $e) {
            Log::error('AI Search: Exception', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->parseQueryManually($query);
        }
    }

    /**
     * Manual fallback with basic intelligence
     */
    private function parseQueryManually(string $query): array
    {
        Log::info('AI Search: Using manual fallback', ['query' => $query]);
        
        // Basic cleaning
        $cleaned = trim($query);
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        
        $result = ['query' => $cleaned];
        
        // Try to extract location from common patterns
        $locationPatterns = [
            'في ([\p{Arabic}]+)',
            'بـ([\p{Arabic}]+)',
            'in ([a-zA-Z]+)',
        ];
        
        foreach ($locationPatterns as $pattern) {
            if (preg_match('/' . $pattern . '/u', $query, $matches)) {
                $result['location'] = $matches[1];
                break;
            }
        }
        
        // Try to detect if they want open now
        if (preg_match('/(مفتوح|فاتح|open|دلوقتي|الحين|now)/ui', $query)) {
            $result['isOpenNow'] = true;
        }
        
        // Try to detect rating requirements
        if (preg_match('/(\d)\s*(نجم|نجوم|star)/ui', $query, $matches)) {
            $result['minRating'] = (int)$matches[1];
        } elseif (preg_match('/(كويس|ممتاز|شاطر|جيد)/ui', $query)) {
            $result['minRating'] = 4;
        }
        
        return $result;
    }
}