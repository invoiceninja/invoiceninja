<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Queue\SerializesModels;

class ProductWasCreated extends Event
{
    use SerializesModels;

    /**
     * @var Product
     */
    public $product;

    /**
     * @var array
     **/
    public $input;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Product $product, $input = null)
    {
        $this->product = $product;
        $this->input = $input;
    }
}
