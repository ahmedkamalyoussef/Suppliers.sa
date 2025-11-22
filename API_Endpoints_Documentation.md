# Suppliers.sa API Endpoints Documentation

## Authentication Endpoints

### POST /auth/login
**Description**: User login with email and password
**Authorization**: Public (no authentication required)
**Parameters**:
- `email` (string, required): User email
- `password` (string, required): User password
**Returns**: User data with authentication token

### POST /auth/send-otp
**Description**: Send OTP to phone number
**Authorization**: Public (no authentication required)
**Parameters**:
- `phone` (string, required): Phone number with country code
**Returns**: Success message with OTP status

### POST /auth/verify-otp
**Description**: Verify OTP code
**Authorization**: Public (no authentication required)
**Parameters**:
- `phone` (string, required): Phone number
- `otp` (string, required): OTP code
**Returns**: Authentication token if verified

### POST /auth/forgot-password
**Description**: Send password reset email
**Authorization**: Public (no authentication required)
**Parameters**:
- `email` (string, required): User email
**Returns**: Password reset status

### POST /auth/reset-password
**Description**: Reset password with token
**Authorization**: Public (no authentication required)
**Parameters**:
- `token` (string, required): Reset token
- `email` (string, required): User email
- `password` (string, required): New password
- `password_confirmation` (string, required): Confirm new password
**Returns**: Password reset success status

### POST /auth/logout
**Description**: User logout
**Authorization**: Authenticated User (any role)
**Parameters**: None (requires authentication)
**Returns**: Logout success message

### POST /auth/change-password
**Description**: Change user password
**Authorization**: Authenticated User (any role)
**Parameters**:
- `current_password` (string, required): Current password
- `password` (string, required): New password
- `password_confirmation` (string, required): Confirm new password
**Returns**: Password change success status

---

## User Management Endpoints

### POST /users/register
**Description**: Register new user
**Authorization**: Public (no authentication required)
**Parameters**:
- `name` (string, required): User full name
- `email` (string, required): User email
- `password` (string, required): User password
- `password_confirmation` (string, required): Confirm password
- `phone` (string, required): Phone number
- `business_name` (string, optional): Business name
- `plan` (string, optional): Subscription plan (Basic, Premium, Enterprise)
**Returns**: Created user data

### GET /users
**Description**: Get users list with pagination and filtering
**Authorization**: Admin only
**Parameters**:
- `page` (integer, optional): Page number (default: 1)
- `limit` (integer, optional): Items per page (default: 20)
- `search` (string, optional): Search by name or email
- `plan` (string, optional): Filter by subscription plan
- `status` (string, optional): Filter by status
**Returns**: Paginated users list

### GET /users/{id}
**Description**: Get specific user details
**Authorization**: Admin only or own user
**Parameters**: User ID in URL
**Returns**: User details

### PUT /users/{id}
**Description**: Update user information
**Authorization**: Admin only or own user (limited fields)
**Parameters**: User ID in URL
- `name` (string, optional): User name
- `phone` (string, optional): Phone number
- `business_name` (string, optional): Business name
- `plan` (string, optional): Subscription plan (admin only)
- `status` (string, optional): User status (admin only)
**Returns**: Updated user data

### PATCH /users/{id}
**Description**: Partial update user information
**Authorization**: Admin only or own user (limited fields)
**Parameters**: User ID in URL + any user fields
**Returns**: Updated user data

### DELETE /users/{id}
**Description**: Delete user
**Authorization**: Admin only
**Parameters**: User ID in URL
**Returns**: Deletion success status

### GET /users/profile
**Description**: Get current user profile
**Authorization**: Authenticated User (any role)
**Parameters**: None (requires authentication)
**Returns**: Current user profile data

### PUT /users/profile
**Description**: Update current user profile
**Authorization**: Authenticated User (any role)
**Parameters**:
- `name` (string, optional): User name
- `phone` (string, optional): Phone number
- `business_name` (string, optional): Business name
**Returns**: Updated profile data

### GET /users/limits
**Description**: Get current user limits
**Authorization**: Authenticated User (any role)
**Parameters**: None (requires authentication)
**Returns**: User limits based on subscription plan

### POST /users/check-business-limit
**Description**: Check if user can add more businesses
**Authorization**: Authenticated User (any role)
**Parameters**: None (requires authentication)
**Returns**: Business limit status

