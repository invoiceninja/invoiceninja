<?php
namespace App\Services\Import\Quickbooks;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Services\Import\Quickbooks\Auth;
use App\Repositories\Import\Quickbooks\Contracts\RepositoryInterface;
use App\Services\Import\QuickBooks\Contracts\SdkInterface as QuickbooksInterface;

final class Service
{    
    private QuickbooksInterface $sdk;

    public function __construct(QuickbooksInterface $quickbooks) {
        $this->sdk = $quickbooks;
    }

    public function getOAuth() : Auth
    {
        return new Auth($this->sdk);
    }

    public function getAccessToken() : array
    {
       return $this->getOAuth()->getAccessToken(); 
    }

    public function getRefreshToken() : array
    {
        // TODO: Check if token is Cached otherwise fetch a new one and Cache token and expire
        return  $this->getAccessToken();
    }
    /**
     * fetch QuickBooks invoice records
     * @param int $max The maximum records to fetch. Default 100
     * @return Illuminate\Support\Collection;
     */
    public function fetchInvoices(int $max = 100): Collection
    {
        return $this->fetchRecords('Invoice', $max) ;
    }

    /**
     * fetch QuickBooks payment records
     * @param int $max The maximum records to fetch. Default 100
     * @return Illuminate\Support\Collection;
     */
    public function fetchPayments(int $max = 100): Collection
    {
        return $this->fetchRecords('Payment', $max) ;
    }

    /**
     * fetch QuickBooks product records
     * @param int $max The maximum records to fetch. Default 100
     * @return Illuminate\Support\Collection;
     */
    public function fetchItems(int $max = 100): Collection
    {
        return $this->fetchRecords('Item', $max) ;
    }

    protected function fetchRecords(string $entity, $max = 100) : Collection {
        return (self::RepositoryFactory($entity))->get($max);
    }

    private static function RepositoryFactory(string $entity) : RepositoryInterface
    {
        return app("\\App\\Repositories\\Import\Quickbooks\\{$entity}Repository");
    }

    /**
     * fetch QuickBooks customer records
     * @param int $max The maximum records to fetch. Default 100
     * @return Illuminate\Support\Collection;
     */
    public function fetchCustomers(int $max = 100): Collection
    {
        return $this->fetchRecords('Customer', $max) ;
    }

    public function totalRecords(string $entity) : int
    {
        return (self::RepositoryFactory($entity))->count();
    }
}