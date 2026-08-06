<?php

namespace App\Support;

final class Constants
{
    // Business types
    public const BUSINESS_TYPE_RESTAURANT = 'restaurant';

    public const BUSINESS_TYPE_CAFE = 'cafe';

    public const BUSINESS_TYPE_HOTEL = 'hotel';

    public const BUSINESS_TYPE_RETAIL = 'retail';

    public const BUSINESS_TYPE_SERVICE = 'service';

    public const BUSINESS_TYPE_OTHER = 'other';

    // Supplier statuses
    public const SUPPLIER_STATUS_ACTIVE = 'active';

    public const SUPPLIER_STATUS_PENDING = 'pending';

    public const SUPPLIER_STATUS_SUSPENDED = 'suspended';

    public const SUPPLIER_STATUS_INACTIVE = 'inactive';

    // Document statuses
    public const DOCUMENT_STATUS_PENDING_VERIFICATION = 'pending_verification';

    public const DOCUMENT_STATUS_APPROVED = 'approved';

    public const DOCUMENT_STATUS_REJECTED = 'rejected';

    // Rating statuses
    public const RATING_STATUS_PENDING_REVIEW = 'pending_review';

    public const RATING_STATUS_APPROVED = 'approved';

    public const RATING_STATUS_REJECTED = 'rejected';

    public const RATING_STATUS_FLAGGED = 'flagged';

    // Review statuses
    public const REVIEW_STATUS_APPROVED = 'approved';

    public const REVIEW_STATUS_REJECTED = 'rejected';

    public const BUSINESS_TYPES = ['supplier', 'store', 'office', 'individual'];
}
