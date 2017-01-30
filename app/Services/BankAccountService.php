<?php

namespace App\Services;

use App\Libraries\Finance;
use App\Libraries\Login;
use App\Models\BankSubaccount;
use App\Models\Expense;
use App\Models\Vendor;
use App\Ninja\Datatables\BankAccountDatatable;
use App\Ninja\Repositories\BankAccountRepository;
use App\Ninja\Repositories\ExpenseRepository;
use App\Ninja\Repositories\VendorRepository;
use Hash;
use stdClass;
use Utils;

/**
 * Class BankAccountService.
 */
class BankAccountService extends BaseService
{
    /**
     * @var BankAccountRepository
     */
    protected $bankAccountRepo;

    /**
     * @var ExpenseRepository
     */
    protected $expenseRepo;

    /**
     * @var VendorRepository
     */
    protected $vendorRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * BankAccountService constructor.
     *
     * @param BankAccountRepository $bankAccountRepo
     * @param ExpenseRepository     $expenseRepo
     * @param VendorRepository      $vendorRepo
     * @param DatatableService      $datatableService
     */
    public function __construct(BankAccountRepository $bankAccountRepo, ExpenseRepository $expenseRepo, VendorRepository $vendorRepo, DatatableService $datatableService)
    {
        $this->bankAccountRepo = $bankAccountRepo;
        $this->vendorRepo = $vendorRepo;
        $this->expenseRepo = $expenseRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return BankAccountRepository
     */
    protected function getRepo()
    {
        return $this->bankAccountRepo;
    }

    /**
     * @param null $bankId
     *
     * @return array
     */
    private function getExpenses($bankId = null)
    {
        $expenses = Expense::scope()
                        ->bankId($bankId)
                        ->where('transaction_id', '!=', '')
                        ->withTrashed()
                        ->get(['transaction_id'])
                        ->toArray();
        $expenses = array_flip(array_map(function ($val) {
            return $val['transaction_id'];
        }, $expenses));

        return $expenses;
    }

    /**
     * @param $bankId
     * @param $username
     * @param $password
     * @param bool $includeTransactions
     *
     * @return array|bool
     */
    public function loadBankAccounts($bankId, $username, $password, $includeTransactions = true)
    {
        if (! $bankId || ! $username || ! $password) {
            return false;
        }

        $expenses = $this->getExpenses();
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

    /**
     * @param $account
     * @param $bankAccounts
     * @param $expenses
     * @param $includeTransactions
     * @param $vendorMap
     *
     * @return bool|stdClass
     */
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
            $obj = $this->parseTransactions($obj, $account->response, $expenses, $vendorMap);
        }

        return $obj;
    }

    /**
     * @param $account
     * @param $data
     * @param $expenses
     * @param $vendorMap
     *
     * @return mixed
     */
    private function parseTransactions($account, $data, $expenses, $vendorMap)
    {
        $ofxParser = new \OfxParser\Parser();
        $ofx = $ofxParser->loadFromString($data);

        $account->start_date = $ofx->BankAccount->Statement->startDate;
        $account->end_date = $ofx->BankAccount->Statement->endDate;
        $account->transactions = [];

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
            $account->transactions[] = $transaction;
        }

        return $account;
    }

    /**
     * @param $value
     *
     * @return string
     */
    private function prepareValue($value)
    {
        return ucwords(strtolower(trim($value)));
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    public function parseOFX($data)
    {
        $account = new stdClass();
        $expenses = $this->getExpenses();
        $vendorMap = $this->createVendorMap();

        return $this->parseTransactions($account, $data, $expenses, $vendorMap);
    }

    /**
     * @return array
     */
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
            'count_expenses' => $countExpenses,
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

        return $this->datatableService->createDatatable(new BankAccountDatatable(false), $query);
    }
}
