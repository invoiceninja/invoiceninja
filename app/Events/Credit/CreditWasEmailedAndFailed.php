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
    
    public $company;
    
    public function __construct(Credit $credit, $company, array $errors)
    {
        $this->credit = $credit;
        
        $this->company = $company;

        $this->errors = $errors;
    }
}
