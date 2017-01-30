<?php

use Illuminate\Database\Migrations\Migration;

class TrackLastSeenMessage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->unsignedInteger('news_feed_id')->nullable();
        });

        if (DB::table('payment_libraries')->count() > 0) {
            DB::table('gateways')->update(['recommended' => 0]);
            DB::table('gateways')->insert([
                'name' => 'moolah',
                'provider' => 'AuthorizeNet_AIM',
                'sort_order' => 1,
                'recommended' => 1,
                'site_url' => 'https://invoiceninja.mymoolah.com/',
                'payment_library_id' => 1,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function ($table) {
            $table->dropColumn('news_feed_id');
        });
    }
}
