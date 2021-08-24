<?php

namespace App\Http\Requests\Statements;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

class CreateStatementRequest extends FormRequest
{
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
            'start_date' => ['required'],
            'end_date' => ['required'],
        ];
    }

    /**
     * The collection of invoices for the statement.
     *
     * @return Invoice[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getInvoices()
    {
        // $this->request->start_date & $this->request->end_date are available.

        return Invoice::all();
    }

    /**
     * The collection of payments for the statement.
     *
     * @return Payment[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getPayments()
    {
        // $this->request->start_date & $this->request->end_date are available.

        return Payment::all();
    }

    /**
     * The array of aging data.
     */
    public function getAging(): array
    {
        return [
            '0-30' => 1000,
            '30-60' => 2000,
            '60-90' => 3000,
            '90-120' => 4000,
            '120+' => 5000,
        ];
    }
}
