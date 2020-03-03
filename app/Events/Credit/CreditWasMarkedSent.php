<?php
namespace App\Events\Credit;

use App\Models\Company;
use App\Models\Credit;
use Illuminate\Queue\SerializesModels;

/**
 * Class CreditWasMarkedSent.
 */
class CreditWasMarkedSent
{
    use SerializesModels;
    /**
     * @var Credit
     */
    public $credit;
    public $company;

    /**
     * Create a new event instance.
     *
     * @param Quote $credit
     */
    public function __construct(Credit $credit, Company $company)
    {
        $this->credit = $credit;
        $this->company = $company;
    }
}
