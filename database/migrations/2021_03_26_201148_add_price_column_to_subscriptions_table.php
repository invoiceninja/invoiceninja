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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->decimal('price', 20, 6)->default(0);
            $table->decimal('promo_price', 20, 6)->default(0);
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->unsignedInteger('subscription_id')->nullable();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('group_id')->nullable()->change();
            $table->text('product_ids')->nullable()->change();
            $table->text('recurring_product_ids')->nullable()->change();
            $table->text('auto_bill')->nullable()->change();
            $table->text('promo_code')->nullable()->change();
            $table->unsignedInteger('frequency_id')->nullable()->change();
            $table->text('plan_map')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
