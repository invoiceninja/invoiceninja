<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per App Store / Google Play subscription, on the account's own database.
     */
    public function up(): void
    {
        Schema::create('inapp_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_id')->index();
            $table->string('store');
            $table->string('app');
            $table->string('environment');
            $table->string('transaction_id');
            $table->text('purchase_token')->nullable();
            $table->string('product_id')->nullable();
            $table->string('account_token')->nullable();
            $table->string('status');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('notified_at', 3)->nullable();
            $table->timestamps();

            $table->unique(['store', 'transaction_id']);
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inapp_subscriptions');
    }
};
