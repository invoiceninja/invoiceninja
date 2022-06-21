<?php

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
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('custom_surcharge1', 20, 6)->change();
            $table->decimal('custom_surcharge2', 20, 6)->change();
            $table->decimal('custom_surcharge3', 20, 6)->change();
            $table->decimal('custom_surcharge4', 20, 6)->change();
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->decimal('custom_surcharge1', 20, 6)->change();
            $table->decimal('custom_surcharge2', 20, 6)->change();
            $table->decimal('custom_surcharge3', 20, 6)->change();
            $table->decimal('custom_surcharge4', 20, 6)->change();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('custom_surcharge1', 20, 6)->change();
            $table->decimal('custom_surcharge2', 20, 6)->change();
            $table->decimal('custom_surcharge3', 20, 6)->change();
            $table->decimal('custom_surcharge4', 20, 6)->change();
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->decimal('custom_surcharge1', 20, 6)->change();
            $table->decimal('custom_surcharge2', 20, 6)->change();
            $table->decimal('custom_surcharge3', 20, 6)->change();
            $table->decimal('custom_surcharge4', 20, 6)->change();
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
