<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminPermission extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'admin_id',
        // إدارة المستخدمين (1)
        'user_management_view',
        'user_management_edit',
        'user_management_delete',
        'user_management_full',
        // إدارة المحتوى (2)
        'content_management_view',
        'content_management_supervise',
        'content_management_delete',
        // التحليلات (3)
        'analytics_view',
        'analytics_export',
        // التقارير (4)
        'reports_view',
        'reports_create',
        // النظام (5)
        'system_manage',
        'system_settings',
        'system_backups',
        // الدعم (6)
        'support_manage',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_management_view' => 'boolean',
            'user_management_edit' => 'boolean',
            'user_management_delete' => 'boolean',
            'user_management_full' => 'boolean',
            'content_management_view' => 'boolean',
            'content_management_supervise' => 'boolean',
            'content_management_delete' => 'boolean',
            'analytics_view' => 'boolean',
            'analytics_export' => 'boolean',
            'reports_view' => 'boolean',
            'reports_create' => 'boolean',
            'system_manage' => 'boolean',
            'system_settings' => 'boolean',
            'system_backups' => 'boolean',
            'support_manage' => 'boolean',
        ];
    }

    /**
     * Get the admin that owns the permissions.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
