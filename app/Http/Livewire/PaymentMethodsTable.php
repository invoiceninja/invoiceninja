<?php

 


namespace App\Http\Livewire;

use App\Libraries\MultiDB;
use App\Models\ClientGatewayToken;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentMethodsTable extends Component
{
    use WithPagination;
    use WithSorting;

    public $per_page = 10;
    
    public $client;

    public $company;
    
    public function mount($client)
    {
    
        MultiDB::setDb($this->company->db);

        $this->client = $client;
    }

    public function render()
    {
        $query = ClientGatewayToken::query()
            ->with('gateway_type')
            ->where('client_id', $this->client->id)
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->paginate($this->per_page);

        return render('components.livewire.payment-methods-table', [
            'payment_methods' => $query,
        ]);
    }
}
