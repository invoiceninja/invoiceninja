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
        Schema::create('product_types', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('assigned_user_id')->nullable();
            $table->string('name');
            $table->boolean('is_custom')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('unit_of_measure')->nullable();
            $table->boolean('serial_number_required')->nullable();
            $table->string('allocation_type')->nullable();
            $table->string('allocation_aggregation_interval')->nullable();
            $table->unsignedInteger('allocation_max_quantity')->nullable();
            // TODO: counter related fields for serial numbers

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
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('product_allocation_status', function (Blueprint $table) {
            $table->increments('id');
            $table->string('product_type')->index();
            $table->string('name');
            $table->unsignedInteger('priority');
            // TODO: constraints to use next status like: auto_check (for check if next status can be set automaticly, when product_allocation/invoice gets updated), serial_number_required, client_required, invoice_required, payment_required
            // TODO: actions f.ex. subject & text for email to client
            // TODO: visibility in client portal => asset management for clients

            $table->unique(['product_type', 'name']);
            $table->foreign('product_type')->references('id')->on('product_type')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type')->index();
            $table->foreign('product_type')->references('id')->on('product_type')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('product_allocations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('assigned_user_id')->nullable();
            $table->unsignedInteger('product_id')->index();
            $table->unsignedInteger('product_type')->nullable()->index();
            $table->string('serial_number')->nullable();
            $table->unsignedInteger('status')->nullable()->index();

            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedInteger('recurring_id')->nullable();
            $table->unsignedInteger('subscription_id')->nullable();

            $table->unsignedInteger('quantity')->default(1);
            $table->dateTime('from');
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
            $table->index(['invoice_id', 'company_id']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('product_type')->references('id')->on('product_typess')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign(['product_type', 'status'])->references(['product_type', 'id'])->on('product_types')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade')->onUpdate('cascade');
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
