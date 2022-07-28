<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Bank\Yodlee;

use Illuminate\Support\Facades\Http;
 
class Yodlee
{

    public bool $test_mode;

    private string $api_endpoint = '';

    private string $test_api_endpoint = 'https://sandbox.api.yodlee.com/ysl';

    protected string $fast_track_url = 'https://fl4.sandbox.yodlee.com/authenticate/restserver/fastlink';

    protected string $client_id;

    protected string $client_secret;

    protected string $admin_name;

    public function __construct(bool $test_mode = false)
    {
        $this->test_mode = $test_mode;

        if($this->test_mode)
            $this->api_endpoint = $this->test_api_endpoint;

        $this->client_id = config('ninja.yodlee.client_id');

        $this->client_secret = config('ninja.yodlee.client_secret');

        $this->admin_name = config('ninja.yodlee.admin_name');

    }

    public function getAccessToken()
    {
        return $this->bankRequest('/auth/token', 'post');
    }

    private function bankRequest(string $uri, string $verb, array $data = [])
    {
        nlog($this->getHeaders());
        nlog($this->buildBody());
        nlog($this->api_endpoint . $uri);

        $response = Http::withHeaders($this->getHeaders())->asForm()->{$verb}($this->api_endpoint . $uri, $this->buildBody());

        if($response->successful())
            return $response->object();

        if($response->failed())
            return $response->body();

    }

    private function getHeaders($data = [])
    {
        return array_merge($data, [
            'Api-Version' => '1.1',
            'loginName' => $this->admin_name
        ]);
    }

    private function buildBody()
    {

        return [
            'clientId' => $this->client_id,
            'secret' => $this->client_secret,
        ];

    }

}
