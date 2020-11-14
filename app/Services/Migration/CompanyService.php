<?php

namespace App\Services\Migration;

use App\Models\Account;
use Unirest\Request;
use Unirest\Request\Body;

class CompanyService
{
    protected $isSuccessful;
    protected $companies = [];

    public function start()
    {
        try {
            foreach (session(SESSION_USER_ACCOUNTS) as $company) {
                $account = Account::find($company->account_id);

                if ($account) {
                    $this->companies[] = [
                        'id' => $company->account_id, 
                        'name' => $account->name,
                        'company_key' => $account->account_key, 
                    ];
                }
            }

            $this->isSuccessful = true;
        } catch (\Exception $th) {
            $this->isSuccessful = false;
            $this->errors = [];
        }

        return $this;
    }

    public function isSuccessful()
    {
        return $this->isSuccessful;
    }

    public function getCompanies()
    {
        if ($this->isSuccessful) {
            return $this->companies;
        }

        return [];
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
