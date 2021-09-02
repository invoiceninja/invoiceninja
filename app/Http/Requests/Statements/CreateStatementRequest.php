<?php

namespace App\Http\Requests\Statements;

use App\Http\Requests\Request;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Number;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;

class CreateStatementRequest extends Request
{
    use MakesHash;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date'   => 'required|date_format:Y-m-d',
            'client_id'  => 'bail|required|exists:clients,id,company_id,'.auth()->user()->company()->id,
            'show_payments_table' => 'boolean',
            'show_aging_table' => 'boolean',
        ];
    }
    protected function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);
        
        $this->replace($input);
    }
    /**
     * The collection of invoices for the statement.
     *
     * @return Invoice[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getInvoices()
    {
        $input = $this->all();

        // $input['start_date & $input['end_date are available.
        $client = Client::where('id', $input['client_id'])->first();

        $from = Carbon::parse($input['start_date']);
        $to = Carbon::parse($input['end_date']);

        return Invoice::where('company_id', auth()->user()->company()->id)
                      ->where('client_id', $client->id)
                      ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])
                      ->whereBetween('date',[$from, $to])
                      ->get();
    }

    /**
     * The collection of payments for the statement.
     *
     * @return Payment[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getPayments()
    {
        // $input['start_date & $input['end_date are available.
        $input = $this->all();

        $client = Client::where('id', $input['client_id'])->first();

        $from = Carbon::parse($input['start_date']);
        $to = Carbon::parse($input['end_date']);

        return Payment::where('company_id', auth()->user()->company()->id)
                      ->where('client_id', $client->id)
                      ->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])
                      ->whereBetween('date',[$from, $to])
                      ->get();

    }


    /**
     * The array of aging data.
     */
    public function getAging(): array
    {
        return [
            '0-30' => $this->getAgingAmount('30'),
            '30-60' => $this->getAgingAmount('60'),
            '60-90' => $this->getAgingAmount('90'),
            '90-120' => $this->getAgingAmount('120'),
            '120+' => $this->getAgingAmount('120+'),
        ];
    }

    private function getAgingAmount($range)
    {
        $input = $this->all();

        $ranges = $this->calculateDateRanges($range);

        $from = $ranges[0];
        $to = $ranges[1];

        $client = Client::where('id', $input['client_id'])->first();

        $amount = Invoice::where('company_id', auth()->user()->company()->id)
                      ->where('client_id', $client->id)
                      ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                      ->where('balance', '>', 0)
                      ->whereBetween('date',[$from, $to])
                      ->sum('balance');

        return Number::formatMoney($amount, $client);
    }

    private function calculateDateRanges($range)
    {

        $ranges = [];

        switch ($range) {
            case '30':
                $ranges[0] = now();
                $ranges[1] = now()->subDays(30);
                    return $ranges;
                break;
            case '60':
                $ranges[0] = now()->subDays(30);
                $ranges[1] = now()->subDays(60);
                    return $ranges;
                break;
            case '90':
                $ranges[0] = now()->subDays(60);
                $ranges[1] = now()->subDays(90);
                    return $ranges;
                break;
            case '120':
                $ranges[0] = now()->subDays(90);
                $ranges[1] = now()->subDays(120);
                    return $ranges;
                break;
            case '120+':
                $ranges[0] = now()->subDays(120);
                $ranges[1] = now()->subYears(40);
                    return $ranges;
                break;            
            default:
                $ranges[0] = now()->subDays(0);
                $ranges[1] = now()->subDays(30);
                    return $ranges;
                break;
        }

    }
}
