<?php

namespace App\Providers;

use App\Models\SupplierCertification;
use App\Models\SupplierProductImage;
use App\Models\SupplierService;
use App\Policies\SupplierCertificationPolicy;
use App\Policies\SupplierProductImagePolicy;
use App\Policies\SupplierServicePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        SupplierProductImage::class => SupplierProductImagePolicy::class,
        SupplierService::class => SupplierServicePolicy::class,
        SupplierCertification::class => SupplierCertificationPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
