# Analytics Endpoints - Postman Collection

## Base URLs
- **Base URL**: `http://localhost:8000/api/supplier/analytics`
- **Full Base**: `http://localhost:8000/api/supplier/analytics`

## Headers (Required for all requests)
```json
{
  "Content-Type": "application/json",
  "Accept": "application/json",
  "Authorization": "Bearer 123|kT7dJgOpOP7GWRHwHsFvPj3aM6YFIN8JTUi5MIrb5212e7b0"
}
```

---

## 1. Performance Metrics
**URL**: `GET http://localhost:8000/api/supplier/analytics/performance`

**Method**: GET
**Headers**: (See above)
**Body**: None

**Expected Response**:
```json
{
  "metrics": [
    {
      "metric": "Profile Completion",
      "value": 100,
      "target": 100,
      "color": "bg-green-500",
      "unit": "%"
    },
    {
      "metric": "Response Rate",
      "value": 100,
      "target": 90,
      "color": "bg-yellow-500",
      "unit": "%"
    },
    {
      "metric": "Customer Satisfaction",
      "value": 3,
      "target": 4.5,
      "color": "bg-blue-500",
      "unit": "stars",
      "isRating": true
    },
    {
      "metric": "Search Visibility",
      "value": 70,
      "target": 80,
      "color": "bg-purple-500",
      "unit": "%"
    }
  ],
  "overallScore": 76.1
}
```

---

## 2. Charts Data - Views
**URL**: `GET http://localhost:8000/api/supplier/analytics/charts?range=30&type=views`

**Method**: GET
**Headers**: (See above)
**Query Params**:
- `range`: 30 (days)
- `type`: views

**Expected Response**:
```json
{
  "type": "views",
  "range": 30,
  "data": [12, 15, 8, 25, 30, 18, 22, 35, 28, 40, 33, 45, 38, 50, 42, 55, 48, 60, 52, 58, 45, 62, 55, 68, 60, 72, 65, 78, 70, 75],
  "labels": ["Oct 28", "Oct 29", "Oct 30", "Oct 31", "Nov 1", "Nov 2", "Nov 3", "Nov 4", "Nov 5", "Nov 6", "Nov 7", "Nov 8", "Nov 9", "Nov 10", "Nov 11", "Nov 12", "Nov 13", "Nov 14", "Nov 15", "Nov 16", "Nov 17", "Nov 18", "Nov 19", "Nov 20", "Nov 21", "Nov 22", "Nov 23", "Nov 24", "Nov 25", "Nov 26"]
}
```

---

## 3. Charts Data - Contacts
**URL**: `GET http://localhost:8000/api/supplier/analytics/charts?range=7&type=contacts`

**Method**: GET
**Headers**: (See above)
**Query Params**:
- `range`: 7 (days)
- `type`: contacts

**Expected Response**:
```json
{
  "type": "contacts",
  "range": 7,
  "data": [0, 0, 0, 0, 0, 0, 0],
  "labels": ["Nov 20", "Nov 21", "Nov 22", "Nov 23", "Nov 24", "Nov 25", "Nov 26"]
}
```

---

## 4. Charts Data - Inquiries
**URL**: `GET http://localhost:8000/api/supplier/analytics/charts?range=7&type=inquiries`

**Method**: GET
**Headers**: (See above)
**Query Params**:
- `range`: 7 (days)
- `type`: inquiries

**Expected Response**:
```json
{
  "type": "inquiries",
  "range": 7,
  "data": [0, 0, 0, 0, 0, 0, 0],
  "labels": ["Nov 20", "Nov 21", "Nov 22", "Nov 23", "Nov 24", "Nov 25", "Nov 26"]
}
```

---

## 5. Keywords Analytics
**URL**: `GET http://localhost:8000/api/supplier/analytics/keywords`

**Method**: GET
**Headers**: (See above)
**Body**: None

**Expected Response**:
```json
{
  "keywords": [],
  "totalSearches": 0,
  "averageChange": 0,
  "period": "Last 30 days"
}
```

