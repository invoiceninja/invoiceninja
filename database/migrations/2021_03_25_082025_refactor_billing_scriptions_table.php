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
        Schema::rename('billing_subscriptions', 'subscriptions');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->text('product_id')->change();
            $table->text('recurring_product_ids');
            $table->string('name');
            $table->unique(['company_id', 'name']);
            $table->unsignedInteger('group_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('product_id', 'product_ids');
            $table->dropColumn('is_recurring');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
