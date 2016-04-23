<?php

use App\Models\PaymentStatus;

class PaymentStatusSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $this->createPaymentStatuses();

        Eloquent::reguard();
    }

    private function createPaymentStatuses()
    {
        $statuses = [
            ['id' => '1', 'name' => 'Pending'],
            ['id' => '2', 'name' => 'Failed'],
            ['id' => '3', 'name' => 'Completed'],
            ['id' => '4', 'name' => 'Partially Refunded'],
            ['id' => '5', 'name' => 'Refunded'],
        ];

        foreach ($statuses as $status) {
            $record = PaymentStatus::find($status['id']);
            if ($record) {
                $record->name = $status['name'];
                $record->save();
            } else {
                PaymentStatus::create($status);
            }
        }
    }   
}
