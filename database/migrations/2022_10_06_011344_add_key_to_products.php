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
        Schema::table('products', function (Blueprint $table) {
            $table->index(['product_key', 'company_id']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->index(['subdomain', 'portal_mode']);
            $table->index(['portal_domain', 'portal_mode']);
            $table->index('company_key');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->index('key');
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
