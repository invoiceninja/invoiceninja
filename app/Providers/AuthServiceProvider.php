<?php

namespace App\Providers;

use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        \App\Models\Client::class => \App\Policies\ClientPolicy::class,
        \App\Models\Contact::class => \App\Policies\ContactPolicy::class,
        \App\Models\Credit::class => \App\Policies\CreditPolicy::class,
        \App\Models\Document::class => \App\Policies\DocumentPolicy::class,
        \App\Models\Expense::class => \App\Policies\ExpensePolicy::class,
        \App\Models\ExpenseCategory::class => \App\Policies\ExpenseCategoryPolicy::class,
        \App\Models\Invoice::class => \App\Policies\InvoicePolicy::class,
        \App\Models\Payment::class => \App\Policies\PaymentPolicy::class,
        \App\Models\Task::class => \App\Policies\TaskPolicy::class,
        \App\Models\Vendor::class => \App\Policies\VendorPolicy::class,
        \App\Models\Product::class => \App\Policies\ProductPolicy::class,
        \App\Models\TaxRate::class => \App\Policies\TaxRatePolicy::class,
        \App\Models\AccountGateway::class => \App\Policies\AccountGatewayPolicy::class,
        \App\Models\AccountToken::class => \App\Policies\TokenPolicy::class,
        \App\Models\BankAccount::class => \App\Policies\BankAccountPolicy::class,
        \App\Models\PaymentTerm::class => \App\Policies\PaymentTermPolicy::class,
        \App\Models\Project::class => \App\Policies\ProjectPolicy::class,
    ];

    /**
     * Register any application authentication / authorization services.
     *
     * @param \Illuminate\Contracts\Auth\Access\Gate $gate
     *
     * @return void
     */
    public function boot(GateContract $gate)
    {
        foreach (get_class_methods(new \App\Policies\GenericEntityPolicy()) as $method) {
            $gate->define($method, "App\Policies\GenericEntityPolicy@{$method}");
        }

        $this->registerPolicies($gate);
    }
}
