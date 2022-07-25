<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Gateway::query()->update(['visible' => 0]);

        Gateway::whereIn('id', [1, 15, 20, 39])->update(['visible' => 1]);

        Schema::table('recurring_invoice_invitations', function ($t) {
            $t->string('transaction_reference')->nullable();
            $t->string('message_id')->nullable();
            $t->mediumText('email_error')->nullable();
            $t->text('signature_base64')->nullable();
            $t->datetime('signature_date')->nullable();

            $t->datetime('sent_date')->nullable();
            $t->datetime('viewed_date')->nullable();
            $t->datetime('opened_date')->nullable();
        });

        Schema::table('expenses', function ($t) {
            $t->renameColumn('invoice_category_id', 'category_id');
        });

        Schema::table('projects', function ($t) {
            $t->text('public_notes')->nullable();
            $t->dropColumn('description');
            $t->decimal('budgeted_hours', 12, 2)->change();
            $t->boolean('is_deleted')->default(0);
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
