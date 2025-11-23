<?php

namespace App\Helpers\Bank\Nordigen\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NordigenClient
{
    private string $baseUrl = 'https://bankaccountdata.gocardless.com/api/v2';

    private PendingRequest $httpClient;

    public function __construct(private string $accessToken)
    {
        $this->accessToken = $accessToken;
        $this->httpClient = Http::withHeaders([
            'Authorization' => "Bearer {$this->accessToken}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(30);
    }

    // ==================== REQUISITIONS ====================

    /**
     * Get all requisitions with pagination
     */
    public function getRequisitions(int $limit = 100, ?string $offset = null): Collection
    {
        $params = ['limit' => $limit];
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/requisitions/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get a specific requisition by ID
     */
    public function getRequisition(string $requisitionId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/requisitions/{$requisitionId}/");
        
        return $this->handleResponse($response);
    }

    /**
     * Create a new requisition
     */
    public function createRequisition(array $data): ?array
    {
        $response = $this->httpClient->post("{$this->baseUrl}/requisitions/", $data);
        
        return $this->handleResponse($response);
    }

    /**
     * Update a requisition
     */
    public function updateRequisition(string $requisitionId, array $data): ?array
    {
        $response = $this->httpClient->put("{$this->baseUrl}/requisitions/{$requisitionId}/", $data);
        
        return $this->handleResponse($response);
    }

    /**
     * Delete a requisition
     */
    public function deleteRequisition(string $requisitionId): bool
    {
        $response = $this->httpClient->delete("{$this->baseUrl}/requisitions/{$requisitionId}/");
        
        return $response->successful();
    }

    /**
     * Get all requisitions with full pagination
     */
    public function getAllRequisitions(): Collection
    {
        $allRequisitions = collect();
        $offset = null;
        $limit = 100;
        $maxIterations = 1000; // Safety limit to prevent infinite loops
        $iteration = 0;

        do {
            $iteration++;
            
            // Safety check to prevent infinite loops
            if ($iteration > $maxIterations) {
                nlog("getAllRequisitions: Maximum iterations reached ({$maxIterations}), breaking to prevent infinite loop");
                break;
            }

            $requisitions = $this->getRequisitions($limit, $offset);
            
            if ($requisitions->isEmpty()) {
                break;
            }

            $allRequisitions = $allRequisitions->merge($requisitions);
            
            // Check if we got fewer results than requested (end of data)
            if ($requisitions->count() < $limit) {
                break;
            }
            
            // Use the last requisition's ID as the offset for cursor-based pagination
            $lastRequisition = $requisitions->last();
            $newOffset = $lastRequisition['id'] ?? null;
            
            // Check if we're making progress (offset is changing)
            if ($newOffset === $offset) {
                nlog("getAllRequisitions: Offset not changing, likely stuck in loop. Breaking.");
                break;
            }
            
            $offset = $newOffset;

        } while ($offset);

        return $allRequisitions;
    }

    // ==================== AGREEMENTS ====================

    /**
     * Get all agreements with pagination
     */
    public function getAgreements(int $limit = 100, ?string $offset = null): Collection
    {
        $params = ['limit' => $limit];
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/agreements/enduser", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get a specific agreement by ID
     */
    public function getAgreement(string $agreementId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/agreements/enduser{$agreementId}/");
        
        return $this->handleResponse($response);
    }

    /**
     * Create a new agreement
     */
    public function createAgreement(array $data): ?array
    {
        $response = $this->httpClient->post("{$this->baseUrl}/agreements/enduser", $data);
        
        return $this->handleResponse($response);
    }

    /**
     * Update an agreement
     */
    public function updateAgreement(string $agreementId, array $data): ?array
    {
        $response = $this->httpClient->put("{$this->baseUrl}/agreements/enduser/{$agreementId}/", $data);
        
        return $this->handleResponse($response);
    }

    /**
     * Delete an agreement
     */
    public function deleteAgreement(string $agreementId): bool
    {
        $response = $this->httpClient->delete("{$this->baseUrl}/agreements/enduser/{$agreementId}/");
        
        return $response->successful();
    }

    /**
     * Get all agreements with full pagination
     */
    public function getAllAgreements(): Collection
    {
        $allAgreements = collect();
        $offset = null;
        $limit = 100;

        do {
            $agreements = $this->getAgreements($limit, $offset);
            
            if ($agreements->isEmpty()) {
                break;
            }

            $allAgreements = $allAgreements->merge($agreements);
            
            $lastAgreement = $agreements->last();
            $offset = $lastAgreement['id'] ?? null;

        } while ($agreements->count() === $limit && $offset);

        return $allAgreements;
    }

    // ==================== INSTITUTIONS ====================

    /**
     * Get all institutions with pagination
     */
    public function getInstitutions(int $limit = 100, ?string $offset = null): Collection
    {
        $params = [];

        $response = $this->httpClient->get("{$this->baseUrl}/institutions/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get a specific institution by ID
     */
    public function getInstitution(string $institutionId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/institutions/{$institutionId}/");
        
        return $this->handleResponse($response);
    }

    /**
     * Get institutions by country
     */
    public function getInstitutionsByCountry(string $countryCode, int $limit = 100, ?string $offset = null): Collection
    {
        $params = ['limit' => $limit, 'country' => $countryCode];
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/institutions/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get all institutions with full pagination
     */
    public function getAllInstitutions(): Collection
    {
        $allInstitutions = collect();
        $offset = null;
        $limit = 100;

        do {
            $institutions = $this->getInstitutions($limit, $offset);
            
            if ($institutions->isEmpty()) {
                break;
            }

            $allInstitutions = $allInstitutions->merge($institutions);
            
            $lastInstitution = $institutions->last();
            $offset = $lastInstitution['id'] ?? null;

        } while ($institutions->count() === $limit && $offset);

        return $allInstitutions;
    }

    // ==================== ENDUSER AGREEMENTS ====================

    /**
     * Get all enduser agreements with pagination
     */
    public function getEnduserAgreements(int $limit = 100, ?string $offset = null): Collection
    {
        $params = ['limit' => $limit];
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/enduser-agreements/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get a specific enduser agreement by ID
     */
    public function getEnduserAgreement(string $agreementId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/enduser-agreements/{$agreementId}/");
        
        return $this->handleResponse($response);
    }

    /**
     * Create a new enduser agreement
     */
    public function createEnduserAgreement(array $data): ?array
    {
        $response = $this->httpClient->post("{$this->baseUrl}/enduser-agreements/", $data);
        
        return $this->handleResponse($response);
    }

    /**
     * Update an enduser agreement
     */
    public function updateEnduserAgreement(string $agreementId, array $data): ?array
    {
        $response = $this->httpClient->put("{$this->baseUrl}/enduser-agreements/{$agreementId}/", $data);
        
        return $this->handleResponse($response);
    }

    /**
     * Delete an enduser agreement
     */
    public function deleteEnduserAgreement(string $agreementId): bool
    {
        $response = $this->httpClient->delete("{$this->baseUrl}/enduser-agreements/{$agreementId}/");
        
        return $response->successful();
    }

    // ==================== BANK ACCOUNTS ====================

    /**
     * Get all bank accounts with pagination
     */
    public function getBankAccounts(int $limit = 100, ?string $offset = null): Collection
    {
        $params = ['limit' => $limit];
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/accounts/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get a specific bank account by ID
     */
    public function getBankAccount(string $accountId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/accounts/{$accountId}/");
        
        return $this->handleResponse($response);
    }

    /**
     * Get account balances
     */
    public function getAccountBalances(string $accountId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/accounts/{$accountId}/balances/");
        
        return $this->handleResponse($response);
    }

    /**
     * Get account details
     */
    public function getAccountDetails(string $accountId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/accounts/{$accountId}/details/");
        
        return $this->handleResponse($response);
    }

    /**
     * Get account metadata
     */
    public function getAccountMetadata(string $accountId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/accounts/{$accountId}/metadata/");
        
        return $this->handleResponse($response);
    }

    /**
     * Get account holder information
     */
    public function getAccountHolder(string $accountId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/accounts/{$accountId}/holder/");
        
        return $this->handleResponse($response);
    }

    /**
     * Get account status
     */
    public function getAccountStatus(string $accountId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/accounts/{$accountId}/status/");
        
        return $this->handleResponse($response);
    }

    /**
     * Get all bank accounts with full pagination
     */
    public function getAllBankAccounts(): Collection
    {
        $allAccounts = collect();
        $offset = null;
        $limit = 100;

        do {
            $accounts = $this->getBankAccounts($limit, $offset);
            
            if ($accounts->isEmpty()) {
                break;
            }

            $allAccounts = $allAccounts->merge($accounts);
            
            $lastAccount = $accounts->last();
            $offset = $lastAccount['id'] ?? null;

        } while ($accounts->count() === $limit && $offset);

        return $allAccounts;
    }

    // ==================== TRANSACTIONS ====================

    /**
     * Get account transactions with pagination
     */
    public function getAccountTransactions(
        string $accountId, 
        ?string $dateFrom = null, 
        ?string $dateTo = null, 
        int $limit = 100, 
        ?string $offset = null
    ): Collection {
        $params = ['limit' => $limit];
        
        if ($dateFrom) {
            $params['date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $params['date_to'] = $dateTo;
        }
        
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/accounts/{$accountId}/transactions/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get all transactions across all accounts with pagination
     */
    public function getAllTransactions(
        ?string $dateFrom = null, 
        ?string $dateTo = null, 
        int $limit = 100, 
        ?string $offset = null
    ): Collection {
        $params = ['limit' => $limit];
        
        if ($dateFrom) {
            $params['date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $params['date_to'] = $dateTo;
        }
        
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/transactions/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get account transactions with full pagination support
     */
    public function getAllAccountTransactions(
        string $accountId, 
        ?string $dateFrom = null, 
        ?string $dateTo = null
    ): Collection {
        $allTransactions = collect();
        $offset = null;
        $limit = 100;

        do {
            $transactions = $this->getAccountTransactions($accountId, $dateFrom, $dateTo, $limit, $offset);
            
            if ($transactions->isEmpty()) {
                break;
            }

            $allTransactions = $allTransactions->merge($transactions);
            
            $lastTransaction = $transactions->last();
            $offset = $lastTransaction['id'] ?? null;

        } while ($transactions->count() === $limit && $offset);

        return $allTransactions;
    }

    /**
     * Get account transactions with specific status
     */
    public function getAccountTransactionsByStatus(
        string $accountId, 
        string $status, 
        int $limit = 100, 
        ?string $offset = null
    ): Collection {
        $params = [
            'limit' => $limit,
            'status' => $status
        ];
        
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/accounts/{$accountId}/transactions/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get account transactions by category
     */
    public function getAccountTransactionsByCategory(
        string $accountId, 
        string $category, 
        int $limit = 100, 
        ?string $offset = null
    ): Collection {
        $params = [
            'limit' => $limit,
            'category' => $category
        ];
        
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/accounts/{$accountId}/transactions/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Search transactions
     */
    public function searchTransactions(
        string $accountId, 
        string $query, 
        int $limit = 100, 
        ?string $offset = null
    ): Collection {
        $params = [
            'limit' => $limit,
            'search' => $query
        ];
        
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/accounts/{$accountId}/transactions/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get account transactions with amount range
     */
    public function getAccountTransactionsByAmount(
        string $accountId, 
        float $minAmount, 
        float $maxAmount, 
        int $limit = 100, 
        ?string $offset = null
    ): Collection {
        $params = [
            'limit' => $limit,
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount
        ];
        
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/accounts/{$accountId}/transactions/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    // ==================== PAYMENTS ====================

    /**
     * Get all payments with pagination
     */
    public function getPayments(int $limit = 100, ?string $offset = null): Collection
    {
        $params = ['limit' => $limit];
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/payments/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get a specific payment by ID
     */
    public function getPayment(string $paymentId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/payments/{$paymentId}/");
        
        return $this->handleResponse($response);
    }

    /**
     * Create a new payment
     */
    public function createPayment(array $data): ?array
    {
        $response = $this->httpClient->post("{$this->baseUrl}/payments/", $data);
        
        return $this->handleResponse($response);
    }

    /**
     * Update a payment
     */
    public function updatePayment(string $paymentId, array $data): ?array
    {
        $response = $this->httpClient->put("{$this->baseUrl}/payments/{$paymentId}/", $data);
        
        return $this->handleResponse($response);
    }

    /**
     * Delete a payment
     */
    public function deletePayment(string $paymentId): bool
    {
        $response = $this->httpClient->delete("{$this->baseUrl}/payments/{$paymentId}/");
        
        return $response->successful();
    }

    // ==================== MANDATES ====================

    /**
     * Get all mandates with pagination
     */
    public function getMandates(int $limit = 100, ?string $offset = null): Collection
    {
        $params = ['limit' => $limit];
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/mandates/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get a specific mandate by ID
     */
    public function getMandate(string $mandateId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/mandates/{$mandateId}/");
        
        return $this->handleResponse($response);
    }

    /**
     * Create a new mandate
     */
    public function createMandate(array $data): ?array
    {
        $response = $this->httpClient->post("{$this->baseUrl}/mandates/", $data);
        
        return $this->handleResponse($response);
    }

    /**
     * Update a mandate
     */
    public function updateMandate(string $mandateId, array $data): ?array
    {
        $response = $this->httpClient->put("{$this->baseUrl}/mandates/{$mandateId}/", $data);
        
        return $this->handleResponse($response);
    }

    /**
     * Delete a mandate
     */
    public function deleteMandate(string $mandateId): bool
    {
        $response = $this->httpClient->delete("{$this->baseUrl}/mandates/{$mandateId}/");
        
        return $response->successful();
    }

    // ==================== REFUNDS ====================

    /**
     * Get all refunds with pagination
     */
    public function getRefunds(int $limit = 100, ?string $offset = null): Collection
    {
        $params = ['limit' => $limit];
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/refunds/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get a specific refund by ID
     */
    public function getRefund(string $refundId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/refunds/{$refundId}/");
        
        return $this->handleResponse($response);
    }

    /**
     * Create a new refund
     */
    public function createRefund(array $data): ?array
    {
        $response = $this->httpClient->post("{$this->baseUrl}/refunds/", $data);
        
        return $this->handleResponse($response);
    }

    // ==================== EVENTS ====================

    /**
     * Get all events with pagination
     */
    public function getEvents(int $limit = 100, ?string $offset = null): Collection
    {
        $params = ['limit' => $limit];
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/events/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get a specific event by ID
     */
    public function getEvent(string $eventId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/events/{$eventId}/");
        
        return $this->handleResponse($response);
    }

    // ==================== WEBHOOKS ====================

    /**
     * Get all webhooks with pagination
     */
    public function getWebhooks(int $limit = 100, ?string $offset = null): Collection
    {
        $params = ['limit' => $limit];
        if ($offset) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->get("{$this->baseUrl}/webhooks/", $params);
        
        return $this->handlePaginatedResponse($response);
    }

    /**
     * Get a specific webhook by ID
     */
    public function getWebhook(string $webhookId): ?array
    {
        $response = $this->httpClient->get("{$this->baseUrl}/webhooks/{$webhookId}/");
        
        return $this->handleResponse($response);
    }

    /**
     * Create a new webhook
     */
    public function createWebhook(array $data): ?array
    {
        $response = $this->httpClient->post("{$this->baseUrl}/webhooks/", $data);
        
        return $this->handleResponse($response);
    }

    /**
     * Update a webhook
     */
    public function updateWebhook(string $webhookId, array $data): ?array
    {
        $response = $this->httpClient->put("{$this->baseUrl}/webhooks/{$webhookId}/", $data);
        
        return $this->handleResponse($response);
    }

    /**
     * Delete a webhook
     */
    public function deleteWebhook(string $webhookId): bool
    {
        $response = $this->httpClient->delete("{$this->baseUrl}/webhooks/{$webhookId}/");
        
        return $response->successful();
    }

    // ==================== UTILITY METHODS ====================

    /**
     * Handle paginated response
     */
    private function handlePaginatedResponse(Response $response): Collection
    {
        if (!$response->successful()) {
            $this->logError('Paginated request failed', $response);
            return collect();
        }

        $data = $response->json()['results'];
    
        return collect($data);
    }



    /**
     * Handle single response
     */
    private function handleResponse(Response $response): ?array
    {
        if (!$response->successful()) {
            $this->logError('Request failed', $response);
            return null;
        }

        return $response->json();
    }

    /**
     * Log error responses
     */
    private function logError(string $message, Response $response): void
    {
        nlog([
            'message' => $message,
            'status' => $response->status(),
            'body' => $response->body(),
            'headers' => $response->headers()
        ]);
    }

    /**
     * Set custom timeout
     */
    public function setTimeout(int $seconds): self
    {
        $this->httpClient = $this->httpClient->timeout($seconds);
        return $this;
    }

    /**
     * Set custom headers
     */
    public function setHeaders(array $headers): self
    {
        $this->httpClient = $this->httpClient->withHeaders($headers);
        return $this;
    }

    /**
     * Get the underlying HTTP client
     */
    public function getHttpClient(): PendingRequest
    {
        return $this->httpClient;
    }
}
