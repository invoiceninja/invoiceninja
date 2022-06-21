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
        Schema::table('invoices', function (Blueprint $table) {
            $table->smallInteger('auto_bill_tries')->default(0);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('stop_on_unpaid_recurring')->default(0);
            $table->boolean('use_quote_terms_on_conversion')->default(0);
            $table->boolean('show_production_description_dropdown')->default(0);
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
