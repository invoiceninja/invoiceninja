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
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Models\TaskStatus;
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

        $this->assertEquals(2, count($this->backup_json_object->expense_categories));

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


        /***************************** Task Statuses *****************************/
        TaskStatus::unguard();

        $this->assertEquals(4, count($this->backup_json_object->task_statuses));

        foreach($this->backup_json_object->task_statuses as $obj)
        {
        
            $user_id = $this->transformId('users', $obj->user_id);

            $obj_array = (array)$obj;
            unset($obj_array['user_id']);
            unset($obj_array['company_id']);
            unset($obj_array['account_id']);
            unset($obj_array['hashed_id']);
            unset($obj_array['id']);
            unset($obj_array['tax_rate_id']);

            $new_obj = TaskStatus::firstOrNew(
                        ['name' => $obj->name, 'company_id' => $this->company->id],
                        $obj_array,
                    );

            $new_obj->save(['timestamps' => false]);
            
        }

        TaskStatus::reguard();
    
        $this->assertEquals(4, TaskStatus::count());
        /***************************** Task Statuses *****************************/

        /***************************** Clients *****************************/
        Client::unguard();

        $this->assertEquals(1, count($this->backup_json_object->clients));

        foreach($this->backup_json_object->clients as $obj)
        {
        
            $user_id = $this->transformId('users', $obj->user_id);
            $assigned_user_id = $this->transformId('users', $obj->assigned_user_id);

            $obj_array = (array)$obj;
            unset($obj_array['user_id']);
            unset($obj_array['company_id']);
            unset($obj_array['account_id']);
            unset($obj_array['hashed_id']);
            unset($obj_array['id']);
            unset($obj_array['gateway_tokens']);
            unset($obj_array['contacts']);
            unset($obj_array['documents']);
            
            $new_obj = Client::firstOrNew(
                        ['number' => $obj->number, 'company_id' => $this->company->id],
                        $obj_array,
                    );

            $new_obj->save(['timestamps' => false]);
            
            $this->ids['clients']["{$obj->hashed_id}"] = $new_obj->id;

        }

        Client::reguard();
    
        $this->assertEquals(1, Client::count());
        /***************************** Clients *****************************/

        /***************************** Client Contacts *****************************/
        ClientContact::unguard();

        $this->assertEquals(1, count($this->backup_json_object->client_contacts));

        foreach($this->backup_json_object->client_contacts as $obj)
        {

            $user_id = $this->transformId('users', $obj->user_id);
            $client_id = $this->transformId('clients', $obj->client_id);

            $obj_array = (array)$obj;
            unset($obj_array['user_id']);
            unset($obj_array['company_id']);
            unset($obj_array['account_id']);
            unset($obj_array['hashed_id']);
            unset($obj_array['id']);
            unset($obj_array['gateway_tokens']);
            unset($obj_array['contacts']);
            unset($obj_array['documents']);
            
            $obj_array['client_id'] = $client_id;

            $new_obj = ClientContact::firstOrNew(
                        ['email' => $obj->email, 'company_id' => $this->company->id],
                        $obj_array,
                    );

            $new_obj->save(['timestamps' => false]);
            
            $this->ids['client_contacts']["{$obj->hashed_id}"] = $new_obj->id;

        }

        ClientContact::reguard();
    
        $this->assertEquals(1, ClientContact::count());
        /***************************** Client Contacts *****************************/

        /* Generic */


        /* Generic */

//vendors!
//projects!

        /***************************** Products *****************************/
        // Product::unguard();

        // $this->assertEquals(1, count($this->backup_json_object->products));

        // foreach($this->backup_json_object->products as $obj)
        // {

        //     $user_id = $this->transformId('users', $obj->user_id);
        //     $assigned_user_id = $this->transformId('users', $obj->assigned_user_id);
        //     $vendor_id = $this->transformId('vendors', $obj->vendor_id);
        //     $project_id = $this->transformId('projects', $obj->project_id);

        //     $obj_array = (array)$obj;
        //     unset($obj_array['user_id']);
        //     unset($obj_array['company_id']);
        //     unset($obj_array['account_id']);
        //     unset($obj_array['hashed_id']);
        //     unset($obj_array['id']);
            

        //     $new_obj = new Product();
        //     $new_obj->company_id = $this->company->id;
        //     $new_obj->user_id = $user_id;
        //     $new_obj->assigned_user_id = $assigned_user_id;
        //     $new_obj->vendor_id = $vendor_id;
        //     $new_obj->project_id = $project_id;
        //     $new_obj->fill($obj_array);

        //     $new_obj->save(['timestamps' => false]);
            
        //     $this->ids['products']["{$obj->hashed_id}"] = $new_obj->id;

        // }

        // Product::reguard();
    
        // $this->assertEquals(1, Product::count());
        /***************************** Products *****************************/

    }

    private function genericImport($class, $unset, $transform, $object_property, $match_key)
    {

        $class::unguard();


        foreach($this->backup_json_object->{$object_property} as $obj)
        {

            $obj_array = (array)$obj;
            foreach($unset as $un){
                unset($obj_array[$un]);
            }

            foreach($trans as $key => $value)
            {
                $obj_array["{$value}"] = $this->transformId($object_property, $obj->{$value});
            }
            
            $new_obj = $class::firstOrNew(
                    [$match_key => $obj->{$match_key}, 'company_id' => $this->company->id],
                    $obj_array,
                );

            $new_obj->save(['timestamps' => false]);
            
            $this->ids["{$object_property}"]["{$obj->hashed_id}"] = $new_obj->id;

        }

        $class::reguard();
           

    }

    private function transformId(string $resource, ?string $old): ?int
    {
        if(empty($old))
            return null;

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
