<?php

use App\Models\GatewayType;
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
        $gt = GatewayType::find(GatewayType::CARD_PRESENT);

        if (!$gt) {
            $type = new GatewayType();
            $type->id = GatewayType::CARD_PRESENT;
            $type->alias = 'card_present';
            $type->name = 'Card Present';
            $type->save();
        }

        Schema::create('terminal_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('invoice_id')->index();
            $table->unsignedInteger('company_gateway_id');
            $table->unsignedInteger('payment_hash_id')->nullable();
            $table->unsignedInteger('payment_id')->nullable();
            $table->uuid('collection_id');
            $table->string('payment_intent_id')->nullable()->unique();
            $table->string('currency', 3);
            $table->boolean('livemode')->default(false);
            $table->decimal('amount', 20, 6);
            $table->decimal('fee_gross', 20, 6)->default(0);
            $table->decimal('fee_net', 20, 6)->default(0);
            $table->boolean('apply_fee')->default(false);
            $table->string('surcharge_path')->nullable();
            $table->string('status', 32)->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('touched_at')->nullable();
            $table->timestamps(6);

            $table->unique(['company_id', 'collection_id']);
        });
    }
};
