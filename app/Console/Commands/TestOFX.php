<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BankAccountService;

class TestOFX extends Command
{
    protected $name = 'ninja:test-ofx';
    protected $description = 'Test OFX';

    public function __construct(BankAccountService $bankAccountService)
    {
        parent::__construct();

        $this->bankAccountService = $bankAccountService;
    }

    public function fire()
    {
        $this->info(date('Y-m-d').' Running TestOFX...');
        
        /*
        $bankId = env('TEST_BANK_ID');
        $username = env('TEST_BANK_USERNAME');
        $password = env('TEST_BANK_PASSWORD');

        $data = $this->bankAccountService->loadBankAccounts($bankId, $username, $password, false);

        echo json_encode($data);
        */
    }
}