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

        // load the current values
        $gatewayConfigs = [];
        $bankUsernames = [];

        foreach (AccountGateway::all() as $gateway) {
            $gatewayConfigs[$gateway->id] = $gateway->getConfig();
        }

        foreach (BankAccount::all() as $bank) {
            $bankUsernames[$bank->id] = $bank->getUsername();
        }

        // set the new key and create a new encrypter
        Artisan::call('key:generate');
        $key = base64_decode(str_replace('base64:', '', config('app.key')));
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

        $this->info(date('Y-m-d h:i:s') . ' Successfully updated the application key');
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
