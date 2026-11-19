<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Bank\EnableBanking;

use App\Models\Company;
use App\Models\BankIntegration;
use App\Services\Email\Email;
use App\Services\Email\EmailObject;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Mail\Mailables\Address;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use App\Helpers\Bank\EnableBanking\Transformer\AccountTransformer;
use App\Helpers\Bank\EnableBanking\Transformer\TransactionTransformer;

class EnableBanking
{
    protected string $application_id;
    protected string $key_path;
    protected ?string $rsa_key = null;
    
    public function __construct()
    {
        $this->application_id = config('ninja.enablebanking.application_id');
        $this->key_path = config('ninja.enablebanking.key_path');

        if (!$this->application_id || !$this->key_path) {
            throw new \Exception('Missing EnableBanking credentials');
        }

        if (file_exists($this->key_path)) {
            $this->rsa_key = file_get_contents($this->key_path);
        } else {
            throw new \Exception('EnableBanking RSA key file not found: ' . $this->key_path);
        }
    }

    /**
     * Create JWT token for EnableBanking API authentication
     */
    protected function createJwtToken(): string
    {
        $payload = [
            'iss' => 'enablebanking.com',
            'aud' => 'api.enablebanking.com',
            'iat' => time(),
            'exp' => time() + 3600
        ];

        return JWT::encode($payload, $this->rsa_key, 'RS256', $this->application_id);
    }

    /**
     * Make API request to EnableBanking
     */
    protected function request(string $url, string $method = 'GET', array $data = []): array
    {
        $jwt = $this->createJwtToken();
        $headers = [
            'Authorization' => 'Bearer ' . $jwt,
        ];

        $response = Http::withHeaders($headers);

        if ($method === 'GET') {
            $response = $response->get($url, $data);
        } else {
            $response = $response->post($url, $data);
        }

        return [
            'body' => $response->body(),
            'status' => $response->status(),
            'success' => $response->successful()
        ];
    }

    /**
     * Get available ASPSPs (banks)
     */
    public function getAspsps(): array
    {
        $result = $this->request('https://api.enablebanking.com/aspsps');

        if ($result['success']) {
            return json_decode($result['body'], true) ?? [];
        }
        
        throw new \Exception('EnableBanking API error: ' . $result['body']);
    }

    /**
     * Default consent validity (180 days) when the ASPSP does not report its maximum.
     */
    public const DEFAULT_CONSENT_VALIDITY = 180 * 24 * 60 * 60;

    /**
     * Start authorization process
     *
     * @param int|null $maximum_consent_validity Maximum consent validity of the ASPSP in seconds
     *                                           (`maximum_consent_validity` from /aspsps)
     */
    public function startAuthorization(string $aspsp_name, string $aspsp_country, string $redirect_url, string $state, ?int $maximum_consent_validity = null): array
    {
        // Request the longest consent the bank allows, so users rarely need to reconnect.
        // A one hour margin keeps valid_until within the ASPSP maximum by the time the request is handled.
        $consent_validity = ($maximum_consent_validity ?: self::DEFAULT_CONSENT_VALIDITY) - 60 * 60;
        $valid_until = time() + $consent_validity;

        $body = [
            'access' => [ 'valid_until' => date('c', $valid_until) ],
            'aspsp' => [ 
                'name' => $aspsp_name, 
                'country' => $aspsp_country 
            ],
            'state' => $state,
            'redirect_url' => $redirect_url,
            'psu_type' => 'personal'
        ];
        
        $result = $this->request('https://api.enablebanking.com/auth', 'POST', $body);
        
        if ($result['success']) {
            $response = json_decode($result['body'], true);
            $url = data_get($response, 'url');

            if (!$url) {
                throw new \Exception('EnableBanking authorization error: missing url in response: ' . $result['body']);
            }

            return [
                'url' => $url,
                'state' => $state
            ];
        }
        
        throw new \Exception('EnableBanking authorization error: ' . $result['body']);
    }

    /**
     * Create user session from authorization code
     */
    public function createSession(string $auth_code): array
    {
        $body = [ 'code' => $auth_code ];
        $result = $this->request('https://api.enablebanking.com/sessions', 'POST', $body);

        if ($result['success']) {
            return json_decode($result['body'], true) ?? [];
        }
        
        throw new \Exception('EnableBanking session creation error: ' . $result['body']);
    }

    /**
     * Get account balances
     */
    public function getAccountBalances(string $account_id): array
    {
        $result = $this->request("https://api.enablebanking.com/accounts/{$account_id}/balances");

        if ($result['success']) {
            return json_decode($result['body'], true) ?? [];
        }
        
        throw new \Exception('EnableBanking get account balances error: ' . $result['body']);
    }

