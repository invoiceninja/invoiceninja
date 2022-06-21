<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('assigned_user_id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('product_id');
            $table->boolean('is_recurring')->default(false);
            $table->unsignedInteger('frequency_id');
            $table->string('auto_bill')->default('');
            $table->string('promo_code')->default('');
            $table->float('promo_discount')->default(0);
            $table->boolean('is_amount_discount')->default(false);
            $table->boolean('allow_cancellation')->default(true);
            $table->boolean('per_seat_enabled')->default(false);
            $table->unsignedInteger('min_seats_limit');
            $table->unsignedInteger('max_seats_limit');
            $table->boolean('trial_enabled')->default(false);
            $table->unsignedInteger('trial_duration');
            $table->boolean('allow_query_overrides')->default(false);
            $table->boolean('allow_plan_changes')->default(false);
            $table->mediumText('plan_map');
            $table->unsignedInteger('refund_period')->nullable();
            $table->mediumText('webhook_configuration');
            $table->softDeletes('deleted_at', 6);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->index(['company_id', 'deleted_at']);
        });

        Schema::create('client_subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('subscription_id');
            $table->unsignedInteger('recurring_invoice_id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('trial_started')->nullable();
            $table->unsignedInteger('trial_ends')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->softDeletes('deleted_at', 6);
            $table->timestamps();
            $table->foreign('subscription_id')->references('id')->on('billing_subscriptions');
            $table->foreign('recurring_invoice_id')->references('id')->on('recurring_invoices');
            $table->foreign('client_id')->references('id')->on('clients');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->index(['company_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('billing_subscriptions');
        Schema::dropIfExists('client_subscriptions');
    }
};
