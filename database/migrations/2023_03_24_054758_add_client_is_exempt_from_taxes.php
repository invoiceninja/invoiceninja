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
        Schema::table('clients', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->boolean('is_tax_exempt')->default(false);
            $table->boolean('has_valid_vat_number')->default(false);
            $table->mediumText('tax_data')->nullable()->change();
        });

        Schema::table('companies', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->mediumText('tax_data')->nullable()->change();
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
