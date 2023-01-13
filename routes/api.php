<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientGatewayTokenController;
use App\Http\Controllers\ClientStatementController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyGatewayController;
use App\Http\Controllers\CompanyLedgerController;
use App\Http\Controllers\CompanyUserController;
use App\Http\Controllers\ConnectedAccountController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\GroupSettingController;
use App\Http\Controllers\HostedMigrationController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ImportJsonController;
use App\Http\Controllers\InAppPurchase\AppleController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\MigrationController;
use App\Http\Controllers\OneTimeTokenController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentNotificationWebhookController;
use App\Http\Controllers\PaymentTermController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PingController;
use App\Http\Controllers\PostMarkController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\PreviewPurchaseOrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RecurringExpenseController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\RecurringQuoteController;
use App\Http\Controllers\Reports\ClientContactReportController;
use App\Http\Controllers\Reports\ClientReportController;
use App\Http\Controllers\Reports\CreditReportController;
use App\Http\Controllers\Reports\DocumentReportController;
use App\Http\Controllers\Reports\ExpenseReportController;
use App\Http\Controllers\Reports\InvoiceItemReportController;
use App\Http\Controllers\Reports\InvoiceReportController;
use App\Http\Controllers\Reports\PaymentReportController;
use App\Http\Controllers\Reports\ProductReportController;
use App\Http\Controllers\Reports\ProductSalesReportController;
use App\Http\Controllers\Reports\ProfitAndLossController;
use App\Http\Controllers\Reports\QuoteItemReportController;
use App\Http\Controllers\Reports\QuoteReportController;
use App\Http\Controllers\Reports\RecurringInvoiceReportController;
use App\Http\Controllers\Reports\TaskReportController;
use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\SelfUpdateController;
use App\Http\Controllers\StaticController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SubdomainController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Support\Messages\SendingController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskSchedulerController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\TwilioController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WebCronController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;


Route::group(['middleware' => ['throttle:300,1', 'api_db', 'token_auth', 'locale'], 'prefix' => 'api/v1', 'as' => 'api.'], function () {

    Route::post('preview', [PreviewController::class, 'show'])->name('preview.show');
    Route::post('live_preview', [PreviewController::class, 'live'])->name('preview.live');

    Route::post('preview/purchase_order', [PreviewPurchaseOrderController::class, 'show'])->name('preview_purchase_order.show');
    Route::post('live_preview/purchase_order', [PreviewPurchaseOrderController::class, 'live'])->name('preview_purchase_order.live');

});


Route::fallback([BaseController::class, 'notFound']);