---

## Business Management Endpoints

### GET /businesses
**Description**: Get businesses list with filtering
**Authorization**: Public (basic list) or Authenticated User (full details)
**Parameters**:
- `page` (integer, optional): Page number
- `limit` (integer, optional): Items per page
- `category` (string, optional): Filter by category
- `city` (string, optional): Filter by city
- `verified` (boolean, optional): Filter by verification status
- `status` (string, optional): Filter by business status
**Returns**: Paginated businesses list

### POST /businesses
**Description**: Create new business
**Authorization**: Authenticated User (supplier role)
**Parameters**:
- `business_name` (string, required): Business name
- `category` (string, required): Business category
- `business_type` (string, required): Business type
- `description` (string, required): Business description
- `phone` (string, required): Business phone
- `email` (string, required): Business email
- `website` (string, optional): Business website
- `location` (object, required): Location data (address, city, region, postal_code, country, latitude, longitude)
- `operating_hours` (object, optional): Operating hours by day
- `social_media` (object, optional): Social media links
- `tags` (array, optional): Business tags
**Returns**: Created business data

### GET /businesses/{id}
**Description**: Get specific business details
**Authorization**: Public (basic) or Authenticated User (full details)
**Parameters**: Business ID in URL
**Returns**: Business details with images and reviews

### PUT /businesses/{id}
**Description**: Update business information
**Authorization**: Business Owner or Admin
**Parameters**: Business ID in URL + business fields
**Returns**: Updated business data

### PATCH /businesses/{id}
**Description**: Partial update business information
**Authorization**: Business Owner or Admin
**Parameters**: Business ID in URL + any business fields
**Returns**: Updated business data

### DELETE /businesses/{id}
**Description**: Delete business
**Authorization**: Business Owner or Admin
**Parameters**: Business ID in URL
**Returns**: Deletion success status

### POST /businesses/{id}/images
**Description**: Upload business image
**Authorization**: Business Owner or Admin
**Parameters**: Business ID in URL
- `image` (file, required): Image file
- `caption` (string, optional): Image caption
- `sort_order` (integer, optional): Sort order
**Returns**: Uploaded image data

### DELETE /businesses/{id}/images/{image_id}
**Description**: Delete business image
**Authorization**: Business Owner or Admin
**Parameters**: Business ID and image ID in URL
**Returns**: Deletion success status

### GET /businesses/{id}/reviews
**Description**: Get business reviews
**Authorization**: Public (basic) or Authenticated User (full details)
**Parameters**: Business ID in URL
- `page` (integer, optional): Page number
- `limit` (integer, optional): Items per page
**Returns**: Paginated reviews list

### PUT /businesses/{id}/location
**Description**: Update business location
**Authorization**: Business Owner or Admin
**Parameters**: Business ID in URL
- `location` (object, required): New location data
**Returns**: Updated location data

---

## Search and Filtering Endpoints

### GET /search/businesses
**Description**: Search businesses with filters
**Authorization**: Public (basic) or Authenticated User (full details)
**Parameters**:
- `q` (string, required): Search query
- `category` (string, optional): Filter by category
- `city` (string, optional): Filter by city
- `rating` (integer, optional): Minimum rating
- `page` (integer, optional): Page number
- `limit` (integer, optional): Items per page
**Returns**: Search results

### GET /search/suggestions
**Description**: Get search suggestions
**Authorization**: Public
**Parameters**:
- `q` (string, required): Search query
- `limit` (integer, optional): Number of suggestions
**Returns**: Search suggestions

### POST /search/advanced
**Description**: Advanced business search
**Authorization**: Authenticated User (any role)
**Parameters**:
- `keywords` (string, optional): Keywords
- `category` (string, optional): Category
- `business_type` (string, optional): Business type
- `location` (object, optional): Location filters
- `rating` (object, optional): Rating range
- `verified` (boolean, optional): Verification status
- `tags` (array, optional): Business tags
- `operating_hours` (object, optional): Operating hours filters
- `sort_by` (string, optional): Sort field
- `sort_order` (string, optional): Sort direction
- `page` (integer, optional): Page number
- `limit` (integer, optional): Items per page
**Returns**: Advanced search results

