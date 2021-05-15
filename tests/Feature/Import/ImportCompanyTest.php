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
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
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

    public function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );


        $this->withoutExceptionHandling();
    }

    public function testBackupJsonRead()
    {
        $backup_json_file = base_path().'/tests/Feature/Import/backup.json';

        $this->assertTrue(is_array(json_decode(file_get_contents($backup_json_file),1)));
    }

    public function testImportUsers()
    {

        $backup_json_file = base_path().'/tests/Feature/Import/backup.json';

        $backup_json_file = json_decode(file_get_contents($backup_json_file));

        $this->assertTrue(property_exists($backup_json_file, 'app_version'));
        $this->assertTrue(property_exists($backup_json_file, 'users'));

        // User::unguard();

        // foreach ($this->backup_file->users as $user)
        // {

        //     $new_user = User::firstOrNew(
        //         ['email' => $user->email],
        //         (array)$user,
        //     );

        //     $new_user->account_id = $this->account->id;
        //     $new_user->save(['timestamps' => false]);

        //     $this->ids['users']["{$user->id}"] = $new_user->id;
        // }

        // User::reguard();
    }


}
