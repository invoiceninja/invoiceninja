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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('client_id')->nullable()->index();
            $table->unsignedInteger('vendor_id')->nullable()->index();
            $table->unsignedInteger('company_id')->index();

            $table->string('name')->nullable();
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->boolean('is_shipping_location')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->unsignedInteger('country_id')->nullable();

            $table->text('custom_value1')->nullable();
            $table->text('custom_value2')->nullable();
            $table->text('custom_value3')->nullable();
            $table->text('custom_value4')->nullable();

            $table->mediumText('tax_data')->nullable();

            $table->softDeletes('deleted_at', 6);
            $table->timestamps(6);
        
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        Schema::table('invoices', function (Blueprint $table){

            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();
        });

    }
    
    public function down(): void
    {
        //
    }
};
