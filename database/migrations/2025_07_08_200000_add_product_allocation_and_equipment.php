<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::table('products', function (Blueprint $table) {
            $table->string('unit_of_measure')->nullable();
            $table->boolean('is_equipment');
            $table->string('allocation_type')->nullable();
            $table->boolean('allocation_equipment_required')->nullable();
            $table->string('allocation_aggregation_interval')->nullable();
            $table->unsignedInteger('allocation_max_quantity')->nullable();
        });

        Schema::create('product_equipments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('assigned_user_id')->nullable();
            $table->unsignedInteger('product_id')->index();
            $table->string('serial_number')->nullable();

            $table->unsignedInteger('client_id')->nullable();

            $table->text('public_notes')->nullable();
            $table->text('private_notes')->nullable();
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
            $table->string('custom_value3')->nullable();
            $table->string('custom_value4')->nullable();
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->boolean('is_deleted')->default(false);

            $table->index(['company_id', 'deleted_at']);
            $table->index(['user_id', 'company_id']);
            $table->index(['product_id', 'company_id']);
            $table->index(['client_id', 'company_id']);
            $table->index(['project_id', 'company_id']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('product_allocations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('assigned_user_id')->nullable();
            $table->unsignedInteger('product_id')->index();

            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('equipment_id')->nullable();
            $table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedInteger('recurring_id')->nullable();
            $table->unsignedInteger('subscription_id')->nullable();

            $table->unsignedInteger('quantity');
            $table->dateTime('from')->nullable();
            $table->dateTime('until')->nullable();
            $table->boolean('should_be_invoiced')->default(false);
            $table->string('invoice_aggregation_key')->nullable();

            $table->text('public_notes')->nullable();
            $table->text('private_notes')->nullable();
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
            $table->string('custom_value3')->nullable();
            $table->string('custom_value4')->nullable();
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->boolean('is_deleted')->default(false);

            $table->index(['company_id', 'deleted_at']);
            $table->index(['user_id', 'company_id']);
            $table->index(['product_id', 'company_id']);
            $table->index(['client_id', 'company_id']);
            $table->index(['project_id', 'company_id']);
            $table->index(['equipment_id', 'company_id']);
            $table->index(['invoice_id', 'company_id']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('equipment_id')->references('id')->on('product_equipments')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('recurring_id')->references('id')->on('recurring_invoices')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
