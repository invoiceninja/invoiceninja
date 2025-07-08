<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if(\App\Utils\Ninja::isSelfHost()) {
        
        
        
                \App\Models\Company::query()
                ->cursor()
                ->each(function ($c) {


                    $settings = $c->settings;
                    $pdf_variables = $settings->pdf_variables;

                    $ss =  [
                        '$payment.number',
                        '$payment.date',
                        '$payment.amount',
                        '$payment.payment_balance',
                    ];

                    $pdf_variables->statement_unapplied_columns = $ss;


                    $ss =  [
                        '$invoice.number',
                        '$payment.date',
                        '$method',
                        '$statement_amount',
                    ];

                    $pdf_variables->statement_payment_columns = $ss;

                    $ss =  [
                        '$credit.number',
                        '$credit.date',
                        '$total',
                        '$credit.balance',
                    ];

                    $pdf_variables->statement_credit_columns = $ss;


                    $ss =  [
                        '$statement_date',
                        '$balance'
                    ];

                    $pdf_variables->statement_details = $ss;



                    $ss =  [
                        '$invoice.number',
                        '$invoice.date',
                        '$due_date',
                        '$total',
                        '$balance',
                    ];

                    $pdf_variables->statement_invoice_columns = $ss;

                    $settings->pdf_variables = $pdf_variables;
                    $c->settings = $settings;
                    $c->save();



                });

        
        
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