### POST /search/image-search
**Description**: Search using image (Readdy.ai integration)
**Authorization**: Authenticated User (any role)
**Parameters**:
- `image` (file, required): Search image
- `category` (string, optional): Category filter
- `limit` (integer, optional): Number of results
**Returns**: Image-based search results

---

## Payment Endpoints

### POST /payments/create
**Description**: Create payment request (ClickPay)
**Authorization**: Authenticated User (supplier role)
**Parameters**:
- `amount` (decimal, required): Payment amount
- `currency` (string, required): Currency code
- `description` (string, required): Payment description
- `customer_email` (string, required): Customer email
- `customer_phone` (string, optional): Customer phone
- `callback_url` (string, optional): Payment callback URL
- `return_url` (string, optional): Payment return URL
- `metadata` (object, optional): Additional metadata
**Returns**: Payment request data

### GET /payments/{transaction_id}/query
**Description**: Query payment status
**Authorization**: Authenticated User (supplier role) or Admin
**Parameters**: Transaction ID in URL
**Returns**: Payment status

### POST /payments/{transaction_id}/refund
**Description**: Process payment refund
**Authorization**: Admin only
**Parameters**: Transaction ID in URL
- `amount` (decimal, optional): Refund amount
- `reason` (string, optional): Refund reason
**Returns**: Refund status

### POST /payments/callback
**Description**: Payment callback handler
**Authorization**: Public (ClickPay webhook)
**Parameters**: ClickPay callback data
**Returns**: Callback processing status

### GET /payments/methods
**Description**: Get available payment methods
**Authorization**: Public
**Parameters**: None
**Returns**: Available payment methods

---

## Reviews and Ratings Endpoints

### POST /reviews
**Description**: Create new business review
**Authorization**: Public (basic) or Authenticated User (full details)
**Parameters**:
- `business_id` (integer, required): Business ID
- `customer_name` (string, required): Customer name
- `customer_email` (string, required): Customer email
- `rating` (integer, required): Rating (1-5)
- `title` (string, optional): Review title
- `comment` (string, optional): Review comment
- `verified` (boolean, optional): Verified status
**Returns**: Created review data

### GET /reviews
**Description**: Get reviews list with filtering
**Authorization**: Public (basic) or Authenticated User (full details)
**Parameters**:
- `business_id` (integer, optional): Filter by business
- `rating` (integer, optional): Filter by rating
- `status` (string, optional): Filter by status
- `page` (integer, optional): Page number
- `limit` (integer, optional): Items per page
**Returns**: Paginated reviews list

### GET /reviews/{id}
**Description**: Get specific review details
**Authorization**: Public (basic) or Authenticated User (full details)
**Parameters**: Review ID in URL
**Returns**: Review details

### PUT /reviews/{id}
**Description**: Update review
**Authorization**: Review Owner or Admin
**Parameters**: Review ID in URL
- `rating` (integer, optional): New rating
- `title` (string, optional): New title
- `comment` (string, optional): New comment
**Returns**: Updated review data

### DELETE /reviews/{id}
**Description**: Delete review
**Authorization**: Review Owner or Admin
**Parameters**: Review ID in URL
**Returns**: Deletion success status

### POST /reviews/{id}/helpful
**Description**: Mark review as helpful
**Authorization**: Authenticated User (any role)
**Parameters**: Review ID in URL
**Returns**: Helpful mark status

### POST /reviews/{id}/report
**Description**: Report inappropriate review
**Authorization**: Authenticated User (any role)
**Parameters**: Review ID in URL
- `reason` (string, required): Report reason
- `description` (string, optional): Report description
**Returns**: Report status

### POST /reviews/{id}/approve
**Description**: Approve review (admin)
**Authorization**: Admin only
**Parameters**: Review ID in URL
**Returns**: Approval status

### POST /reviews/{id}/reject
**Description**: Reject review (admin)
**Authorization**: Admin only
**Parameters**: Review ID in URL
- `reason` (string, optional): Rejection reason
**Returns**: Rejection status

### GET /reviews/pending
**Description**: Get pending reviews (admin)
**Authorization**: Admin only
**Parameters**:
- `page` (integer, optional): Page number
- `limit` (integer, optional): Items per page
**Returns**: Pending reviews list

### GET /reviews/statistics
**Description**: Get review statistics
**Authorization**: Admin only
**Parameters**:
- `period` (string, optional): Time period (day, week, month, year)
**Returns**: Review statistics

