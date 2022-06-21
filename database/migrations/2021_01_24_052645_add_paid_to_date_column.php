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
            $table->decimal('paid_to_date', 20, 6)->default(0);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('paid_to_date', 20, 6)->default(0);
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->decimal('paid_to_date', 20, 6)->default(0);
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->decimal('paid_to_date', 20, 6)->default(0);
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
