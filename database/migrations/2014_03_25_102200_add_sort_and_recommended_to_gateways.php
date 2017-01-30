<?php

use Illuminate\Database\Migrations\Migration;

class AddSortAndRecommendedToGateways extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gateways', function ($table) {
            $table->unsignedInteger('sort_order')->default(10000);
            $table->boolean('recommended')->default(0);
            $table->string('site_url', 200)->nullable();
        });
    }
    
    public function down()
    {
        if (Schema::hasColumn('gateways', 'sort_order')) {
            Schema::table('gateways', function ($table) {
                $table->dropColumn('sort_order');
            });
        }
        
        if (Schema::hasColumn('gateways', 'recommended')) {
            Schema::table('gateways', function ($table) {
                $table->dropColumn('recommended');
            });
        }
        
        if (Schema::hasColumn('gateways', 'site_url')) {
            Schema::table('gateways', function ($table) {
                $table->dropColumn('site_url');
            });
        }
    }
}
