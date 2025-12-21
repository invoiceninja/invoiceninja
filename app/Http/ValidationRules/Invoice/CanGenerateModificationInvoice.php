<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\Invoice;

use Closure;
use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class CanGenerateModificationInvoice.
 * @deprecated
 */
class CanGenerateModificationInvoice implements ValidationRule
{
    use MakesHash;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if (empty($value)) {
            return;
        }

        $user = auth()->user();

        $company = $user->company();

        /** For verifactu, we do not allow restores of deleted invoices */
        if (!$company->verifactuEnabled())
            $fail("Verifactu no está habilitado para esta empresa"); // Verifactu is not enabled for this company

        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($value));
        
        \DB::connection(config('database.default'))->beginTransaction();

            $array_data = request()->all();
            unset($array_data['client_id']);
            $invoice->fill($array_data);
            $total = $invoice->calc()->getTotal();

        \DB::connection(config('database.default'))->rollBack();

        if (is_null($invoice)) {
            $fail("Factura no encontrada."); // Invoice not found
        } elseif($invoice->is_deleted) {
            $fail("No se puede crear una factura de rectificación para una factura eliminada."); // Cannot create a rectification invoice for a deleted invoice
        } elseif($invoice->backup->document_type !== 'F1') {
            $fail("Solo las facturas originales F1 pueden ser rectificadas."); // Only original F1 invoices can be rectified
        } elseif($invoice->status_id === Invoice::STATUS_DRAFT){
            $fail("No se puede crear una factura de rectificación para una factura en borrador."); // Cannot create a rectification invoice for a draft invoice
        } elseif(in_array($invoice->status_id, [Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])) {
            $fail("No se puede crear una factura de rectificación cuando se ha realizado un pago."); // Cannot create a rectification invoice where a payment has been made
        } elseif($invoice->status_id === Invoice::STATUS_CANCELLED  ) {
            $fail("No se puede crear una factura de rectificación para una factura cancelada."); // Cannot create a rectification invoice for a cancelled invoice
        } elseif($invoice->status_id === Invoice::STATUS_REVERSED) {
            $fail("No se puede crear una factura de rectificación para una factura revertida."); // Cannot create a rectification invoice for a reversed invoice
        } elseif($invoice->backup->adjustable_amount < abs($total)){
            $fail("El importe de la factura de rectificación no puede ser mayor al importe de la factura original."); // The rectification invoice amount cannot be greater than the original invoice amount
        }
        // } elseif ($invoice->status_id !== Invoice::STATUS_SENT) {
        //     $fail("Cannot create a modification invoice.");
        // } elseif($invoice->amount <= 0){
        //     $fail("Cannot create a modification invoice for an invoice with an amount less than 0.");
        

    }
}
