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

namespace App\Livewire\BillingPortal;

use App\Libraries\MultiDB;
use App\Livewire\BillingPortal\Cart\Cart;
use App\Livewire\BillingPortal\Payments\Methods;
use App\Models\Subscription;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Str;

class Purchase extends Component
{
    public Subscription $subscription;

    public string $db;

    public array $request_data;

    public string $hash;

    public ?string $campaign;

    // 

    public int $step = 0;

    public array $steps = [
        Setup::class,
        Cart::class,
        Authentication::class,
        RFF::class,
        Methods::class,
        Submit::class,
    ];

    public string $id;

    /**  $context = [   
     *  'hash' => string,
     *  'quantity' => int,
     *  'request_data' => [
     *      'q'  => string,
     *  ],
     *  'campaign => ?string,
     *  'bundle' => $bundle, 
     *  'contact' =>  [
     *     'first_name' => ?string,
     *     'last_name' => ?string,
     *     'phone' => ?string,
     *     'custom_value1' => ?string,
     *     'custom_value2' => ?string,
     *     'custom_value3' => ?string,
     *     'custom_value4' => ?string,
     *     'email' => ?string,
     *     'email_verified_at' => ?int,
     *     'confirmation_code' => ?string,
     *     'is_primary' => bool,
     *     'confirmed' => bool,
     *     'last_login' => ?datetime,
     *     'failed_logins' => ?int,
     *     'accepted_terms_version' => ?string,
     *     'avatar' => ?string,
     *     'avatar_type' => ?string,
     *     'avatar_size' => ?string,
     *     'is_locked' => bool,
     *     'send_email' => bool,
     *     'contact_key' => string,
     *     'created_at' => int,
     *     'updated_at' => int,
     *     'deleted_at' => int,
     *     'hashed_id' => string,  
     * ], 
     *  'client_id' => string, 
     *  'quantity' => int, 
     *  'products' => [
     *      [
     *       'product_key' => string,
     *       'quantity' => int,
     *       'total_raw' => float,
     *       'total' => string,
     *      ],
     *  ]
     * ];
     * 
     *  $bundle =[
     *  'optional_one_time_products' => array, 
     *  'one_time_products' => array, 
     *  'optional_recurring_products' => array, 
     *  'recurring_products' => [
     *      'hashed_id' => [
     *          'product' => [
     *          'id' => int,
     *          'company_id' => int,
     *          'user_id' => int,
     *          'assigned_user_id' => ?int,
     *          'project_id' => ?int,
     *          'vendor_id' => ?int,
     *          'custom_value1' => ?string,
     *          'custom_value2' => ?string,
     *          'custom_value3' => ?string,
     *          'custom_value4' => ?string,
     *          'product_key' => ?string,
     *          'notes' => ?string,
     *          'cost' => float,
     *          'price' => float,
     *          'quantity' => float,
     *          'tax_name1' => ?string,
     *          'tax_rate1' => float,
     *          'tax_name2' => ?string,
     *          'tax_rate2' => float,
     *          'tax_name3' => ?string,
     *          'tax_rate3' => float,
     *          'deleted_at' => ?int,
     *          'created_at' => ?int,
     *          'updated_at' => ?int,
     *          'is_deleted' => bool,
     *          'in_stock_quantity' => ?int,
     *          'stock_notification' => bool,
     *          'stock_notification_threshold' => ?int,
     *          'max_quantity' => ?int,
     *          'product_image' => ?string,
     *          'tax_id' => ?int,
     *          'hashed_id' => ?string,
     *          ],
     *      ],
     *  ],
     * ] $bundle
     */
    public array $context = [];

    #[On('purchase.context')]
    public function handleContext(string $property, $value): self
    {
        $clone = $this->context;

        data_set($this->context, $property, $value);

        // The following may not be needed, as we can pass arround $context.
        // cache()->set($this->hash, $this->context);

        if ($clone !== $this->context) {
            $this->id = Str::uuid();
        }

        return $this;
    }

    #[On('purchase.next')]
    public function handleNext(): void
    {
        if ($this->step < count($this->steps) - 1) {
            $this->step++;
        }

        $this->id = Str::uuid();
    }

    #[On('purchase.forward')]
    public function handleForward(string $component): void
    {
        $this->step = array_search($component, $this->steps);

        $this->id = Str::uuid();
    }

    #[Computed()]
    public function component(): string
    {
        return $this->steps[$this->step];
    }

    #[Computed()]
    public function componentUniqueId(): string
    {
        return "purchase-{$this->id}";
    }

    #[Computed()]
    public function summaryUniqueId(): string
    {
        return "summary-{$this->id}";
    }

    public function mount()
    {
        $this->id = Str::uuid();

        MultiDB::setDb($this->db);

        $this
            ->handleContext('hash', $this->hash)
            ->handleContext('quantity', 1)
            ->handleContext('request_data', $this->request_data)
            ->handleContext('campaign', $this->campaign);
    }

    public function render()
    {
        return view('billing-portal.v3.purchase');
    }
}
