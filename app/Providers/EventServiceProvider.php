<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Providers;

use App\Events\Account\AccountCreated;
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
use App\Events\Credit\CreditWasEmailed;
use App\Events\Credit\CreditWasEmailedAndFailed;
use App\Events\Credit\CreditWasMarkedSent;
use App\Events\Credit\CreditWasRestored;
use App\Events\Credit\CreditWasUpdated;
use App\Events\Credit\CreditWasViewed;
use App\Events\Design\DesignWasArchived;
use App\Events\Expense\ExpenseWasArchived;
use App\Events\Expense\ExpenseWasCreated;
use App\Events\Expense\ExpenseWasDeleted;
use App\Events\Expense\ExpenseWasRestored;
use App\Events\Expense\ExpenseWasUpdated;
use App\Events\Invoice\InvoiceReminderWasEmailed;
use App\Events\Invoice\InvoiceWasArchived;
use App\Events\Invoice\InvoiceWasCancelled;
use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasDeleted;
use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Events\Invoice\InvoiceWasMarkedSent;
use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Invoice\InvoiceWasRestored;
use App\Events\Invoice\InvoiceWasReversed;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Events\Invoice\InvoiceWasViewed;
use App\Events\Misc\InvitationWasViewed;
use App\Events\Payment\PaymentWasArchived;
use App\Events\Payment\PaymentWasCreated;
use App\Events\Payment\PaymentWasDeleted;
use App\Events\Payment\PaymentWasEmailed;
use App\Events\Payment\PaymentWasEmailedAndFailed;
use App\Events\Payment\PaymentWasRefunded;
use App\Events\Payment\PaymentWasRestored;
use App\Events\Payment\PaymentWasUpdated;
use App\Events\Payment\PaymentWasVoided;
use App\Events\PurchaseOrder\PurchaseOrderWasAccepted;
use App\Events\PurchaseOrder\PurchaseOrderWasArchived;
use App\Events\PurchaseOrder\PurchaseOrderWasCreated;
use App\Events\PurchaseOrder\PurchaseOrderWasDeleted;
use App\Events\PurchaseOrder\PurchaseOrderWasEmailed;
use App\Events\PurchaseOrder\PurchaseOrderWasMarkedSent;
use App\Events\PurchaseOrder\PurchaseOrderWasRestored;
use App\Events\PurchaseOrder\PurchaseOrderWasUpdated;
use App\Events\PurchaseOrder\PurchaseOrderWasViewed;
use App\Events\Quote\QuoteWasApproved;
use App\Events\Quote\QuoteWasArchived;
use App\Events\Quote\QuoteWasCreated;
use App\Events\Quote\QuoteWasDeleted;
use App\Events\Quote\QuoteWasEmailed;
use App\Events\Quote\QuoteWasRestored;
use App\Events\Quote\QuoteWasUpdated;
use App\Events\Quote\QuoteWasViewed;
use App\Events\RecurringExpense\RecurringExpenseWasArchived;
use App\Events\RecurringExpense\RecurringExpenseWasCreated;
use App\Events\RecurringExpense\RecurringExpenseWasDeleted;
use App\Events\RecurringExpense\RecurringExpenseWasRestored;
use App\Events\RecurringExpense\RecurringExpenseWasUpdated;
use App\Events\RecurringInvoice\RecurringInvoiceWasArchived;
use App\Events\RecurringInvoice\RecurringInvoiceWasCreated;
use App\Events\RecurringInvoice\RecurringInvoiceWasDeleted;
use App\Events\RecurringInvoice\RecurringInvoiceWasRestored;
use App\Events\RecurringInvoice\RecurringInvoiceWasUpdated;
use App\Events\RecurringQuote\RecurringQuoteWasArchived;
use App\Events\RecurringQuote\RecurringQuoteWasCreated;
use App\Events\RecurringQuote\RecurringQuoteWasDeleted;
use App\Events\RecurringQuote\RecurringQuoteWasRestored;
use App\Events\RecurringQuote\RecurringQuoteWasUpdated;
use App\Events\Subscription\SubscriptionWasArchived;
use App\Events\Subscription\SubscriptionWasCreated;
use App\Events\Subscription\SubscriptionWasDeleted;
use App\Events\Subscription\SubscriptionWasRestored;
use App\Events\Subscription\SubscriptionWasUpdated;
use App\Events\Task\TaskWasArchived;
use App\Events\Task\TaskWasCreated;
use App\Events\Task\TaskWasDeleted;
use App\Events\Task\TaskWasRestored;
use App\Events\Task\TaskWasUpdated;
use App\Events\User\UserLoggedIn;
use App\Events\User\UserWasArchived;
use App\Events\User\UserWasCreated;
use App\Events\User\UserWasDeleted;
use App\Events\User\UserWasRestored;
use App\Events\User\UserWasUpdated;
use App\Events\Vendor\VendorWasArchived;
use App\Events\Vendor\VendorWasCreated;
use App\Events\Vendor\VendorWasDeleted;
use App\Events\Vendor\VendorWasRestored;
use App\Events\Vendor\VendorWasUpdated;
use App\Listeners\Activity\ArchivedClientActivity;
use App\Listeners\Activity\ClientUpdatedActivity;
use App\Listeners\Activity\CreatedClientActivity;
use App\Listeners\Activity\CreatedCreditActivity;
use App\Listeners\Activity\CreatedExpenseActivity;
use App\Listeners\Activity\CreatedQuoteActivity;
use App\Listeners\Activity\CreatedSubscriptionActivity;
use App\Listeners\Activity\CreatedTaskActivity;
use App\Listeners\Activity\CreatedVendorActivity;
use App\Listeners\Activity\CreditArchivedActivity;
use App\Listeners\Activity\DeleteClientActivity;
use App\Listeners\Activity\DeleteCreditActivity;
use App\Listeners\Activity\ExpenseArchivedActivity;
use App\Listeners\Activity\ExpenseDeletedActivity;
use App\Listeners\Activity\ExpenseRestoredActivity;
use App\Listeners\Activity\ExpenseUpdatedActivity;
use App\Listeners\Activity\PaymentArchivedActivity;
use App\Listeners\Activity\PaymentCreatedActivity;
use App\Listeners\Activity\PaymentDeletedActivity;
use App\Listeners\Activity\PaymentRefundedActivity;
use App\Listeners\Activity\PaymentUpdatedActivity;
use App\Listeners\Activity\PaymentVoidedActivity;
use App\Listeners\Activity\QuoteUpdatedActivity;
use App\Listeners\Activity\RestoreClientActivity;
use App\Listeners\Activity\SubscriptionArchivedActivity;
use App\Listeners\Activity\SubscriptionDeletedActivity;
use App\Listeners\Activity\SubscriptionRestoredActivity;
use App\Listeners\Activity\SubscriptionUpdatedActivity;
use App\Listeners\Activity\TaskArchivedActivity;
use App\Listeners\Activity\TaskDeletedActivity;
use App\Listeners\Activity\TaskRestoredActivity;
use App\Listeners\Activity\TaskUpdatedActivity;
use App\Listeners\Activity\UpdatedCreditActivity;
use App\Listeners\Activity\VendorArchivedActivity;
use App\Listeners\Activity\VendorDeletedActivity;
use App\Listeners\Activity\VendorRestoredActivity;
use App\Listeners\Activity\VendorUpdatedActivity;
use App\Listeners\Contact\UpdateContactLastLogin;
use App\Listeners\Credit\CreditCreatedNotification;
use App\Listeners\Credit\CreditEmailedNotification;
use App\Listeners\Credit\CreditRestoredActivity;
use App\Listeners\Credit\CreditViewedActivity;
use App\Listeners\Document\DeleteCompanyDocuments;
use App\Listeners\Invoice\CreateInvoiceActivity;
use App\Listeners\Invoice\CreateInvoiceHtmlBackup;
use App\Listeners\Invoice\CreateInvoicePdf;
use App\Listeners\Invoice\InvoiceArchivedActivity;
use App\Listeners\Invoice\InvoiceCancelledActivity;
use App\Listeners\Invoice\InvoiceCreatedNotification;
use App\Listeners\Invoice\InvoiceDeletedActivity;
use App\Listeners\Invoice\InvoiceEmailActivity;
use App\Listeners\Invoice\InvoiceEmailedNotification;
use App\Listeners\Invoice\InvoiceEmailFailedActivity;
use App\Listeners\Invoice\InvoiceFailedEmailNotification;
use App\Listeners\Invoice\InvoicePaidActivity;
use App\Listeners\Invoice\InvoiceReminderEmailActivity;
use App\Listeners\Invoice\InvoiceRestoredActivity;
use App\Listeners\Invoice\InvoiceReversedActivity;
use App\Listeners\Invoice\InvoiceViewedActivity;
use App\Listeners\Invoice\UpdateInvoiceActivity;
use App\Listeners\Mail\MailSentListener;
use App\Listeners\Misc\InvitationViewedListener;
use App\Listeners\Payment\PaymentEmailedActivity;
use App\Listeners\Payment\PaymentEmailFailureActivity;
use App\Listeners\Payment\PaymentNotification;
use App\Listeners\Payment\PaymentRestoredActivity;
use App\Listeners\PurchaseOrder\CreatePurchaseOrderActivity;
use App\Listeners\PurchaseOrder\PurchaseOrderAcceptedActivity;
use App\Listeners\PurchaseOrder\PurchaseOrderAcceptedNotification;
use App\Listeners\PurchaseOrder\PurchaseOrderArchivedActivity;
use App\Listeners\PurchaseOrder\PurchaseOrderDeletedActivity;
use App\Listeners\PurchaseOrder\PurchaseOrderEmailActivity;
use App\Listeners\PurchaseOrder\PurchaseOrderRestoredActivity;
use App\Listeners\PurchaseOrder\PurchaseOrderViewedActivity;
use App\Listeners\PurchaseOrder\UpdatePurchaseOrderActivity;
use App\Listeners\Quote\QuoteApprovedActivity;
use App\Listeners\Quote\QuoteApprovedNotification;
use App\Listeners\Quote\QuoteApprovedWebhook;
use App\Listeners\Quote\QuoteArchivedActivity;
use App\Listeners\Quote\QuoteCreatedNotification;
use App\Listeners\Quote\QuoteDeletedActivity;
use App\Listeners\Quote\QuoteEmailActivity;
use App\Listeners\Quote\QuoteEmailedNotification;
use App\Listeners\Quote\QuoteRestoredActivity;
use App\Listeners\Quote\QuoteViewedActivity;
use App\Listeners\Quote\ReachWorkflowSettings;
use App\Listeners\RecurringExpense\CreatedRecurringExpenseActivity;
use App\Listeners\RecurringExpense\RecurringExpenseArchivedActivity;
use App\Listeners\RecurringExpense\RecurringExpenseDeletedActivity;
use App\Listeners\RecurringExpense\RecurringExpenseRestoredActivity;
use App\Listeners\RecurringExpense\RecurringExpenseUpdatedActivity;
use App\Listeners\RecurringInvoice\CreateRecurringInvoiceActivity;
use App\Listeners\RecurringInvoice\RecurringInvoiceArchivedActivity;
use App\Listeners\RecurringInvoice\RecurringInvoiceDeletedActivity;
use App\Listeners\RecurringInvoice\RecurringInvoiceRestoredActivity;
use App\Listeners\RecurringInvoice\UpdateRecurringInvoiceActivity;
use App\Listeners\RecurringQuote\CreateRecurringQuoteActivity;
use App\Listeners\RecurringQuote\RecurringQuoteArchivedActivity;
use App\Listeners\RecurringQuote\RecurringQuoteDeletedActivity;
use App\Listeners\RecurringQuote\RecurringQuoteRestoredActivity;
use App\Listeners\RecurringQuote\UpdateRecurringQuoteActivity;
use App\Listeners\SendVerificationNotification;
use App\Listeners\User\ArchivedUserActivity;
use App\Listeners\User\CreatedUserActivity;
use App\Listeners\User\DeletedUserActivity;
use App\Listeners\User\RestoredUserActivity;
use App\Listeners\User\UpdatedUserActivity;
use App\Listeners\User\UpdateUserLastLogin;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyToken;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use App\Observers\AccountObserver;
use App\Observers\ClientObserver;
use App\Observers\CompanyGatewayObserver;
use App\Observers\CompanyObserver;
use App\Observers\CompanyTokenObserver;
use App\Observers\CreditObserver;
use App\Observers\ExpenseObserver;
use App\Observers\InvoiceObserver;
use App\Observers\PaymentObserver;
use App\Observers\ProductObserver;
use App\Observers\ProjectObserver;
use App\Observers\ProposalObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\QuoteObserver;
use App\Observers\SubscriptionObserver;
use App\Observers\TaskObserver;
use App\Observers\UserObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        AccountCreated::class => [
        ],
        MessageSending::class => [
        ],
        MessageSent::class => [
            MailSentListener::class,
        ],
        UserWasCreated::class => [
            CreatedUserActivity::class,
            SendVerificationNotification::class,
        ],
        UserWasDeleted::class => [
            DeletedUserActivity::class,
        ],
        UserWasArchived::class => [
            ArchivedUserActivity::class,
        ],
        UserLoggedIn::class => [
            UpdateUserLastLogin::class,
        ],
        UserWasUpdated::class => [
            UpdatedUserActivity::class,
        ],
        UserWasRestored::class => [
            RestoredUserActivity::class,
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
        PaymentWasRestored::class => [
            PaymentRestoredActivity::class,
        ],
        // Clients
        ClientWasCreated::class => [
            CreatedClientActivity::class,
        ],
        ClientWasArchived::class => [
            ArchivedClientActivity::class,
        ],
        ClientWasUpdated::class => [
            ClientUpdatedActivity::class,
        ],
        ClientWasDeleted::class => [
            DeleteClientActivity::class,
        ],
        ClientWasRestored::class => [
            RestoreClientActivity::class,
        ],
        // Documents
        DocumentWasCreated::class => [
        ],
        DocumentWasArchived::class => [
        ],
        DocumentWasUpdated::class => [
        ],
        DocumentWasDeleted::class => [
        ],
        DocumentWasRestored::class => [
        ],
        CreditWasCreated::class => [
            CreatedCreditActivity::class,
            CreditCreatedNotification::class,
        ],
        CreditWasDeleted::class => [
            DeleteCreditActivity::class,
        ],
        CreditWasUpdated::class => [
            UpdatedCreditActivity::class,
            CreateInvoicePdf::class,
        ],
        CreditWasEmailedAndFailed::class => [
        ],
        CreditWasEmailed::class => [
            CreditEmailedNotification::class,
        ],
        CreditWasMarkedSent::class => [
        ],
        CreditWasArchived::class => [
            CreditArchivedActivity::class,
        ],
        CreditWasRestored::class => [
            CreditRestoredActivity::class,
        ],
        CreditWasViewed::class => [
            CreditViewedActivity::class,
        ],
        //Designs
        DesignWasUpdated::class => [
        ],
        DesignWasArchived::class => [
        ],
        DesignWasDeleted::class => [
        ],
        DesignWasRestored::class => [
        ],
        ExpenseWasCreated::class => [
            CreatedExpenseActivity::class,
        ],
        ExpenseWasUpdated::class => [
            ExpenseUpdatedActivity::class,
        ],
        ExpenseWasArchived::class => [
            ExpenseArchivedActivity::class,
        ],
        ExpenseWasDeleted::class => [
            ExpenseDeletedActivity::class,
        ],
        ExpenseWasRestored::class => [
            ExpenseRestoredActivity::class,
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
            InvoiceCreatedNotification::class,
            //    CreateInvoicePdf::class,
        ],
        InvoiceWasPaid::class => [
            InvoicePaidActivity::class,
            CreateInvoicePdf::class,
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
            InvoiceFailedEmailNotification::class,
        ],
        InvoiceReminderWasEmailed::class => [
            InvoiceReminderEmailActivity::class,
        ],
        InvoiceWasDeleted::class => [
            InvoiceDeletedActivity::class,
            CreateInvoicePdf::class,
        ],
        InvoiceWasArchived::class => [
            InvoiceArchivedActivity::class,
        ],
        InvoiceWasRestored::class => [
            InvoiceRestoredActivity::class,
        ],
        InvoiceWasReversed::class => [
            InvoiceReversedActivity::class,
            CreateInvoicePdf::class,
        ],
        InvoiceWasCancelled::class => [
            InvoiceCancelledActivity::class,
        ],
        InvitationWasViewed::class => [
            InvitationViewedListener::class,
        ],
        PaymentWasEmailed::class => [
            PaymentEmailedActivity::class,
        ],
        PaymentWasEmailedAndFailed::class => [
            PaymentEmailFailureActivity::class,
        ],
        PurchaseOrderWasArchived::class => [
            PurchaseOrderArchivedActivity::class,
        ],
        PurchaseOrderWasCreated::class => [
            CreatePurchaseOrderActivity::class,
        ],
        PurchaseOrderWasDeleted::class => [
            PurchaseOrderDeletedActivity::class,
        ],
        PurchaseOrderWasEmailed::class => [
            PurchaseOrderEmailActivity::class,
        ],
        PurchaseOrderWasRestored::class => [
            PurchaseOrderRestoredActivity::class,
        ],
        PurchaseOrderWasUpdated::class => [
            UpdatePurchaseOrderActivity::class,
        ],
        PurchaseOrderWasViewed::class => [
            PurchaseOrderViewedActivity::class,
        ],
        PurchaseOrderWasAccepted::class => [
            PurchaseOrderAcceptedActivity::class,
            PurchaseOrderAcceptedNotification::class,
        ],
        CompanyDocumentsDeleted::class => [
            DeleteCompanyDocuments::class,
        ],
        QuoteWasApproved::class => [
            ReachWorkflowSettings::class,
            QuoteApprovedActivity::class,
            QuoteApprovedWebhook::class,
            QuoteApprovedNotification::class,
        ],
        QuoteWasCreated::class => [
            CreatedQuoteActivity::class,
            QuoteCreatedNotification::class,
        ],
        QuoteWasUpdated::class => [
            QuoteUpdatedActivity::class,
            CreateInvoicePdf::class,
        ],
        QuoteWasEmailed::class => [
            QuoteEmailActivity::class,
            QuoteEmailedNotification::class,
        ],
        QuoteWasViewed::class => [
            QuoteViewedActivity::class,
        ],
        QuoteWasArchived::class => [
            QuoteArchivedActivity::class,
        ],
        QuoteWasDeleted::class => [
            QuoteDeletedActivity::class,
        ],
        QuoteWasRestored::class => [
            QuoteRestoredActivity::class,
        ],
        RecurringExpenseWasCreated::class => [
            CreatedRecurringExpenseActivity::class,
        ],
        RecurringExpenseWasUpdated::class => [
            RecurringExpenseUpdatedActivity::class,
        ],
        RecurringExpenseWasArchived::class => [
            RecurringExpenseArchivedActivity::class,
        ],
        RecurringExpenseWasDeleted::class => [
            RecurringExpenseDeletedActivity::class,
        ],
        RecurringExpenseWasRestored::class => [
            RecurringExpenseRestoredActivity::class,
        ],
        RecurringQuoteWasUpdated::class => [
            UpdateRecurringQuoteActivity::class,
        ],
        RecurringQuoteWasCreated::class => [
            CreateRecurringQuoteActivity::class,
        ],
        RecurringQuoteWasDeleted::class => [
            RecurringQuoteDeletedActivity::class,
        ],
        RecurringQuoteWasArchived::class => [
            RecurringQuoteArchivedActivity::class,
        ],
        RecurringQuoteWasRestored::class => [
            RecurringQuoteRestoredActivity::class,
        ],
        RecurringInvoiceWasUpdated::class => [
            UpdateRecurringInvoiceActivity::class,
        ],
        RecurringInvoiceWasCreated::class => [
            CreateRecurringInvoiceActivity::class,
        ],
        RecurringInvoiceWasDeleted::class => [
            RecurringInvoiceDeletedActivity::class,
        ],
        RecurringInvoiceWasArchived::class => [
            RecurringInvoiceArchivedActivity::class,
        ],
        RecurringInvoiceWasRestored::class => [
            RecurringInvoiceRestoredActivity::class,
        ],
        TaskWasCreated::class => [
            CreatedTaskActivity::class,
        ],
        TaskWasUpdated::class => [
            TaskUpdatedActivity::class,
        ],
        TaskWasArchived::class => [
            TaskArchivedActivity::class,
        ],
        TaskWasDeleted::class => [
            TaskDeletedActivity::class,
        ],
        TaskWasRestored::class => [
            TaskRestoredActivity::class,
        ],
        SubscriptionWasCreated::class => [
            CreatedSubscriptionActivity::class,
        ],
        SubscriptionWasUpdated::class => [
            SubscriptionUpdatedActivity::class,
        ],
        SubscriptionWasArchived::class => [
            SubscriptionArchivedActivity::class,
        ],
        SubscriptionWasDeleted::class => [
            SubscriptionDeletedActivity::class,
        ],
        SubscriptionWasRestored::class => [
            SubscriptionRestoredActivity::class,
        ],
        VendorWasCreated::class => [
            CreatedVendorActivity::class,
        ],
        VendorWasArchived::class => [
            VendorArchivedActivity::class,
        ],
        VendorWasDeleted::class => [
            VendorDeletedActivity::class,
        ],
        VendorWasRestored::class => [
            VendorRestoredActivity::class,
        ],
        VendorWasUpdated::class => [
            VendorUpdatedActivity::class,
        ],
        \SocialiteProviders\Manager\SocialiteWasCalled::class => [
            // ... Manager won't register drivers that are not added to this listener.
            \SocialiteProviders\Apple\AppleExtendSocialite::class.'@handle',
            \SocialiteProviders\Microsoft\MicrosoftExtendSocialite::class.'@handle',
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
    public function boot()
    {
        parent::boot();

        Account::observe(AccountObserver::class);
        Subscription::observe(SubscriptionObserver::class);
        Client::observe(ClientObserver::class);
        Company::observe(CompanyObserver::class);
        CompanyGateway::observe(CompanyGatewayObserver::class);
        CompanyToken::observe(CompanyTokenObserver::class);
        Credit::observe(CreditObserver::class);
        Expense::observe(ExpenseObserver::class);
        Invoice::observe(InvoiceObserver::class);
        Payment::observe(PaymentObserver::class);
        Product::observe(ProductObserver::class);
        Project::observe(ProjectObserver::class);
        Proposal::observe(ProposalObserver::class);
        Quote::observe(QuoteObserver::class);
        Task::observe(TaskObserver::class);
        User::observe(UserObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
