# API Endpoints Documentation for Postman Testing

## Base URL
```
http://your-domain.com/api
```

---

## 🔓 Public Endpoints (No Authentication)

### 1. Login (Admin or Supplier)
**POST** `/auth/login`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "email": "admin@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "message": "Login successful",
  "user_type": "admin",
  "user": {
    "id": 1,
    "name": "Super Admin",
    "email": "admin@example.com",
    "role": "super_admin",
    "permissions": null
  },
  "access_token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "token_type": "Bearer"
}
```

---

### 2. Send OTP (Supplier Only)
**POST** `/auth/send-otp`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "email": "supplier@example.com"
}
```

**Response:**
```json
{
  "message": "OTP has been sent to your email"
}
```

---

### 3. Verify OTP (Supplier Only)
**POST** `/auth/verify-otp`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "email": "supplier@example.com",
  "otp": "123456"
}
```

**Response:**
```json
{
  "message": "Email verified successfully",
  "user": {
    "id": 1,
    "name": "Supplier Name",
    "email": "supplier@example.com"
  },
  "access_token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "token_type": "Bearer"
}
```

---

### 4. Forgot Password (Supplier Only)
**POST** `/auth/forgot-password`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "email": "supplier@example.com"
}
```

**Response:**
```json
{
  "message": "Password reset OTP has been sent to your email."
}
```

---

### 5. Reset Password (Supplier Only)
**POST** `/auth/reset-password`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "email": "supplier@example.com",
  "otp": "123456",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Response:**
```json
{
  "message": "Password has been reset successfully"
}
```

---

### 6. Supplier Register
**POST** `/supplier/register`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "business_name": "My Business",
  "email": "supplier@example.com",
  "phone": "01234567890",
  "password": "password123",
  "password_confirmation": "password123",
  "referral_code": "REF123"
}
```

**Response:**
```json
{
  "message": "Registration successful",
  "user": {
    "id": 1,
    "name": "My Business",
    "email": "supplier@example.com",
    "phone": "01234567890"
  }
}
```

---

## 🔒 Protected Endpoints (Require Authentication)

### 7. Get Authenticated User
**GET** `/user`

**Headers:**
```
Authorization: Bearer {access_token}
Accept: application/json
```

**Response:**
```json
{
  "id": 1,
  "name": "Admin Name",
  "email": "admin@example.com",
  "role": "super_admin"
}
```

---

### 8. Logout
**POST** `/auth/logout`

**Headers:**
```
Authorization: Bearer {access_token}
Accept: application/json
```

**Response:**
```json
{
  "message": "Logged out successfully"
}
```

---

### 9. Change Password
**POST** `/auth/change-password`

**Headers:**
```
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "current_password": "oldpassword123",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Response:**
```json
{
  "message": "Password changed successfully"
}
```

---

## 👑 Super Admin Only Endpoints

### 10. Get All Admins
**GET** `/admins`

**Headers:**
```
Authorization: Bearer {super_admin_token}
Accept: application/json
```

**Response:**
```json
[
  {
    "id": 1,
    "name": "Super Admin",
    "email": "super@example.com",
    "role": "super_admin",
    "department": null,
    "job_role": null,
    "permissions": null
  },
  {
    "id": 2,
    "name": "Regular Admin",
    "email": "admin@example.com",
    "role": "admin",
    "department": "IT",
    "job_role": "Manager",
    "permissions": {
      "user_management_view": true,
      "user_management_edit": true,
      "user_management_delete": false,
      "user_management_full": false,
      "content_management_view": true,
      "content_management_supervise": false,
      "content_management_delete": false,
      "analytics_view": true,
      "analytics_export": false,
      "reports_view": true,
      "reports_create": false,
      "system_manage": false,
      "system_settings": false,
      "system_backups": false,
      "support_manage": false
    }
  }
]
```

---

### 11. Get Single Admin
**GET** `/admins/{id}`

**Example:** `/admins/1`

**Headers:**
```
Authorization: Bearer {super_admin_token}
Accept: application/json
```

