<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSubscriptionFormat extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscriptions', function ($table) {
            $table->enum('format', ['JSON', 'UBL'])->default('JSON');
        });

        Schema::table('accounts', function ($table) {
            $table->boolean('ubl_email_attachment')->default(false);
        });

        Schema::create('proposal_categories', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->string('name');

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::create('proposal_snippets', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->unsignedInteger('proposal_category_id');
            $table->string('name');

            $table->mediumText('html');
            $table->mediumText('css');

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::create('proposal_templates', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->string('name');
            $table->text('tags');
            $table->mediumText('html');
            $table->mediumText('css');

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::create('proposals', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->unsignedInteger('quote_id')->index();
            $table->unsignedInteger('temlate_id')->index();
            $table->mediumText('html');
            $table->mediumText('css');

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('quote_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('temlate_id')->references('id')->on('proposal_templates')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscriptions', function ($table) {
            $table->dropColumn('format');
        });

        Schema::table('accounts', function ($table) {
            $table->dropColumn('ubl_email_attachment');
        });

        Schema::dropIfExists('proposals');
        Schema::dropIfExists('proposal_templates');
        Schema::dropIfExists('proposal_snippets');
        Schema::dropIfExists('proposal_categories');
    }
}
