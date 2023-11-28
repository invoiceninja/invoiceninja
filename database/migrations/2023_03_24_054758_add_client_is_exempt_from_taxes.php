<?php

use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\Company;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Models\Product;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    use MakesHash;
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clients', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->boolean('is_tax_exempt')->default(false);
            $table->boolean('has_valid_vat_number')->default(false);
            $table->mediumText('tax_data')->nullable()->change();
        });

        Schema::table('companies', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->mediumText('tax_data')->nullable()->change();
            $table->dropColumn('tax_all_products');
        });

        Schema::table('projects', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->unsignedInteger('current_hours')->nullable();
        });

        Schema::table('bank_transactions', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->text('expense_id')->default('')->change();
        });

        BankTransaction::withTrashed()
                       ->whereNotNull('expense_id')
                       ->cursor()
                       ->each(function ($transaction) {
                           $transaction->expense_id = $this->encodePrimaryKey($transaction->expense_id);
                           $transaction->save();
                       });

        Company::query()
               ->cursor()
               ->each(function ($company) {
                   $company->tax_data = null;
                   $company->save();
               });
        
        Client::query()
               ->cursor()
               ->each(function ($client) {
                   $client->tax_data = null;
                   $client->save();
               });

        Product::query()
               ->cursor()
               ->each(function ($product) {
                   $product->tax_id = 1;
                   $product->save();
               });


        //payment types from 34

        if(Ninja::isSelfHost()) {

            $pt = PaymentType::find(34);

            if(!$pt) {
                $type = new PaymentType();
                $type->id = 34;
                $type->name = 'Mollie Bank Transfer';
                $type->gateway_type_id = GatewayType::BANK_TRANSFER;
                $type->save();

            }

            $pt = PaymentType::find(35);

            if(!$pt) {

                $type = new PaymentType();
                $type->id = 35;
                $type->name = 'KBC/CBC';
                $type->gateway_type_id = GatewayType::KBC;
                $type->save();

            }

            $pt = PaymentType::find(36);

            if(!$pt) {
                $type = new PaymentType();
                $type->id = 36;
                $type->name = 'Bancontact';
                $type->gateway_type_id = GatewayType::BANCONTACT;
                $type->save();

            }

            $pt = PaymentType::find(37);

            if(!$pt) {
                    
                $type = new PaymentType();
                $type->id = 37;
                $type->name = 'iDEAL';
                $type->gateway_type_id = GatewayType::IDEAL;
                $type->save();

            }

            $pt = PaymentType::find(38);

            if(!$pt) {
                $type = new PaymentType();
                $type->id = 38;
                $type->name = 'Hosted Page';
                $type->gateway_type_id = GatewayType::HOSTED_PAGE;
                $type->save();
            }

            $pt = PaymentType::find(39);

            if(!$pt) {
                $type = new PaymentType();
                $type->id = 39;
                $type->name = 'GiroPay';
                $type->gateway_type_id = GatewayType::GIROPAY;
                $type->save();
            }

            $pt = PaymentType::find(40);

            if(!$pt) {
                $type = new PaymentType();
                $type->id = 40;
                $type->name = 'Przelewy24';
                $type->gateway_type_id = GatewayType::PRZELEWY24;
                $type->save();
            }

            $pt = PaymentType::find(41);

            if(!$pt) {
                $type = new PaymentType();
                $type->id = 41;
                $type->name = 'EPS';
                $type->gateway_type_id = GatewayType::EPS;
                $type->save();
            }

            $pt = PaymentType::find(42);

            if(!$pt) {
                $type = new PaymentType();
                $type->id = 42;
                $type->name = 'Direct Debit';
                $type->gateway_type_id = GatewayType::DIRECT_DEBIT;

                $type->save();
            }

            $pt = PaymentType::find(43);

            if(!$pt) {
                $type = new PaymentType();
                $type->id = 43;
                $type->name = 'BECS';
                $type->gateway_type_id = GatewayType::BECS;
                $type->save();
            }

            $pt = PaymentType::find(44);

            if(!$pt) {
                $type = new PaymentType();
                $type->id = 44;
                $type->name = 'ACSS';
                $type->gateway_type_id = GatewayType::ACSS;

                $type->save();
            }

            $pt = PaymentType::find(45);

            if(!$pt) {
                $type = new PaymentType();
                $type->id = PaymentType::INSTANT_BANK_PAY;
                $type->name = 'Instant Bank Pay';
                $type->gateway_type_id = GatewayType::INSTANT_BANK_PAY;

                $type->save();
            }

            $pt = PaymentType::find(47);

            if (!$pt) {
                $type = new PaymentType();
                $type->id = 47;
                $type->name = 'Klarna';
                $type->gateway_type_id = GatewayType::KLARNA;
                $type->save();
            }


            $pt = PaymentType::find(48);

            if (!$pt) {
                $type = new PaymentType();
                $type->id = 48;
                $type->name = 'Interac E-Transfer';
                $type->save();
            }

            $gt = GatewayType::find(23);

            if (!$gt) {
                $type = new GatewayType();
                $type->id = 23;
                $type->alias = 'klarna';
                $type->name = 'Klarna';
                $type->save();
            }

        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
    }
};
