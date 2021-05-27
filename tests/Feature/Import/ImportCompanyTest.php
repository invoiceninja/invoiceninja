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
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\TaxRate;
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
    public $company;
    public $backup_json_object;
    public $ids;

    public function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        Account::all()->each(function ($account){
            $account->delete();
        });

        $this->account = Account::factory()->create();
        $this->company = Company::factory()->create(['account_id' => $this->account->id]);

        $backup_json_file_zip = base_path().'/tests/Feature/Import/backup.zip';

        $zip = new \ZipArchive;
        $res = $zip->open($backup_json_file_zip);
        if ($res === TRUE) {
          $zip->extractTo(sys_get_temp_dir());
          $zip->close();
        } 

        $backup_json_file = sys_get_temp_dir() . "/backup/backup.json";

        $this->backup_json_object = json_decode(file_get_contents($backup_json_file));

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

    public function testAppVersion()
    {
        $this->assertEquals("5.1.65", $this->backup_json_object->app_version);
    }

    public function testImportUsers()
    {

        $this->assertTrue(property_exists($this->backup_json_object, 'app_version'));

        /***************************** Users *****************************/
        $this->assertTrue(property_exists($this->backup_json_object, 'users'));

        User::all()->each(function ($user){
            $user->forceDelete();
        });

        User::unguard();

        $this->assertEquals(2, count($this->backup_json_object->users));

        foreach ($this->backup_json_object->users as $user)
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
        /***************************** Users *****************************/


        /***************************** Company Users *****************************/

        $this->assertEquals(2, count($this->backup_json_object->company_users));

        CompanyUser::unguard();

        foreach($this->backup_json_object->company_users as $cu)
        {
            $user_id = $this->transformId('users', $cu->user_id);

            $cu_array = (array)$cu;
            unset($cu_array['user_id']);
            unset($cu_array['company_id']);
            unset($cu_array['account_id']);
            unset($cu_array['hashed_id']);
            unset($cu_array['id']);

            $new_cu = CompanyUser::firstOrNew(
                        ['user_id' => $user_id, 'company_id' => $this->company->id],
                        $cu_array,
                    );

            $new_cu->account_id = $this->account->id;
            $new_cu->save(['timestamps' => false]);
            
        }

        CompanyUser::reguard();

        $this->assertEquals(2, CompanyUser::count());
        /***************************** Company Users *****************************/


        /***************************** Company Tokens *****************************/

        $this->assertEquals(2, count($this->backup_json_object->company_tokens));

        CompanyToken::unguard();

        foreach($this->backup_json_object->company_tokens as $ct)
        {
            $user_id = $this->transformId('users', $ct->user_id);

            $ct_array = (array)$ct;
            unset($ct_array['user_id']);
            unset($ct_array['company_id']);
            unset($ct_array['account_id']);
            unset($ct_array['hashed_id']);
            unset($ct_array['id']);

            $new_ct = CompanyToken::firstOrNew(
                        ['user_id' => $user_id, 'company_id' => $this->company->id],
                        $ct_array,
                    );

            $new_ct->account_id = $this->account->id;
            $new_ct->save(['timestamps' => false]);
            
        }

        CompanyToken::reguard();

        $this->assertEquals(2, CompanyToken::count());
        /***************************** Company Tokens *****************************/


        /***************************** Payment Terms *****************************/
        PaymentTerm::unguard();

        $this->assertEquals(8, count($this->backup_json_object->payment_terms));

        foreach($this->backup_json_object->payment_terms as $obj)
        {
        
            $user_id = $this->transformId('users', $obj->user_id);

            $obj_array = (array)$obj;
            unset($obj_array['user_id']);
            unset($obj_array['company_id']);
            unset($obj_array['account_id']);
            unset($obj_array['hashed_id']);
            unset($obj_array['id']);

            $new_obj = PaymentTerm::firstOrNew(
                        ['num_days' => $obj->num_days, 'company_id' => $this->company->id],
                        $obj_array,
                    );

            $new_obj->save(['timestamps' => false]);
            
        }

        PaymentTerm::reguard();
    
        $this->assertEquals(8, PaymentTerm::count());
        /***************************** Payment Terms *****************************/

        /***************************** Tax Rates *****************************/
        TaxRate::unguard();

        $this->assertEquals(2, count($this->backup_json_object->tax_rates));

        foreach($this->backup_json_object->tax_rates as $obj)
        {
        
            $user_id = $this->transformId('users', $obj->user_id);

            $obj_array = (array)$obj;
            unset($obj_array['user_id']);
            unset($obj_array['company_id']);
            unset($obj_array['account_id']);
            unset($obj_array['hashed_id']);
            unset($obj_array['id']);
            unset($obj_array['tax_rate_id']);

            $new_obj = TaxRate::firstOrNew(
                        ['name' => $obj->name, 'company_id' => $this->company->id, 'rate' => $obj->rate],
                        $obj_array,
                    );

            $new_obj->save(['timestamps' => false]);
            
        }

        TaxRate::reguard();
    
        $this->assertEquals(2, TaxRate::count());
        /***************************** Tax Rates *****************************/

        /***************************** Expense Category *****************************/
        ExpenseCategory::unguard();

        $this->assertEquals(2, count($this->backup_json_object->tax_rates));

        foreach($this->backup_json_object->expense_categories as $obj)
        {
        
            $user_id = $this->transformId('users', $obj->user_id);

            $obj_array = (array)$obj;
            unset($obj_array['user_id']);
            unset($obj_array['company_id']);
            unset($obj_array['account_id']);
            unset($obj_array['hashed_id']);
            unset($obj_array['id']);
            unset($obj_array['tax_rate_id']);

            $new_obj = ExpenseCategory::firstOrNew(
                        ['name' => $obj->name, 'company_id' => $this->company->id],
                        $obj_array,
                    );

            $new_obj->save(['timestamps' => false]);
            
        }

        ExpenseCategory::reguard();
    
        $this->assertEquals(2, ExpenseCategory::count());
        /***************************** Expense Category *****************************/

    }


    private function transformId(string $resource, string $old): int
    {
        if (! array_key_exists($resource, $this->ids)) {
            throw new \Exception("Resource {$resource} not available.");
        }

        if (! array_key_exists("{$old}", $this->ids[$resource])) {
            throw new \Exception("Missing resource key: {$old}");
        }

        return $this->ids[$resource]["{$old}"];
    }

    public function tearDown() :void
    {
        $backup_json_file = sys_get_temp_dir() . "/backup/backup.json";

     //   unlink($backup_json_file);
    }


}
