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
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('client_id')->index();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('assigned_user_id')->nullable();
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('status_id');
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('vendor_id')->nullable();
            $table->unsignedInteger('recurring_id')->nullable();
            $table->unsignedInteger('design_id')->nullable();
            $table->unsignedInteger('invoice_id')->nullable();

            $table->string('number')->nullable();
            $table->float('discount')->default(0);
            $table->boolean('is_amount_discount')->default(0);

            $table->string('po_number')->nullable();
            $table->date('date')->nullable();
            $table->datetime('last_sent_date')->nullable();

            $table->date('due_date')->nullable();

            $table->boolean('is_deleted')->default(false);
            $table->mediumText('line_items')->nullable();
            $table->mediumText('backup')->nullable();
            $table->text('footer')->nullable();
            $table->text('public_notes')->nullable();
            $table->text('private_notes')->nullable();
            $table->text('terms')->nullable();

            $table->string('tax_name1')->nullable();

            $table->decimal('tax_rate1', 20, 6)->default(0);

            $table->string('tax_name2')->nullable();
            $table->decimal('tax_rate2', 20, 6)->default(0);

            $table->string('tax_name3')->nullable();
            $table->decimal('tax_rate3', 20, 6)->default(0);

            $table->decimal('total_taxes', 20, 6)->default(0);
            $table->boolean('uses_inclusive_taxes')->default(0);

            $table->date('reminder1_sent')->nullable();
            $table->date('reminder2_sent')->nullable();
            $table->date('reminder3_sent')->nullable();
            $table->date('reminder_last_sent')->nullable();

            $table->text('custom_value1')->nullable();
            $table->text('custom_value2')->nullable();
            $table->text('custom_value3')->nullable();
            $table->text('custom_value4')->nullable();

            $table->datetime('next_send_date')->nullable();

            $table->decimal('custom_surcharge1', 20, 6)->nullable();
            $table->decimal('custom_surcharge2', 20, 6)->nullable();
            $table->decimal('custom_surcharge3', 20, 6)->nullable();
            $table->decimal('custom_surcharge4', 20, 6)->nullable();

            $table->boolean('custom_surcharge_tax1')->default(false);
            $table->boolean('custom_surcharge_tax2')->default(false);
            $table->boolean('custom_surcharge_tax3')->default(false);
            $table->boolean('custom_surcharge_tax4')->default(false);

            $table->decimal('exchange_rate', 20, 6)->default(1);
            $table->decimal('balance', 20, 6);
            $table->decimal('partial', 20, 6)->nullable();
            $table->decimal('amount', 20, 6);
            $table->decimal('paid_to_date', 20, 6)->default(0);

            $table->datetime('partial_due_date')->nullable();

            $table->datetime('last_viewed')->nullable();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->index(['company_id', 'deleted_at']);

            $table->softDeletes();
            $table->timestamps();
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
