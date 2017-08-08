<?php

use Illuminate\Database\Migrations\Migration;

class AddReminderEmails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->string('email_subject_invoice')->nullable();
            $table->string('email_subject_quote')->nullable();
            $table->string('email_subject_payment')->nullable();

            $table->string('email_subject_reminder1')->nullable();
            $table->string('email_subject_reminder2')->nullable();
            $table->string('email_subject_reminder3')->nullable();

            $table->text('email_template_reminder1')->nullable();
            $table->text('email_template_reminder2')->nullable();
            $table->text('email_template_reminder3')->nullable();

            $table->boolean('enable_reminder1')->default(false);
            $table->boolean('enable_reminder2')->default(false);
            $table->boolean('enable_reminder3')->default(false);

            $table->smallInteger('num_days_reminder1')->default(7);
            $table->smallInteger('num_days_reminder2')->default(14);
            $table->smallInteger('num_days_reminder3')->default(30);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            if (Schema::hasColumn('accounts', 'email_subject_invoice')) {
                $table->dropColumn('email_subject_invoice');
                $table->dropColumn('email_subject_quote');
                $table->dropColumn('email_subject_payment');

                $table->dropColumn('email_subject_reminder1');
                $table->dropColumn('email_subject_reminder2');
                $table->dropColumn('email_subject_reminder3');

                $table->dropColumn('email_template_reminder1');
                $table->dropColumn('email_template_reminder2');
                $table->dropColumn('email_template_reminder3');
            }

            $table->dropColumn('enable_reminder1');
            $table->dropColumn('enable_reminder2');
            $table->dropColumn('enable_reminder3');

            $table->dropColumn('num_days_reminder1');
            $table->dropColumn('num_days_reminder2');
            $table->dropColumn('num_days_reminder3');
        });
    }
}
