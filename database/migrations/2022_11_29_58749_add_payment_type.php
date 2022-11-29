?php

use App\Models\GatewayType;
use App\Models\PaymentType;
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
        Schema::table('payment_types', function (Blueprint $table) {
           $type = new GatewayType();

            $type->id = 23;
            $type->alias = 'BACS';
            $type->name = 'BACS';

            $type->save();            

            $type = new PaymentType();

            $type->id = 47;
            $type->name = 'BACS';
            $type->gateway_type_id = GatewayType::BACS;

            $type->save();
        });
    }
};
