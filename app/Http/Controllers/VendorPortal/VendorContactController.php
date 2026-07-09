<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\VendorPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorPortal\EditVendorContactRequest;
use App\Http\Requests\VendorPortal\UpdateVendorContactRequest;
use App\Models\VendorContact;
use App\Utils\Traits\MakesHash;

class VendorContactController extends Controller
{
    use MakesHash;

    public const MODULE_RECURRING_INVOICES = 1;

    public const MODULE_CREDITS = 2;

    public const MODULE_QUOTES = 4;

    public const MODULE_TASKS = 8;

    public const MODULE_EXPENSES = 16;

    public const MODULE_PROJECTS = 32;

    public const MODULE_VENDORS = 64;

    public const MODULE_TICKETS = 128;

    public const MODULE_PROPOSALS = 256;

    public const MODULE_RECURRING_EXPENSES = 512;

    public const MODULE_RECURRING_TASKS = 1024;

    public const MODULE_RECURRING_QUOTES = 2048;

    public const MODULE_INVOICES = 4096;

    public const MODULE_PROFORMAL_INVOICES = 8192;

    public const MODULE_PURCHASE_ORDERS = 16384;

    public function edit(EditVendorContactRequest $request, VendorContact $vendor_contact)
    {

        if (!$vendor_contact->vendor->country_id) {
            $vendor_contact->vendor->country_id = auth()->guard('vendor')->user()->company->country()->id ?? 840;
        }

        return $this->render('vendor_profile.edit', [
            'contact' => $vendor_contact,
            'vendor' => $vendor_contact->vendor,
            'settings' => $vendor_contact->vendor->company->settings,
            'company' => $vendor_contact->vendor->company,
            'sidebar' => $this->sidebarMenu(),
            'countries' => app('countries'),
        ]);
    }

    public function update(UpdateVendorContactRequest $request, VendorContact $vendor_contact)
    {
        $vendor_contact->fill($request->only(['first_name', 'last_name', 'email', 'phone']));
        $vendor_contact->vendor->fill($request->only(['address1', 'address2', 'city', 'state', 'postal_code', 'country_id', 'public_notes']));
        $vendor_contact->push();

        return back()->withSuccess(ctrans('texts.profile_updated_successfully'));
    }

    private function sidebarMenu(): array
    {
        $enabled_modules = auth()->guard('vendor')->user()->company->enabled_modules;
        $data = [];

        if (self::MODULE_PURCHASE_ORDERS & $enabled_modules) {
            $data[] = ['title' => ctrans('texts.purchase_orders'), 'url' => 'vendor.purchase_orders.index', 'icon' => 'file-text', 'id' => 'purchase_orders'];
        }

        return $data;
    }
}
