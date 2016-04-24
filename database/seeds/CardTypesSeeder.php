<?php

use App\Models\CardType;

class CardTypesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $this->createPaymentSourceTypes();

        Eloquent::reguard();
    }

    private function createPaymentSourceTypes()
    {
        $statuses = [
            ['id' => '0', 'name' => 'Unknown', 'code'=>'unknown']
            ['id' => '1', 'name' => 'American Express', 'code'=>'amex'],
            ['id' => '2', 'name' => 'Carte Blanche', 'code'=>'carteblanche'],
            ['id' => '3', 'name' => 'China UnionPay', 'code'=>'unionpay'],
            ['id' => '4', 'name' => 'Diners Club', 'code'=>'diners'],
            ['id' => '5', 'name' => 'Discover', 'code'=>'discover'],
            ['id' => '6', 'name' => 'JCB', 'code'=>'jcb'],
            ['id' => '7', 'name' => 'Laser', 'code'=>'laser'],
            ['id' => '8', 'name' => 'Maestro', 'code'=>'maestro'],
            ['id' => '9', 'name' => 'MasterCard', 'code'=>'mastercard'],
            ['id' => '10', 'name' => 'Solo', 'code'=>'solo'],
            ['id' => '11', 'name' => 'Switch', 'code'=>'switch'],
            ['id' => '12', 'name' => 'Visa', 'code'=>'visa'],
        ];

        foreach ($statuses as $status) {
            $record = CardType::find($status['id']);
            if ($record) {
                $record->name = $status['name'];
                $record->save();
            } else {
                CardType::create($status);
            }
        }
    }   
}
