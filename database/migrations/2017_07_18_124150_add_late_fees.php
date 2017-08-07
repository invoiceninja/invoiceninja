<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLateFees extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('account_email_settings', function ($table) {
            $table->decimal('late_fee1_amount', 13, 2)->nullable();
            $table->decimal('late_fee1_percent', 13, 3)->nullable();
            $table->decimal('late_fee2_amount', 13, 2)->nullable();
            $table->decimal('late_fee2_percent', 13, 3)->nullable();
            $table->decimal('late_fee3_amount', 13, 2)->nullable();
            $table->decimal('late_fee3_percent', 13, 3)->nullable();
        });

        Schema::table('documents', function ($table) {
            $table->boolean('is_default')->default(false)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('account_email_settings', function ($table) {
            $table->dropColumn('late_fee1_amount');
            $table->dropColumn('late_fee1_percent');
            $table->dropColumn('late_fee2_amount');
            $table->dropColumn('late_fee2_percent');
            $table->dropColumn('late_fee3_amount');
            $table->dropColumn('late_fee3_percent');
        });

        Schema::table('documents', function ($table) {
            $table->dropColumn('is_default');
        });
    }
}
