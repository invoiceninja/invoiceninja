<?php namespace App\Services;

use Auth;
use Utils;
use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\ClientRepository;
use App\Events\QuoteInvitationWasApproved;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Payment;

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

    public function save($data, $invoice = null)
    {
        if (isset($data['client'])) {
            $canSaveClient = false;
            $clientPublicId = array_get($data, 'client.public_id') ?: array_get($data, 'client.id'); 
            if (empty($clientPublicId) || $clientPublicId == '-1') {
                $canSaveClient = Auth::user()->can('create', ENTITY_CLIENT);
            } else {
                $canSaveClient = Auth::user()->can('edit', Client::scope($clientPublicId)->first());
            }            
            if ($canSaveClient) {
                $client = $this->clientRepo->save($data['client']);
                $data['client_id'] = $client->id;
            }
        }

        $invoice = $this->invoiceRepo->save($data, $invoice);

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
        $account = $quote->account;
        
        if (!$quote->is_quote || $quote->quote_invoice_id) {
            return null;
        }

        if ($account->auto_convert_quote || ! $account->hasFeature(FEATURE_QUOTES)) {
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

        if(!Utils::hasPermission('view_all')){
            $query->where('invoices.user_id', '=', Auth::user()->id);
        }
        
        return $this->createDatatable($entityType, $query, !$clientPublicId);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'invoice_number',
                function ($model) use ($entityType) {
                    if(!Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id])){
                        return $model->invoice_number;
                    }
                    
                    return link_to("{$entityType}s/{$model->public_id}/edit", $model->invoice_number, ['class' => Utils::getEntityRowClass($model)])->toHtml();
                }
            ],
            [
                'client_name',
                function ($model) {
                    if(!Auth::user()->can('viewByOwner', [ENTITY_CLIENT, $model->client_user_id])){
                        return Utils::getClientDisplayName($model);
                    }
                    return link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml();
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
                function ($model) use ($entityType) {
                    return $model->quote_invoice_id ? link_to("invoices/{$model->quote_invoice_id}/edit", trans('texts.converted'))->toHtml() : self::getStatusLabel($entityType, $model);
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
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]);
                }
            ],
            [
                trans("texts.clone_{$entityType}"),
                function ($model) use ($entityType) {
                    return URL::to("{$entityType}s/{$model->public_id}/clone");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_INVOICE);
                }
            ],
            [
                trans("texts.view_history"),
                function ($model) use ($entityType) {
                    return URL::to("{$entityType}s/{$entityType}_history/{$model->public_id}");
                }
            ],
            [
                '--divider--', function(){return false;},
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]) || Auth::user()->can('create', ENTITY_PAYMENT);
                }
            ],
            [
                trans("texts.mark_sent"),
                function ($model) {
                    return "javascript:markEntity({$model->public_id})";
                },
                function ($model) {
                    return $model->invoice_status_id < INVOICE_STATUS_SENT && Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]);
                }
            ],
            [
                trans('texts.enter_payment'),
                function ($model) {
                    return URL::to("payments/create/{$model->client_public_id}/{$model->public_id}");
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_INVOICE && $model->balance > 0 && Auth::user()->can('create', ENTITY_PAYMENT);
                }
            ],
            [
                trans("texts.view_quote"),
                function ($model) {
                    return URL::to("quotes/{$model->quote_id}/edit");
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_INVOICE && $model->quote_id && Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]);
                }
            ],
            [
                trans("texts.view_invoice"),
                function ($model) {
                    return URL::to("invoices/{$model->quote_invoice_id}/edit");
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_QUOTE && $model->quote_invoice_id && Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]);
                }
            ],
            [
                trans("texts.convert_to_invoice"),
                function ($model) {
                    return "javascript:convertEntity({$model->public_id})";
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_QUOTE && ! $model->quote_invoice_id && Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]);
                }
            ]
        ];
    }

    private function getStatusLabel($entityType, $model)
    {
        // check if invoice is overdue
        if (Utils::parseFloat($model->balance) && $model->due_date && $model->due_date != '0000-00-00') {
            if (\DateTime::createFromFormat('Y-m-d', $model->due_date) < new \DateTime("now")) {
                $label = $entityType == ENTITY_INVOICE ? trans('texts.overdue') : trans('texts.expired');
                return "<h4><div class=\"label label-danger\">" . $label . "</div></h4>";
            }
        }

        $label = trans("texts.status_" . strtolower($model->invoice_status_name));
        $class = 'default';
        switch ($model->invoice_status_id) {
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
