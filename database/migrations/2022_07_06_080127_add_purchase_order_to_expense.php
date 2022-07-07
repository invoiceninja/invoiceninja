<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
use App\Models\Language;
use App\Models\PurchaseOrder;
use App\Utils\Traits\AppSetup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPurchaseOrderToExpense extends Migration
{
    use AppSetup;
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedInteger('expense_id')->nullable()->index();
        });

        PurchaseOrder::withTrashed()->where('status_id', 4)->update(['status_id' => 5]);

        $language = Language::find(36);

        if(!$language){

            Language::unguard();
            Language::create(['id' => 36, 'name' => 'Bulgarian', 'locale' => 'bg']);

            $this->buildCache(true);

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
}
