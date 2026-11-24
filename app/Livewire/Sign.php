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

use Livewire\Component;
use App\Libraries\MultiDB;
use Livewire\Attributes\On;
use App\Models\QuoteInvitation;
use App\Models\CreditInvitation;
use App\Livewire\Flow2\DocuNinja;
use App\Models\InvoiceInvitation;
use Livewire\Attributes\Computed;
use App\Livewire\Flow2\DocuNinjaLoader;
use App\Models\PurchaseOrderInvitation;
use App\Utils\Traits\WithSecureContext;
use Livewire\Attributes\Locked;
use App\Services\Quote\QuoteOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Sign extends Component
{
    use WithSecureContext;

    #[Locked]
    public $invitation_id;

    #[Locked]
    public $entity_type;

    #[Locked]
    public $db;

    public $docu_ninja_ready = false;

    public $signature_accepted = false;

    #[Locked]
    public $request_hash;

    public $initializing = true;

    #[Locked]
    public $_key;

    #[Locked]
    public ?string $selection_key = null;

    public function mount()
    {
        MultiDB::setDb($this->db);

        if ($this->entity_type === 'quote') {
            $invitation = $this->resolveInvitationModel($this->invitation_id);
            $quote = $invitation?->quote;

            if ($quote?->hasPendingOptionSelection()) {
                $contact = auth()->guard('contact')->user();
                abort_unless($contact && $invitation->company_id === $contact->company_id
                    && $invitation->client_contact_id === $contact->id, 403);

                $this->selection_key = $quote->backup->option_selection['key'];
            }
        }

        $this->bulkSetContext($this->_key, [
            'entity_type' => $this->entity_type,
            'db' => $this->db,
            'invitation_id' => $this->invitation_id,
            'request_hash' => $this->request_hash,
            'selection_key' => $this->selection_key,
        ]);

        $this->initializing = false;
    }

    /**
     * Resolve the invitation model based on entity_type
     *
     * @param int $invitation_id
     * @return InvoiceInvitation|QuoteInvitation|CreditInvitation|PurchaseOrderInvitation|null
     */
    protected function resolveInvitationModel(int $invitation_id)
    {
        return match ($this->entity_type) {
            'invoice' => InvoiceInvitation::withTrashed()->with('contact.client', 'invoice')->find($invitation_id),
            'quote' => QuoteInvitation::withTrashed()->with('contact.client', 'quote')->find($invitation_id),
            'credit' => CreditInvitation::withTrashed()->with('contact.client', 'credit')->find($invitation_id),
            'purchase_order' => PurchaseOrderInvitation::withTrashed()->with('contact.vendor', 'purchase_order')->find($invitation_id),
            default => InvoiceInvitation::withTrashed()->with('contact.client', 'invoice')->find($invitation_id),
        };
    }

    #[Computed()]
    public function component(): ?string
    {

        if ($this->docu_ninja_ready) {
            return DocuNinja::class;
        } else {
            return DocuNinjaLoader::class;
        }

    }

    #[On('docuninja-signature-captured')]
    public function docuNinjaSignatureCaptured()
    {

        if (!$this->signature_accepted) {
            $this->signature_accepted = true;
        }

        $this->processPayment();

    }

    #[On('docuninja-loader-ready')]
    public function docuninjaLoaderReady()
    {
        $this->docu_ninja_ready = true;
    }

    public function processPayment()
    {

        $invitation = $this->resolveInvitationModel($this->invitation_id);
        $invitation->{$this->entity_type}->sync->dn_completed = true;
        $invitation->{$this->entity_type}->save();

        if ($this->entity_type == 'invoice') {
            $this->redirectRoute('client.payments.process', ['request_hash' => $this->request_hash]);
        } elseif ($this->entity_type == 'quote' && $this->request_hash) {
            $this->redirectRoute('client.quotes.approval.continue', ['request_hash' => $this->request_hash]);
        } elseif ($this->entity_type == 'quote') {
            $this->dispatch('quote-signed');
        }

    }

    #[Computed()]
    public function componentUniqueId(): string
    {
        return "sign-" . md5(microtime());
    }

    public function checkSigningState(): void
    {
        $this->skipRender();

        if ($this->entity_type !== 'quote' || $this->selection_key === null) {
            return;
        }

        MultiDB::setDb($this->db);
        $invitation = $this->resolveInvitationModel($this->invitation_id);
        $contact = auth()->guard('contact')->user();
        abort_unless($contact && (! $invitation
            || ($invitation->company_id === $contact->company_id
                && $invitation->client_contact_id === $contact->id)), 403);

        $quote = $invitation?->quote;

        if ($quote && $quote->hasPendingOptionSelection()
            && $quote->backup->option_selection['key'] === $this->selection_key) {
            return;
        }

        $this->redirectRoute(
            $quote ? 'client.quote.show' : 'client.quotes.index',
            $quote ? ['quote' => $quote->hashed_id] : [],
        );
    }

    #[On('docuninja-signing-cancelled')]
    public function cancelQuoteSigning(string $document_id, string $document_invitation_id): void
    {
        $this->skipRender();

        if ($this->entity_type !== 'quote' || $this->selection_key === null) {
            return;
        }

        MultiDB::setDb($this->db);
        $invitation = $this->resolveInvitationModel($this->invitation_id);
        $contact = auth()->guard('contact')->user();
        abort_unless($contact && (! $invitation
            || ($invitation->company_id === $contact->company_id
                && $invitation->client_contact_id === $contact->id)), 403);

        $quote = $invitation?->quote;

        if (! $quote) {
            $this->redirectRoute('client.quotes.index');
            return;
        }

        abort_if(! $invitation->can_sign
            && $quote->invitations()->where('can_sign', true)->exists(), 403);

        $document = $quote->sync?->getInvitation($invitation->key);

        if (($document['dn_id'] ?? null) !== $document_id
            || ($document['dn_invitation_id'] ?? null) !== $document_invitation_id) {
            return;
        }

        try {
            app(QuoteOptions::class)->restart($quote, $this->selection_key);
        } catch (ValidationException | HttpException $exception) {
            if ($exception instanceof HttpException && $exception->getStatusCode() !== 409) {
                throw $exception;
            }

            session()->flash('message', ctrans('texts.quote_options_changed'));
            $this->redirectRoute('client.quote.show', ['quote' => $quote->hashed_id]);
            return;
        }

        Cache::forget($this->_key);
        if ($this->request_hash) {
            Cache::forget($this->request_hash);
        }

        $this->redirectRoute('client.quote.show', ['quote' => $quote->hashed_id]);
    }

    public function render()
    {
        return render('components.livewire.sign', ['_key' => $this->_key]);
    }
}
