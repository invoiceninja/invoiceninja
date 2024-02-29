<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('steps')->nullable();
        });

        $steps = collect(\App\Livewire\BillingPortal\Purchase::$dependencies)
            ->pluck('id')
            ->implode(',');

       \App\Models\Subscription::query()
        ->withTrashed()
        ->cursor()
        ->each(function ($subscription) use ($steps){
            
            $subscription->steps = $steps;
            $subscription->save();

        });
    }
};
