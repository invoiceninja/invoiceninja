<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMoreCustomFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_custom_settings', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->timestamps();

            $table->string('custom_account_label1')->nullable();
            $table->string('custom_account_value1')->nullable();
            $table->string('custom_account_label2')->nullable();
            $table->string('custom_account_value2')->nullable();
            $table->string('custom_client_label1')->nullable();
            $table->string('custom_client_label2')->nullable();
            $table->string('custom_invoice_label1')->nullable();
            $table->string('custom_invoice_label2')->nullable();
            $table->string('custom_invoice_taxes1')->nullable();
            $table->string('custom_invoice_taxes2')->nullable();
            $table->string('custom_invoice_text_label1')->nullable();
            $table->string('custom_invoice_text_label2')->nullable();
            $table->string('custom_product_label1')->nullable();
            $table->string('custom_product_label2')->nullable();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });

        DB::statement('insert into account_custom_settings (account_id,
                custom_account_label1,
                custom_account_value1,
                custom_account_label2,
                custom_account_value2,
                custom_client_label1,
                custom_client_label2,
                custom_invoice_label1,
                custom_invoice_label2,
                custom_invoice_taxes1,
                custom_invoice_taxes2,
                custom_invoice_text_label1,
                custom_invoice_text_label2,
                custom_product_label1,
                custom_product_label2
            )
            select id,
                custom_label1,
                custom_value1,
                custom_label2,
                custom_value2,
                custom_client_label1,
                custom_client_label2,
                custom_invoice_label1,
                custom_invoice_label2,
                custom_invoice_taxes1,
                custom_invoice_taxes2,
                custom_invoice_text_label1,
                custom_invoice_text_label2,
                custom_invoice_item_label1,
                custom_invoice_item_label2
            from accounts;');

        Schema::table('accounts', function ($table) {
            $table->dropColumn('custom_label1');
            $table->dropColumn('custom_value1');
            $table->dropColumn('custom_label2');
            $table->dropColumn('custom_value2');
            $table->dropColumn('custom_client_label1');
            $table->dropColumn('custom_client_label2');
            $table->dropColumn('custom_invoice_label1');
            $table->dropColumn('custom_invoice_label2');
            $table->dropColumn('custom_invoice_taxes1');
            $table->dropColumn('custom_invoice_taxes2');
            $table->dropColumn('custom_invoice_text_label1');
            $table->dropColumn('custom_invoice_text_label2');
            $table->dropColumn('custom_invoice_item_label1');
            $table->dropColumn('custom_invoice_item_label2');
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
}
