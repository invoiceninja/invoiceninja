<?php

namespace App\PaymentDrivers\Rotessa\Listeners;


use App\Models\Gateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\PaymentDrivers\Rotessa\Events\CacheGateways as Event;
use App\PaymentDrivers\Rotessa\Models\Gateway as RotessaGateway;

class CacheGateways
{
    public function handle(Event $event)
    {
        
        $gateways = Cache::get('gateways');
        if (empty($gateways) || $gateways->where('name', 'Rotessa')->isEmpty()) {
            $gateways = Gateway::orderBy('id')->get();
        }

        $gateways = $gateways->map(fn($item) => $item->name == 'Rotessa'? RotessaGateway::find($item->toArray()['id']) : $item );
        
        Cache::forever('gateways', $gateways);
    }
}

