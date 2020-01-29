<?php

namespace App\Events\Credit;

use App\Models\Company;
use App\Models\Credit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditWasUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $credit;

    public $company;

    public function __construct(Credit $credit, Company $company)
    {
        $this->invoice = $credit;
        $this->company = $company;
    }
}
