<?php

namespace App\Events\Credit;

use App\Models\Company;
use App\Models\Credit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditWasCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $credit;

    public $company;
    
    /**
     * Create a new event instance.
     *
     * @param Credit $credit
     */
    public function __construct(Credit $credit, Company $company)
    {
        $this->credit = $credit;
        $this->company = $company;
    }
}
