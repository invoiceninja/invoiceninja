<?php namespace App\Services;

use Auth;
use Utils;
use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\ClientRepository;
use App\Events\QuoteInvitationWasApproved;
use App\Models\Invitation;

class InvoiceService extends BaseService
{
    protected $clientRepo;
    protected $invoiceRepo;
    protected $datatableService;

    public function __construct(ClientRepository $clientRepo, InvoiceRepository $invoiceRepo, DatatableService $datatableService)
    {
        $this->clientRepo = $clientRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->invoiceRepo;
    }

    public function save($data)
    {
        if (isset($data['client'])) {
            $client = $this->clientRepo->save($data['client']);
            $data['client_id'] = $client->id;
        }

        $invoice = $this->invoiceRepo->save($data);
        
        $client = $invoice->client;
        $client->load('contacts');
        $sendInvoiceIds = [];

        foreach ($client->contacts as $contact) {
            if ($contact->send_invoice || count($client->contacts) == 1) {
                $sendInvoiceIds[] = $contact->id;
            }
        }
        
        foreach ($client->contacts as $contact) {
            $invitation = Invitation::scope()->whereContactId($contact->id)->whereInvoiceId($invoice->id)->first();

            if (in_array($contact->id, $sendInvoiceIds) && !$invitation) {
                $invitation = Invitation::createNew();
                $invitation->invoice_id = $invoice->id;
                $invitation->contact_id = $contact->id;
                $invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
                $invitation->save();
            } elseif (!in_array($contact->id, $sendInvoiceIds) && $invitation) {
                $invitation->delete();
            }
        }

        return $invoice;
    }

    public function convertQuote($quote, $invitation = null)
    {
        $invoice = $this->invoiceRepo->cloneInvoice($quote, $quote->id);
        if (!$invitation) {
            return $invoice;
        }
        
        foreach ($invoice->invitations as $invoiceInvitation) {
            if ($invitation->contact_id == $invoiceInvitation->contact_id) {
                return $invoiceInvitation->invitation_key;
            }
        }
    }

    public function approveQuote($quote, $invitation = null)
    {
        $account = Auth::user()->account;
        if (!$quote->is_quote || $quote->quote_invoice_id) {
            return null;
        }
        
        if ($account->auto_convert_quote || ! $account->isPro()) {
            $invoice = $this->convertQuote($quote, $invitation);

            event(new QuoteInvitationWasApproved($quote, $invoice, $invitation));

            return $invoice;
        } else {
            $quote->markApproved();

            event(new QuoteInvitationWasApproved($quote, null, $invitation));
            
            foreach ($quote->invitations as $invoiceInvitation) {
                if ($invitation->contact_id == $invoiceInvitation->contact_id) {
                    return $invoiceInvitation->invitation_key;
                }
            }
        }
    }

    public function getDatatable($accountId, $clientPublicId = null, $entityType, $search)
    {
        $query = $this->invoiceRepo->getInvoices($accountId, $clientPublicId, $entityType, $search)
                    ->where('invoices.is_quote', '=', $entityType == ENTITY_QUOTE ? true : false);

        return $this->createDatatable($entityType, $query, !$clientPublicId);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'invoice_number',
                function ($model) use ($entityType) {
                    return link_to("{$entityType}s/{$model->public_id}/edit", $model->invoice_number, ['class' => Utils::getEntityRowClass($model)]); 
                }
            ],
            [
                'client_name',
                function ($model) {
                    return link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model));
                },
                ! $hideClient
            ],
            [
                'invoice_date',
                function ($model) {
                    return Utils::fromSqlDate($model->invoice_date);
                }
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);
                }
            ],
            [
                'balance',
                function ($model) {
                    return $model->partial > 0 ?
                        trans('texts.partial_remaining', [
                            'partial' => Utils::formatMoney($model->partial, $model->currency_id, $model->country_id),
                            'balance' => Utils::formatMoney($model->balance, $model->currency_id, $model->country_id)]
                        ) :
                        Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
                },
                $entityType == ENTITY_INVOICE
            ],
            [
                'due_date',
                function ($model) {
                    return Utils::fromSqlDate($model->due_date);
                },
            ],
            [
                'invoice_status_name',
                function ($model) {
                    return $model->quote_invoice_id ? link_to("invoices/{$model->quote_invoice_id}/edit", trans('texts.converted')) : self::getStatusLabel($model->invoice_status_id, $model->invoice_status_name);
                }
            ]
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                trans("texts.edit_{$entityType}"),
                function ($model) use ($entityType) {
                    return URL::to("{$entityType}s/{$model->public_id}/edit");
                }
            ],
            [
                trans("texts.clone_{$entityType}"),
                function ($model) use ($entityType) {
                    return URL::to("{$entityType}s/{$model->public_id}/clone");
                }
            ],
            [
                trans("texts.view_history"),
                function ($model) use ($entityType) {
                    return URL::to("{$entityType}s/{$entityType}_history/{$model->public_id}");
                }
            ],
            [],
            [
                trans("texts.mark_sent"),
                function ($model) {
                    return "javascript:markEntity({$model->public_id})";
                },
                function ($model) {
                    return $model->invoice_status_id < INVOICE_STATUS_SENT;
                }
            ],
            [
                trans('texts.enter_payment'),
                function ($model) {
                    return URL::to("payments/create/{$model->client_public_id}/{$model->public_id}");
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_INVOICE && $model->balance > 0;
                }
            ],
            [
                trans("texts.view_quote"),
                function ($model) {
                    return URL::to("quotes/{$model->quote_id}/edit");
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_INVOICE && $model->quote_id;
                }
            ],
            [
                trans("texts.view_invoice"),
                function ($model) {
                    return URL::to("invoices/{$model->quote_invoice_id}/edit");
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_QUOTE && $model->quote_invoice_id;
                }
            ],
            [
                trans("texts.convert_to_invoice"),
                function ($model) {
                    return "javascript:convertEntity({$model->public_id})";
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_QUOTE && ! $model->quote_invoice_id;
                }
            ]
        ];
    }

    private function getStatusLabel($statusId, $statusName)
    {
        $label = trans("texts.status_" . strtolower($statusName));
        $class = 'default';
        switch ($statusId) {
            case INVOICE_STATUS_SENT:
                $class = 'info';
                break;
            case INVOICE_STATUS_VIEWED:
                $class = 'warning';
                break;
            case INVOICE_STATUS_APPROVED:
                $class = 'success';
                break;
            case INVOICE_STATUS_PARTIAL:
                $class = 'primary';
                break;
            case INVOICE_STATUS_PAID:
                $class = 'success';
                break;
        }
        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }
    
}
