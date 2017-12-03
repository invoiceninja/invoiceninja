<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use App\Models\AccountGateway;
use App\Models\BankAccount;
use Artisan;
use Illuminate\Encryption\Encrypter;
use Laravel\LegacyEncrypter\McryptEncrypter;

/**
 * Class UpdateKey
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
        $this->info(date('r') . ' Running UpdateKey...');

        if (! env('APP_KEY') || ! env('APP_CIPHER')) {
            $this->info(date('r') . ' Error: app key and cipher are not set');
            exit;
        }

        $legacy = false;
        if ($this->option('legacy') == 'true') {
            $legacy = new McryptEncrypter(env('APP_KEY'));
        }

        // load the current values
        $gatewayConfigs = [];
        $bankUsernames = [];

        foreach (AccountGateway::all() as $gateway) {
            if ($legacy) {
                $gatewayConfigs[$gateway->id] = json_decode($legacy->decrypt($gateway->config));
            } else {
                $gatewayConfigs[$gateway->id] = $gateway->getConfig();
            }
        }

        foreach (BankAccount::all() as $bank) {
            if ($legacy) {
                $bankUsernames[$bank->id] = $legacy->decrypt($bank->username);
            } else {
                $bankUsernames[$bank->id] = $bank->getUsername();
            }
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

        $cipher = $legacy ? 'AES-256-CBC' : config('app.cipher');
        $crypt = new Encrypter($key, $cipher);

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

        $message = date('r') . ' Successfully updated ';
        if ($envWriteable) {
            if ($legacy) {
                $message .= 'the key, set the cipher in the .env file to AES-256-CBC';
            } else {
                $message .= 'the key';
            }
        } else {
            if ($legacy) {
                $message .= 'the data, make sure to set the new cipher/key: AES-256-CBC/' . $key;
            } else {
                $message .= 'the data, make sure to set the new key: ' . $key;
            }
        }
        $this->info($message);
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
        return [
            ['legacy', null, InputOption::VALUE_OPTIONAL, 'Legacy', null],
        ];
    }
}
