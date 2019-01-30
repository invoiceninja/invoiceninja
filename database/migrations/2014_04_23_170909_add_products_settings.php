<?php

use Illuminate\Database\Migrations\Migration;

class AddProductsSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->boolean('fill_products')->default(true);
            $table->boolean('update_products')->default(true);
        });

        DB::table('accounts')->update(['fill_products' => true]);
        DB::table('accounts')->update(['update_products' => true]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('fill_products');
            $table->dropColumn('update_products');
        });
    }
}
