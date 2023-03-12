<?php

use App\Models\Company;
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
            $table->unsignedInteger("max_quantity")->nullable();
            $table->string("product_image", 191)->nullable();
        });

        Company::query()
                ->chunk(1000, function ($companies) {
                    foreach ($companies as $c) {
                        $settings = $c->settings;
                        $settings->font_size = 16;
                        $c->settings = $settings;
                        $c->save();
                    }
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
