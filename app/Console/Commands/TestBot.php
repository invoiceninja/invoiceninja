<?php namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Class TestOFX
 */
class TestBot extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:test-bot';

    /**
     * @var string
     */
    protected $description = 'Test Bot';

    /**
     * TestOFX constructor.
     *
     * @param BankAccountService $bankAccountService
     */
    public function __construct()
    {
        parent::__construct();
    }


    public function fire()
    {
        $this->info(date('Y-m-d').' Running TestBot...');

        $clientId = env('MSBOT_CLIENT_ID');
        $clientSecret = env('MSBOT_CLIENT_SECRET');

        $data = sprintf('grant_type=client_credentials&client_id=%s&client_secret=%s&scope=https://graph.microsoft.com/.default', $clientId, $clientSecret);
        $curl = curl_init();

        $opts = [
            CURLOPT_URL => MSBOT_LOGIN_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => 'POST',
            CURLOPT_POSTFIELDS => $data,
        ];

        curl_setopt_array($curl, $opts);
        $response = print_r(curl_exec($response));
        curl_close($curl);

        print_r($response);
    }
}
