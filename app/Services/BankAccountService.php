<?php namespace App\Services;

use stdClass;
use Utils;
use URL;
use App\Models\Gateway;
use App\Services\BaseService;
use App\Ninja\Repositories\BankAccountRepository;

use App\Libraries\Finance;
use App\Libraries\Login;

class BankAccountService extends BaseService
{
    protected $bankAccountRepo;
    protected $datatableService;

    public function __construct(BankAccountRepository $bankAccountRepo, DatatableService $datatableService)
    {
        $this->bankAccountRepo = $bankAccountRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->bankAccountRepo;
    }

    /*
    public function save()
    {
        return null;
    }
    */

    public function loadBankAccounts($bankId, $username, $password, $includeTransactions = true)
    {
        if ( ! $bankId || ! $username || ! $password) {
            return false;
        }

        $bank = Utils::getFromCache($bankId, 'banks');
        $data = [];

        try {
            $finance = new Finance();
            $finance->banks[$bankId] = $bank->getOFXBank($finance);
            $finance->banks[$bankId]->logins[] = new Login($finance->banks[$bankId], $username, $password);
            
            foreach ($finance->banks as $bank) {
                foreach ($bank->logins as $login) {
                    $login->setup();
                    foreach ($login->accounts as $account) {
                        $account->setup($includeTransactions);
                        $obj = new stdClass;
                        $obj->account_number = Utils::maskAccountNumber($account->id);
                        $obj->type = $account->type;
                        $obj->balance = Utils::formatMoney($account->ledgerBalance, CURRENCY_DOLLAR);
                        $data[] = $obj;
                    }
                }
            }
            
            return $data;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getDatatable($accountId)
    {
        $query = $this->bankAccountRepo->find($accountId);

        return $this->createDatatable(ENTITY_BANK_ACCOUNT, $query, false);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'bank_name',
                function ($model) {
                    return link_to("bank_accounts/{$model->public_id}/edit", $model->bank_name);
                }
            ],
            [
                'bank_library_id',
                function ($model) {
                    return 'OFX';
                }
            ],
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                uctrans('texts.edit_bank_account'),
                function ($model) {
                    return URL::to("bank_accounts/{$model->public_id}/edit");
                }
            ]
        ];
    }

}