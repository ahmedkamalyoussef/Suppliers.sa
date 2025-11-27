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

            // في دالة analyzeQuery، غيّر الـ system message:

$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $apiKey,
    'Content-Type' => 'application/json',
])->post('https://api.groq.com/openai/v1/chat/completions', [
    'model' => 'llama-3.1-8b-instant',
    'messages' => [
        [
            'role' => 'system',
'content' => 'You are an intelligent search assistant for a suppliers directory system. Your PRIMARY goal is to ALWAYS extract something useful from ANY user query, no matter how messy, mixed, or unclear.

DATABASE STRUCTURE YOU SEARCH:
- suppliers: name, email, phone, status, plan
- supplier_profiles: business_name, description, business_address, business_type, category, keywords, working_hours, latitude, longitude
- supplier_services: service_name
- supplier_products: product_name
- supplier_ratings: score, comment

CRITICAL RULES:
1. ALWAYS return a valid query - NEVER return empty or null
2. Extract ANY meaningful word or phrase from the input
3. Keep Arabic terms EXACTLY as they are - do NOT translate
4. Keep English terms as they are
5. Handle mixed Arabic/English naturally
6. If query seems random, extract the most business-relevant words
7. Remove only: special characters (except Arabic letters), excessive spaces, numbers (unless part of address/phone)

EXAMPLES:
Input: "محل اثاث في الرياض"
Output: {"query": "محل اثاث الرياض"}

Input: "furniture store riyadh"
Output: {"query": "furniture store riyadh"}

Input: "عايز supplier بياع products رخيصة"
Output: {"query": "supplier بياع products"}

Input: "$$$ random !!! محل @@@ cheap"
Output: {"query": "محل cheap"}

Input: "123 بقالة 456 near me"
Output: {"query": "بقالة near"}

Input: "asdfgh محامي qwerty"
Output: {"query": "محامي"}

Input: "مطعم restaurant كويس good"
Output: {"query": "مطعم restaurant"}

STRATEGY:
1. Identify all Arabic words → keep them
2. Identify all English business-related words → keep them
3. Remove noise (symbols, excessive numbers, meaningless text)
4. Combine remaining words with spaces
5. If nothing meaningful found, return the least noisy part of input

RESPONSE FORMAT:
Always return ONLY this JSON structure:
{"query": "extracted search terms"}

The query field must NEVER be empty. If truly no words found, return the original input trimmed

'
        ],
        [
            'role' => 'user', 
            'content' => "Extract searchable terms from: \"{$query}\"\n\nReturn JSON: {\"query\": \"processed query\"}"
        ]
    ],
    'response_format' => [
        'type' => 'json_object'
    ],
    'temperature' => 0.1,
    'max_tokens' => 150
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

            Log::info('AI Search: Successfully processed query', ['query' => $processed['query']]);
            return ['query' => $processed['query']];

        } catch (\Exception $e) {
            Log::error('AI Search: Groq Error', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            
            return $this->parseQueryManually($query);
        }
    }

    private function buildPrompt(string $query): string
    {
        return "You are a smart business search assistant. Analyze the user's query and extract search filters.

Available filters:
- keyword: general search term for business name, description, or services
- minRating: minimum rating (number from 1-5)
- maxRating: maximum rating (number from 1-5) 
- location: city or area name
- category: business type (Furniture, Automobile, Electronics, IT, Consulting, Retail, Store, Supplier, Individual)
- isOpenNow: whether business should be currently open

User query: \"{$query}\"

Return JSON with filters object. Only include filters that are explicitly mentioned. Handle mixed languages (Arabic, English, Spanish).";
    }

    /**
     * Manual fallback - just return the original query
     */
    private function parseQueryManually(string $query): array
    {
        Log::info('AI Search: Using manual fallback', ['query' => $query]);
        return ['query' => $query];
    }
}