---

## Maps and Location Endpoints

### GET /maps/businesses
**Description**: Get businesses within map bounds
**Authorization**: Public (basic) or Authenticated User (full details)
**Parameters**:
- `bounds` (string, required): Map bounds (lat1,lng1,lat2,lng2)
- `category` (string, optional): Filter by category
- `verified` (boolean, optional): Filter by verification status
**Returns**: Businesses within bounds

### POST /maps/directions
**Description**: Get directions between two points
**Authorization**: Authenticated User (any role)
**Parameters**:
- `origin` (object, required): Origin point (latitude, longitude, address)
- `destination` (object, required): Destination point (latitude, longitude, address)
- `mode` (string, optional): Travel mode (driving, walking, transit)
**Returns**: Directions data

### POST /maps/geocode
**Description**: Convert address to coordinates
**Authorization**: Authenticated User (any role)
**Parameters**:
- `address` (string, required): Address to geocode
**Returns**: Coordinates and address data

### POST /maps/reverse-geocode
**Description**: Convert coordinates to address
**Authorization**: Authenticated User (any role)
**Parameters**:
- `latitude` (decimal, required): Latitude
- `longitude` (decimal, required): Longitude
**Returns**: Address data

### GET /maps/nearby
**Description**: Get nearby businesses
**Authorization**: Public (basic) or Authenticated User (full details)
**Parameters**:
- `latitude` (decimal, required): Center latitude
- `longitude` (decimal, required): Center longitude
- `radius` (integer, optional): Search radius in km
- `category` (string, optional): Filter by category
**Returns**: Nearby businesses

---

## System Settings Endpoints

### GET /settings/general
**Description**: Get general system settings
**Authorization**: Admin only
**Parameters**: None
**Returns**: General settings

### PUT /settings/general
**Description**: Update general system settings
**Authorization**: Admin only
**Parameters**:
- `site_name` (string, optional): Site name
- `site_description` (string, optional): Site description
- `contact_email` (string, optional): Contact email
- `contact_phone` (string, optional): Contact phone
- `maintenance_mode` (boolean, optional): Maintenance mode status
**Returns**: Updated settings

### GET /settings/plans
**Description**: Get subscription plans settings
**Authorization**: Admin only
**Parameters**: None
**Returns**: Plans configuration

### PUT /settings/plans
**Description**: Update subscription plans settings
**Authorization**: Admin only
**Parameters**:
- `plans` (object, required): Plans configuration
**Returns**: Updated plans

### GET /settings/payment
**Description**: Get payment settings
**Authorization**: Admin only
**Parameters**: None
**Returns**: Payment configuration

### PUT /settings/payment
**Description**: Update payment settings
**Authorization**: Admin only
**Parameters**:
- `clickpay_api_url` (string, optional): ClickPay API URL
- `clickpay_profile_id` (string, optional): ClickPay profile ID
- `clickpay_server_key` (string, optional): ClickPay server key
- `default_currency` (string, optional): Default currency
**Returns**: Updated payment settings

### GET /settings/notifications
**Description**: Get notification settings
**Authorization**: Admin only
**Parameters**: None
**Returns**: Notification configuration

### PUT /settings/notifications
**Description**: Update notification settings
**Authorization**: Admin only
**Parameters**:
- `email_notifications` (boolean, optional): Email notifications enabled
- `sms_notifications` (boolean, optional): SMS notifications enabled
- `push_notifications` (boolean, optional): Push notifications enabled
- `new_review_notifications` (boolean, optional): New review notifications
- `payment_notifications` (boolean, optional): Payment notifications
**Returns**: Updated notification settings

### GET /settings/security
**Description**: Get security settings
**Authorization**: Admin only
**Parameters**: None
**Returns**: Security configuration

### PUT /settings/security
**Description**: Update security settings
**Authorization**: Admin only
**Parameters**:
- `password_min_length` (integer, optional): Minimum password length
- `require_strong_password` (boolean, optional): Require strong password
- `session_timeout` (integer, optional): Session timeout in minutes
- `max_login_attempts` (integer, optional): Maximum login attempts
- `lockout_duration` (integer, optional): Lockout duration in minutes
**Returns**: Updated security settings

