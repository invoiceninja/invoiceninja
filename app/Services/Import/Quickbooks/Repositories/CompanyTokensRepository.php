<?php

namespace App\Services\Import\Quickbooks\Repositories;

use App\Models\Company;
use App\Libraries\MultiDB;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CompanyTokensRepository {


    private $company_key;
    private $store_key = "quickbooks-token";

    public function __construct(string $key = null) {
        $this->company_key = $key ?? auth()->user->company()->company_key ?? null;
        $this->store_key .= $key;
        $this->setCompanyDbByKey();
    }

    public function save(array $tokens) {
        $this->updateAccessToken($tokens['access_token'], $tokens['access_token_expires']);
        $this->updateRefreshToken($tokens['refresh_token'], $tokens['refresh_token_expires'], $tokens['realm']);
    }


    public function findByCompanyKey(): ?Company
    {
        return Company::where('company_key', $this->company_key)->first();
    }

    public function setCompanyDbByKey() 
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);
    }

    public function get() {
        return $this->getAccessToken() + $this->getRefreshToken();
    }

    
    protected function updateRefreshToken(string $token, string $expires, string $realm)
    {
        DB::table('companies')
                    ->where('company_key', $this->company_key)
                    ->update(['quickbooks_refresh_token' => $token,
                                'quickbooks_realm_id' => $realm,
                                'quickbooks_refresh_expires' => $expires ]);
    }

    protected function updateAccessToken(string $token, string $expires )
    {

        Cache::put([$this->store_key => $token], $expires);
    }

    protected function getAccessToken( )
    {
        $result = Cache::get($this->store_key);
        
        return $result ? ['access_token' => $result] : [];
    }

    protected function getRefreshToken()
    {
        $result = (array) DB::table('companies')
        ->select('quickbooks_refresh_token', 'quickbooks_realm_id')
        ->where('company_key',$this->company_key)
        ->where('quickbooks_refresh_expires','>',now())
        ->first();
        
        return $result? array_combine(['refresh_token','realm'], array_values($result) ) : [];
    }

}
