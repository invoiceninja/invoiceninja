<?php

use Illuminate\Database\Migrations\Migration;

class AddProPlanDiscount extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function ($table) {
            $table->float('discount');
            $table->date('discount_expires')->nullable();
            $table->date('promo_expires')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function ($table) {
            $table->dropColumn('discount');
            $table->dropColumn('discount_expires');
            $table->dropColumn('promo_expires');
        });
    }
}