### GET /settings/maintenance
**Description**: Get maintenance settings
**Authorization**: Admin only
**Parameters**: None
**Returns**: Maintenance configuration

### PUT /settings/maintenance
**Description**: Update maintenance settings
**Authorization**: Admin only
**Parameters**:
- `maintenance_mode` (boolean, optional): Maintenance mode status
- `maintenance_message` (string, optional): Maintenance message
- `backup_enabled` (boolean, optional): Backup enabled
- `backup_frequency` (string, optional): Backup frequency
**Returns**: Updated maintenance settings

### POST /settings/cache/clear
**Description**: Clear system cache
**Authorization**: Admin only
**Parameters**:
- `type` (string, optional): Cache type (all, settings, routes, views)
**Returns**: Cache clear status

### GET /settings/system/status
**Description**: Get system status
**Authorization**: Admin only
**Parameters**: None
**Returns**: System health status

---

## Reports and Analytics Endpoints

### GET /reports/dashboard
**Description**: Get dashboard overview
**Authorization**: Admin only
**Parameters**:
- `period` (string, optional): Time period (day, week, month, year)
**Returns**: Dashboard statistics

### GET /reports/users
**Description**: Get user analytics
**Authorization**: Admin only
**Parameters**:
- `period` (string, optional): Time period
- `group_by` (string, optional): Group by (day, week, month)
- `plan` (string, optional): Filter by plan
- `status` (string, optional): Filter by status
**Returns**: User analytics data

### GET /reports/businesses
**Description**: Get business analytics
**Authorization**: Admin only
**Parameters**:
- `period` (string, optional): Time period
- `group_by` (string, optional): Group by
- `category` (string, optional): Filter by category
- `status` (string, optional): Filter by status
**Returns**: Business analytics data

### GET /reports/reviews
**Description**: Get review analytics
**Authorization**: Admin only
**Parameters**:
- `period` (string, optional): Time period
- `group_by` (string, optional): Group by
- `status` (string, optional): Filter by status
- `rating` (integer, optional): Filter by rating
**Returns**: Review analytics data

### GET /reports/revenue
**Description**: Get revenue analytics
**Authorization**: Admin only
**Parameters**:
- `period` (string, optional): Time period
- `group_by` (string, optional): Group by
- `plan` (string, optional): Filter by plan
**Returns**: Revenue analytics data

### POST /reports/export
**Description**: Export report data
**Authorization**: Admin only
**Parameters**:
- `type` (string, required): Report type (users, businesses, reviews, revenue)
- `format` (string, required): Export format (csv, xlsx, pdf)
- `period` (string, optional): Time period
- `filters` (object, optional): Report filters
**Returns**: Export file information

---

## Notifications Endpoints

### GET /notifications
**Description**: Get user notifications
**Authorization**: Authenticated User (any role)
**Parameters**:
- `type` (string, optional): Filter by type
- `status` (string, optional): Filter by status (read, unread)
- `limit` (integer, optional): Number of notifications
**Returns**: User notifications list

### POST /notifications/{id}/read
**Description**: Mark notification as read
**Authorization**: Authenticated User (any role)
**Parameters**: Notification ID in URL
**Returns**: Mark as read status

### POST /notifications/read-all
**Description**: Mark all notifications as read
**Authorization**: Authenticated User (any role)
**Parameters**: None
**Returns**: All notifications marked as read

### DELETE /notifications/{id}
**Description**: Delete notification
**Authorization**: Authenticated User (any role)
**Parameters**: Notification ID in URL
**Returns**: Deletion success status

### GET /notifications/settings
**Description**: Get user notification settings
**Authorization**: Authenticated User (any role)
**Parameters**: None
**Returns**: User notification preferences

### PUT /notifications/settings
**Description**: Update user notification settings
**Authorization**: Authenticated User (any role)
**Parameters**:
- `email_notifications` (boolean, optional): Email notifications
- `push_notifications` (boolean, optional): Push notifications
- `sms_notifications` (boolean, optional): SMS notifications
- `business_updates` (boolean, optional): Business updates
- `review_notifications` (boolean, optional): Review notifications
- `payment_notifications` (boolean, optional): Payment notifications
- `system_notifications` (boolean, optional): System notifications
- `marketing_emails` (boolean, optional): Marketing emails
**Returns**: Updated notification settings