**With Data Example**:
```json
{
  "keywords": [
    {
      "keyword": "LED TV",
      "searches": 156,
      "change": 12,
      "contacts": 8,
      "last_searched": "2025-11-26"
    },
    {
      "keyword": "Samsung electronics",
      "searches": 134,
      "change": 8,
      "contacts": 5,
      "last_searched": "2025-11-25"
    }
  ],
  "totalSearches": 290,
  "averageChange": 10,
  "period": "Last 30 days"
}
```

---

## 6. Customer Insights
**URL**: `GET http://localhost:8000/api/supplier/analytics/insights`

**Method**: GET
**Headers**: (See above)
**Body**: None

**Expected Response**:
```json
{
  "demographics": [],
  "topLocations": [],
  "totalVisitors": 0,
  "totalCustomers": 0,
  "period": "Last 30 days"
}
```

**With Data Example**:
```json
{
  "demographics": [
    {
      "type": "Large Organizations",
      "percentage": 45,
      "count": 127
    },
    {
      "type": "Small Businesses",
      "percentage": 35,
      "count": 98
    },
    {
      "type": "Individuals",
      "percentage": 20,
      "count": 56
    }
  ],
  "topLocations": [
    {
      "city": "Riyadh",
      "visitors": 234,
      "percentage": 42
    },
    {
      "city": "Jeddah",
      "visitors": 156,
      "percentage": 28
    },
    {
      "city": "Dammam",
      "visitors": 89,
      "percentage": 16
    }
  ],
  "totalVisitors": 479,
  "totalCustomers": 281,
  "period": "Last 30 days"
}
```

---

## 7. Recommendations
**URL**: `GET http://localhost:8000/api/supplier/analytics/recommendations`

**Method**: GET
**Headers**: (See above)
**Body**: None

**Expected Response**:
```json
{
  "recommendations": [
    "Make your profile more attractive to get customer inquiries",
    "Improve your service quality to get better customer ratings",
    "Optimize your profile with better keywords and descriptions",
    "Upload more photos and detailed descriptions to attract more visitors",
    "Promote your business profile to increase visibility and inquiries"
  ],
  "priority": "low",
  "generated_at": "2025-11-27T06:46:19.406237Z",
  "based_on": {
    "profile_completion": 100,
    "response_rate": 100,
    "customer_satisfaction": 3,
    "search_visibility": 70,
    "total_inquiries": 0,
    "total_ratings": 1,
    "profile_views": 4
  }
}
```

## 10. Export - All Analytics
**URL**: `GET http://localhost:8000/api/supplier/analytics/export?format=csv`

**Method**: GET
**Headers**: (See above)
**Query Params**:
- `format`: csv

**Expected Response**:
```json
{
  "profile_completion": 100,
  "response_rate": 100,
  "customer_satisfaction": 3,
  "search_visibility": 70,
  "total_inquiries": 0,
  "total_ratings": 1,
  "profile_views": 4,
  "export_date": "2025-11-27 06:46:19",
  "supplier_info": {
    "name": "انا الجديد",
    "email": "kemo@gmail.com",
    "phone": "+966000000000",
    "category": "Furniture"
  }
}
```

**For CSV format**: Returns a CSV file directly with `Content-Type: text/csv` and `Content-Disposition: attachment; filename="analytics_export.csv"`

---

## Postman Setup Instructions

1. **Create New Collection**: "Analytics API"
2. **Add Headers** at collection level:
   ```json
   {
     "Content-Type": "application/json",
     "Accept": "application/json",
     "Authorization": "Bearer 123|kT7dJgOpOP7GWRHwHsFvPj3aM6YFIN8JTUi5MIrb5212e7b0"
   }
   ```
3. **Add each endpoint** as a separate request
4. **Use the URLs** provided above
5. **Test with the expected responses** to verify

## Quick Test Script
```javascript
// Test all endpoints in order
const endpoints = [
  '/performance',
  '/charts?range=30&type=views',
  '/charts?range=7&type=contacts',
  '/charts?range=7&type=inquiries',
  '/keywords',
  '/insights',
  '/recommendations',
  '/export?type=inquiries&format=csv'
];

endpoints.forEach(endpoint => {
  pm.request(`{{baseUrl}}${endpoint}`, (err, res) => {
    if (err) {
      console.error(`Error testing ${endpoint}:`, err);
    } else {
      console.log(`✅ ${endpoint}: ${res.status}`);
    }
  });
});
```
