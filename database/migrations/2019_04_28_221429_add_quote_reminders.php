<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddQuoteReminders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('account_email_settings', function ($table) {
            $table->string('email_subject_quote_reminder1');
            $table->string('email_subject_quote_reminder2');
            $table->string('email_subject_quote_reminder3');
            $table->string('email_subject_quote_reminder4');

            $table->text('email_template_quote_reminder1');
            $table->text('email_template_quote_reminder2');
            $table->text('email_template_quote_reminder3');
            $table->text('email_template_quote_reminder4');

            $table->boolean('enable_quote_reminder1')->default(false);
            $table->boolean('enable_quote_reminder2')->default(false);
            $table->boolean('enable_quote_reminder3')->default(false);
            $table->boolean('enable_quote_reminder4')->default(false);

            $table->smallInteger('num_days_quote_reminder1')->default(7);
            $table->smallInteger('num_days_quote_reminder2')->default(14);
            $table->smallInteger('num_days_quote_reminder3')->default(30);

            $table->smallInteger('direction_quote_reminder1')->default(1);
            $table->smallInteger('direction_quote_reminder2')->default(1);
            $table->smallInteger('direction_quote_reminder3')->default(1);

            $table->smallInteger('field_quote_reminder1')->default(1);
            $table->smallInteger('field_quote_reminder2')->default(1);
            $table->smallInteger('field_quote_reminder3')->default(1);

            $table->unsignedInteger('frequency_id_quote_reminder4')->nullable();

            $table->decimal('late_fee_quote1_amount', 13, 2)->nullable();
            $table->decimal('late_fee_quote1_percent', 13, 3)->nullable();
            $table->decimal('late_fee_quote2_amount', 13, 2)->nullable();
            $table->decimal('late_fee_quote2_percent', 13, 3)->nullable();
            $table->decimal('late_fee_quote3_amount', 13, 2)->nullable();
            $table->decimal('late_fee_quote3_percent', 13, 3)->nullable();
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
            $table->dropColumn('email_subject_quote_reminder1');
            $table->dropColumn('email_subject_quote_reminder2');
            $table->dropColumn('email_subject_quote_reminder3');
            $table->dropColumn('email_subject_quote_reminder4');

            $table->dropColumn('email_template_quote_reminder1');
            $table->dropColumn('email_template_quote_reminder2');
            $table->dropColumn('email_template_quote_reminder3');
            $table->dropColumn('email_template_quote_reminder4');

            $table->dropColumn('enable_quote_reminder1');
            $table->dropColumn('enable_quote_reminder2');
            $table->dropColumn('enable_quote_reminder3');
            $table->dropColumn('enable_quote_reminder4');

            $table->dropColumn('num_days_quote_reminder1');
            $table->dropColumn('num_days_quote_reminder2');
            $table->dropColumn('num_days_quote_reminder3');

            $table->dropColumn('direction_quote_reminder1');
            $table->dropColumn('direction_quote_reminder2');
            $table->dropColumn('direction_quote_reminder3');

            $table->dropColumn('field_quote_reminder1');
            $table->dropColumn('field_quote_reminder2');
            $table->dropColumn('field_quote_reminder3');

            $table->dropColumn('frequency_id_quote_reminder4');

            $table->dropColumn('late_fee_quote1_amount');
            $table->dropColumn('late_fee_quote1_percent');
            $table->dropColumn('late_fee_quote2_amount');
            $table->dropColumn('late_fee_quote2_percent');
            $table->dropColumn('late_fee_quote3_amount');
            $table->dropColumn('late_fee_quote3_percent');
        });
    }
}
