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
            return json_decode($result['body'], true);
        }
        
        throw new \Exception('EnableBanking API error: ' . $result['body']);
    }

    /**
     * Start authorization process
     */
    public function startAuthorization(string $aspsp_name, string $aspsp_country, string $redirect_url, string $state): array
    {
        $valid_until = time() + 2 * 7 * 24 * 60 * 60; // 2 weeks
        
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
            return [
                'url' => $response['url'],
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
            return json_decode($result['body'], true);
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
            return json_decode($result['body'], true);
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
     * Get transactions for an account
     */
    public function getTransactions(Company $company, string $account_id, ?string $date_from = null): array
    {
        $params = [];
        if ($date_from) {
            $params['date_from'] = $date_from;
        }
        
        $result = $this->request("https://api.enablebanking.com/accounts/{$account_id}/transactions", 'GET', $params);
        if ($result['success']) {
            $transactions = json_decode($result['body'], true);
            
            $it = new TransactionTransformer($company);
            return $it->transform($transactions);
        }
        
        throw new \Exception('EnableBanking get transactions error: ' . $result['body']);
    }

    /**
     * Check if session is still active
     */
    public function isSessionActive(string $session_id): bool
    {
        $result = $this->request("https://api.enablebanking.com/sessions/{$session_id}");
        
        if ($result['success']) {
            $session = json_decode($result['body'], true);
            return isset($session['status']) && $session['status'] === 'AUTHORIZED';
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