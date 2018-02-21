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

        Schema::table('account_email_settings', function ($table) {
            $table->string('email_subject_proposal')->nullable();
            $table->text('email_template_proposal')->nullable();
        });

        Schema::table('documents', function ($table) {
            $table->boolean('is_proposal')->default(false);
            $table->string('document_key')->nullable()->unique();
        });

        Schema::table('invoices', function ($table) {
            $table->decimal('discount', 13, 2)->change();
        });

        Schema::table('invoice_items', function ($table) {
            $table->decimal('discount', 13, 2)->change();
        });

        Schema::create('proposal_categories', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('is_deleted')->default(false);

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
            $table->boolean('is_deleted')->default(false);

            $table->unsignedInteger('proposal_category_id')->nullable();
            $table->string('name');
            $table->string('icon');
            $table->text('private_notes');

            $table->mediumText('html');
            $table->mediumText('css');

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::create('proposal_templates', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('is_deleted')->default(false);
            $table->text('private_notes');

            $table->string('name');
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
            $table->boolean('is_deleted')->default(false);

            $table->unsignedInteger('invoice_id')->index();
            $table->unsignedInteger('proposal_template_id')->nullable()->index();
            $table->text('private_notes');
            $table->mediumText('html');
            $table->mediumText('css');

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('proposal_template_id')->references('id')->on('proposal_templates')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::create('proposal_invitations', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('proposal_id')->index();
            $table->string('invitation_key')->index()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->timestamp('sent_date')->nullable();
            $table->timestamp('viewed_date')->nullable();
            $table->timestamp('opened_date')->nullable();
            $table->string('message_id')->nullable();
            $table->text('email_error')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('proposal_id')->references('id')->on('proposals')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::create('lookup_proposal_invitations', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('lookup_account_id')->index();
            $table->string('invitation_key')->unique();
            $table->string('message_id')->nullable()->unique();

            $table->foreign('lookup_account_id')->references('id')->on('lookup_accounts')->onDelete('cascade');
        });

        DB::table('languages')->where('locale', '=', 'en_UK')->update(['locale' => 'en_GB']);
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

        Schema::table('account_email_settings', function ($table) {
            $table->dropColumn('email_subject_proposal');
            $table->dropColumn('email_template_proposal');
        });

        Schema::table('documents', function ($table) {
            $table->dropColumn('is_proposal');
            $table->dropColumn('document_key');
        });

        Schema::dropIfExists('lookup_proposal_invitations');
        Schema::dropIfExists('proposal_invitations');
        Schema::dropIfExists('proposals');
        Schema::dropIfExists('proposal_templates');
        Schema::dropIfExists('proposal_snippets');
        Schema::dropIfExists('proposal_categories');
    }
}
