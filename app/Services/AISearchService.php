<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AISearchService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        // تأكد أن مفتاح API موجود في ملف .env
        if (!$this->apiKey) {
            throw new \Exception("GEMINI_API_KEY is not set in environment variables.");
        }
    }

    /**
     * يحلل استفسار المستخدم ويحوله إلى مصفوفة فلاتر يمكن تطبيقها على الاستعلام.
     *
     * @param string $query استفسار المستخدم باللغة الطبيعية.
     * @return array مصفوفة تحتوي على الفلاتر المستخرجة.
     */
    public function analyzeQuery(string $query): array
    {
        // استخدام موديل Gemini 1.5 Flash للسرعة والدقة في تحليل النصوص
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$this->apiKey}";

        // قمنا بتحديث الـ Prompt لتعريف حقول البحث كلها (منتجات، شهادات، تقييمات) لـ AI
        $prompt = <<<PROMPT
You are an intelligent search assistant. Analyze the user's query and return search filters in JSON based on the business data schema:

Searchable Fields Across All Tables:
- Supplier Name
- Profile: business_name, description, category, services_offered, keywords.
- Products: product_name.
- Services: service_name.
- Certifications: certification_name.
- Branches: address, special_services.
- Ratings: score (used for minRating).

User Query: "$query"

Expected JSON format:
{
  "filters": {
    "keyword": "string|null",
    "minRating": "integer|null",
    "location": "string|null",
    "category": "string|null",
    "isOpenNow": "boolean|null"
  }
}

Important Rules for Filter Extraction:
- If the user asks for "rating above X", set "minRating": X (e.g., "rating above 4" -> 4).
- If the user searches for a specific product name, service name, or certification name, put that entire phrase in the "keyword" field.
- If the user asks for an open business, set "isOpenNow": true.
- If the user searches for a location (city, area), set the location field.

Return JSON ONLY.

PROMPT;

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    "contents" => [["parts" => [["text" => $prompt]]]]
                ]);

            $text = $response->json("candidates.0.content.parts.0.text");

            // تنظيف النص المستخرج لضمان أنه JSON فقط
            $jsonStart = strpos($text, '{');
            $jsonEnd = strrpos($text, '}') + 1;
            $jsonString = substr($text, $jsonStart, $jsonEnd - $jsonStart);

            // قد يقوم Gemini أحياناً بإرجاع نص توضيحي قبل الـ JSON، هذا يضمن استخراج الـ JSON فقط.
            return json_decode($jsonString, true) ?? [];

        } catch (\Exception $e) {
            Log::error('AI Search Service Failed: ' . $e->getMessage());
            // Fallback: في حالة فشل الاتصال أو التحليل، نعتبر الاستفسار كله كلمة بحث.
            return ['filters' => ['keyword' => $query]];
        }
    }
}