**Response:**
```json
{
  "id": 1,
  "name": "Regular Admin",
  "email": "admin@example.com",
  "role": "admin",
  "department": "IT",
  "job_role": "Manager",
  "permissions": {
    "user_management_view": true,
    "user_management_edit": true,
    "user_management_delete": false,
    "user_management_full": false,
    "content_management_view": true,
    "content_management_supervise": false,
    "content_management_delete": false,
    "analytics_view": true,
    "analytics_export": false,
    "reports_view": true,
    "reports_create": false,
    "system_manage": false,
    "system_settings": false,
    "system_backups": false,
    "support_manage": false
  }
}
```

---

### 12. Create Admin - Regular Admin
**POST** `/admins`

**Headers:**
```
Authorization: Bearer {super_admin_token}
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "name": "New Admin",
  "email": "newadmin@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "admin",
  "department": "IT",
  "job_role": "Manager",
  "permissions": {
    "user_management_view": true,
    "user_management_edit": true,
    "user_management_delete": false,
    "user_management_full": false,
    "content_management_view": true,
    "content_management_supervise": false,
    "content_management_delete": false,
    "analytics_view": true,
    "analytics_export": false,
    "reports_view": true,
    "reports_create": false,
    "system_manage": false,
    "system_settings": false,
    "system_backups": false,
    "support_manage": false
  }
}
```

**Response:**
```json
{
  "message": "Admin created successfully",
  "admin": {
    "id": 3,
    "name": "New Admin",
    "email": "newadmin@example.com",
    "role": "admin",
    "department": "IT",
    "job_role": "Manager",
    "permissions": {
      "user_management_view": true,
      "user_management_edit": true,
      "user_management_delete": false,
      "user_management_full": false,
      "content_management_view": true,
      "content_management_supervise": false,
      "content_management_delete": false,
      "analytics_view": true,
      "analytics_export": false,
      "reports_view": true,
      "reports_create": false,
      "system_manage": false,
      "system_settings": false,
      "system_backups": false,
      "support_manage": false
    }
  }
}
```

---

### 13. Create Admin - Super Admin
**POST** `/admins`

**Headers:**
```
Authorization: Bearer {super_admin_token}
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "name": "Another Super Admin",
  "email": "super2@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "super_admin"
}
```

**Response:**
```json
{
  "message": "Admin created successfully",
  "admin": {
    "id": 4,
    "name": "Another Super Admin",
    "email": "super2@example.com",
    "role": "super_admin",
    "department": null,
    "job_role": null,
    "permissions": null
  }
}
```

---

### 14. Update Admin
**PUT** `/admins/{id}`

**Example:** `/admins/2`

**Headers:**
```
Authorization: Bearer {super_admin_token}
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "name": "Updated Admin Name",
  "email": "updated@example.com",
  "department": "HR",
  "job_role": "Senior Manager",
  "permissions": {
    "user_management_view": true,
    "user_management_edit": true,
    "user_management_delete": true,
    "user_management_full": true,
    "content_management_view": true,
    "content_management_supervise": true,
    "content_management_delete": true,
    "analytics_view": true,
    "analytics_export": true,
    "reports_view": true,
    "reports_create": true,
    "system_manage": false,
    "system_settings": false,
    "system_backups": false,
    "support_manage": true
  }
}
```

**Response:**
```json
{
  "message": "Admin updated successfully",
  "admin": {
    "id": 2,
    "name": "Updated Admin Name",
    "email": "updated@example.com",
    "role": "admin",
    "department": "HR",
    "job_role": "Senior Manager",
    "permissions": {
      "user_management_view": true,
      "user_management_edit": true,
      "user_management_delete": true,
      "user_management_full": true,
      "content_management_view": true,
      "content_management_supervise": true,
      "content_management_delete": true,
      "analytics_view": true,
      "analytics_export": true,
      "reports_view": true,
      "reports_create": true,
      "system_manage": false,
      "system_settings": false,
      "system_backups": false,
      "support_manage": true
    }
  }
}
```

---

### 15. Update Admin Password
**PUT** `/admins/{id}`

