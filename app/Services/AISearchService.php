<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AISearchService
{
    private $knowledgeBase;
    
    public function __construct()
    {
        $this->loadKnowledge();
    }
    
    private function loadKnowledge()
    {
        $this->knowledgeBase = [
            'categories' => [
                'Agriculture' => ['زراعة', 'زراعه', 'مزارع', 'نبات', 'محصول', 'حقل', 'فلاحة', 'زرع', 'agriculture', 'farm', 'crop', 'farming'],
                'Computer Hardware & Software' => ['كمبيوتر', 'كومبيوتر', 'حاسوب', 'برمجة', 'سوفتوير', 'تقنية', 'لابتوب', 'computer', 'software', 'it', 'tech', 'laptop', 'programming'],
                'Construction & Real Estate' => ['بناء', 'عقارات', 'مقاول', 'تشييد', 'انشاءات', 'إنشاءات', 'construction', 'building', 'contractor', 'real estate'],
                'Electronics & Electrical Supplies' => ['كهرباء', 'كهربائي', 'الكترونيات', 'إلكترونيات', 'اسلاك', 'أسلاك', 'electrical', 'electronics', 'electric'],
                'Food & Beverage' => ['طعام', 'اكل', 'أكل', 'مطعم', 'مشروبات', 'طبخ', 'food', 'restaurant', 'beverage', 'drink'],
                'Health & Beauty' => ['صحة', 'جمال', 'طبي', 'دكتور', 'علاج', 'health', 'beauty', 'medical', 'doctor'],
                'Automobile' => ['سيارات', 'عربيات', 'سيارة', 'عربية', 'cars', 'automobile', 'vehicle', 'auto'],
                'Apparel & Fashion' => ['ملابس', 'موضة', 'هدوم', 'لبس', 'فاشن', 'فاشون', 'أزياء', 'fashion', 'clothes', 'apparel', 'clothing'],
                'Furniture' => ['اثاث', 'أثاث', 'عفش', 'موبيليا', 'furniture'],
                'Textiles & Fabrics' => ['نسيج', 'قماش', 'اقمشة', 'أقمشة', 'textile', 'fabric'],
                'Chemicals' => ['كيماويات', 'كيميائي', 'كيميائية', 'chemical', 'chemistry'],
                'Plastics & Products' => ['بلاستيك', 'بلاستك', 'plastic'],
                'Printing & Publishing' => ['طباعة', 'طباعه', 'نشر', 'مطبعة', 'مطبعه', 'printing', 'publishing', 'print'],
                'Consumer Electronics' => ['الكترونيات', 'إلكترونيات', 'اجهزة', 'أجهزة', 'تلفزيون', 'موبايل', 'electronics', 'devices', 'gadgets'],
                'Hospital & Medical Supplies' => ['مستشفى', 'مستشفي', 'طبي', 'صحي', 'hospital', 'medical', 'clinic'],
            ],
            
            'rating_patterns' => [
                'less_than' => [
                    '/(?:اقل|أقل|ادنى|أدنى|تحت)\s+(?:من\s+)?(\d+)/ui',
                    '/(?:اقل|أقل|ادنى|أدنى|تحت)\s+(?:من\s+)?(\d+)\s*(?:نجمة|نجوم|stars?|star)/ui',
                    '/(?:اقل|أقل|ادنى|أدنى|تحت)\s+(?:من\s+)?(\d+)\s*(?:تقييم|تقيم|rating)/ui',
                    '/(?:لا يريد|لا أريد|لا أبحث عن|مرفوض|رفض|لا أحب|لا أريد)\s+(\d+)/ui',
                    '/(?:لا يريد|لا أريد|لا أبحث عن|مرفوض|رفض|لا أحب|لا أريد)\s+(\d+)\s*(?:نجمة|نجوم|stars?|star)/ui',
                    '/(?:سيء|ضعيف|رديء|فاشل|أسوأ|bad|poor|terrible|awful|horrible|worst)\s+(\d+)/ui',
                    '/(?:سيء|ضعيف|رديء|فاشل|أسوأ|bad|poor|terrible|awful|horrible|worst)\s+(\d+)\s*(?:نجمة|نجوم|stars?|star)/ui'
                ],
                'greater_than' => [
                    '/(?:اكبر|أكبر|اعلى|أعلى|فوق)\s+(?:من\s+)?(\d+)/ui',
                    '/(?:اكبر|أكبر|اعلى|أعلى|فوق)\s+(?:من\s+)?(\d+)\s*(?:نجمة|نجوم|stars?|star)/ui',
                    '/(?:اكبر|أكبر|اعلى|أعلى|فوق)\s+(?:من\s+)?(\d+)\s*(?:تقييم|تقيم|rating)/ui',
                    '/(?:يحتاج|أريد|أبحث عن|أفضل|ممتاز|جيد|good|excellent|needs|want|search for|best|great)\s+(\d+)/ui',
                    '/(?:يحتاج|أريد|أبحث عن|أفضل|ممتاز|جيد|good|excellent|needs|want|search for|best|great)\s+(\d+)\s*(?:نجمة|نجوم|stars?|star)/ui'
                ],
                'direct' => [
                    '/(?:تقييم|تقيم|ريت|rating|rate|score)\s+(\d+)/ui',
                    '/(?:تقييم|تقيم|ريت|rating|rate|score)\s*[:=]\s*(\d+)/ui',
                    '/(?:تقييم|تقيم|ريت|rating|rate|score)\s*(\d+)/ui',
                    '/(\d+)\s*(?:نجمة|نجوم|stars?|star)/ui',
                    '/(\d+)\s*(?:من|out of)\s*(\d+)/ui',
                    '/(\d+)\s*\/\s*(\d+)/ui',
                    '/(\d+)\.?\d*\s*(?:من|out of|\/)\s*5/ui',
                    '/(\d+)\.?\d*\s*(?:من|out of|\/)\s*10/ui'
                ],
                'keywords' => [
                    5 => ['ممتاز', 'رائع', 'عظيم', 'خرافي', 'احسن', 'أفضل', 'مثالي', 'مذهل', 'فائق', 'استثنائي', 'superb', 'outstanding', 'excellent', 'amazing', 'perfect', 'wonderful', 'fantastic', 'awesome', 'brilliant', 'exceptional', 'premium', 'top rated', 'five star', '5 star'],
                    4 => ['جيد جداً', 'جيد جدا', 'رائع جداً', 'رائع جدا', 'ممتاز', 'ممتاز جداً', 'ممتاز جدا', 'very good', 'very good', 'great', 'awesome', 'fantastic', 'four star', '4 star', 'highly rated', 'well rated'],
                    3 => ['جيد', 'مقبول', 'طيب', 'لائق', 'متوسط', 'عادي', 'مضحك', 'good', 'decent', 'acceptable', 'average', 'ok', 'okay', 'fair', 'three star', '3 star', 'normal', 'regular'],
                    2 => ['سيء', 'ضعيف', 'رديء', 'فاشل', 'سيء جداً', 'سيء جدا', 'ضعيف جداً', 'ضعيف جدا', 'bad', 'poor', 'terrible', 'awful', 'horrible', 'two star', '2 star', 'low rated', 'below average'],
                    1 => ['سيء جداً', 'سيء جدا', 'أسوأ', 'الأسوأ', 'فاشل جداً', 'فاشل جدا', 'كارثي', 'very bad', 'very poor', 'terrible', 'awful', 'horrible', 'disaster', 'one star', '1 star', 'worst rated'],
                    0 => ['صفر', 'لا شيء', 'بدون تقييم', 'غير مصنف', 'zero', 'nothing', 'no rating', 'unrated', 'not rated', 'no stars', '0 star', 'not classified']
                ]
            ],
            
            'open_now_words' => [
                'دلوقتي', 'دلوقت', 'الآن', 'الان', 'الحين', 'هسا', 'هلأ', 'فاتح', 'مفتوح', 
                'يشتغل', 'يعمل', 'شغال', 'now', 'open', 'working', 'available', 
                'عاجل', 'ضروري', 'طارئ', 'urgent', 'emergency', 'سريع', 'بسرعة', 'fast'
            ],
            
            'stop_words' => [
                'في', 'من', 'إلى', 'الى', 'ال', 'يا', 'عم', 'ريت', 'لو', 'ممكن', 'انا', 'أنا',
                'the', 'a', 'an', 'and', 'or', 'in', 'on', 'at', 'to', 'is', 'with'
            ]
        ];
    }
    
    /**
     * التحليل الرئيسي
     */
    public function analyzeQuery(string $query): array
    {
        Log::info('🔍 AI Search Started', ['original_query' => $query]);
        
        $query = trim($query);
        
        if (empty($query)) {
            return $this->defaultResponse();
        }
        
        // التحليل المحلي أولاً (ضمان وجود نتيجة)
        $localResult = $this->detailedLocalAnalysis($query);
        Log::info('📊 Local Analysis', $localResult);
        
        // محاولة استخدام AI الخارجي
        $aiResult = $this->callExternalAI($query);
        
        if ($aiResult) {
            Log::info('🤖 AI Response', $aiResult);
            // دمج النتائج
            $final = $this->intelligentMerge($aiResult, $localResult, $query);
        } else {
            Log::info('⚠️ AI Failed, using local only');
            $final = $localResult;
        }
        
        // التأكد من وجود query
        if (empty($final['query']) || strlen($final['query']) < 3) {
            $final['query'] = $this->buildFallbackQuery($query, $final);
        }
        
        Log::info('✅ Final Result', $final);
        
        return $final;
    }
    
    /**
     * تحليل محلي مفصّل - MORE FLEXIBLE
     */
    private function detailedLocalAnalysis(string $query): array
    {
        $queryLower = mb_strtolower($query);
        
        $result = [
            'query' => '',
            'category' => null,
            'minRating' => null,
            'isOpenNow' => false,
            'location' => null
        ];
        
        $keywords = [];
        
        // 1. استخراج الفئة
        $category = $this->findCategory($queryLower);
        if ($category) {
            $result['category'] = $category;
            // إضافة كل مرادفات الفئة
            $keywords = array_merge($keywords, $this->knowledgeBase['categories'][$category]);
            Log::info('✓ Category found', ['category' => $category]);
        }
        
        // 2. استخراج التقييم (محسّن جداً)
        $rating = $this->extractRatingAdvanced($queryLower);
        if (!empty($rating)) {
            if (isset($rating['minRating'])) {
                $result['minRating'] = $rating['minRating'];
                $keywords = array_merge($keywords, ['جيد', 'ممتاز', 'موثوق', 'محترم', 'good', 'excellent', 'reliable']);
                // Add general business keywords when rating is specified
                $keywords = array_merge($keywords, ['مورد', 'خدمة', 'شخص', 'شركة', 'supplier', 'service', 'provider', 'business']);
                Log::info('✓ Min Rating found', ['minRating' => $rating['minRating']]);
            }
            if (isset($rating['maxRating'])) {
                $result['maxRating'] = $rating['maxRating'];
                $keywords = array_merge($keywords, ['سيء', 'ضعيف', 'رديء', 'bad', 'poor', 'terrible']);
                // Add general business keywords when rating is specified
                $keywords = array_merge($keywords, ['مورد', 'خدمة', 'شخص', 'شركة', 'supplier', 'service', 'provider', 'business']);
                Log::info('✓ Max Rating found', ['maxRating' => $rating['maxRating']]);
            }
        }
        
        // 3. استخراج "مفتوح الآن"
        if ($this->isOpenNowQuery($queryLower)) {
            $result['isOpenNow'] = true;
            $keywords = array_merge($keywords, ['مفتوح', 'يعمل', 'متاح', 'open', 'available']);
            Log::info('✓ Open now detected');
        }
        
        // 4. استخراج الموقع
        $location = $this->extractLocation($query);
        if ($location) {
            $result['location'] = $location;
            Log::info('✓ Location found', ['location' => $location]);
        }
        
        // 5. استخراج كل الكلمات المفيدة - LESS RESTRICTIVE
        $words = $this->extractAllMeaningfulWords($query);
        $keywords = array_merge($keywords, $words);
        
        // 5.5. Add category name parts if category found
        if ($category) {
            // Split category name into words (e.g., "Apparel & Fashion" -> ["Apparel", "Fashion"])
            $categoryWords = preg_split('/[\s&]+/', $category);
            $keywords = array_merge($keywords, $categoryWords);
        }
        
        // 6. إضافة كلمات عامة إذا لزم الأمر
        if (count($keywords) < 5) {
            $keywords = array_merge($keywords, ['مورد', 'خدمة', 'شخص', 'supplier', 'service', 'provider']);
        }
        
        // بناء الاستعلام النهائي - INCLUDE MORE KEYWORDS
        $keywords = array_unique($keywords);
        $keywords = array_filter($keywords, function($k) {
            return mb_strlen($k) >= 2; // Less restrictive - allow 2 letter words
        });
        
        $result['query'] = implode(' ', array_slice($keywords, 0, 30)); // More keywords
        
        return $result;
    }
    
    /**
     * البحث عن الفئة - محسّن
     */
    private function findCategory(string $text): ?string
    {
        $text = $this->normalizeArabic($text);
        
        // Direct category matching first
        foreach ($this->knowledgeBase['categories'] as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, mb_strtolower($keyword))) {
                    return $category;
                }
            }
        }
        
        // Enhanced pattern matching for broader category detection
        $categoryPatterns = [
            'Apparel & Fashion' => [
                'ملابس', 'موضة', 'أزياء', 'فاشون', 'هدوم', 'لبس', 'قميص', 'فستان', 'بنطلون', 'جينز', 'بلوزة', 'جاكيت', 'معطف', 'بذلة', 'بدلة', 'شورت', 'كراوة', 'تيشرت', 'سويت شيرت', 'كاب', 'شال', 'طرحة', 'حجاب', 'عباية', 'نقاب', 'برقع', 'جلابية', 'دشداشة', 'قميص نوم', 'ملابس داخلية', 'لانجري', 'سبورتنج', 'رياضي', 'ميدي', 'بكيني', 'مايوه', 'ملابس سباحة',
                'clothing', 'fashion', 'apparel', 'garments', 'textiles', 'outfits', 'style', 'shirt', 'dress', 'pants', 'jeans', 'blouse', 'jacket', 'coat', 'suit', 'shorts', 'sweater', 't-shirt', 'cap', 'scarf', 'sportswear', 'swimwear', 'lingerie', 'underwear'
            ],
            'Agriculture' => [
                'زراعة', 'فلاحة', 'محاصيل', 'أسمدة', 'بذور', 'معدات زراعية', 'ري', 'تربة', 'مبيد', 'شتلة', 'نبات', 'شجرة', 'ثمار', 'خضروات', 'فواكه', 'حبوب', 'أرز', 'قمح', 'شعير', 'ذرة', 'قطن', 'قصب سكر', 'زيتون', 'نخيل', 'زراعة عضوية', 'زراعة مائية', 'دفيئة', 'صوب زراعية', 'جرارات', 'حصادات', 'نضح', 'رش', 'تسميد',
                'agriculture', 'farming', 'crops', 'fertilizer', 'seeds', 'irrigation', 'soil', 'pesticide', 'seedlings', 'plants', 'trees', 'fruits', 'vegetables', 'grains', 'rice', 'wheat', 'corn', 'cotton', 'organic', 'greenhouse', 'tractor', 'harvest'
            ],
            'Computer Hardware & Software' => [
                'كمبيوتر', 'برمجيات', 'سوفتوير', 'تقنية', 'تكنولوجيا', 'لابتوب', 'كمبيوتر محمول', 'شاشة', 'طابعة', 'كيبورد', 'ماوس', 'برنامج', 'تطبيق', 'ويندوز', 'لينكس', 'ماك', 'أندرويد', 'آيفون', 'آيباد', 'جهاز لوحي', 'هاردوير', 'سوفتوير', 'برمجة', 'تطوير', 'شبكة', 'إنترنت', 'واي فاي', 'سيرفر', 'قاعدة بيانات', 'سحابة', 'حوسبة سحابية', 'ذكاء اصطناعي', 'تعلم الآلة', 'بلوك تشين',
                'computer', 'software', 'technology', 'laptop', 'pc', 'monitor', 'printer', 'keyboard', 'mouse', 'program', 'app', 'windows', 'linux', 'mac', 'android', 'iphone', 'ipad', 'tablet', 'hardware', 'programming', 'development', 'network', 'internet', 'wifi', 'server', 'database', 'cloud', 'ai', 'machine learning', 'blockchain'
            ],
            'Construction & Real Estate' => [
                'بناء', 'مقاولات', 'تشييد', 'عقار', 'إنشاءات', 'مباني', 'عمارة', 'ترميم', 'تجديد', 'هدم', 'أساسات', 'خرسانة', 'إسمنت', 'طوب', 'حديد', 'سقف', 'أرضيات', 'دهان', 'طلاء', 'سباكة', 'كهرباء', 'تكييف', 'عزل', 'ديكور', 'تصميم داخلي', 'أثاث', 'مفروشات', 'شقة', 'فيلا', 'عمارة', 'برج', 'مول', 'مركز تجاري', 'مكتب', 'محل', 'مستودع', 'مصنع', 'ورشة',
                'construction', 'building', 'real estate', 'contracting', 'architecture', 'renovation', 'demolition', 'foundation', 'concrete', 'brick', 'iron', 'roof', 'flooring', 'painting', 'plumbing', 'electrical', 'ac', 'insulation', 'decor', 'interior design', 'furniture', 'apartment', 'villa', 'tower', 'mall', 'office', 'shop', 'warehouse', 'factory'
            ],
            'Electronics & Electrical Supplies' => [
                'كهرباء', 'تركيبات كهربائية', 'صيانة', 'أسلاك', 'قواطع', 'لمبات', 'إنارة', 'أجهزة', 'إلكترونيات', 'تلفزيون', 'رسيفر', 'جهاز تحكم', 'ريموت', 'هاتف', 'موبايل', 'تابلت', 'كاميرا', 'سماعات', 'مكبر صوت', 'شاحن', 'بطارية', 'باور بانك', 'كهربائي', 'ميكانيكا', 'تكييف', 'مراوح', 'سخانات', 'غسالات', 'ثلاجات', 'غسالات أطباق', 'فرن', 'ميكروويف', 'خلاط', 'عصارة', 'مكنسة', 'مكنسة كهربائية',
                'electronics', 'electrical', 'wiring', 'circuit', 'lighting', 'appliances', 'tv', 'receiver', 'remote', 'phone', 'mobile', 'tablet', 'camera', 'speakers', 'charger', 'battery', 'power bank', 'electrician', 'mechanic', 'ac', 'fan', 'heater', 'washing machine', 'refrigerator', 'dishwasher', 'oven', 'microwave', 'blender', 'vacuum cleaner'
            ],
            'Food & Beverage' => [
                'طعام', 'مطاعم', 'وجبات سريعة', 'مطبخ', 'طبخ', 'أكل', 'بيتزا', 'برجر', 'ساندوتش', 'فريد تشيكن', 'شاورما', 'كباب', 'فلافل', 'حمص', 'فول', 'طاجن', 'كبسة', 'مندي', 'مقلوبة', 'محشي', 'ورق عنب', 'مسقعة', 'بامية', 'ملوخية', 'سبانخ', 'سلطة', 'شوربة', 'حساء', 'مشروب', 'عصير', 'قهوة', 'شاي', 'نسكافيه', 'كابتشينو', 'لاتيه', 'موكا', 'كوكاكولا', 'بيبسي', 'مياه غازية', 'عصائر طبيعية', 'سموذي', 'ميلك شيك', 'آيس كريم', 'حلويات', 'كيك', 'بسكويت', 'شوكولاتة', 'حلوى شرقية', 'كنافة', 'قطايف', 'بقلاوة', 'تمر', 'مكسرات', 'فواكه', 'سلطات فواكه',
                'food', 'restaurant', 'cooking', 'catering', 'delivery', 'fast food', 'pizza', 'burger', 'sandwich', 'chicken', 'shawarma', 'kebab', 'falafel', 'hummus', 'coffee', 'tea', 'juice', 'soda', 'water', 'smoothie', 'milkshake', 'ice cream', 'dessert', 'cake', 'chocolate', 'fruits', 'salad', 'soup'
            ],
            'Health & Beauty' => [
                'صحة', 'طب', 'علاج', 'دواء', 'مستشفى', 'عيادة', 'رعاية صحية', 'طب بيطري', 'صيدلية', 'طبيب', 'دكتور', 'ممرضة', 'جراحة', 'عملية', 'فحص', 'تحاليل', 'أشعة', 'طب أسنان', 'عيادة أسنان', 'نظارات', 'عدسات لاصقة', 'سماعات طبية', 'أجهزة طبية', 'كرسي متحرك', 'عكازات', 'أطراف صناعية', 'تجميل', 'عناية بالبشرة', 'صالونات', 'تجميل نسائي', 'صبغة شعر', 'قص شعر', 'مكياج', 'كريمات', 'مرطبات', 'واقي شمس', 'مستحضرات تجميل', 'عطور', 'بارفان', 'دهانات', 'زيوت', 'شامبو', 'بلسم', 'صبغة', 'فروة رأس', 'عناية بالشعر', 'تقشير', 'ليزر', 'تخسيس', 'نحافة', 'ريجيم', 'حمية', 'تغذية', 'فيتامينات', 'مكملات غذائية', 'بروتين', 'منتجات طبيعية', 'أعشاب',
                'health', 'medical', 'hospital', 'clinic', 'pharmacy', 'doctor', 'nurse', 'surgery', 'test', 'x-ray', 'dentist', 'glasses', 'contact lenses', 'hearing aids', 'medical devices', 'wheelchair', 'beauty', 'skincare', 'salon', 'hair', 'makeup', 'cosmetics', 'perfume', 'cream', 'lotion', 'sunscreen', 'shampoo', 'laser', 'diet', 'nutrition', 'vitamins', 'supplements', 'protein', 'natural products', 'herbs'
            ],
            'Automobile' => [
                'سيارات', 'محركات', 'ميكانيكا', 'صيانة سيارات', 'قطع غيار', 'بيع سيارات', 'تأجير سيارات', 'سيارة مستعملة', 'سيارة جديدة', 'مرسيدس', 'بي إم دبليو', 'أودي', 'فولكس فاجن', 'تويوتا', 'هوندا', 'نيسان', 'كيا', 'هيundai', 'فورد', 'شيفروليه', 'جي إم سي', 'لاند روفر', 'جاغوار', 'بورش', 'فيراري', 'لامبورغيني', 'مازيراتي', 'بنتلي', 'رولز رويس', 'تسلا', 'سيارة كهربائية', 'هجين', 'بنزين', 'ديزل', 'زيت', 'إطارات', 'بطارية', 'شمعات', 'فلاتر', 'فرامل', 'كوابح', 'تعليق', 'جير', 'ناقل حركة', 'محرك', ' radiator', 'مكيف سيارة', 'صندوق', 'شنطة', 'جناح', 'مرآة', 'زجاج', 'طلاء', 'غسيل سيارات', 'تلميع', 'بوليش', 'شمع سيارة', 'كفرات', 'جنط', 'سحب',
                'automotive', 'cars', 'vehicles', 'mechanic', 'auto repair', 'parts', 'dealership', 'rental', 'used cars', 'new cars', 'mercedes', 'bmw', 'audi', 'vw', 'toyota', 'honda', 'nissan', 'kia', 'hyundai', 'ford', 'chevrolet', 'gmc', 'land rover', 'jaguar', 'porsche', 'ferrari', 'lamborghini', 'maserati', 'bentley', 'rolls royce', 'tesla', 'electric car', 'hybrid', 'gasoline', 'diesel', 'oil', 'tires', 'battery', 'brakes', 'suspension', 'transmission', 'engine', 'ac', 'car wash', 'wax', 'alloys', 'rims'
            ],
            'Furniture' => [
                'أثاث', 'مفروشات', 'كنب', 'طاولات', 'سرير', 'ديكور', 'تصميم داخلي', 'كراسي', 'خزائن', 'رفوف', 'مكتبة', 'دولاب', 'غرفة نوم', 'غرفة معيشة', 'طعام', 'مطبخ', 'أطفال', 'سفرة', 'كنبة', 'لاندري', 'مرآة', 'إضاءة', 'ستائر', 'سجاد', 'بسط', 'وسائد', 'بطانيات', 'لحاف', 'شراشف', 'مفارش', 'طاولات جانبية', 'طاولات قهوة', 'طاولات كمبيوتر', 'كرسي مكتب', 'كرسي أطفال', 'سرير أطفال', 'سرير مزدوج', 'سرير كنج', 'أريكة', 'صوفا', 'تشيز لونج', 'بانكو', 'أوتومان', 'رفوف حائط', 'رفوف زجاج', 'خزانة أطفال', 'خزانة ملابس', 'خزانة كتب', 'تلفزيون', 'ستاند تلفزيون', 'مكتب', 'مكتب كمبيوتر', 'مكتب دراسة', 'مكتب طفل', 'كرسي جلوس', 'كرسي طعام', 'كرسي بار',
                'furniture', 'home decor', 'interior design', 'sofa', 'bed', 'table', 'chair', 'cabinet', 'shelves', 'bookshelf', 'wardrobe', 'bedroom', 'living room', 'dining', 'kitchen', 'kids', 'mirror', 'lighting', 'curtains', 'carpet', 'rugs', 'pillows', 'blankets', 'sheets', 'coffee table', 'desk', 'tv stand', 'ottoman'
            ],
            'Printing & Publishing' => [
                'طباعة', 'نشر', 'كتب', 'مجلات', 'بروشور', 'بوستر', 'تصميم جرافيك', 'دعاية', 'إعلان', 'فلير', 'كتيب', 'دليل', 'نشرة', 'بطاقة', 'كرت دعوة', 'كرت شكر', 'فوترة', 'فاتورة', 'رأس letter', 'ظرف', 'ورق', 'كرتون', 'طباعة رقمية', 'طباعة أوفست', 'طباعة حريرية', 'طباعة بالاستنسل', 'طباعة ليزر', 'طباعة حبر', 'طابعة', 'مكتبة', 'ناشر', 'موزع', 'كتب إلكترونية', 'e-book', 'صحيفة', 'جريدة', 'مجلة علمية', 'مجلة ثقافية', 'مجلة أطفال', 'قصة', 'رواية', 'شعر', 'كتب دينية', 'كتب تعليمية', 'كتب طبخ', 'كتب أطفال', 'تلوين', 'رسوم', 'كاريكاتير',
                'printing', 'publishing', 'books', 'magazines', 'brochure', 'poster', 'graphic design', 'advertising', 'flyer', 'booklet', 'guide', 'newsletter', 'card', 'invitation', 'thank you card', 'invoice', 'letterhead', 'envelope', 'paper', 'cardboard', 'digital printing', 'offset printing', 'screen printing', 'laser printing', 'printer', 'library', 'publisher', 'distributor', 'e-book', 'newspaper', 'journal', 'novel', 'poetry', 'coloring', 'cartoon'
            ],
            'Transportation & Logistics' => [
                'نقل', 'شحن', 'توصيل', 'لوجستيات', 'خدمات توصيل', 'نقل بضائع', 'شحن دولي', 'شحن جوي', 'شحن بحري', 'نقل بري', 'شاحنات', 'شحنة', 'بضائع', 'مستودعات', 'تخزين', 'تخليص جمركي', 'براءة', 'تأمين', 'تغليف', 'صندوق', 'كرتون', 'بالتة', 'شكة', 'حاوية', 'كونتينر', 'ميناء', 'مطار', 'محطة', 'قطار', 'مترو', 'أجرة', 'تاكسي', 'أوبر', 'كريم', 'باص', 'ميكروباص', 'خدمة توصيل سريع', 'شحن سريع', 'توصيل في نفس اليوم', 'توصيلexpress', 'نقل عفش', 'نقل سيارات', 'شحن سيارات', 'مقاولات نقل', 'شركة نقل', 'وكالة شحن', 'مكتب شحن', 'موزع',
                'transport', 'logistics', 'shipping', 'delivery', 'freight', 'cargo', 'warehousing', 'storage', 'customs clearance', 'insurance', 'packaging', 'container', 'port', 'airport', 'station', 'train', 'metro', 'taxi', 'uber', 'careem', 'bus', 'fast delivery', 'same day delivery', 'express', 'furniture moving', 'car transport', 'moving company', 'shipping agency', 'distributor'
            ],
            'Education & Training' => [
                'تعليم', 'تدريب', 'دورات', 'مدارس', 'جامعات', 'معاهد', 'تعليم خاص', 'روضة', 'حضانة', 'مدرسة ابتدائية', 'إعدادية', 'ثانوية', 'معهد فني', 'معهد تجاري', 'جامعة خاصة', 'كلية', 'معهد لغات', 'مركز تدريب', 'دورة تدريبية', 'شهادة', 'دبلوم', 'بكالوريوس', 'ماجستير', 'دكتوراه', 'تعليم عن بعد', 'e-learning', 'منصة تعليمية', 'كورسات أونلاين', 'مدرس خصوصي', 'معلم', 'أستاذ', 'محاضر', 'مدرب', 'مادة دراسية', 'رياضيات', 'علوم', 'لغة عربية', 'لغة إنجليزية', 'فرنسية', 'ألمانية', 'إسبانية', 'برمجة', 'تصميم', 'تسويق', 'محاسبة', 'إدارة', 'قانون', 'طب', 'هندسة', 'فنون', 'موسيقى', 'رسم',
                'education', 'training', 'courses', 'school', 'university', 'institute', 'kindergarten', 'nursery', 'elementary', 'high school', 'college', 'language center', 'training center', 'certificate', 'diploma', 'bachelor', 'master', 'phd', 'distance learning', 'e-learning', 'online courses', 'private tutor', 'teacher', 'professor', 'lecturer', 'trainer', 'math', 'science', 'arabic', 'english', 'french', 'programming', 'design', 'marketing', 'accounting', 'management', 'law', 'medicine', 'engineering', 'arts', 'music', 'drawing'
            ],
            'Marketing & Advertising' => [
                'تسويق', 'إعلان', 'دعاية', 'ترويج', 'حملات إعلانية', 'وسائل التواصل', 'سوشيال ميديا', 'فيسبوك', 'إنستجرام', 'تويتر', 'يوتيوب', 'تيك توك', 'لينكد إن', 'واتساب', 'تليجرام', 'سوق مستهدف', 'عملاء', 'بيع مباشر', 'تسويق رقمي', 'تسويق بالمحتوى', 'SEO', 'SEM', 'جوجل', 'إعلانات جوجل', 'فيسبوك ads', 'إنستجرام ads', 'براند', 'هوية بصرية', 'لوجو', 'شعار', 'تصميم شعارات', 'فيديو', 'موشن جرافيك', 'تصوير فوتوغرافي', 'حملة ترويجية', 'حدث', 'معرض', 'مؤتمر', 'ندوة', 'ورشة عمل', 'إدارة سمعة', 'علاقات عامة', 'PR', 'صحافة', 'إعلام',
                'marketing', 'advertising', 'promotion', 'social media', 'facebook', 'instagram', 'twitter', 'youtube', 'tiktok', 'linkedin', 'whatsapp', 'telegram', 'target market', 'customers', 'direct sales', 'digital marketing', 'content marketing', 'SEO', 'SEM', 'google', 'google ads', 'facebook ads', 'instagram ads', 'brand', 'visual identity', 'logo', 'logo design', 'video', 'motion graphics', 'photography', 'campaign', 'event', 'exhibition', 'conference', 'workshop', 'reputation management', 'public relations', 'PR', 'journalism', 'media'
            ],
            'Accounting & Finance' => [
                'محاسبة', 'ضرائب', 'استشارات مالية', 'مراجعة حسابات', 'تدقيق', 'محاسب', 'مراجع', 'خبير ضريبي', 'استشاري مالي', 'مخطط مالي', 'ميزانية', 'قائمة مالية', 'ميزان مراجع', 'دفتر أستاذ', 'قيد يومية', 'مركز تكلفة', 'تحليل تكاليف', 'محاسبة إدارية', 'محاسبة مالية', 'محاسبة ضريبية', 'زكاة', 'ضريبة دخل', 'ضريبة قيمة مضافة', 'ضريبة جمارك', 'إقرار ضريبي', 'تسوية ضريبية', 'جرد', 'مخزون', 'أصول', 'خصوم', 'حقوق ملكية', 'أرباح', 'خسائر', 'تدفق نقدي', 'ميزانية مدفوعة', 'شيكات', 'تحويلات بنكية', 'ائتمان', 'تمويل', 'قروض', 'استثمار',
                'accounting', 'tax', 'financial consulting', 'bookkeeping', 'audit', 'accountant', 'auditor', 'tax expert', 'financial advisor', 'financial planner', 'budget', 'financial statement', 'balance sheet', 'ledger', 'journal entry', 'cost center', 'cost analysis', 'managerial accounting', 'financial accounting', 'tax accounting', 'zakat', 'income tax', 'VAT', 'customs tax', 'tax return', 'tax settlement', 'inventory', 'assets', 'liabilities', 'equity', 'profits', 'losses', 'cash flow', 'checks', 'bank transfers', 'credit', 'financing', 'loans', 'investment'
            ],
            'Legal Services' => [
                'قانون', 'محاماة', 'استشارات قانونية', 'قضايا', 'عقود', 'توثيق', 'محامٍ', 'مستشار قانوني', 'شركة محاماة', 'مكتب محاماة', 'قاضي', 'نيابة', 'مدعي عام', 'دفاع', 'ادعاء', 'دعوى قضائية', 'قضية', 'حكم', 'استئناف', 'نقض', 'محكمة', 'محكمة ابتدائية', 'محكمة جزئية', 'محكمة استئناف', 'محكمة نقض', 'محكمة دستورية', 'قضاء إداري', 'قضاء تجاري', 'قضاء أسري', 'قضاء عمالي', 'توثيق عقود', 'عقد زواج', 'عقد طلاق', 'عقد بيع', 'عقد إيجار', 'عقد شركة', 'وكالة', 'صك', 'ورقة رسمية', 'توكيل', 'إرث', 'وصية', 'ورثة', 'قسمة', 'حصر إرث', 'تصفية شركة', 'إفلاس',
                'legal', 'law', 'lawyer', 'attorney', 'legal consultant', 'law firm', 'legal office', 'judge', 'prosecutor', 'defense', 'prosecution', 'lawsuit', 'case', 'verdict', 'appeal', 'cassation', 'court', 'primary court', 'appellate court', 'supreme court', 'constitutional court', 'administrative judiciary', 'commercial judiciary', 'family judiciary', 'labor judiciary', 'contract documentation', 'marriage contract', 'divorce contract', 'sales contract', 'lease contract', 'company contract', 'agency', 'deed', 'official document', 'power of attorney', 'inheritance', 'will', 'heirs', 'division', 'inheritance determination', 'company liquidation', 'bankruptcy'
            ],
            'Tourism & Hospitality' => [
                'سياحة', 'سفر', 'فنادق', 'شقق مفروشة', 'وكالة سفر', 'رحلات', 'حجز فنادق', 'حجز طيران', 'تذاكر طيران', 'شركة طيران', 'مطار', 'وجهة سياحية', 'مزار سياحي', 'آثار', 'متحف', 'معالم أثرية', 'متنزه', 'حديقة', 'شاطئ', 'بحر', 'مسبح', 'منتجع', 'سبا', ' massages', 'معالجة', 'علاج طبيعي', 'غوص', 'رياضات مائية', 'صيد', 'رحلات برية', 'تخييم', 'سفاري', 'جبال', 'تسلق', 'مشاية', 'رحلات ثقافية', 'سياحة دينية', 'سياحة علاجية', 'سياحة بيئية', 'سياحة شتوية', 'سياحة صيفية', 'مهرجان', 'فعالية', 'مؤتمر', 'معرض', 'دليل سياحي', 'مرشد سياحي',
                'tourism', 'travel', 'hotel', 'furnished apartment', 'travel agency', 'trips', 'hotel booking', 'flight booking', 'air tickets', 'airline', 'airport', 'tourist destination', 'tourist attraction', 'antiquities', 'museum', 'archaeological sites', 'park', 'garden', 'beach', 'sea', 'pool', 'resort', 'spa', 'massages', 'treatment', 'physical therapy', 'diving', 'water sports', 'fishing', 'safari', 'mountains', 'climbing', 'hiking', 'cultural trips', 'religious tourism', 'medical tourism', 'eco tourism', 'winter tourism', 'summer tourism', 'festival', 'event', 'conference', 'exhibition', 'tourist guide', 'tour guide'
            ],
            'Sports & Fitness' => [
                'رياضة', 'نوادي رياضية', 'تدريب رياضي', 'معدات رياضية', 'ملابس رياضية', 'جيم', 'صالة ألعاب رياضية', 'نادي رياضي', 'مدرب شخصي', 'برنامج رياضي', 'لياقة بدنية', 'fitness', 'كارديو', 'تمارين هوائية', 'رفع أثقال', 'bodybuilding', 'كمال أجسام', 'يوجا', 'بيلاتس', 'crossfit', 'HIIT', 'تمارين القوة', 'تمارين المرونة', 'تمارين التوازن', 'سباحة', 'غوص', 'رياضات مائية', 'كرة قدم', 'كرة سلة', 'كرة طائرة', 'كرة يد', 'تنس', 'تنس طاولة', 'اسكواش', 'بادلتنون', 'جولف', 'فروسية', 'ركوب الخيل', 'دراجة هوائية', 'ركوب الدراجات', 'جري', 'عدو', 'ماراتون', 'سباق', 'فنون قتالية', 'karate', 'taekwondo', 'judo', 'kickboxing', 'boxing', 'ملاكمة', 'مصارعة', 'فنون الدفاع عن النفس', 'تزلج', 'تزلج على الجليد', 'تزلج على الماء', 'تزلج على الجليد', 'هوكي', 'رياضات جماعية', 'رياضات فردية',
                'sports', 'fitness', 'gym', 'health club', 'sports club', 'personal trainer', 'fitness program', 'cardio', 'aerobics', 'weightlifting', 'bodybuilding', 'yoga', 'pilates', 'crossfit', 'HIIT', 'strength training', 'flexibility exercises', 'balance exercises', 'swimming', 'diving', 'water sports', 'football', 'basketball', 'volleyball', 'handball', 'tennis', 'table tennis', 'squash', 'badminton', 'golf', 'horse riding', 'cycling', 'running', 'marathon', 'race', 'martial arts', 'karate', 'taekwondo', 'judo', 'kickboxing', 'boxing', 'wrestling', 'self defense', 'skating', 'ice skating', 'water skiing', 'skiing', 'hockey', 'team sports', 'individual sports'
            ],
            'Beauty & Cosmetics' => [
                'تجميل', 'عناية بالبشرة', 'صالونات', 'تجميل نسائي', 'صبغة شعر', 'تصفيف شعر', 'قص شعر', 'مكياج', 'كريمات', 'مرطبات', 'واقي شمس', 'مستحضرات تجميل', 'عطور', 'بارفان', 'دهانات', 'زيوت', 'شامبو', 'بلسم', 'صبغة', 'فروة رأس', 'عناية بالشعر', 'تقشير', 'ليزر', 'تخسيس', 'نحافة', 'ريجيم', 'حمية', 'تغذية', 'فيتامينات', 'مكملات غذائية', 'بروتين', 'منتجات طبيعية', 'أعشاب', 'ماكيراتش', 'ميك أب ارتيست', 'مصفف شعر', 'خبير تجميل', 'أخصائي تجميل', 'مركز تجميل', 'عيادة تجميل', 'جراحة تجميلية', 'حقن', 'فيلر', 'بوتوكس', 'ميزوثيرابي', 'ميزوديرما', 'كولاجين', 'إيلاستين', 'سيروم', 'قناع', 'ماسك', 'غسول', 'تونر', 'scrub', 'peeling', 'facial', 'massage', 'spa', 'سونا', 'جاكوزي', 'حمام مغربي', 'حمام تركي', 'ساونا',
                'beauty', 'cosmetics', 'skincare', 'salon', 'hair styling', 'hair coloring', 'haircut', 'makeup', 'creams', 'lotions', 'sunscreen', 'beauty products', 'perfume', 'parfum', 'oils', 'shampoo', 'conditioner', 'hair dye', 'scalp care', 'hair care', 'exfoliation', 'laser', 'weight loss', 'slimming', 'diet', 'nutrition', 'vitamins', 'supplements', 'protein', 'natural products', 'herbs', 'makeup artist', 'hair stylist', 'beauty expert', 'beauty specialist', 'beauty center', 'beauty clinic', 'cosmetic surgery', 'injections', 'filler', 'botox', 'mesotherapy', 'mesoderma', 'collagen', 'elastin', 'serum', 'mask', 'wash', 'toner', 'scrub', 'peeling', 'facial', 'massage', 'spa', 'sauna', 'jacuzzi', 'moroccan bath', 'turkish bath'
            ],
            'Cleaning & Maintenance' => [
                'نظافة', 'تنظيف', 'صيانة منازل', 'خدمات نظافة', 'معدات نظافة', 'مكنسة', 'مكنسة كهربائية', 'ممسحة', 'ممسحة بخار', 'ممسحة رطبة', 'نظافة عميقة', 'تنظيف سجاد', 'غسيل سجاد', 'تنظيف كنب', 'تنظيف مجالس', 'تنظيف زجاج', 'تنظيف نوافذ', 'تنظيف واجهات', 'غسيل واجهات', 'نظافة شقق', 'نظافة فلل', 'نظافة مكاتب', 'نظافة محلات', 'نظافة مصانع', 'نظافة مدارس', 'نظافة مستشفيات', 'نظافة فنادق', 'نظافة مطاعم', 'نظافة مجمعات', 'نظافة مواقف', 'نظافة حدائق', 'تنظيف بعد البناء', 'نظافة بعد التشطيب', 'نظافة بعد الحريق', 'نظافة بعد السرقة', 'تعقيم', 'تطهير', 'مبيد حشري', 'مبيد حشرات', 'مكافحة حشرات', 'رش مبيدات', 'مكافحة قوارض', 'مكافحة الصراصير', 'مكافحة البق', 'مكافحة النمل الأبيض', 'مكافحة الفئران', 'مكافحة الخفافيش', 'مكافحة الطيور', 'صيانة دورية', 'صيانة وقائية', 'صيانة طوارئ', 'سباك', 'كهربائي', 'نجار', 'دهان', 'مبلط', 'نقاش', 'مقاول',
                'cleaning', 'maintenance', 'house cleaning', 'cleaning services', 'cleaning equipment', 'vacuum cleaner', 'steam mop', 'wet mop', 'deep cleaning', 'carpet cleaning', 'carpet washing', 'sofa cleaning', 'upholstery cleaning', 'glass cleaning', 'window cleaning', 'facade cleaning', 'building washing', 'apartment cleaning', 'villa cleaning', 'office cleaning', 'shop cleaning', 'factory cleaning', 'school cleaning', 'hospital cleaning', 'hotel cleaning', 'restaurant cleaning', 'mall cleaning', 'parking cleaning', 'garden cleaning', 'post construction cleaning', 'post renovation cleaning', 'fire damage cleaning', 'theft cleaning', 'disinfection', 'sanitization', 'pesticide', 'insecticide', 'pest control', 'spraying pesticides', 'rodent control', 'cockroach control', 'bed bug control', 'termite control', 'rat control', 'bat control', 'bird control', 'regular maintenance', 'preventive maintenance', 'emergency maintenance', 'plumber', 'electrician', 'carpenter', 'painter', 'tiler', 'plasterer', 'contractor'
            ],
            'Security Services' => [
                'أمن', 'حراسة', 'أنظمة أمن', 'كاميرات مراقبة', 'شركات أمن', 'حراس أمن', 'ضباط أمن', 'فرق أمن', 'دورية أمنية', 'سيارات أمن', 'حراس ليل', 'حراس نهار', 'حراس شخصيين', 'حراس VIP', 'حراس خاص', 'أمن منازل', 'أمن شركات', 'أمن مصانع', 'أمن بنوك', 'أمن فنادق', 'أمن مستشفيات', 'أمن مدارس', 'أمن جامعات', 'أمن مطارات', 'أمن موانئ', 'أمن محطات', 'أمن مواقف', 'أمن مجمعات', 'أمن فعاليات', 'أمن مؤتمرات', 'أمن معارض', 'أمن حفلات', 'أمن شخصيات', 'حماية شخصيات', 'حراسة شخصيات', 'أمن سيارات', 'أمن مواقع', 'أمن منشآت', 'أنظمة إنذار', 'إنذار ضد السرقة', 'إنذار ضد الحريق', 'إنذار دخول', 'إنذار خروج', 'كاشف دخان', 'كاشف حريق', 'كاشف حرارة', 'كاشف غاز', 'كاشف تسرب مياه', 'قفل أمني', 'باب أمني', 'نافذة أمنية', 'سياج أمني', 'أسلاك شائكة', 'حاجز أمني', 'بوابة أمنية', 'دخول وخروج', 'بطاقة دخول', 'بصمة', 'وجه', 'عين', 'كلمة مرور', 'رمز',
                'security', 'protection', 'surveillance', 'cameras', 'security companies', 'security guards', 'security officers', 'security teams', 'security patrol', 'security cars', 'night guards', 'day guards', 'personal guards', 'VIP guards', 'private guards', 'home security', 'company security', 'factory security', 'bank security', 'hotel security', 'hospital security', 'school security', 'university security', 'airport security', 'port security', 'station security', 'parking security', 'mall security', 'event security', 'conference security', 'exhibition security', 'party security', 'celebrity protection', 'personality protection', 'car security', 'site security', 'facility security', 'alarm systems', 'burglar alarm', 'fire alarm', 'entry alarm', 'exit alarm', 'smoke detector', 'fire detector', 'heat detector', 'gas detector', 'water leak detector', 'security lock', 'security door', 'security window', 'security fence', 'barbed wire', 'security barrier', 'security gate', 'access control', 'access card', 'fingerprint', 'face', 'eye', 'password', 'code'
            ],
            'Telecommunications' => [
                'اتصالات', 'هواتف', 'إنترنت', 'شبكات', 'ألياف بصرية', 'خدمات اتصالات', 'هاتف أرضي', 'هاتف محمول', 'スマホ', 'جهاز لوحي', 'تابلت', 'آيفون', 'سامسونج', 'هواوي', 'شاومي', 'أوبو', 'فيفو', 'نوكيا', 'سوني', 'إل جي', 'موتورولا', ' HTC', 'بلاك بيري', 'جوجل بكسل', 'هواوي P', 'سامسونج جالاكسي', 'آيفون برو', 'آيفون ماكس', 'آيباد', 'آيباد برو', 'آيباد ميني', 'آيباد إير', 'سامسونج تاب', 'هواوي ميد باد', 'شاومي باد', 'سوني إكسبريا تاب', 'لينوفو تاب', 'أيسر تاب', 'توشيبا تاب', 'فوجيتسو تاب', 'باناسونيك تاب', 'إل جي تاب', 'سيم كارت', 'شريحة اتصال', 'شريحة إنترنت', 'رصيد', 'باقات', 'دقائق', 'رسائل', 'إنترنت 4G', 'إنترنت 5G', 'واي فاي', 'بلوتوث', 'NFC', 'GPS', 'GLONASS', 'غاليليو', 'بيدو', 'ساتناڤ', 'خرائط', 'ملاحة', 'تطبيق خرائط', 'جوجل مابس', 'خرائط جوجل', 'ويزي', 'سيرجا', 'نافيتيل', 'توم توم', 'جارمين', 'هنا',
                'telecommunications', 'internet', 'networks', 'fiber optics', 'communication services', 'landline', 'mobile phone', 'smartphone', 'tablet', 'iphone', 'samsung', 'huawei', 'xiaomi', 'oppo', 'vivo', 'nokia', 'sony', 'lg', 'motorola', 'htc', 'blackberry', 'google pixel', 'huawei p', 'samsung galaxy', 'iphone pro', 'iphone max', 'ipad', 'ipad pro', 'ipad mini', 'ipad air', 'samsung tab', 'huawei medipad', 'xiaomi pad', 'sony xperia tab', 'lenovo tab', 'acer tab', 'toshiba tab', 'fujitsu tab', 'panasonic tab', 'lg tab', 'sim card', 'internet chip', 'balance', 'packages', 'minutes', 'messages', '4G internet', '5G internet', 'wifi', 'bluetooth', 'NFC', 'GPS', 'GLONASS', 'Galileo', 'Beidou', 'satnav', 'maps', 'navigation', 'map app', 'google maps', 'waze', 'sygic', 'navitel', 'tomtom', 'garmin', 'here'
            ]
        ];
        
        foreach ($categoryPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($text, mb_strtolower($pattern))) {
                    return $category;
                }
            }
        }
        
        return null;
    }
    
    /**
     * مطابقة مرنة للنصوص
     */
    private function fuzzyMatch(string $text, string $pattern): bool
    {
        // إزالة علامات التشكيل والهمزات
        $text = $this->normalizeArabic($text);
        $pattern = $this->normalizeArabic($pattern);
        
        return str_contains($text, $pattern) || 
               levenshtein($text, $pattern) <= 2;
    }
    
    /**
     * تطبيع النص العربي
     */
    private function normalizeArabic(string $text): string
    {
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        $text = str_replace(['ة'], 'ه', $text);
        $text = str_replace(['ى'], 'ي', $text);
        return $text;
    }
    
    /**
     * استخراج التقييم المحسّن
     */
    private function extractRatingAdvanced(string $text): array
    {
        $patterns = $this->knowledgeBase['rating_patterns'];
        $result = [];
        
        // 1. بحث عن "أقل من X" أو "أدنى من X" - NEW
        foreach ($patterns['less_than'] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $number = (int)$matches[1];
                $result['maxRating'] = $number; // أقل من 2 = 2 (show ratings <= 2)
                Log::info('Max rating from less_than pattern', ['found' => $number, 'maxRating' => $result['maxRating']]);
                return $result;
            }
        }
        
        // 2. بحث عن "أكبر من X" أو "أعلى من X"
        foreach ($patterns['greater_than'] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $number = (int)$matches[1];
                $result['minRating'] = $number + 1; // أكبر من 2 = 3
                Log::info('Min rating from greater_than pattern', ['found' => $number, 'minRating' => $result['minRating']]);
                return $result;
            }
        }
        
        // 3. بحث عن أنماط مباشرة - Handle 0 rating
        foreach ($patterns['direct'] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $rating = min(5, max(0, (int)$matches[1])); // Allow 0
                if ($rating == 0) {
                    Log::info('Rating 0 found', ['rating' => 0]);
                    $result['minRating'] = 0;
                    return $result;
                }
                Log::info('Rating from direct pattern', ['rating' => $rating]);
                $result['minRating'] = $rating;
                return $result;
            }
        }
        
        // 4. بحث عن كلمات دالة - UPDATED ORDER (check negative first)
        foreach ($patterns['keywords'] as $rating => $words) {
            foreach ($words as $word) {
                if (str_contains($text, mb_strtolower($word))) {
                    Log::info('Rating from keyword', ['word' => $word, 'rating' => $rating]);
                    $result['minRating'] = $rating;
                    return $result;
                }
            }
        }
        
        // 5. إذا وجدت "تقييم" بدون رقم محدد
        if (preg_match('/(?:تقييم|تقيم|ريت|rating)/ui', $text)) {
            Log::info('Rating generic mention found, defaulting to 3');
            $result['minRating'] = 3;
            return $result;
        }
        
        return $result;
    }
    
    /**
     * التحقق من "مفتوح الآن"
     */
    private function isOpenNowQuery(string $text): bool
    {
        foreach ($this->knowledgeBase['open_now_words'] as $word) {
            if (str_contains($text, mb_strtolower($word))) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * استخراج الموقع
     */
    private function extractLocation(string $query): ?string
    {
        // أنماط الموقع
        $patterns = [
            '/(?:في|ب|بـ|من|عند|near|at|in)\s+([\p{Arabic}a-zA-Z\s\-]{3,20})/ui',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query, $matches)) {
                $location = trim($matches[1]);
                if (strlen($location) >= 3) {
                    return $location;
                }
            }
        }
        
        return null;
    }
    
    /**
     * استخراج الكلمات ذات المعنى - LESS RESTRICTIVE
     */
    private function extractAllMeaningfulWords(string $text): array
    {
        $words = preg_split('/[\s\.,!?;:()\[\]{}]+/u', $text);
        $meaningful = [];
        
        foreach ($words as $word) {
            $word = trim($word);
            $wordLower = mb_strtolower($word);
            
            // تجاهل الكلمات الفارغة فقط
            if (empty($word)) {
                continue;
            }
            
            // تجاهل الكلمات القصيرة جداً (فقط 1 حرف)
            if (mb_strlen($word) < 2) {
                continue;
            }
            
            // تجاهل أقل عدد من stop words
            $minimalStopWords = ['في', 'من', 'إلى', 'الى', 'ال', 'يا', 'the', 'a', 'an', 'and', 'or'];
            if (in_array($wordLower, $minimalStopWords)) {
                continue;
            }
            
            $meaningful[] = $word;
        }
        
        // Extract individual words (2+ characters)
        $textWords = preg_split('/[\s،,.;:!?()\'"\\[\\]{}]+/u', $text);
        foreach ($textWords as $word) {
            $word = trim($word);
            if (mb_strlen($word) >= 2 && !in_array($word, $words)) {
                // Skip common stop words
                $stopWords = ['من', 'في', 'على', 'إلى', 'عن', 'مع', 'بعد', 'قبل', 'حتى', 'خلال', 'بدون', 'هذا', 'هذه', 'ذلك', 'التي', 'الذي', 'الذين', 'كان', 'كانت', 'يكون', 'تكون', 'ليس', 'ليست', 'كل', 'بعض', 'أي', 'كل', 'هنا', 'هناك', 'حيث', 'عند', 'عندما', 'إذا', 'حين', 'بين', 'قد', 'سوف', 'س', 'لن', 'لما', 'لماذا', 'كيف', 'متى', 'أين', 'ما', 'ماذا', 'منذ', 'مهما', 'حتى لو', 'بما أن', 'بسبب', 'نتيجة ل', 'على الرغم من', 'طالما', 'كلما', 'كلما', 'بمجرد أن', 'فور', 'حالما', 'أمام', 'خلف', 'يمين', 'يسار', 'فوق', 'تحت', 'داخل', 'خارج', 'أول', 'أخير', 'ثم', 'بعد ذلك', 'قبل ذلك', 'أيضاً', 'كذلك', 'فضلاً', 'من فضلك', 'شكراً', 'عفواً', 'أهلاً', 'أهلاً بك', 'مرحباً', 'معذرة', 'بالتأكيد', 'طبعاً', 'ربما', 'قد يكون', 'يمكن', 'يمكنك', 'نستطيع', 'يجب', 'ينبغي', 'لازم', 'ضروري', 'مهم', 'أساسي', 'رئيسي', 'عام', 'خاص', 'خاص جداً', 'فقط', 'فقط', 'مجرد', 'مثل', 'شبيه ب', 'مشابه ل', 'مختلف عن', 'خلاف', 'عكس', 'بدلاً من', 'مقابل', 'بجانب', 'بصرف النظر عن', 'بغض النظر عن', 'بسبب', 'نتيجة', 'لذلك', 'هكذا', 'هذا', 'هذه', 'ذلك', 'تلك', 'هؤلاء', 'أولئك', 'هنا', 'هناك', 'حيث', 'أين', 'متى', 'كيف', 'لماذا', 'ماذا', 'كم', 'كم عدد', 'كم سعر', 'ما هو', 'ما هي', 'من هو', 'من هي', 'لمن', 'لماذا', 'كيف', 'أين', 'متى', 'أي', 'أيهما', 'أيهم', 'أيهن', 'كل', 'جميع', 'كلها', 'جميعها', 'بعض', 'بعضها', 'عدة', 'كثير', 'كثير جداً', 'قليل', 'قليل جداً', 'أكثر', 'أقل', 'أكبر', 'أصغر', 'أطول', 'أقصر', 'أعرض', 'أضيق', 'أعلى', 'أدنى', 'أفضل', 'أسوأ', 'أجمل', 'أقبح', 'أغلى', 'أرخص', 'أسرع', 'أبطأ', 'أقوى', 'أضعف', 'أثقل', 'أخف', 'أكثر', 'أقل', 'أجدد', 'أقدم', 'أحسن', 'أسوأ', 'أكبر', 'أصغر', 'أطول', 'أقصر', 'أعرض', 'أضيق', 'أعلى', 'أدنى', 'أفضل', 'أسوأ', 'أجمل', 'أقبح', 'أغلى', 'أرخص', 'أسرع', 'أبطأ', 'أقوى', 'أضعف', 'أثقل', 'أخف', 'the', 'of', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'up', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'among', 'under', 'over', 'above', 'below', 'up', 'down', 'out', 'off', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'any', 'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 'can', 'will', 'just', 'don', 'should', 'now'];
                
                if (!in_array(mb_strtolower($word), $stopWords)) {
                    $words[] = $word;
                }
            }
        }
        
        return array_unique($words);
    }
    
    /**
     * استخراج الكلمات ذات المعنى
     */
    private function extractMeaningfulWords(string $text): array
    {
        $words = preg_split('/[\s،,.;:!?()\'"\\[\\]{}]+/u', $text);
        $meaningful = [];
        
        foreach ($words as $word) {
            $word = trim($word);
            $wordLower = mb_strtolower($word);
            
            // تجاهل الكلمات الفارغة والقصيرة جداً
            if (empty($word) || mb_strlen($word) < 2) {
                continue;
            }
            
            // تجاهل stop words
            if (in_array($wordLower, $this->knowledgeBase['stop_words'])) {
                continue;
            }
            
            $meaningful[] = $word;
        }
        
        return $meaningful;
    }
    
    /**
     * استدعاء AI خارجي
     */
    private function callExternalAI(string $query): ?array
    {
        $apiKey = env('GROQ_API_KEY');
        
        if (!$apiKey) {
            Log::warning('GROQ_API_KEY not set');
            return null;
        }
        
        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.1-70b-versatile',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => $query
                    ]
                ],
                'temperature' => 0.1,
                'max_tokens' => 500,
                'response_format' => ['type' => 'json_object']
            ]);
            
            if ($response->successful()) {
                $result = $response->json();
                $content = $result['choices'][0]['message']['content'] ?? null;
                
                if ($content) {
                    $parsed = json_decode($content, true);
                    if ($parsed && is_array($parsed)) {
                        return $this->normalizeAIResponse($parsed);
                    }
                }
            } else {
                Log::error('API Error', ['status' => $response->status()]);
            }
        } catch (\Exception $e) {
            Log::error('AI API Exception', ['error' => $e->getMessage()]);
        }
        
        return null;
    }
    
    /**
     * System Prompt
     */
    private function getSystemPrompt(): string
    {
        $categories = implode(', ', array_keys($this->knowledgeBase['categories']));
        
        return <<<PROMPT
أنت محرك بحث ذكي. افهم أي نص وحوّله لمعلومات بحث.

**الفئات المتاحة:**
$categories

**استخرج:**
- keywords: كلمات البحث (عربي + إنجليزي + مرادفات)
- category: الفئة من القائمة (أو null)
- minRating: رقم من 1-5 (أو null)
- isOpenNow: true/false
- location: الموقع (أو null)

**أمثلة:**

"شخص بتقيم اعلي من 2"
→ {"keywords": "مورد شخص خدمة جيد موثوق محترم good reliable", "category": null, "minRating": 3, "isOpenNow": false, "location": null}

"شخص بمجال الزراعه"
→ {"keywords": "زراعة مزارع نباتات محاصيل حقل agriculture farm crops", "category": "Agriculture", "minRating": null, "isOpenNow": false, "location": null}

"محتاج كمبيوتر كويس"
→ {"keywords": "كمبيوتر حاسوب لابتوب computer laptop جيد محترم good", "category": "Computer Hardware & Software", "minRating": 4, "isOpenNow": false, "location": null}

**قواعد:**
- "أعلى من X" = X+1
- "جيد/كويس/عالي" = 4
- "ممتاز/أفضل" = 5
- أضف مرادفات كثيرة
- استخدم الفئات بالإنجليزي بالضبط

**إخراج:** JSON فقط
PROMPT;
    }
    
    /**
     * تطبيع رد الـ AI
     */
    private function normalizeAIResponse(array $data): array
    {
        return [
            'query' => $data['keywords'] ?? $data['query'] ?? '',
            'category' => $this->validateCategory($data['category'] ?? null),
            'minRating' => $this->validateRating($data['minRating'] ?? null),
            'isOpenNow' => !empty($data['isOpenNow']),
            'location' => $data['location'] ?? null
        ];
    }
    
    /**
     * التحقق من صحة الفئة
     */
    private function validateCategory($category): ?string
    {
        if (!$category) return null;
        
        if (isset($this->knowledgeBase['categories'][$category])) {
            return $category;
        }
        
        return null;
    }
    
    /**
     * التحقق من صحة التقييم
     */
    private function validateRating($rating): ?int
    {
        if ($rating === null || $rating === '') {
            return null;
        }
        
        $rating = (int)$rating;
        return min(5, max(1, $rating));
    }
    
    /**
     * دمج ذكي
     */
    private function intelligentMerge(array $ai, array $local, string $original): array
    {
        // دمج الكلمات المفتاحية
        $allKeywords = array_unique(array_merge(
            explode(' ', $ai['query'] ?? ''),
            explode(' ', $local['query'] ?? ''),
            $this->extractMeaningfulWords($original)
        ));
        
        $allKeywords = array_filter($allKeywords, function($k) {
            return !empty($k) && mb_strlen($k) >= 2 && 
                   !in_array(mb_strtolower($k), $this->knowledgeBase['stop_words']);
        });
        
        return [
            'query' => implode(' ', array_slice($allKeywords, 0, 30)),
            'category' => $ai['category'] ?? $local['category'],
            'minRating' => $ai['minRating'] ?? $local['minRating'],
            'isOpenNow' => $ai['isOpenNow'] || $local['isOpenNow'],
            'location' => $ai['location'] ?? $local['location']
        ];
    }
    
    /**
     * بناء استعلام احتياطي
     */
    private function buildFallbackQuery(string $original, array $result): string
    {
        $keywords = $this->extractMeaningfulWords($original);
        
        // إضافة كلمات من الفئة
        if ($result['category']) {
            $keywords = array_merge($keywords, $this->knowledgeBase['categories'][$result['category']]);
        }
        
        // إضافة كلمات عامة
        $keywords = array_merge($keywords, ['مورد', 'خدمة', 'supplier', 'service']);
        
        $keywords = array_unique($keywords);
        return implode(' ', array_slice($keywords, 0, 20));
    }
    
    /**
     * الرد الافتراضي
     */
    private function defaultResponse(): array
    {
        return [
            'query' => 'موردين خدمات suppliers services',
            'category' => null,
            'minRating' => null,
            'isOpenNow' => false,
            'location' => null
        ];
    }
}