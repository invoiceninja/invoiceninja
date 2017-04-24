<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class SupportNewPricing extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('plan_price', 7, 2)->nullable();
            $table->decimal('pending_plan_price', 7, 2)->nullable();
            $table->smallInteger('num_users')->default(1);
            $table->smallInteger('pending_num_users')->default(1);
        });

        // lock in existing prices
        DB::table('companies')->where('plan', 'pro')->where('plan_term', 'month')->update(['plan_price' => 5]);
        DB::table('companies')->where('plan', 'pro')->where('plan_term', 'year')->update(['plan_price' => 50]);
        DB::table('companies')->where('plan', 'enterprise')->where('plan_term', 'month')->update(['plan_price' => 10]);
        DB::table('companies')->where('plan', 'enterprise')->where('plan_term', 'year')->update(['plan_price' => 100]);
        DB::table('companies')->where('plan', 'enterprise')->update(['num_users' => 5]);

        // https://github.com/invoiceninja/invoiceninja/pull/955
        Schema::table('activities', function (Blueprint $table) {
            $table->integer('task_id')->after('invitation_id')->nullable();
            if (Schema::hasColumn('activities', 'client_id')) {
                $table->unsignedInteger('client_id')->nullable()->change();
            }
        });

        // This may fail if the table was created as MyISAM
        try {
            Schema::table('activities', function (Blueprint $table) {
                $table->dropForeign('activities_client_id_foreign');
            });
        } catch (Exception $e) {
            // do nothing
        }

        // https://github.com/invoiceninja/invoiceninja/pull/950
        Schema::table('accounts', function (Blueprint $table) {
            $table->integer('start_of_week');
        });

        // https://github.com/invoiceninja/invoiceninja/pull/959
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue');
            $table->longText('payload');
            $table->tinyInteger('attempts')->unsigned();
            $table->tinyInteger('reserved')->unsigned();
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
            $table->index(['queue', 'reserved', 'reserved_at']);
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('plan_price');
            $table->dropColumn('pending_plan_price');
            $table->dropColumn('num_users');
            $table->dropColumn('pending_num_users');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('task_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('start_of_week');
        });

        Schema::drop('jobs');
        Schema::drop('failed_jobs');
    }
}