**Example:** `/admins/2`

**Headers:**
```
Authorization: Bearer {super_admin_token}
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Response:**
```json
{
  "message": "Admin updated successfully",
  "admin": {
    "id": 2,
    "name": "Admin Name",
    "email": "admin@example.com",
    "role": "admin"
  }
}
```

---

### 16. Delete Admin
**DELETE** `/admins/{id}`

**Example:** `/admins/2`

**Headers:**
```
Authorization: Bearer {super_admin_token}
Accept: application/json
```

**Response:**
```json
{
  "message": "Admin deleted successfully"
}
```

---

## 🏪 Supplier Endpoints

### 17. Update Supplier Profile
**PUT** `/supplier/profile`

**Headers:**
```
Authorization: Bearer {supplier_token}
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "name": "Updated Supplier Name",
  "phone": "01234567890",
  "business_name": "Updated Business Name",
  "business_type": "Retail",
  "service_distance": 50.5,
  "business_categories": ["Category 1", "Category 2"],
  "keywords": ["keyword1", "keyword2"],
  "target_market": ["Market 1", "Market 2"],
  "services_offered": ["Service 1", "Service 2"],
  "website": "https://example.com",
  "additional_phones": ["01234567891", "01234567892"],
  "business_address": "123 Main St",
  "latitude": 30.0444,
  "longitude": 31.2357,
  "working_hours": {
    "monday": {"open": "09:00", "close": "17:00"},
    "tuesday": {"open": "09:00", "close": "17:00"}
  },
  "has_branches": true
}
```

**Response:**
```json
{
  "message": "Profile updated successfully",
  "user": {
    "id": 1,
    "name": "Updated Supplier Name",
    "email": "supplier@example.com",
    "phone": "01234567890",
    "profile": {
      "business_name": "Updated Business Name",
      "business_type": "Retail"
    }
  }
}
```

---

### 18. Update Supplier Profile Image
**POST** `/supplier/profile/image`

**Headers:**
```
Authorization: Bearer {supplier_token}
Accept: application/json
```

**Body (form-data):**
- Key: `profile_image`
- Type: File
- Value: [Select image file]

**Response:**
```json
{
  "message": "Profile image updated successfully",
  "user": {
    "id": 1,
    "name": "Supplier Name",
    "email": "supplier@example.com",
    "profile_image": "uploads/suppliers/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx.jpg"
  }
}
```

---

## 📝 Notes

1. **Authentication**: Most endpoints require Bearer token in Authorization header
2. **Super Admin Only**: Admin management endpoints (10-16) require Super Admin role
3. **Supplier Only**: OTP and password reset endpoints (2-5) are for suppliers only
4. **Permissions**: Regular admins have permissions object, super admins have null permissions
5. **Base URL**: Replace `{base_url}` with your actual API base URL
6. **Tokens**: Replace `{access_token}`, `{super_admin_token}`, `{supplier_token}` with actual tokens from login

---

## 🔑 Permission Fields

All permission fields are boolean (true/false):

- **User Management (إدارة المستخدمين)**:
  - `user_management_view` - عرض المستخدمين
  - `user_management_edit` - تعديل المستخدمين
  - `user_management_delete` - حذف المستخدمين
  - `user_management_full` - إدارة كاملة للمستخدمين

- **Content Management (إدارة المحتوى)**:
  - `content_management_view` - عرض المحتوى
  - `content_management_supervise` - إشراف على المحتوى
  - `content_management_delete` - حذف المحتوى

- **Analytics (التحليلات)**:
  - `analytics_view` - عرض التحليلات
  - `analytics_export` - تصدير التحليلات

- **Reports (التقارير)**:
  - `reports_view` - عرض التقارير
  - `reports_create` - إنشاء التقارير

- **System (النظام)**:
  - `system_manage` - إدارة النظام
  - `system_settings` - تعديل الإعدادات
  - `system_backups` - إدارة النسخ الاحتياطية

- **Support (الدعم)**:
  - `support_manage` - إدارة الدعم