### POST /notifications/send
**Description**: Send notification to user
**Authorization**: Admin only
**Parameters**:
- `user_id` (integer, required): User ID
- `type` (string, required): Notification type
- `title` (string, required): Notification title
- `message` (string, required): Notification message
- `icon` (string, optional): Notification icon
- `action_url` (string, optional): Action URL
**Returns**: Notification sent status

### POST /notifications/send-bulk
**Description**: Send bulk notifications
**Authorization**: Admin only
**Parameters**:
- `recipients` (array, required): User IDs array
- `type` (string, required): Notification type
- `title` (string, required): Notification title
- `message` (string, required): Notification message
- `icon` (string, optional): Notification icon
**Returns**: Bulk notification status

### GET /notifications/statistics
**Description**: Get notification statistics
**Authorization**: Admin only
**Parameters**:
- `period` (string, optional): Time period
**Returns**: Notification statistics

### GET /notifications/templates
**Description**: Get notification templates
**Authorization**: Admin only
**Parameters**: None
**Returns**: Available notification templates

---

## Data Export Endpoints

### POST /exports/users
**Description**: Export users data
**Authorization**: Admin only
**Parameters**:
- `format` (string, required): Export format (csv, xlsx, pdf)
- `filters` (object, optional): Export filters (plan, status, date range)
- `fields` (array, optional): Fields to export
**Returns**: Export file information

### POST /exports/businesses
**Description**: Export businesses data
**Authorization**: Admin only
**Parameters**:
- `format` (string, required): Export format
- `filters` (object, optional): Export filters (category, status, verified, date range)
- `fields` (array, optional): Fields to export
**Returns**: Export file information

### POST /exports/reviews
**Description**: Export reviews data
**Authorization**: Admin only
**Parameters**:
- `format` (string, required): Export format
- `filters` (object, optional): Export filters (rating, status, verified, date range)
- `fields` (array, optional): Fields to export
**Returns**: Export file information

### POST /exports/analytics
**Description**: Export analytics data
**Authorization**: Admin only
**Parameters**:
- `format` (string, required): Export format
- `type` (string, required): Analytics type (user_growth, business_growth, review_stats)
- `period` (string, optional): Time period
**Returns**: Export file information

### GET /exports/history
**Description**: Get export history
**Authorization**: Admin only
**Parameters**:
- `limit` (integer, optional): Number of records
**Returns**: Export history list

### GET /exports/download/{filename}
**Description**: Download export file
**Authorization**: Admin only
**Parameters**: Filename in URL
**Returns**: Export file download

---

## Public Endpoints

### GET /public/businesses
**Description**: Get public businesses list
**Authorization**: Public (no authentication required)
**Parameters**:
- `page` (integer, optional): Page number
- `limit` (integer, optional): Items per page
- `category` (string, optional): Filter by category
- `city` (string, optional): Filter by city
**Returns**: Public businesses list

### GET /public/businesses/{slug}
**Description**: Get public business details
**Authorization**: Public (no authentication required)
**Parameters**: Business slug in URL
**Returns**: Business public details

### GET /public/businesses/{slug}/reviews
**Description**: Get business reviews (public)
**Authorization**: Public (no authentication required)
**Parameters**: Business slug in URL
- `page` (integer, optional): Page number
- `limit` (integer, optional): Items per page
**Returns**: Public reviews list

### POST /public/businesses/{slug}/reviews
**Description**: Submit review (public)
**Authorization**: Public (no authentication required)
**Parameters**: Business slug in URL
- `customer_name` (string, required): Customer name
- `customer_email` (string, required): Customer email
- `rating` (integer, required): Rating
- `title` (string, optional): Review title
- `comment` (string, optional): Review comment
**Returns**: Submitted review data

### POST /public/businesses/{slug}/inquiries
**Description**: Submit business inquiry
**Authorization**: Public (no authentication required)
**Parameters**: Business slug in URL
- `name` (string, required): Inquirer name
- `email` (string, required): Inquirer email
- `phone` (string, optional): Inquirer phone
- `subject` (string, required): Inquiry subject
- `message` (string, required): Inquiry message
**Returns**: Inquiry submission status

### POST /public/reports
**Description**: Submit public report
**Authorization**: Public (no authentication required)
**Parameters**:
- `type` (string, required): Report type (business, review, user)
- `target_id` (integer, required): Target ID
- `reason` (string, required): Report reason
- `description` (string, optional): Report description
- `reporter_name` (string, required): Reporter name
- `reporter_email` (string, required): Reporter email
**Returns**: Report submission status

