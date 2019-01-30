<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MoveEmailRelatedColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('account_email_settings', function ($table) {
            $table->boolean('enable_reminder1')->default(false);
            $table->boolean('enable_reminder2')->default(false);
            $table->boolean('enable_reminder3')->default(false);
            $table->boolean('enable_reminder4')->default(false);

            $table->smallInteger('num_days_reminder1')->default(7);
            $table->smallInteger('num_days_reminder2')->default(14);
            $table->smallInteger('num_days_reminder3')->default(30);

            $table->smallInteger('direction_reminder1')->default(1);
            $table->smallInteger('direction_reminder2')->default(1);
            $table->smallInteger('direction_reminder3')->default(1);

            $table->smallInteger('field_reminder1')->default(1);
            $table->smallInteger('field_reminder2')->default(1);
            $table->smallInteger('field_reminder3')->default(1);

            $table->smallInteger('email_design_id')->default(1);
            $table->boolean('enable_email_markup')->default(false);
            $table->text('email_footer')->collation('utf8_unicode_ci');
        });

        DB::statement('UPDATE `account_email_settings` AS `aes` 
                           INNER JOIN (SELECT `id`, 
                                          `enable_reminder1`, 
                                          `enable_reminder2`, 
                                          `enable_reminder3`, 
                                          `enable_reminder4`, 
                                          `num_days_reminder1`, 
                                          `num_days_reminder2`, 
                                          `num_days_reminder3`, 
                                          `direction_reminder1`, 
                                          `direction_reminder2`, 
                                          `direction_reminder3`, 
                                          `field_reminder1`, 
                                          `field_reminder2`, 
                                          `field_reminder3`, 
                                          `email_design_id`, 
                                          `enable_email_markup`, 
                                          `email_footer` 
                                       FROM   `accounts`) AS `a` 
                           ON `aes`.`account_id` = `a`.`id` 
                        SET `aes`.`enable_reminder1` = `a`.`enable_reminder1`, 
                            `aes`.`enable_reminder2` = `a`.`enable_reminder2`, 
                            `aes`.`enable_reminder3` = `a`.`enable_reminder3`, 
                            `aes`.`enable_reminder4` = `a`.`enable_reminder4`, 
                            `aes`.`num_days_reminder1` = `a`.`num_days_reminder1`, 
                            `aes`.`num_days_reminder2` = `a`.`num_days_reminder2`, 
                            `aes`.`num_days_reminder3` = `a`.`num_days_reminder3`, 
                            `aes`.`direction_reminder1` = `a`.`direction_reminder1`, 
                            `aes`.`direction_reminder2` = `a`.`direction_reminder2`, 
                            `aes`.`direction_reminder3` = `a`.`direction_reminder3`, 
                            `aes`.`field_reminder1` = `a`.`field_reminder1`, 
                            `aes`.`field_reminder2` = `a`.`field_reminder2`, 
                            `aes`.`field_reminder3` = `a`.`field_reminder3`, 
                            `aes`.`email_design_id` = `a`.`email_design_id`, 
                            `aes`.`enable_email_markup` = `a`.`enable_email_markup`, 
                            `aes`.`email_footer` = `a`.`email_footer`
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('account_email_settings', function ($table) {
            $table->dropColumn('enable_reminder1');
            $table->dropColumn('enable_reminder2');
            $table->dropColumn('enable_reminder3');
            $table->dropColumn('enable_reminder4');

            $table->dropColumn('num_days_reminder1');
            $table->dropColumn('num_days_reminder2');
            $table->dropColumn('num_days_reminder3');

            $table->dropColumn('direction_reminder1');
            $table->dropColumn('direction_reminder2');
            $table->dropColumn('direction_reminder3');

            $table->dropColumn('field_reminder1');
            $table->dropColumn('field_reminder2');
            $table->dropColumn('field_reminder3');

            $table->dropColumn('email_design_id');
            $table->dropColumn('enable_email_markup');
            $table->dropColumn('email_footer');
        });
    }
}
