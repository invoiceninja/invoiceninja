<?php

use App\Models\Currency;
use App\Utils\Traits\AppSetup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    use AppSetup;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('invoice_task_lock')->default(false);
            $table->boolean('use_vendor_currency')->default(false);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedInteger('currency_id')->nullable();
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->bigInteger('bank_transaction_rule_id')->nullable();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('registration_required')->default(false);
            $table->boolean('use_inventory_management')->default(false);
            $table->text('optional_product_ids')->nullable();
            $table->text('optional_recurring_product_ids')->nullable();
        });

        $currencies = [

            ['id' => 113, 'name' => 'Swazi lilangeni', 'code' => 'SZL', 'symbol' => 'E', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],

        ];

        foreach ($currencies as $currency) {
            $record = Currency::query()->whereCode($currency['code'])->first();
            if ($record) {
                $record->name = $currency['name'];
                $record->symbol = $currency['symbol'];
                $record->precision = $currency['precision'];
                $record->thousand_separator = $currency['thousand_separator'];
                $record->decimal_separator = $currency['decimal_separator'];
                if (isset($currency['swap_currency_symbol'])) {
                    $record->swap_currency_symbol = $currency['swap_currency_symbol'];
                }
                $record->save();
            } else {
                Currency::create($currency);
            }
        }

        \Illuminate\Support\Facades\Artisan::call('ninja:design-update');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
