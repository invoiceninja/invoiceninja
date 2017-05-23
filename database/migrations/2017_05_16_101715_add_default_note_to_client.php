<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDefaultNoteToClient extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clients', function ($table) {
            $table->text('public_notes')->nullable();
        });

        Schema::table('invoices', function ($table) {
            $table->text('private_notes')->nullable();
        });

        Schema::table('payments', function ($table) {
            $table->text('private_notes')->nullable();
        });

        Schema::table('accounts', function ($table) {
            $table->string('tax_name1')->nullable();
            $table->decimal('tax_rate1', 13, 3);
            $table->string('tax_name2')->nullable();
            $table->decimal('tax_rate2', 13, 3);
        });

        Schema::table('products', function ($table) {
            $table->string('tax_name1')->nullable();
            $table->decimal('tax_rate1', 13, 3);
            $table->string('tax_name2')->nullable();
            $table->decimal('tax_rate2', 13, 3);
        });

        DB::statement('update products
            left join tax_rates on tax_rates.id = products.default_tax_rate_id
            set products.tax_name1 = tax_rates.name, products.tax_rate1 = tax_rates.rate');

        DB::statement('update accounts
            left join tax_rates on tax_rates.id = accounts.default_tax_rate_id
            set accounts.tax_name1 = tax_rates.name, accounts.tax_rate1 = tax_rates.rate');

        if (Schema::hasColumn('accounts', 'default_tax_rate_id')) {
            Schema::table('accounts', function ($table) {
                $table->dropColumn('default_tax_rate_id');
            });
        }

        if (Schema::hasColumn('products', 'default_tax_rate_id')) {
            Schema::table('products', function ($table) {
                $table->dropColumn('default_tax_rate_id');
            });
        }

        if (Utils::isNinja()) {
            Schema::table('users', function ($table) {
                $table->unique(['oauth_user_id', 'oauth_provider_id']);
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
        Schema::table('clients', function ($table) {
            $table->dropColumn('public_notes');
        });

        Schema::table('invoices', function ($table) {
            $table->dropColumn('private_notes');
        });

        Schema::table('payments', function ($table) {
            $table->dropColumn('private_notes');
        });

        Schema::table('accounts', function ($table) {
            $table->dropColumn('tax_name1');
            $table->dropColumn('tax_rate1');
            $table->dropColumn('tax_name2');
            $table->dropColumn('tax_rate2');
        });

        Schema::table('products', function ($table) {
            $table->dropColumn('tax_name1');
            $table->dropColumn('tax_rate1');
            $table->dropColumn('tax_name2');
            $table->dropColumn('tax_rate2');
        });
    }
}
