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

namespace App\Livewire;

use App\DataMapper\InvoiceItem;
use App\Http\ViewComposers\PortalComposer;
use App\Jobs\EDocument\CreateEDocument;
use App\Libraries\MultiDB;
use App\Models\BaseModel;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceInvitation;
use App\Services\Pdf\Markdown;
use App\Utils\HtmlEngine;
use App\Utils\Number;
use App\Utils\Traits\MakesHash;
use App\Utils\VendorHtmlEngine;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfSlot extends Component
{
    use MakesHash;

    /** @var array<string, class-string<BaseModel>> */
    private const ENTITY_CLASSES = [
        'invoice' => Invoice::class,
        'quote' => Quote::class,
        'credit' => Credit::class,
        'recurring_invoice' => RecurringInvoice::class,
        'purchase_order' => PurchaseOrder::class,
    ];

    /** @var array<string, int> */
    private const ENTITY_MODULES = [
        'invoice' => PortalComposer::MODULE_INVOICES,
        'quote' => PortalComposer::MODULE_QUOTES,
        'credit' => PortalComposer::MODULE_CREDITS,
        'recurring_invoice' => PortalComposer::MODULE_RECURRING_INVOICES,
        'purchase_order' => PortalComposer::MODULE_PURCHASE_ORDERS,
    ];

    #[Locked]
    public string $entity_type;

    #[Locked]
    public string $entity_key;

    #[Locked]
    public ?string $invitation_key = null;

    #[Locked]
    public ?string $pdf = null;

    #[Locked]
    public ?string $with_close_button = null;

    private Invoice|Quote|Credit|RecurringInvoice|PurchaseOrder|null $resolved_entity = null;

    private InvoiceInvitation|QuoteInvitation|CreditInvitation|RecurringInvoiceInvitation|PurchaseOrderInvitation|null $resolved_invitation = null;

    private object $settings;

    /** @var array{labels: array<string, string>, values: array<string, string>} */
    private array $html_variables;

    private bool $preference_product_notes_for_html_view = false;

    public function mount(
        string $entity_type,
        string $entity_key,
        string $db,
        ?string $invitation_key = null,
        ?string $with_close_button = null,
    ): void {
        MultiDB::setDb($db);

        $this->entity_type = $entity_type;
        $this->entity_key = $entity_key;
        $this->invitation_key = $invitation_key;
        $this->with_close_button = $with_close_button;

        $entity = $this->resolveEntity();

        if (! $this->invitation_key) {
            $entity->service()->createInvitations();
        }

        $invitation = $this->resolveInvitation();
        $this->invitation_key = $invitation->key;
    }

    private function resolveEntity(): Invoice|Quote|Credit|RecurringInvoice|PurchaseOrder
    {
        if ($this->resolved_entity) {
            return $this->resolved_entity;
        }

        $class = self::ENTITY_CLASSES[$this->entity_type] ?? null;

        if (! $class) {
            abort(404);
        }

        $entity_id = $this->decodePrimaryKey($this->entity_key, true);

        if (! is_int($entity_id)) {
            abort(404);
        }

        $entity = $class::query()->withTrashed()->findOrFail($entity_id);

        $this->authorizeEntity($entity);

        return $this->resolved_entity = $entity;
    }

    private function resolveInvitation(): InvoiceInvitation|QuoteInvitation|CreditInvitation|RecurringInvoiceInvitation|PurchaseOrderInvitation
    {
        if ($this->resolved_invitation) {
            return $this->resolved_invitation;
        }

        $entity = $this->resolveEntity();
        $query = $entity->invitations();

        if ($this->invitation_key) {
            $query->where('key', $this->invitation_key);
        } elseif ($this->entity_type === 'purchase_order') {
            $query->where('vendor_contact_id', auth()->guard('vendor')->id());
        } else {
            $query->where('client_contact_id', auth()->guard('contact')->id());
        }

        $invitation = $query->first();

        if (! $invitation && ! $this->invitation_key) {
            $invitation = $entity->invitations()->first();
        }

        if (
            ! $invitation instanceof InvoiceInvitation
            && ! $invitation instanceof QuoteInvitation
            && ! $invitation instanceof CreditInvitation
            && ! $invitation instanceof RecurringInvoiceInvitation
            && ! $invitation instanceof PurchaseOrderInvitation
        ) {
            abort(404);
        }

        $this->authorizeInvitation($invitation);
        $invitation->setRelation($this->entity_type, $entity);

        return $this->resolved_invitation = $invitation;
    }

    public function getPdf(): void
    {
        $invitation = $this->resolveInvitation();
        $route = $this->entity_type === 'purchase_order'
            ? 'vendor.purchase_order.showBlob'
            : 'client.invoices.showBlob';

        $this->pdf = route($route, [
            'entity_type' => $this->entity_type,
            'invitation_key' => $invitation->key,
        ], false);
    }

    public function downloadPdf(): StreamedResponse
    {
        $entity = $this->resolveEntity();
        $invitation = $this->resolveInvitation();

        $file_name = $entity->numberFormatter() . '.pdf';

        $file = (new \App\Jobs\Entity\CreateRawPdf($invitation))->handle();

        $headers = ['Content-Type' => 'application/pdf'];

        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $file_name, $headers);

    }

    public function downloadEDocument(): StreamedResponse
    {
        $entity = $this->resolveEntity();

        abort_unless($entity instanceof Invoice || $entity instanceof Quote || $entity instanceof Credit, 404);

        $file_name = $entity->numberFormatter() . '.xml';

        $file = (new CreateEDocument($entity))->handle();

        $headers = ['Content-Type' => 'application/xml'];

        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $file_name, $headers);

    }

    public function render(): View
    {
        $entity = $this->resolveEntity();
        $invitation = $this->resolveInvitation();
        $entity_calc = $entity->calc();

        $this->settings = $entity->client ? $entity->client->getMergedSettings() : $entity->company->settings;
        $html_entity_option = $entity->client ? $entity->client->getSetting('show_pdfhtml_on_mobile') : $entity->company->getSetting('show_pdfhtml_on_mobile');
        $this->preference_product_notes_for_html_view = $entity->client ? $entity->client->getSetting('preference_product_notes_for_html_view') : $entity->company->getSetting('preference_product_notes_for_html_view');

        $show_cost = in_array('$product.unit_cost', $this->settings->pdf_variables->product_columns);
        $show_line_total = in_array('$product.line_total', $this->settings->pdf_variables->product_columns);
        $show_quantity = in_array('$product.quantity', $this->settings->pdf_variables->product_columns);
        $show_tags = in_array('$product.tags', $this->settings->pdf_variables->product_columns);

        if ($this->entity_type === 'quote' && ! $this->settings->sync_invoice_quote_columns) {
            $show_cost = in_array('$product.unit_cost', $this->settings->pdf_variables->product_quote_columns);
            $show_quantity = in_array('$product.quantity', $this->settings->pdf_variables->product_quote_columns);
            $show_line_total = in_array('$product.line_total', $this->settings->pdf_variables->product_quote_columns);
            $show_tags = in_array('$product.tags', $this->settings->pdf_variables->product_quote_columns);
        }

        $this->html_variables = $invitation instanceof PurchaseOrderInvitation
                            ? (new VendorHtmlEngine($invitation))->generateLabelsAndValues()
                            : (new HtmlEngine($invitation))->generateLabelsAndValues();

        $terms = $entity->parseHtmlVariables('terms', $this->html_variables);
        $public_notes = $entity->parseHtmlVariables('public_notes', $this->html_variables);

        return render('components.livewire.pdf-slot', [
            'invitation' => $invitation,
            'entity' => $entity,
            'settings' => $this->settings,
            'data' => $invitation->company->settings,
            'entity_type' => $this->entity_type,
            'products' => $this->getProducts(),
            'services' => $this->getServices(),
            'amount' => Number::formatMoney($entity->amount, $entity->client ?: $entity->vendor),
            'balance' => Number::formatMoney($entity->partial > 0 ? $entity->partial : $entity->balance, $entity->client ?: $entity->vendor),
            'discount' => $entity_calc->getTotalDiscount() > 0 ? Number::formatMoney($entity_calc->getTotalDiscount(), $entity->client ?: $entity->vendor) : false,
            'taxes' => $entity_calc->getTotalTaxes() > 0 ? Number::formatMoney($entity_calc->getTotalTaxes(), $entity->client ?: $entity->vendor) : false,
            'company_details' => $this->getCompanyDetails(),
            'company_address' => $this->getCompanyAddress(),
            'entity_details' => $this->getEntityDetails(),
            'user_details' => $this->getUserDetails(),
            'user_name' => $this->getUserName(),
            'terms' => $terms,
            'public_notes' => $public_notes,
            'html_entity_option' => $html_entity_option,
            'show_cost' => $show_cost,
            'show_line_total' => $show_line_total,
            'show_quantity' => $show_quantity,
            'show_tags' => $show_tags,
            'is_quote' => $this->entity_type === 'quote',
            'pdf' => $this->pdf,
            'with_close_button' => $this->with_close_button,
        ]);
    }

    private function convertVariables(string $string): string
    {

        $html = strtr($string, $this->html_variables['labels']);
        $html = strtr($html, $this->html_variables['values']);

        return $html;

    }

    private function getCompanyAddress(): string
    {

        $company_address = "";

        foreach ($this->settings->pdf_variables?->company_address as $variable) {
            $company_address .= "<p>{$variable}</p>";
        }

        return $this->convertVariables($company_address);

    }

    private function getCompanyDetails(): string
    {
        $company_details = "";

        foreach ($this->settings->pdf_variables->company_details as $variable) {
            $company_details .= "<p>{$variable}</p>";
        }

        return $this->convertVariables($company_details);

    }

    private function getEntityDetails(): string
    {
        $entity_details = "";

        if ($this->entity_type === 'invoice' || $this->entity_type === 'recurring_invoice') {
            foreach ($this->settings->pdf_variables->invoice_details as $variable) {
                $entity_details .= "<div class='flex px-5 block'><p class= w-36 block'>{$variable}_label</p><p class='ml-5 w-36 block entity-field'>{$variable}</p></div>";
            }

        } elseif ($this->entity_type === 'quote') {
            foreach ($this->settings->pdf_variables->quote_details ?? [] as $variable) {
                $entity_details .= "<div class='flex px-5 block'><p class= w-36 block'>{$variable}_label</p><p class='ml-5 w-36 block entity-field'>{$variable}</p></div>";
            }
        } elseif ($this->entity_type === 'credit') {
            foreach ($this->settings->pdf_variables->credit_details ?? [] as $variable) {
                $entity_details .= "<div class='flex px-5 block'><p class= w-36 block'>{$variable}_label</p><p class='ml-5 w-36 block entity-field'>{$variable}</p></div>";
            }
        } elseif ($this->entity_type === 'purchase_order') {
            foreach ($this->settings->pdf_variables->purchase_order_details ?? [] as $variable) {
                $entity_details .= "<div class='flex px-5 block'><p class= w-36 block'>{$variable}_label</p><p class='ml-5 w-36 block entity-field'>{$variable}</p></div>";
            }
        }

        return $this->convertVariables($entity_details);

    }

    private function getUserName(): string
    {
        $name = ctrans('texts.details');

        if ($this->entity_type === 'purchase_order' && isset($this->settings->pdf_variables->vendor_details[0])) {
            $name = $this->settings->pdf_variables->vendor_details[0];

        } elseif (isset($this->settings->pdf_variables->client_details[0])) {

            $name = $this->settings->pdf_variables->client_details[0];
        }

        return $this->convertVariables($name);

    }

    private function getUserDetails(): string
    {
        $user_details = "";

        if ($this->entity_type === 'purchase_order') {
            foreach (array_slice($this->settings->pdf_variables->vendor_details, 1) as $variable) {
                $user_details .= "<p>{$variable}</p>";
            }
        } else {
            foreach (array_slice($this->settings->pdf_variables->client_details, 1) as $variable) {
                $user_details .= "<p>{$variable}</p>";
            }
        }

        return $this->convertVariables($user_details);
    }

    private function getProducts(): Collection
    {
        $entity = $this->resolveEntity();
        $invitation = $this->resolveInvitation();

        $product_items = collect($entity->line_items)->filter(function ($item) {
            return $item->type_id == 1 || $item->type_id == 6 || $item->type_id == 5;
        })->map(function ($item) use ($entity, $invitation) {

            //$notes = strlen($item->notes) > 4 ? $item->notes : $item->product_key;
            $notes = $this->preference_product_notes_for_html_view ? $item->notes : $item->product_key;

            return [
                'quantity' => $item->quantity,
                'cost' => Number::formatMoney($item->cost, $entity->client ?: $entity->vendor),
                'notes' => $invitation->company->markdown_enabled ? Markdown::parse($notes) : $notes,
                'tags' => InvoiceItem::tagNames($item->tags ?? ''),
                'line_total' => Number::formatMoney($item->line_total, $entity->client ?: $entity->vendor),
            ];
        });

        return $product_items;
    }

    private function getServices(): Collection
    {
        $entity = $this->resolveEntity();
        $invitation = $this->resolveInvitation();

        $task_items = collect($entity->line_items)->filter(function ($item) {
            return $item->type_id == 2;
        })->map(function ($item) use ($entity, $invitation) {
            return [
                'quantity' => $item->quantity,
                'cost' => Number::formatMoney($item->cost, $entity->client ?: $entity->vendor),
                'notes' => $invitation->company->markdown_enabled ? Markdown::parse($item->notes) : $item->notes,
                'line_total' => Number::formatMoney($item->line_total, $entity->client ?: $entity->vendor),
            ];
        });

        return $task_items;

    }

    private function authorizeEntity(Invoice|Quote|Credit|RecurringInvoice|PurchaseOrder $entity): void
    {
        $module = self::ENTITY_MODULES[$this->entity_type] ?? null;

        if ($module === null) {
            abort(404);
        }

        if ($entity instanceof PurchaseOrder) {
            $contact = auth()->guard('vendor')->user();

            abort_unless(
                $contact
                && (int) $contact->vendor_id === (int) $entity->vendor_id
                && (int) $contact->company_id === (int) $entity->company_id
                && (bool) ($entity->company->enabled_modules & $module),
                403
            );

            return;
        }

        $contact = auth()->guard('contact')->user();

        abort_unless(
            $contact
            && (int) $contact->client_id === (int) $entity->client_id
            && (int) $contact->company_id === (int) $entity->company_id
            && (bool) ($entity->company->enabled_modules & $module)
            && (! ($entity instanceof Credit) || ! $entity->is_deleted),
            403
        );
    }

    private function authorizeInvitation(InvoiceInvitation|QuoteInvitation|CreditInvitation|RecurringInvoiceInvitation|PurchaseOrderInvitation $invitation): void
    {
        if ($invitation instanceof PurchaseOrderInvitation) {
            $contact = auth()->guard('vendor')->user();

            abort_unless(
                $contact
                && (int) $invitation->contact->vendor_id === (int) $contact->vendor_id
                && (int) $invitation->company_id === (int) $contact->company_id,
                403
            );

            return;
        }

        $contact = auth()->guard('contact')->user();

        abort_unless(
            $contact
            && (int) $invitation->contact->client_id === (int) $contact->client_id
            && (int) $invitation->company_id === (int) $contact->company_id,
            403
        );
    }
}