### POST /supplier/register
**Description**: Register new supplier
**Authorization**: Public (no authentication required)
**Parameters**:
- `name` (string, required): Supplier name
- `email` (string, required): Supplier email
- `password` (string, required): Password
- `password_confirmation` (string, required): Confirm password
- `phone` (string, required): Phone number
- `business_name` (string, required): Business name
- `business_type` (string, required): Business type
- `category` (string, required): Business category
- `description` (string, optional): Business description
---

# Suppliers.sa Project Overview

## Project Concept
Suppliers.sa is a comprehensive B2B (Business-to-Business) supplier directory platform designed specifically for the Saudi Arabian market. The platform serves as a centralized marketplace where suppliers can showcase their businesses and potential buyers can discover, evaluate, and connect with suppliers.

## System Architecture

### User Roles and Permissions

#### 1. Public Users (Unauthenticated)
**Who they are**: Visitors to the platform, potential customers, researchers
**What they can do**:
- Browse and search businesses publicly
- View business details (basic information)
- Read reviews and ratings
- Submit reviews for businesses
- Submit business inquiries
- Report inappropriate content
- Register new accounts
- Reset passwords
- Access basic search suggestions

**What they CANNOT do**:
- Access detailed business analytics
- Manage any business information
- Access other users' data
- Use advanced search features
- Access system settings
- Export data

#### 2. Authenticated Users (Suppliers)
**Who they are**: Registered business owners, suppliers, service providers
**What they can do**:
- Everything public users can do
- Create and manage their own business listings
- Upload and manage business images
- Update business information and location
- View detailed business analytics
- Use advanced search features
- Get directions and location services
- Manage their profile and account settings
- Receive notifications
- Check their subscription limits
- Submit reviews for other businesses
- Report inappropriate content

**What they CANNOT do**:
- Manage other users' businesses
- Access system administration settings
- Approve/reject reviews
- View platform analytics
- Export platform data
- Manage subscription plans
- Access other users' private information

#### 3. Administrators
**Who they are**: Platform administrators, system managers
**What they can do**:
- Everything authenticated users can do
- Manage all users (view, update, delete)
- Manage all businesses (view, update, delete, verify)
- Approve/reject reviews
- Access comprehensive platform analytics
- Export platform data
- Manage system settings
- Send notifications to users
- Manage subscription plans
- Access payment processing
- View system health and status
- Manage maintenance mode
- Clear system cache

**What they CANNOT do**:
- Delete their own admin account (requires another admin)
- Access external payment credentials directly

## Business Workflow

### Registration Process
1. **Public Registration**: Users register with basic information (name, email, phone, business name)
2. **Plan Selection**: Users select subscription plan (Basic, Premium, Enterprise)
3. **Account Creation**: System creates user account with default permissions
4. **Business Limits**: System assigns business limits based on plan:
   - Basic: 8 businesses
   - Premium: 15 businesses
   - Enterprise: 50 businesses

### Business Management Workflow
1. **Business Creation**: Suppliers create business listings with detailed information
2. **Verification Process**: Admins can verify businesses for credibility
3. **Content Management**: Suppliers can update business info, upload images, manage operating hours
4. **Review System**: Customers can rate and review businesses
5. **Inquiry Handling**: Public users can submit inquiries that are routed to business owners

### Review and Rating System
1. **Public Reviews**: Anyone can submit reviews (authenticated or public)
2. **Review Moderation**: Reviews may require admin approval based on settings
3. **Quality Control**: Users can report inappropriate reviews
4. **Helpful Voting**: Authenticated users can mark reviews as helpful
5. **Analytics**: Businesses can view review statistics

### Payment Integration
1. **Subscription Payments**: Users pay for premium plans via ClickPay
2. **Payment Processing**: System integrates with ClickPay for secure transactions
3. **Refund Management**: Admins can process refunds when needed
4. **Transaction Tracking**: All transactions are logged and trackable

## Data Flow and Permissions

### Business Data
- **Public Access**: Basic business name, category, contact info (limited)
- **Authenticated Access**: Full business details, analytics, management tools
- **Admin Access**: All business data plus management and verification capabilities

