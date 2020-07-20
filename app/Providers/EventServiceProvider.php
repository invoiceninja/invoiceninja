<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Providers;

use App\Events\Client\ClientWasArchived;
use App\Events\Client\ClientWasCreated;
use App\Events\Client\ClientWasDeleted;
use App\Events\Client\ClientWasRestored;
use App\Events\Client\ClientWasUpdated;
use App\Events\Client\DesignWasDeleted;
use App\Events\Client\DesignWasRestored;
use App\Events\Client\DesignWasUpdated;
use App\Events\Company\CompanyDocumentsDeleted;
use App\Events\Contact\ContactLoggedIn;
use App\Events\Credit\CreditWasArchived;
use App\Events\Credit\CreditWasCreated;
use App\Events\Credit\CreditWasDeleted;
use App\Events\Credit\CreditWasEmailedAndFailed;
use App\Events\Credit\CreditWasMarkedSent;
use App\Events\Credit\CreditWasUpdated;
use App\Events\Design\DesignWasArchived;
use App\Events\Invoice\InvoiceWasArchived;
use App\Events\Invoice\InvoiceWasCancelled;
use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasDeleted;
use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasMarkedSent;
use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Invoice\InvoiceWasReversed;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Events\Invoice\InvoiceWasViewed;
use App\Events\Misc\InvitationWasViewed;
use App\Events\Payment\PaymentWasArchived;
use App\Events\Payment\PaymentWasCreated;
use App\Events\Payment\PaymentWasDeleted;
use App\Events\Payment\PaymentWasRefunded;
use App\Events\Payment\PaymentWasUpdated;
use App\Events\Payment\PaymentWasVoided;
use App\Events\Quote\QuoteWasApproved;
use App\Events\Quote\QuoteWasCreated;
use App\Events\Quote\QuoteWasEmailed;
use App\Events\Quote\QuoteWasUpdated;
use App\Events\Quote\QuoteWasViewed;
use App\Events\User\UserLoggedIn;
use App\Events\User\UserWasCreated;
use App\Events\User\UserWasDeleted;
use App\Listeners\Activity\ArchivedClientActivity;
use App\Listeners\Activity\CreatedClientActivity;
use App\Listeners\Activity\CreatedCreditActivity;
use App\Listeners\Activity\CreatedQuoteActivity;
use App\Listeners\Activity\CreditArchivedActivity;
use App\Listeners\Activity\DeleteClientActivity;
use App\Listeners\Activity\DeleteCreditActivity;
use App\Listeners\Activity\PaymentCreatedActivity;
use App\Listeners\Activity\PaymentDeletedActivity;
use App\Listeners\Activity\PaymentRefundedActivity;
use App\Listeners\Activity\PaymentUpdatedActivity;
use App\Listeners\Activity\PaymentVoidedActivity;
use App\Listeners\Activity\QuoteUpdatedActivity;
use App\Listeners\Activity\RestoreClientActivity;
use App\Listeners\Activity\UpdatedCreditActivity;
use App\Listeners\Contact\UpdateContactLastLogin;
use App\Listeners\Document\DeleteCompanyDocuments;
use App\Listeners\Invoice\CreateInvoiceActivity;
use App\Listeners\Invoice\CreateInvoiceHtmlBackup;
use App\Listeners\Invoice\CreateInvoiceInvitation;
use App\Listeners\Invoice\CreateInvoicePdf;
use App\Listeners\Invoice\InvoiceArchivedActivity;
use App\Listeners\Invoice\InvoiceDeletedActivity;
use App\Listeners\Invoice\InvoiceEmailActivity;
use App\Listeners\Invoice\InvoiceEmailFailedActivity;
use App\Listeners\Invoice\InvoiceEmailedNotification;
use App\Listeners\Invoice\InvoiceViewedActivity;
use App\Listeners\Invoice\UpdateInvoiceActivity;
use App\Listeners\Invoice\UpdateInvoiceInvitations;
use App\Listeners\Misc\InvitationViewedListener;
use App\Listeners\Payment\PaymentNotification;
use App\Listeners\Quote\QuoteEmailActivity;
use App\Listeners\Quote\QuoteViewedActivity;
use App\Listeners\Quote\ReachWorkflowSettings;
use App\Listeners\SendVerificationNotification;
use App\Listeners\SetDBListener;
use App\Listeners\User\DeletedUserActivity;
use App\Listeners\User\UpdateUserLastLogin;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        UserWasCreated::class => [
            SendVerificationNotification::class,
        ],
        UserWasDeleted::class => [
            DeletedUserActivity::class,
        ],
        UserLoggedIn::class => [
            UpdateUserLastLogin::class,
        ],
        ContactLoggedIn::class => [
            UpdateContactLastLogin::class,
        ],
        PaymentWasCreated::class => [
            PaymentCreatedActivity::class,
            PaymentNotification::class,
        ],
        PaymentWasDeleted::class => [
            PaymentDeletedActivity::class,
        ],
        PaymentWasArchived::class => [
            PaymentArchivedActivity::class,
        ],
        PaymentWasUpdated::class => [
            PaymentUpdatedActivity::class,
        ],
        PaymentWasRefunded::class => [
            PaymentRefundedActivity::class,
        ],
        PaymentWasVoided::class => [
            PaymentVoidedActivity::class,
        ],
        // Clients
        ClientWasCreated::class =>[
            CreatedClientActivity::class,
        ],
        ClientWasArchived::class =>[
            ArchivedClientActivity::class,
        ],
        ClientWasUpdated::class =>[
        ],
        ClientWasDeleted::class =>[
            DeleteClientActivity::class,
        ],
        ClientWasRestored::class =>[
            RestoreClientActivity::class,
        ],
        // Documents
        DocumentWasCreated::class =>[
        ],
        DocumentWasArchived::class =>[
        ],
        DocumentWasUpdated::class =>[
        ],
        DocumentWasDeleted::class =>[
        ],
        DocumentWasRestored::class =>[
        ],
        CreditWasCreated::class => [
            CreatedCreditActivity::class,
        ],
        CreditWasDeleted::class => [
            DeleteCreditActivity::class,
        ],
        CreditWasUpdated::class => [
            UpdatedCreditActivity::class,
        ],
        CreditWasEmailedAndFailed::class => [
        ],
        CreditWasEmailed::class => [
        ],
        CreditWasMarkedSent::class => [
        ],
        CreditWasArchived::class => [
            CreditArchivedActivity::class,
        ],
        //Designs
        DesignWasArchived::class => [
        ],
        DesignWasUpdated::class => [
        ],
        DesignWasDeleted::class => [
        ],
        DesignWasRestored::class => [
        ],
        //Invoices
        InvoiceWasMarkedSent::class => [
            CreateInvoiceHtmlBackup::class,
        ],
        InvoiceWasUpdated::class => [
            UpdateInvoiceActivity::class,
            CreateInvoicePdf::class,
        ],
        InvoiceWasCreated::class => [
            CreateInvoiceActivity::class,
        //    CreateInvoicePdf::class,
        ],
        InvoiceWasPaid::class => [
       //    CreateInvoiceHtmlBackup::class,
        ],
        InvoiceWasViewed::class => [
            InvoiceViewedActivity::class,
        ],
        InvoiceWasEmailed::class => [
            InvoiceEmailActivity::class,
            InvoiceEmailedNotification::class,
        ],
        InvoiceWasEmailedAndFailed::class => [
            InvoiceEmailFailedActivity::class,
        ],
        InvoiceWasDeleted::class => [
            InvoiceDeletedActivity::class,
        ],
        InvoiceWasArchived::class => [
            InvoiceArchivedActivity::class,
        ],
        InvoiceWasReversed::class => [
        ],
        InvoiceWasCancelled::class => [
        ],
        InvitationWasViewed::class => [
            InvitationViewedListener::class
        ],
        CompanyDocumentsDeleted::class => [
            DeleteCompanyDocuments::class,
        ],
        QuoteWasApproved::class => [
            ReachWorkflowSettings::class,
        ],
        QuoteWasCreated::class => [
            CreatedQuoteActivity::class,
        ],
        QuoteWasUpdated::class => [
            QuoteUpdatedActivity::class,
        ],
        QuoteWasEmailed::class => [
            QuoteEmailActivity::class,
        ],
        QuoteWasViewed::class => [
            QuoteViewedActivity::class,
        ],

    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [

    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    // public function boot()
    // {
    //     parent::boot();
    // }

    public function boot()
    {
        parent::boot();
    }
}
