<?php namespace App\Services;

use Utils;
use Auth;
use URL;
use DateTime;
use Event;
use Cache;
use Omnipay;
use Session;
use CreditCard;
use WePay;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Account;
use App\Models\Country;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Activity;
use App\Models\AccountGateway;
use App\Http\Controllers\PaymentController;
use App\Models\AccountGatewayToken;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\AccountRepository;
use App\Services\BaseService;
use App\Events\PaymentWasCreated;
use App\Ninja\Datatables\PaymentDatatable;

class PaymentService extends BaseService
{
    public $lastError;
    protected $datatableService;

    public function __construct(PaymentRepository $paymentRepo, AccountRepository $accountRepo, DatatableService $datatableService)
    {
        $this->datatableService = $datatableService;
        $this->paymentRepo = $paymentRepo;
        $this->accountRepo = $accountRepo;
    }

    protected function getRepo()
    {
        return $this->paymentRepo;
    }

    public function autoBillInvoice($invoice)
    {
        $client = $invoice->client;
        $account = $client->account;
        $invitation = $invoice->invitations->first();

        if ( ! $invitation) {
            return false;
        }

        $paymentDriver = $account->paymentDriver($invitation, GATEWAY_TYPE_TOKEN);
        $customer = $paymentDriver->customer();

        if ( ! $customer) {
            return false;
        }

        $paymentMethod = $customer->default_payment_method;

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


        return $paymentDriver->completeOnsitePurchase(false, $paymentMethod);

        /*
        if ($accountGateway->gateway_id == GATEWAY_WEPAY) {
            $details['transaction_id'] = 'autobill_'.$invoice->id;
        }
        */
    }

    public function getDatatable($clientPublicId, $search)
    {
        $datatable = new PaymentDatatable( ! $clientPublicId, $clientPublicId);
        $query = $this->paymentRepo->find($clientPublicId, $search);

        if(!Utils::hasPermission('view_all')){
            $query->where('payments.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }


    public function bulk($ids, $action, $params = array())
    {
        if ($action == 'refund') {
            if ( ! $ids ) {
                return 0;
            }

            $payments = $this->getRepo()->findByPublicIdsWithTrashed($ids);
            $successful = 0;

            foreach ($payments as $payment) {
                if (Auth::user()->can('edit', $payment)) {
                    $amount = !empty($params['amount']) ? floatval($params['amount']) : null;
                    $accountGateway = $payment->account_gateway;
                    $paymentDriver = $accountGateway->paymentDriver();
                    if ($paymentDriver->refundPayment($payment, $amount)) {
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
