<?php

use App\Models\Account;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Repositories\BankTransactionRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        // FIX: used column transaction_id was int and resulted in wrong value in field
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->text('nordigen_transaction_id')->nullable();
        });

        // remove invalid transactions
        BankIntegration::query()->where('integration_type', BankIntegration::INTEGRATION_TYPE_NORDIGEN)->cursor()->each(function ($bank_integration) {
            $bank_integration->from_date = now()->subDays(90);
            $bank_integration->save();

            BankTransaction::query()->where('bank_integration_id', $bank_integration->id)->cursor()->each(function ($bank_transaction) {
                if ($bank_transaction->invoiceIds != '' || $bank_transaction->expense_id != '')
                    return;

                $btrepo = new BankTransactionRepository();
                $btrepo->delete($bank_transaction);
            });
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
};
