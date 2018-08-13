<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTicketsSchema extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {


        Schema::create('ticket_categories', function ($table) {
            $table->increments('id');
            $table->text('name');
            $table->string('key', 255);
            $table->timestamps();
            $table->softDeletes();
        });


        Schema::create('ticket_statuses', function ($table) {
            $table->increments('id');
            $table->string('name', 255);
            $table->string('trigger_column', 255);
            $table->text('trigger_threshold');
            $table->string('color', 255);
            $table->text('description');
            $table->unsignedInteger('category_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('public_id');
            $table->unsignedInteger('sort_order');
            $table->boolean('is_deleted')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('ticket_categories');

        });

        Schema::create('tickets', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('agent_id');
            $table->unsignedInteger('public_id');
            $table->unsignedInteger('priority_id')->default(1);
            $table->boolean('is_deleted')->default(0);
            $table->boolean('is_internal')->default(0);
            $table->unsignedInteger('status_id');
            $table->unsignedInteger('category_id');
            $table->unsignedInteger('merged_parent_ticket_id')->nullable();
            $table->unsignedInteger('parent_ticket_id')->nullable();
            $table->unsignedInteger('ticket_number');
            $table->text('subject');
            $table->text('description');
            $table->longtext('tags');
            $table->longtext('private_notes');
            $table->longtext('ccs');
            $table->string('ip_address', 255);
            $table->string('contact_key', 255);
            $table->dateTime('due_date');
            $table->dateTime('closed');
            $table->dateTime('reopened');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('ticket_statuses');


        });



        Schema::create('ticket_relations', function ($table) {
            $table->increments('id');
            $table->string('entity', 255);
            $table->unsignedInteger('entity_id');
            $table->unsignedInteger('ticket_id');

            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');

        });

        Schema::create('ticket_templates', function ($table) {
            $table->increments('id');
            $table->string('name', 255);
            $table->text('description');
            $table->unsignedInteger('account_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('public_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('ticket_comments', function ($table) {
            $table->increments('id');
            $table->text('description');
            $table->string('contact_key', 255);
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('ticket_id');
            $table->unsignedInteger('public_id');
            $table->boolean('is_deleted')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
        });

        Schema::table('documents', function ($table) {
            $table->unsignedInteger('ticket_id')->nullable();
        });


        Schema::table('activities', function ($table) {
            $table->unsignedInteger('ticket_id')->nullable();

            $table->index(['ticket_id', 'account_id']);
        });


        Schema::create('ticket_invitations', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('ticket_id')->index();
            $table->string('invitation_key')->index()->unique();
            $table->string('ticket_hash')->index()->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->timestamp('sent_date')->nullable();
            $table->timestamp('viewed_date')->nullable();
            $table->timestamp('opened_date')->nullable();
            $table->string('message_id')->nullable();
            $table->text('email_error')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::create('lookup_ticket_invitations', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('lookup_account_id')->index();
            $table->string('invitation_key')->unique();
            $table->string('ticket_hash')->unique();
            $table->string('message_id')->nullable()->unique();

            $table->foreign('lookup_account_id')->references('id')->on('lookup_accounts')->onDelete('cascade');
        });

        Schema::create('account_ticket_settings', function ($table){
            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->timestamps();

            $table->string('support_email_local_part')->unique()->nullable(); //allows a user to specify a custom *@support.invoiceninja.com domain
            $table->string('from_name', 255); //define the from email addresses name

            $table->boolean('client_upload')->default(true);
            $table->unsignedInteger('max_file_size')->default(0);
            $table->string('mime_types');

            $table->unsignedInteger('ticket_master_id');

            $table->unsignedInteger('new_ticket_template_id');
            $table->unsignedInteger('close_ticket_template_id');
            $table->unsignedInteger('update_ticket_template_id');

            $table->unsignedInteger('default_priority');
            $table->string('ticket_number_prefix');
            $table->unsignedInteger('ticket_number_start');

            $table->unsignedInteger('alert_new_comment')->default(0);
            $table->longtext('alert_new_comment_email');
            $table->unsignedInteger('alert_ticket_assign_agent')->default(0);
            $table->longtext('alert_ticket_assign_email');
            $table->unsignedInteger('alert_ticket_overdue_agent')->default(0);
            $table->longtext('alert_ticket_overdue_email');

            $table->boolean('show_agent_details')->default(true);
            $table->string('postmark_api_token');

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('ticket_master_id')->references('id')->on('users')->onDelete('cascade');
        });


        Schema::table('lookup_accounts', function ($table) {
            $table->string('support_email_local_part')->unique()->nullable();
        });


        $accounts = \App\Models\Account::all();

            foreach ($accounts as $account){

                if(!$account->account_ticket_settings) {

                    $user = $account->users()->whereIsAdmin(1)->first();
                    
                    $accountTicketSettings = new \App\Models\AccountTicketSettings();
                    $accountTicketSettings->ticket_master_id = $user->id;

                    $account->account_ticket_settings()->save($accountTicketSettings);
                }

            }



        Schema::table('users', function ($table) {
            $table->string('avatar', 255);
            $table->unsignedInteger('avatar_width');
            $table->unsignedInteger('avatar_height');
            $table->unsignedInteger('avatar_size');
            $table->text('signature');
        });



        if(!Utils::isNinja()) {

            Schema::table('activities', function ($table) {
                $table->index(['contact_id', 'account_id']);
                $table->index(['payment_id', 'account_id']);
                $table->index(['invitation_id', 'account_id']);
                $table->index(['user_id', 'account_id']);
                $table->index(['invoice_id', 'account_id']);
                $table->index(['client_id', 'account_id']);
            });


            Schema::table('invitations', function ($table) {
                $table->index(['deleted_at', 'invoice_id']);
            });
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ticket_statuses');
        Schema::dropIfExists('ticket_categories');
        Schema::dropIfExists('ticket_templates');
        Schema::dropIfExists('ticket_relations');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('account_ticket_settings');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('lookup_ticket_invitations');
        Schema::dropIfExists('ticket_invitations');
    }
}
