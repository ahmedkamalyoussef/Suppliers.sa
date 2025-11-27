# Updated Analytics Endpoints - Postman Collection

## 🚀 **New Features Added:**

### **1. 📥 Direct Export Download**
**URL**: `GET http://localhost:8000/api/supplier/analytics/export?type=all&format=csv`

**Response**: Direct CSV file download (no intermediate step)
- Single unified export for all data
- Instant file download
- No token required for download

### **2. 👁️ Profile Views Tracking**
**URL**: `POST http://localhost:8000/api/supplier/analytics/track-view`

**Body**:
```json
{
  "supplier_id": 49,
  "location": "Riyadh",
  "customer_type": "Large Organizations",
  "duration": 120,
  "session_id": "abc123"
}
```

**Use Case**: Call this when someone views a supplier profile in frontend

### **3. 🔍 Search Tracking**
**URL**: `POST http://localhost:8000/api/supplier/analytics/track-search`

**Body**:
```json
{
  "keyword": "LED TV",
  "search_type": "supplier",
  "supplier_id": 49,
  "location": "Riyadh"
}
```

**Use Case**: Call this when someone searches on the platform

---

## 📋 **Complete Endpoints List:**

### **📊 Analytics Data**
- `GET /performance` - Real performance metrics
- `GET /charts?range=30&type=views` - Chart data (views/contacts/inquiries)
- `GET /keywords` - Search keywords analytics
- `GET /insights` - Customer demographics & locations
- `GET /recommendations` - Personalized recommendations

### **📥 Export**
- `GET /export?type=all&format=csv` - **Direct CSV download**
- `GET /export?type=all&format=json` - JSON data

### **🔍 Tracking (New)**
- `POST /track-view` - Track profile views
- `POST /track-search` - Track search keywords

---

## 🎯 **Frontend Integration:**

### **When someone views a supplier profile:**
```javascript
// Call this API to track the view
fetch('/api/supplier/analytics/track-view', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    supplier_id: 49,
    location: 'Riyadh',
    customer_type: 'Large Organizations',
    duration: 120,
    session_id: session_id
  })
})
```

### **When someone searches:**
```javascript
// Call this API to track the search
fetch('/api/supplier/analytics/track-search', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    keyword: 'LED TV',
    search_type: 'supplier',
    supplier_id: 49, // if search led to this supplier
    location: 'Riyadh'
  })
})
```

### **Export button:**
```javascript
// Direct download - no intermediate step
window.location.href = '/api/supplier/analytics/export?type=all&format=csv';
```

---

## 🔧 **Headers (for GET requests only):**
```json
{
  "Content-Type": "application/json",
  "Accept": "application/json",
  "Authorization": "Bearer 123|kT7dJgOpOP7GWRHwHsFvPj3aM6YFIN8JTUi5MIrb5212e7b0"
}
```

**Note**: POST tracking endpoints don't need authorization (public access)

---

## 🎉 **Test the New Features:**

### **1. Test Direct Export:**
```bash
curl -X GET "http://localhost:8000/api/supplier/analytics/export?type=all&format=csv" \
     -H "Authorization: Bearer 123|kT7dJgOpOP7GWRHwHsFvPj3aM6YFIN8JTUi5MIrb5212e7b0" \
     --output analytics_export.csv
```

### **2. Test View Tracking:**
```bash
curl -X POST "http://localhost:8000/api/supplier/analytics/track-view" \
     -H "Content-Type: application/json" \
     -d '{"supplier_id": 49, "location": "Riyadh", "customer_type": "Large Organizations"}'
```

### **3. Test Search Tracking:**
```bash
curl -X POST "http://localhost:8000/api/supplier/analytics/track-search" \
     -H "Content-Type: application/json" \
     -d '{"keyword": "LED TV", "search_type": "supplier", "location": "Riyadh"}'
```

### **4. Test Keywords After Search:**
```bash
curl -X GET "http://localhost:8000/api/supplier/analytics/keywords" \
     -H "Authorization: Bearer 123|kT7dJgOpOP7GWRHwHsFvPj3aM6YFIN8JTUi5MIrb5212e7b0" \
     -s | python3 -m json.tool
```

---

## 📈 **Expected Results:**

### **After tracking views and searches:**
- **Keywords endpoint** will show real search data
- **Insights endpoint** will show real visitor locations and demographics
- **Charts endpoint** will show real view counts
- **Export** will contain all tracked data

### **Customer Insights will populate with:**
- Real visitor locations (from tracking)
- Customer types (Large Organizations, Small Businesses, Individuals)
- Visit patterns and behavior

---

## 🔥 **All Issues Fixed:**

1. ✅ **Direct export download** - No intermediate step
2. ✅ **Single unified export** - All data in one file
3. ✅ **Profile views tracking** - Real visitor data
4. ✅ **Search keywords tracking** - Real search analytics
5. ✅ **Customer insights** - Real demographics and locations

Everything is now working with real data! 🚀
