<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Account;

class AddMoreCustomFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->mediumText('custom_fields')->nullable();
        });

        $accounts = Account::where('custom_label1', '!=', '')
            ->orWhere('custom_label2', '!=', '')
            ->orWhere('custom_client_label1', '!=', '')
            ->orWhere('custom_client_label2', '!=', '')
            ->orWhere('custom_invoice_label1', '!=', '')
            ->orWhere('custom_invoice_label2', '!=', '')
            ->orWhere('custom_invoice_text_label1', '!=', '')
            ->orWhere('custom_invoice_text_label2', '!=', '')
            ->orWhere('custom_invoice_item_label1', '!=', '')
            ->orWhere('custom_invoice_item_label2', '!=', '')
            ->orderBy('id')
            ->get();

        $fields = [
            'account1' => 'custom_label1',
            'account2' => 'custom_label2',
            'client1' => 'custom_client_label1',
            'client2' => 'custom_client_label2',
            'invoice1' => 'custom_invoice_label1',
            'invoice2' => 'custom_invoice_label2',
            'invoice_text1' => 'custom_invoice_text_label1',
            'invoice_text2' => 'custom_invoice_text_label2',
            'product1' => 'custom_invoice_item_label1',
            'product2' => 'custom_invoice_item_label2',
        ];

        foreach ($accounts as $accounts) {
            $config = [];

            foreach ($fields as $key => $field) {
                if ($account->$field) {
                    $config[$key] = $account->$field;
                }
            }

            if (count($config)) {
                $account->custom_fields = $config;
                $account->save();
            }
        }

        Schema::table('accounts', function ($table) {
            $table->dropColumn('custom_label1');
            $table->dropColumn('custom_label2');
            $table->dropColumn('custom_client_label1');
            $table->dropColumn('custom_client_label2');
            $table->dropColumn('custom_invoice_label1');
            $table->dropColumn('custom_invoice_label2');
            $table->dropColumn('custom_invoice_text_label1');
            $table->dropColumn('custom_invoice_text_label2');
            $table->dropColumn('custom_invoice_item_label1');
            $table->dropColumn('custom_invoice_item_label2');
        });

        Schema::table('tasks', function ($table) {
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
        });

        Schema::table('projects', function ($table) {
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
        });

        Schema::table('expenses', function ($table) {
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
        });

        Schema::table('vendors', function ($table) {
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
        });
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
}