### User Data
- **Self-Access**: Users can view and edit their own profiles
- **Admin Access**: Full access to all user data for management purposes
- **Public Access**: Limited to public profile information (name, business name)

### Review Data
- **Public Access**: Read reviews, submit new reviews
- **Authenticated Access**: All public access plus helpful voting, reporting
- **Admin Access**: Full review management including approval/rejection

### System Data
- **Admin Only**: All system settings, analytics, exports, user management
- **No Public Access**: System configuration and sensitive data are protected

## Security and Access Control

### Authentication Methods
- **Email/Password**: Traditional login method
- **OTP Verification**: Phone-based authentication
- **Token-based**: API access using bearer tokens

### Permission Levels
1. **Public**: No authentication, limited read access
2. **User**: Authentication required, personal data access
3. **Admin**: Full system access and management capabilities

### Data Protection
- **Personal Information**: Protected and only accessible by owners and admins
- **Business Data**: Tiered access based on user role
- **Financial Data**: Admin only for security
- **System Configuration**: Admin only

## Platform Features by User Type

### For Public Users
- **Discovery**: Find suppliers by category, location, or search
- **Evaluation**: Read reviews, check ratings, view business details
- **Communication**: Submit inquiries and reviews
- **Trust**: Verified business badges and review system

### For Suppliers
- **Visibility**: Showcase business to potential customers
- **Management**: Complete control over business listings
- **Analytics**: Track views, inquiries, and performance
- **Growth**: Upgrade plans for more features and visibility

### For Administrators
- **Oversight**: Monitor platform activity and user behavior
- **Quality Control**: Ensure content quality and user safety
- **Revenue Management**: Track subscriptions and payments
- **System Health**: Monitor and maintain platform performance

## Business Rules and Constraints

### Business Limits
- **Basic Plan**: Maximum 8 businesses per user
- **Premium Plan**: Maximum 15 businesses per user
- **Enterprise Plan**: Maximum 50 businesses per user

### Content Guidelines
- **Business Listings**: Must be legitimate businesses in Saudi Arabia
- **Reviews**: Must be genuine experiences, no spam or fake reviews
- **Images**: Business-related images only, appropriate content required
- **Contact Information**: Must be valid and verifiable

### Review System Rules
- **Rating Scale**: 1-5 stars
- **Verification**: Reviews may require admin approval
- **Reporting**: Inappropriate reviews can be reported by users
- **Helpful Votes**: Authenticated users can vote on review helpfulness

## Integration Points

### External Services
- **ClickPay**: Payment processing for subscriptions
- **Google Maps**: Location services, directions, geocoding
- **Readdy.ai**: Image-based search functionality
- **Email Services**: Notification delivery (configurable)

### Internal Systems
- **Database**: User, business, review, and system data storage
- **Cache System**: Performance optimization for frequently accessed data
- **File Storage**: Business images and export files
- **Notification System**: Multi-channel notification delivery

## Platform Scalability

### Multi-Tenant Architecture
- Shared platform with isolated user data
- Scalable to handle thousands of businesses
- Tiered subscription model for sustainable growth

### Performance Considerations
- Caching for frequently accessed data
- Optimized search and filtering
- Efficient image handling and storage
- Scalable notification system

This comprehensive system ensures that Suppliers.sa operates as a trusted, efficient, and scalable platform connecting Saudi Arabian suppliers with potential business customers while maintaining proper security, privacy, and user experience standards.

---

## Response Format

All API endpoints return responses in the following format:

### Success Response
```json
{
  "success": true,
  "data": {}, // Response data
  "message": "Success message"
}
```

### Error Response
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Error message",
    "details": {} // Optional error details
  }
}
```

### Paginated Response
```json
{
  "success": true,
  "data": {
    "items": [], // Data items
    "pagination": {
      "current_page": 1,
      "total_pages": 10,
      "total_items": 200,
      "per_page": 20
    }
  }
}
```

## Authentication

Most endpoints require authentication using Bearer token. Include the token in the Authorization header:

```
Authorization: Bearer {your_token}
```

## Rate Limiting

API endpoints are rate-limited to prevent abuse. Standard rate limits:
- 100 requests per minute per IP
- 1000 requests per hour per authenticated user

## Error Codes

Common error codes:
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests
- `500` - Internal Server Error
