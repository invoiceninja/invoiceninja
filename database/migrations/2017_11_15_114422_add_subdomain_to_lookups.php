<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Subscription;

class AddSubdomainToLookups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lookup_accounts', function ($table) {
            $table->string('subdomain')->nullable()->unique();
        });

        Schema::table('payments', function ($table) {
            $table->decimal('exchange_rate', 13, 4)->default(1);
            $table->unsignedInteger('exchange_currency_id')->nullable(false);
        });

        Schema::table('expenses', function ($table) {
            $table->decimal('exchange_rate', 13, 4)->default(1)->change();
        });

        Schema::table('clients', function ($table) {
            $table->string('shipping_address1')->nullable();
            $table->string('shipping_address2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->unsignedInteger('shipping_country_id')->nullable();
            $table->boolean('show_tasks_in_portal')->default(0);
            $table->boolean('send_reminders')->default(1);
        });

        Schema::table('clients', function ($table) {
            $table->foreign('shipping_country_id')->references('id')->on('countries');
        });

        Schema::table('account_gateways', function ($table) {
            $table->boolean('show_shipping_address')->default(false)->nullable();
        });

        Schema::dropIfExists('scheduled_reports');
        Schema::create('scheduled_reports', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->text('config');
            $table->enum('frequency', ['daily', 'weekly', 'biweekly', 'monthly']);
            $table->date('send_date');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');

            $table->unsignedInteger('public_id')->nullable();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::table('subscriptions', function ($table) {
            $table->unsignedInteger('public_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
        });

        $accountPublicIds = [];
        foreach (Subscription::withTrashed()
            ->with('account.users')
            ->orderBy('id')
            ->get() as $subscription) {
            $accountId = $subscription->account_id;
            if (isset($accountPublicIds[$accountId])) {
                $publicId = $accountPublicIds[$accountId];
                $accountPublicIds[$accountId]++;
            } else {
                $publicId = 1;
                $accountPublicIds[$accountId] = 2;
            }
            $subscription->public_id = $publicId;
            $subscription->user_id = $subscription->account->users[0]->id;
            $subscription->save();
        }

        Schema::table('subscriptions', function ($table) {
            $table->unique(['account_id', 'public_id']);
        });

        Schema::table('accounts', function ($table) {
            $table->boolean('inclusive_taxes')->default(0);
        });

        if (Utils::isNinja()) {
            Schema::table('activities', function ($table) {
                $table->index('user_id');
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
        Schema::table('lookup_accounts', function ($table) {
            $table->dropColumn('subdomain');
        });

        Schema::table('payments', function ($table) {
            $table->dropColumn('exchange_rate');
            $table->dropColumn('exchange_currency_id');
        });

        Schema::table('clients', function ($table) {
            $table->dropForeign('clients_shipping_country_id_foreign');
            $table->dropColumn('shipping_address1');
            $table->dropColumn('shipping_address2');
            $table->dropColumn('shipping_city');
            $table->dropColumn('shipping_state');
            $table->dropColumn('shipping_postal_code');
            $table->dropColumn('shipping_country_id');
            $table->dropColumn('show_tasks_in_portal');
            $table->dropColumn('send_reminders');
        });

        Schema::table('account_gateways', function ($table) {
            $table->dropColumn('show_shipping_address');
        });

        Schema::dropIfExists('scheduled_reports');

        Schema::table('subscriptions', function ($table) {
            $table->dropUnique('subscriptions_account_id_public_id_unique');
        });

        Schema::table('subscriptions', function ($table) {
            $table->dropColumn('public_id');
            $table->dropColumn('user_id');
        });

        Schema::table('accounts', function ($table) {
            $table->dropColumn('inclusive_taxes');
        });

    }
}
