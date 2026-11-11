<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_contacts', function (Blueprint $table) {
            $table->boolean('can_sign')->default(false);
        });

        Schema::table('vendor_contacts', function (Blueprint $table) {
            $table->boolean('can_sign')->default(false);
        });

        Schema::table('invoice_invitations', function (Blueprint $table) {
            $table->boolean('can_sign')->default(false);
        });

        Schema::table('quote_invitations', function (Blueprint $table) {
            $table->boolean('can_sign')->default(false);
        });

        Schema::table('credit_invitations', function (Blueprint $table) {
            $table->boolean('can_sign')->default(false);
        });

        Schema::table('purchase_order_invitations', function (Blueprint $table) {
            $table->boolean('can_sign')->default(false);
        });

        Schema::table('recurring_invoice_invitations', function (Blueprint $table) {
            $table->boolean('can_sign')->default(false);
        });

        Illuminate\Support\Facades\Schema::table('credits', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->text('sync')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
