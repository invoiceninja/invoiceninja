<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Invoice;
use App\Ninja\Datatables\PaymentDatatable;
use App\Ninja\Repositories\AccountRepository;
use App\Ninja\Repositories\PaymentRepository;
use Auth;
use Exception;
use Utils;

class PaymentService extends BaseService
{
    /**
     * PaymentService constructor.
     *
     * @param PaymentRepository $paymentRepo
     * @param AccountRepository $accountRepo
     * @param DatatableService  $datatableService
     */
    public function __construct(
        PaymentRepository $paymentRepo,
        AccountRepository $accountRepo,
        DatatableService $datatableService
    ) {
        $this->datatableService = $datatableService;
        $this->paymentRepo = $paymentRepo;
        $this->accountRepo = $accountRepo;
    }

    /**
     * @return PaymentRepository
     */
    protected function getRepo()
    {
        return $this->paymentRepo;
    }

    /**
     * @param Invoice $invoice
     *
     * @return bool
     */
    public function autoBillInvoice(Invoice $invoice)
    {
        if (! $invoice->canBePaid()) {
            return false;
        }

        /** @var \App\Models\Client $client */
        $client = $invoice->client;

        /** @var \App\Models\Account $account */
        $account = $client->account;

        /** @var \App\Models\Invitation $invitation */
        $invitation = $invoice->invitations->first();

        if (! $invitation) {
            return false;
        }

        $invoice->markSentIfUnsent();

        if ($credits = $client->credits->sum('balance')) {
            $balance = $invoice->balance;
            $amount = min($credits, $balance);
            $data = [
                'payment_type_id' => PAYMENT_TYPE_CREDIT,
                'invoice_id' => $invoice->id,
                'client_id' => $client->id,
                'amount' => $amount,
            ];
            $payment = $this->paymentRepo->save($data);
            if ($amount == $balance) {
                return $payment;
            }
        }

        $paymentDriver = $account->paymentDriver($invitation, GATEWAY_TYPE_TOKEN);

        if (! $paymentDriver) {
            return false;
        }

        $customer = $paymentDriver->customer();

        if (! $customer) {
            return false;
        }

        $paymentMethod = $customer->default_payment_method;

        if (! $paymentMethod) {
            return false;
        }

        if ($paymentMethod->requiresDelayedAutoBill()) {
            $invoiceDate = \DateTime::createFromFormat('Y-m-d', $invoice->invoice_date);
            $minDueDate = clone $invoiceDate;
            $minDueDate->modify('+10 days');

            if (date_create() < $minDueDate) {
                // Can't auto bill now
                return false;
            }

            if ($invoice->partial > 0) {
                // The amount would be different than the amount in the email
                return false;
            }

            $firstUpdate = Activity::where('invoice_id', '=', $invoice->id)
                ->where('activity_type_id', '=', ACTIVITY_TYPE_UPDATE_INVOICE)
                ->first();

            if ($firstUpdate) {
                $backup = json_decode($firstUpdate->json_backup);

                if ($backup->balance != $invoice->balance || $backup->due_date != $invoice->due_date) {
                    // It's changed since we sent the email can't bill now
                    return false;
                }
            }

            if ($invoice->payments->count()) {
                // ACH requirements are strict; don't auto bill this
                return false;
            }
        }

        try {
            return $paymentDriver->completeOnsitePurchase(false, $paymentMethod);
        } catch (Exception $exception) {
            if (! Auth::check()) {
                $subject = trans('texts.auto_bill_failed', ['invoice_number' => $invoice->invoice_number]);
                $message = sprintf('%s: %s', ucwords($paymentDriver->providerName()), $exception->getMessage());
                $mailer = app('App\Ninja\Mailers\UserMailer');
                $mailer->sendMessage($invoice->user, $subject, $message, $invoice);
            }

            return false;
        }
    }

    public function save($input, $payment = null)
    {
        return $this->paymentRepo->save($input, $payment);
    }


    public function getDatatable($clientPublicId, $search)
    {
        $datatable = new PaymentDatatable(true, $clientPublicId);
        $query = $this->paymentRepo->find($clientPublicId, $search);

        if (! Utils::hasPermission('view_all')) {
            $query->where('payments.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }

    public function bulk($ids, $action, $params = [])
    {
        if ($action == 'refund') {
            if (! $ids) {
                return 0;
            }

            $payments = $this->getRepo()->findByPublicIdsWithTrashed($ids);
            $successful = 0;

            foreach ($payments as $payment) {
                if (Auth::user()->can('edit', $payment)) {
                    $amount = ! empty($params['refund_amount']) ? floatval($params['refund_amount']) : null;
                    $paymentDriver = false;
                    if ($accountGateway = $payment->account_gateway) {
                        $paymentDriver = $accountGateway->paymentDriver();
                    }
                    if ($paymentDriver && $paymentDriver->canRefundPayments) {
                        if ($paymentDriver->refundPayment($payment, $amount)) {
                            $successful++;
                        }
                    } else {
                        $payment->recordRefund($amount);
                        $successful++;
                    }
                }
            }

            return $successful;
        } else {
            return parent::bulk($ids, $action);
        }
    }
}
