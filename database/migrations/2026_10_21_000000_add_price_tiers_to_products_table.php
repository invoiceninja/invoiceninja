<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('price_tiers')->nullable();
            $table->string('price_tiers_mode', 32)->default('all_units');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('enable_price_tiers')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_tiers', 'price_tiers_mode']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('enable_price_tiers');
        });
    }
};
