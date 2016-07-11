<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BankAccountService;

/**
 * Class TestOFX
 */
class TestOFX extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:test-ofx';

    /**
     * @var string
     */
    protected $description = 'Test OFX';

    /**
     * @var BankAccountService
     */
    protected $bankAccountService;

    /**
     * TestOFX constructor.
     *
     * @param BankAccountService $bankAccountService
     */
    public function __construct(BankAccountService $bankAccountService)
    {
        parent::__construct();

        $this->bankAccountService = $bankAccountService;
    }

    public function fire()
    {
        $this->info(date('Y-m-d').' Running TestOFX...');
    }
}