<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Feature\Import;

use App\Jobs\Import\CSVImport;
use App\Models\Account;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Statement;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * 
 */
class ImportCompanyTest extends TestCase
{
    use MakesHash;

    public $account;

    public function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );


        $this->withoutExceptionHandling();

        $this->account = Account::factory()->create();
    }

    public function testBackupJsonRead()
    {
        $backup_json_file_zip = base_path().'/tests/Feature/Import/backup.zip';

        $zip = new \ZipArchive;
        $res = $zip->open($backup_json_file_zip);

        if ($res === TRUE) {
          $zip->extractTo(sys_get_temp_dir());
          $zip->close();
        } 

        $backup_json_file = sys_get_temp_dir() . "/backup/backup.json";

        $this->assertTrue(is_array(json_decode(file_get_contents($backup_json_file),1)));

        unlink($backup_json_file);
    }

    public function testImportUsers()
    {
        $backup_json_file_zip = base_path().'/tests/Feature/Import/backup.zip';

        $zip = new \ZipArchive;
        $res = $zip->open($backup_json_file_zip);
        if ($res === TRUE) {
          $zip->extractTo(sys_get_temp_dir());
          $zip->close();
        } 

        $backup_json_file = sys_get_temp_dir() . "/backup/backup.json";

        $backup_json_object = json_decode(file_get_contents($backup_json_file));

        $this->assertTrue(property_exists($backup_json_object, 'app_version'));
        $this->assertTrue(property_exists($backup_json_object, 'users'));

        unlink($backup_json_file);

        User::all()->each(function ($user){
            $user->forceDelete();
        });

        User::unguard();

        foreach ($backup_json_object->users as $user)
        {
            $user_array = (array)$user;
            unset($user_array['laravel_through_key']);
            unset($user_array['hashed_id']);

            $new_user = User::firstOrNew(
                ['email' => $user->email],
                array_merge($user_array, ['account_id' => $this->account->id]),
            );

            $new_user->save(['timestamps' => false]);

            $this->ids['users']["{$user->hashed_id}"] = $new_user->id;

        }

        User::reguard();

        $this->assertEquals(2, User::count());
    }


}
