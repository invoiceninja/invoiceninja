<?php

use Illuminate\Database\Migrations\Migration;

class AddInvoiceSignature extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasColumn('invitations', 'signature_base64')) {
            Schema::table('invitations', function ($table) {
                $table->text('signature_base64')->nullable();
                $table->timestamp('signature_date')->nullable();
            });

            Schema::table('companies', function ($table) {
                $table->string('utm_source')->nullable();
                $table->string('utm_medium')->nullable();
                $table->string('utm_campaign')->nullable();
                $table->string('utm_term')->nullable();
                $table->string('utm_content')->nullable();
            });

            if (Utils::isNinja()) {
                Schema::table('payment_methods', function ($table) {
                    $table->unsignedInteger('account_gateway_token_id')->nullable()->change();
                    $table->dropForeign('payment_methods_account_gateway_token_id_foreign');
                });

                Schema::table('payment_methods', function ($table) {
                    $table->foreign('account_gateway_token_id')->references('id')->on('account_gateway_tokens')->onDelete('cascade');
                });

                Schema::table('payments', function ($table) {
                    $table->dropForeign('payments_payment_method_id_foreign');
                });

                Schema::table('payments', function ($table) {
                    $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('cascade');
                    ;
                });
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
        Schema::table('invitations', function ($table) {
            $table->dropColumn('signature_base64');
            $table->dropColumn('signature_date');
        });

        Schema::table('companies', function ($table) {
            $table->dropColumn('utm_source');
            $table->dropColumn('utm_medium');
            $table->dropColumn('utm_campaign');
            $table->dropColumn('utm_term');
            $table->dropColumn('utm_content');
        });
    }
}
