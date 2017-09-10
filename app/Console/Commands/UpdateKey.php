<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use App\Models\AccountGateway;
use App\Models\BankAccount;
use Artisan;
use Crypt;
use Illuminate\Encryption\Encrypter;

/**
 * Class PruneData.
 */
class UpdateKey extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:update-key';

    /**
     * @var string
     */
    protected $description = 'Update application key';

    public function fire()
    {
        $this->info(date('Y-m-d h:i:s') . ' Running UpdateKey...');

        if (! env('APP_KEY') || ! env('APP_CIPHER')) {
            $this->info(date('Y-m-d h:i:s') . ' Error: app key and cipher are not set');
            exit;
        }

        // load the current values
        $gatewayConfigs = [];
        $bankUsernames = [];

        foreach (AccountGateway::all() as $gateway) {
            $gatewayConfigs[$gateway->id] = $gateway->getConfig();
        }

        foreach (BankAccount::all() as $bank) {
            $bankUsernames[$bank->id] = $bank->getUsername();
        }

        // check if we can write to the .env file
        $envPath = base_path() . '/.env';
        $envWriteable = file_exists($envPath) && @fopen($envPath, 'a');

        if ($envWriteable) {
            Artisan::call('key:generate');
            $key = base64_decode(str_replace('base64:', '', config('app.key')));
        } else {
            $key = str_random(32);
        }

        $crypt = new Encrypter($key, config('app.cipher'));

        // update values using the new key/encrypter
        foreach (AccountGateway::all() as $gateway) {
            $config = $gatewayConfigs[$gateway->id];
            $gateway->config = $crypt->encrypt(json_encode($config));
            $gateway->save();
        }

        foreach (BankAccount::all() as $bank) {
            $username = $bankUsernames[$bank->id];
            $bank->username = $crypt->encrypt($username);
            $bank->save();
        }

        if ($envWriteable) {
            $this->info(date('Y-m-d h:i:s') . ' Successfully update the key');
        } else {
            $this->info(date('Y-m-d h:i:s') . ' Successfully update data, make sure to set the new app key: ' . $key);
        }
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
