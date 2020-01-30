<?php

namespace App\Events\Credit;

use App\Models\Credit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditWasEmailedAndFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $credit;

    public $errors;
    
    public function __construct(Credit $credit, array $errors)
    {
        $this->credit = $credit;
        
        $this->errors = $errors;
    }
}
