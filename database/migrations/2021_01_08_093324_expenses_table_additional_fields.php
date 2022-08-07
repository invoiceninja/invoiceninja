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
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('tax_amount1', 20, 6)->default();
            $table->decimal('tax_amount2', 20, 6)->default();
            $table->decimal('tax_amount3', 20, 6)->default();
            $table->boolean('uses_inclusive_taxes')->default(0);
            $table->boolean('amount_is_pretax')->default(1);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('expense_inclusive_taxes')->default(0);
            $table->boolean('expense_amount_is_pretax')->default(1);
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
