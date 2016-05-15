<?php namespace App\Services;

use stdClass;
use Utils;
use URL;
use Hash;
use App\Models\BankSubaccount;
use App\Models\Vendor;
use App\Models\Expense;
use App\Services\BaseService;
use App\Ninja\Repositories\BankAccountRepository;
use App\Ninja\Repositories\ExpenseRepository;
use App\Ninja\Repositories\VendorRepository;
use App\Libraries\Finance;
use App\Libraries\Login;

class BankAccountService extends BaseService
{
    protected $bankAccountRepo;
    protected $expenseRepo;
    protected $vendorRepo;
    protected $datatableService;

    public function __construct(BankAccountRepository $bankAccountRepo, ExpenseRepository $expenseRepo, VendorRepository $vendorRepo, DatatableService $datatableService)
    {
        $this->bankAccountRepo = $bankAccountRepo;
        $this->vendorRepo = $vendorRepo;
        $this->expenseRepo = $expenseRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->bankAccountRepo;
    }

    public function loadBankAccounts($bankId, $username, $password, $includeTransactions = true)
    {
        if (! $bankId || ! $username || ! $password) {
            return false;
        }

        $expenses = Expense::scope()
                        ->whereBankId($bankId)
                        ->where('transaction_id', '!=', '')
                        ->withTrashed()
                        ->get(['transaction_id'])
                        ->toArray();
        $expenses = array_flip(array_map(function ($val) {
            return $val['transaction_id'];
        }, $expenses));

        $vendorMap = $this->createVendorMap();
        $bankAccounts = BankSubaccount::scope()
                            ->whereHas('bank_account', function ($query) use ($bankId) {
                                $query->where('bank_id', '=', $bankId);
                            })
                            ->get();
        $bank = Utils::getFromCache($bankId, 'banks');
        $data = [];

        // load OFX trnansactions
        try {
            $finance = new Finance();
            $finance->banks[$bankId] = $bank->getOFXBank($finance);
            $finance->banks[$bankId]->logins[] = new Login($finance->banks[$bankId], $username, $password);

            foreach ($finance->banks as $bank) {
                foreach ($bank->logins as $login) {
                    $login->setup();
                    foreach ($login->accounts as $account) {
                        $account->setup($includeTransactions);
                        if ($account = $this->parseBankAccount($account, $bankAccounts, $expenses, $includeTransactions, $vendorMap)) {
                            $data[] = $account;
                        }
                    }
                }
            }

            return $data;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function parseBankAccount($account, $bankAccounts, $expenses, $includeTransactions, $vendorMap)
    {
        $obj = new stdClass();
        $obj->account_name = '';

        // look up bank account name
        foreach ($bankAccounts as $bankAccount) {
            if (Hash::check($account->id, $bankAccount->account_number)) {
                $obj->account_name = $bankAccount->account_name;
            }
        }

        // if we can't find a match skip the account
        if (count($bankAccounts) && ! $obj->account_name) {
            return false;
        }

        $obj->masked_account_number = Utils::maskAccountNumber($account->id);
        $obj->hashed_account_number = bcrypt($account->id);
        $obj->type = $account->type;
        $obj->balance = Utils::formatMoney($account->ledgerBalance, CURRENCY_DOLLAR);

        if ($includeTransactions) {
            $ofxParser = new \OfxParser\Parser();
            $ofx = $ofxParser->loadFromString($account->response);

            $obj->start_date = $ofx->BankAccount->Statement->startDate;
            $obj->end_date = $ofx->BankAccount->Statement->endDate;
            $obj->transactions = [];

            foreach ($ofx->BankAccount->Statement->transactions as $transaction) {
                // ensure transactions aren't imported as expenses twice
                if (isset($expenses[$transaction->uniqueId])) {
                    continue;
                }
                if ($transaction->amount >= 0) {
                    continue;
                }

                // if vendor has already been imported use current name
                $vendorName = trim(substr($transaction->name, 0, 20));
                $key = strtolower($vendorName);
                $vendor = isset($vendorMap[$key]) ? $vendorMap[$key] : null;

                $transaction->vendor = $vendor ? $vendor->name : $this->prepareValue($vendorName);
                $transaction->info = $this->prepareValue(substr($transaction->name, 20));
                $transaction->memo = $this->prepareValue($transaction->memo);
                $transaction->date = \Auth::user()->account->formatDate($transaction->date);
                $transaction->amount *= -1;
                $obj->transactions[] = $transaction;
            }
        }

        return $obj;
    }

    private function prepareValue($value)
    {
        return ucwords(strtolower(trim($value)));
    }

    private function createVendorMap()
    {
        $vendorMap = [];
        $vendors = Vendor::scope()
                        ->withTrashed()
                        ->get(['id', 'name', 'transaction_name']);
        foreach ($vendors as $vendor) {
            $vendorMap[strtolower($vendor->name)] = $vendor;
            $vendorMap[strtolower($vendor->transaction_name)] = $vendor;
        }

        return $vendorMap;
    }

    public function importExpenses($bankId, $input)
    {
        $vendorMap = $this->createVendorMap();
        $countVendors = 0;
        $countExpenses = 0;

        foreach ($input as $transaction) {
            $vendorName = $transaction['vendor'];
            $key = strtolower($vendorName);
            $info = $transaction['info'];

            // find vendor otherwise create it
            if (isset($vendorMap[$key])) {
                $vendor = $vendorMap[$key];
            } else {
                $field = $this->determineInfoField($info);
                $vendor = $this->vendorRepo->save([
                    $field => $info,
                    'name' => $vendorName,
                    'transaction_name' => $transaction['vendor_orig'],
                    'vendor_contact' => [],
                ]);
                $vendorMap[$key] = $vendor;
                $vendorMap[$transaction['vendor_orig']] = $vendor;
                $countVendors++;
            }

            // create the expense record
            $this->expenseRepo->save([
                'vendor_id' => $vendor->id,
                'amount' => $transaction['amount'],
                'public_notes' => $transaction['memo'],
                'expense_date' => $transaction['date'],
                'transaction_id' => $transaction['id'],
                'bank_id' => $bankId,
                'should_be_invoiced' => true,
            ]);
            $countExpenses++;
        }

        return trans('texts.imported_expenses', [
            'count_vendors' => $countVendors,
            'count_expenses' => $countExpenses
        ]);
    }

    private function determineInfoField($value)
    {
        if (preg_match("/^[0-9\-\(\)\.]+$/", $value)) {
            return 'work_phone';
        } elseif (strpos($value, '.') !== false) {
            return 'private_notes';
        } else {
            return 'city';
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
                    return link_to("bank_accounts/{$model->public_id}/edit", $model->bank_name)->toHtml();
                },
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
                },
            ]
        ];
    }
}