    /**
     * Get account details
     */
    public function getAccount(string $account_id): array
    {
        try {
            $result = $this->request("https://api.enablebanking.com/accounts/{$account_id}/details");

            if ($result['success']) {
                $account = json_decode($result['body'], true);
                $it = new AccountTransformer();
                return $it->transform([$account]);
            }
        }
        catch (ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();

            if ($statusCode === 429) {
                nlog("EnableBanking Rate Limit hit for account {$account_id}");
                return ['error' => 'EnableBanking Rate Limit Reached', 'code' => 429];
            }
        } catch (\Exception $e) {

            nlog("EnableBanking getAccount() failed => {$account_id} => " . $e->getMessage());
            return ['error' => $e->getMessage(), 'requisition' => true, 'code' => 401];

        }
    }

    /**
     * Safety net against a misbehaving ASPSP that keeps returning new continuation keys.
     * Not a documented limit: far above normal use (500 pages of even ~25 transactions
     * covers ~12.500 transactions), it only prevents the job from running forever.
     */
    public const MAX_TRANSACTION_PAGES = 500;

    /**
     * Get all (booked) transactions for an account.
     *
     * EnableBanking paginates the transactions: as long as the response contains a
     * continuation_key, the next page is requested with it. Pages can be empty while
     * still carrying a key, so iterate on the key and not on the transactions.
     *
     * @see https://enablebanking.com/docs/api/reference/#get-account-transactions
     */
    public function getTransactions(Company $company, string $account_id, ?string $date_from = null): array
    {
        $params = [];

        if ($date_from) {
            // the API expects a plain date (YYYY-MM-DD)
            $params['date_from'] = \Carbon\Carbon::parse($date_from)->format('Y-m-d');
        }

        $transactions = [];
        $seen_keys = [];
        $page = 0;

        do {
            $result = $this->request("https://api.enablebanking.com/accounts/{$account_id}/transactions", 'GET', $params);

            if (!$result['success']) {
                throw new \Exception('EnableBanking get transactions error: ' . $result['body']);
            }

            $response = json_decode($result['body'], true) ?? [];
            $page_transactions = data_get($response, 'transactions') ?? [];

            if (is_array($page_transactions)) {
                array_push($transactions, ...array_values($page_transactions));
            }

            $continuation_key = data_get($response, 'continuation_key');
            $page++;

            if (empty($continuation_key)) {
                break;
            }

            if (in_array($continuation_key, $seen_keys, true)) {
                nlog("EnableBanking: repeated continuation_key for account {$account_id}, stopping after {$page} pages");
                break;
            }

            if ($page >= self::MAX_TRANSACTION_PAGES) {
                nlog("EnableBanking: reached the maximum of " . self::MAX_TRANSACTION_PAGES . " transaction pages for account {$account_id}");
                break;
            }

            $seen_keys[] = $continuation_key;
            $params['continuation_key'] = $continuation_key;
        } while (true);

        $it = new TransactionTransformer($company);

        return $it->transform(['transactions' => $transactions]);
    }

    /**
     * Check if session is still active
     */
    public function isSessionActive(string $session_id): bool
    {
        $result = $this->request("https://api.enablebanking.com/sessions/{$session_id}");
        
        if ($result['success']) {
            $session = json_decode($result['body'], true);
            return data_get($session, 'status') === 'AUTHORIZED';
        }
        
        return false;
    }

    /**
     * Send disabled account email notification
     */
    public function disabledAccountEmail(BankIntegration $bank_integration): void
    {
        $cache_key = "email_quota:{$bank_integration->company->company_key}:{$bank_integration->id}";

        if (Cache::has($cache_key)) {
            return;
        }

        Cache::put($cache_key, true, 60 * 60 * 24);

        App::setLocale($bank_integration->company->getLocale());

        $mo = new EmailObject();
        $mo->subject = ctrans('texts.enablebanking_session_subject');
        $mo->body = ctrans('texts.enablebanking_session_body');
        $mo->text_body = ctrans('texts.enablebanking_session_body');
        $mo->company_key = $bank_integration->company->company_key;
        $mo->html_template = 'email.template.generic';
        $mo->to = [new Address($bank_integration->company->owner()->email, $bank_integration->company->owner()->present()->name())];
        $mo->email_template_body = 'enablebanking_session_body';
        $mo->email_template_subject = 'enablebanking_session_subject';

        Email::dispatch($mo, $bank_integration->company);
    }
}