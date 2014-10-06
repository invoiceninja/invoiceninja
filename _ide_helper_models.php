<?php
/**
 * An helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace {
/**
 * Client
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $account_id
 * @property integer $currency_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $name
 * @property string $address1
 * @property string $address2
 * @property string $city
 * @property string $state
 * @property string $postal_code
 * @property integer $country_id
 * @property string $work_phone
 * @property string $private_notes
 * @property float $balance
 * @property float $paid_to_date
 * @property string $last_login
 * @property string $website
 * @property integer $industry_id
 * @property integer $size_id
 * @property boolean $is_deleted
 * @property integer $payment_terms
 * @property integer $public_id
 * @property string $custom_value1
 * @property string $custom_value2
 * @property-read \Account $account
 * @property-read \Illuminate\Database\Eloquent\Collection|\Invoice[] $invoices
 * @property-read \Illuminate\Database\Eloquent\Collection|\Payment[] $payments
 * @property-read \Illuminate\Database\Eloquent\Collection|\Contact[] $contacts
 * @property-read \Country $country
 * @property-read \Currency $currency
 * @property-read \Size $size
 * @property-read \Industry $industry
 * @method static \Illuminate\Database\Query\Builder|\Client whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereCurrencyId($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereAddress1($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereAddress2($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereCity($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereState($value)
 * @method static \Illuminate\Database\Query\Builder|\Client wherePostalCode($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereCountryId($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereWorkPhone($value)
 * @method static \Illuminate\Database\Query\Builder|\Client wherePrivateNotes($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereBalance($value)
 * @method static \Illuminate\Database\Query\Builder|\Client wherePaidToDate($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereLastLogin($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereWebsite($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereIndustryId($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereSizeId($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereIsDeleted($value)
 * @method static \Illuminate\Database\Query\Builder|\Client wherePaymentTerms($value)
 * @method static \Illuminate\Database\Query\Builder|\Client wherePublicId($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereCustomValue1($value)
 * @method static \Illuminate\Database\Query\Builder|\Client whereCustomValue2($value)
 * @method static \EntityModel scope($publicId = false, $accountId = false)
 */
	class Client {}
}

namespace {
/**
 * User
 *
 * @property integer $id
 * @property integer $account_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $first_name
 * @property string $last_name
 * @property string $phone
 * @property string $username
 * @property string $email
 * @property string $password
 * @property string $confirmation_code
 * @property boolean $registered
 * @property boolean $confirmed
 * @property integer $theme_id
 * @property boolean $notify_sent
 * @property boolean $notify_viewed
 * @property boolean $notify_paid
 * @property integer $public_id
 * @property boolean $force_pdfjs
 * @property string $remember_token
 * @property-read \Account $account
 * @property-read \Theme $theme
 * @method static \Illuminate\Database\Query\Builder|\User whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereFirstName($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereLastName($value)
 * @method static \Illuminate\Database\Query\Builder|\User wherePhone($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereUsername($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereEmail($value)
 * @method static \Illuminate\Database\Query\Builder|\User wherePassword($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereConfirmationCode($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereRegistered($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereConfirmed($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereThemeId($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereNotifySent($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereNotifyViewed($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereNotifyPaid($value)
 * @method static \Illuminate\Database\Query\Builder|\User wherePublicId($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereForcePdfjs($value)
 * @method static \Illuminate\Database\Query\Builder|\User whereRememberToken($value)
 */
	class User {}
}

namespace {
/**
 * Size
 *
 * @property integer $id
 * @property string $name
 * @method static \Illuminate\Database\Query\Builder|\Size whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Size whereName($value)
 */
	class Size {}
}

namespace {
/**
 * Country
 *
 * @property integer $id
 * @property string $capital
 * @property string $citizenship
 * @property string $country_code
 * @property string $currency
 * @property string $currency_code
 * @property string $currency_sub_unit
 * @property string $full_name
 * @property string $iso_3166_2
 * @property string $iso_3166_3
 * @property string $name
 * @property string $region_code
 * @property string $sub_region_code
 * @property boolean $eea
 * @method static \Illuminate\Database\Query\Builder|\Country whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Country whereCapital($value)
 * @method static \Illuminate\Database\Query\Builder|\Country whereCitizenship($value)
 * @method static \Illuminate\Database\Query\Builder|\Country whereCountryCode($value)
 * @method static \Illuminate\Database\Query\Builder|\Country whereCurrency($value)
 * @method static \Illuminate\Database\Query\Builder|\Country whereCurrencyCode($value)
 * @method static \Illuminate\Database\Query\Builder|\Country whereCurrencySubUnit($value)
 * @method static \Illuminate\Database\Query\Builder|\Country whereFullName($value)
 * @method static \Illuminate\Database\Query\Builder|\Country whereIso31662($value)
 * @method static \Illuminate\Database\Query\Builder|\Country whereIso31663($value)
 * @method static \Illuminate\Database\Query\Builder|\Country whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\Country whereRegionCode($value)
 * @method static \Illuminate\Database\Query\Builder|\Country whereSubRegionCode($value)
 * @method static \Illuminate\Database\Query\Builder|\Country whereEea($value)
 */
	class Country {}
}

namespace {
/**
 * Language
 *
 * @property integer $id
 * @property string $name
 * @property string $locale
 * @method static \Illuminate\Database\Query\Builder|\Language whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Language whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\Language whereLocale($value)
 */
	class Language {}
}

namespace {
/**
 * Contact
 *
 * @property integer $id
 * @property integer $account_id
 * @property integer $user_id
 * @property integer $client_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property boolean $is_primary
 * @property boolean $send_invoice
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property string $last_login
 * @property integer $public_id
 * @property-read \Client $client
 * @method static \Illuminate\Database\Query\Builder|\Contact whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact whereClientId($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact whereIsPrimary($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact whereSendInvoice($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact whereFirstName($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact whereLastName($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact whereEmail($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact wherePhone($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact whereLastLogin($value)
 * @method static \Illuminate\Database\Query\Builder|\Contact wherePublicId($value)
 * @method static \EntityModel scope($publicId = false, $accountId = false)
 */
	class Contact {}
}

namespace {
/**
 * PaymentLibrary
 *
 * @property integer $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string $name
 * @property boolean $visible
 * @property-read \Illuminate\Database\Eloquent\Collection|\Gateway[] $gateways
 * @method static \Illuminate\Database\Query\Builder|\PaymentLibrary whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\PaymentLibrary whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\PaymentLibrary whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\PaymentLibrary whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\PaymentLibrary whereVisible($value)
 */
	class PaymentLibrary {}
}

namespace {
/**
 * Frequency
 *
 * @property integer $id
 * @property string $name
 * @method static \Illuminate\Database\Query\Builder|\Frequency whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Frequency whereName($value)
 */
	class Frequency {}
}

namespace {
/**
 * InvoiceItem
 *
 * @property integer $id
 * @property integer $account_id
 * @property integer $user_id
 * @property integer $invoice_id
 * @property integer $product_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $product_key
 * @property string $notes
 * @property float $cost
 * @property float $qty
 * @property string $tax_name
 * @property float $tax_rate
 * @property integer $public_id
 * @property-read \Invoice $invoice
 * @property-read \Product $product
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereInvoiceId($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereProductId($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereProductKey($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereNotes($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereCost($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereQty($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereTaxName($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem whereTaxRate($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceItem wherePublicId($value)
 * @method static \EntityModel scope($publicId = false, $accountId = false)
 */
	class InvoiceItem {}
}

namespace {
/**
 * AccountGateway
 *
 * @property integer $id
 * @property integer $account_id
 * @property integer $user_id
 * @property integer $gateway_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $config
 * @property integer $public_id
 * @property integer $accepted_credit_cards
 * @property-read \Gateway $gateway
 * @method static \Illuminate\Database\Query\Builder|\AccountGateway whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\AccountGateway whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\AccountGateway whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\AccountGateway whereGatewayId($value)
 * @method static \Illuminate\Database\Query\Builder|\AccountGateway whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\AccountGateway whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\AccountGateway whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\AccountGateway whereConfig($value)
 * @method static \Illuminate\Database\Query\Builder|\AccountGateway wherePublicId($value)
 * @method static \Illuminate\Database\Query\Builder|\AccountGateway whereAcceptedCreditCards($value)
 * @method static \EntityModel scope($publicId = false, $accountId = false)
 */
	class AccountGateway {}
}

namespace {
/**
 * Theme
 *
 * @property integer $id
 * @property string $name
 * @method static \Illuminate\Database\Query\Builder|\Theme whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Theme whereName($value)
 */
	class Theme {}
}

namespace {
/**
 * Payment
 *
 * @property integer $id
 * @property integer $invoice_id
 * @property integer $account_id
 * @property integer $client_id
 * @property integer $contact_id
 * @property integer $invitation_id
 * @property integer $user_id
 * @property integer $account_gateway_id
 * @property integer $payment_type_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property boolean $is_deleted
 * @property float $amount
 * @property string $payment_date
 * @property string $transaction_reference
 * @property string $payer_id
 * @property integer $public_id
 * @property-read \Invoice $invoice
 * @property-read \Invitation $invitation
 * @property-read \Client $client
 * @property-read \Account $account
 * @property-read \Contact $contact
 * @method static \Illuminate\Database\Query\Builder|\Payment whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment whereInvoiceId($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment whereClientId($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment whereContactId($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment whereInvitationId($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment whereAccountGatewayId($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment wherePaymentTypeId($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment whereIsDeleted($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment whereAmount($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment wherePaymentDate($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment whereTransactionReference($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment wherePayerId($value)
 * @method static \Illuminate\Database\Query\Builder|\Payment wherePublicId($value)
 * @method static \EntityModel scope($publicId = false, $accountId = false)
 */
	class Payment {}
}

namespace {
/**
 * Invoice
 *
 * @property integer $id
 * @property integer $client_id
 * @property integer $user_id
 * @property integer $account_id
 * @property integer $invoice_status_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $invoice_number
 * @property float $discount
 * @property string $po_number
 * @property string $invoice_date
 * @property string $due_date
 * @property string $terms
 * @property string $public_notes
 * @property boolean $is_deleted
 * @property boolean $is_recurring
 * @property integer $frequency_id
 * @property string $start_date
 * @property string $end_date
 * @property string $last_sent_date
 * @property integer $recurring_invoice_id
 * @property string $tax_name
 * @property float $tax_rate
 * @property float $amount
 * @property float $balance
 * @property integer $public_id
 * @property integer $invoice_design_id
 * @property boolean $is_quote
 * @property integer $quote_id
 * @property integer $quote_invoice_id
 * @property float $custom_value1
 * @property float $custom_value2
 * @property boolean $custom_taxes1
 * @property boolean $custom_taxes2
 * @property-read \Account $account
 * @property-read \User $user
 * @property-read \Client $client
 * @property-read \Illuminate\Database\Eloquent\Collection|\InvoiceItem[] $invoice_items
 * @property-read \InvoiceStatus $invoice_status
 * @property-read \Illuminate\Database\Eloquent\Collection|\Invitation[] $invitations
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereClientId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereInvoiceStatusId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereInvoiceNumber($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereDiscount($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice wherePoNumber($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereInvoiceDate($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereDueDate($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereTerms($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice wherePublicNotes($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereIsDeleted($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereIsRecurring($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereFrequencyId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereStartDate($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereEndDate($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereLastSentDate($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereRecurringInvoiceId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereTaxName($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereTaxRate($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereAmount($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereBalance($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice wherePublicId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereInvoiceDesignId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereIsQuote($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereQuoteId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereQuoteInvoiceId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereCustomValue1($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereCustomValue2($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereCustomTaxes1($value)
 * @method static \Illuminate\Database\Query\Builder|\Invoice whereCustomTaxes2($value)
 * @method static \EntityModel scope($publicId = false, $accountId = false)
 */
	class Invoice {}
}

namespace {
/**
 * Subscription
 *
 * @property integer $id
 * @property integer $account_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property integer $event_id
 * @property string $target_url
 * @method static \Illuminate\Database\Query\Builder|\Subscription whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Subscription whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\Subscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Subscription whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Subscription whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Subscription whereEventId($value)
 * @method static \Illuminate\Database\Query\Builder|\Subscription whereTargetUrl($value)
 */
	class Subscription {}
}

namespace {
/**
 * InvoiceStatus
 *
 * @property integer $id
 * @property string $name
 * @method static \Illuminate\Database\Query\Builder|\InvoiceStatus whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceStatus whereName($value)
 */
	class InvoiceStatus {}
}

namespace {
/**
 * Industry
 *
 * @property integer $id
 * @property string $name
 * @method static \Illuminate\Database\Query\Builder|\Industry whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Industry whereName($value)
 */
	class Industry {}
}

namespace {
/**
 * Activity
 *
 * @property integer $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property integer $account_id
 * @property integer $client_id
 * @property integer $user_id
 * @property integer $contact_id
 * @property integer $payment_id
 * @property integer $invoice_id
 * @property integer $credit_id
 * @property integer $invitation_id
 * @property string $message
 * @property string $json_backup
 * @property integer $activity_type_id
 * @property float $adjustment
 * @property float $balance
 * @property-read \Account $account
 * @method static \Illuminate\Database\Query\Builder|\Activity whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereClientId($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereContactId($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity wherePaymentId($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereInvoiceId($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereCreditId($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereInvitationId($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereMessage($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereJsonBackup($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereActivityTypeId($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereAdjustment($value)
 * @method static \Illuminate\Database\Query\Builder|\Activity whereBalance($value)
 * @method static \Activity scope()
 */
	class Activity {}
}

namespace {
/**
 * Timesheet
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $account_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $start_date
 * @property string $end_date
 * @property float $discount
 * @property float $hours
 * @property integer $public_id
 * @property-read \Account $account
 * @property-read \User $user
 * @property-read \Illuminate\Database\Eloquent\Collection|\TimeSheetEvent[] $timesheet_events
 * @method static \Illuminate\Database\Query\Builder|\Timesheet whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Timesheet whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\Timesheet whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\Timesheet whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Timesheet whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Timesheet whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Timesheet whereStartDate($value)
 * @method static \Illuminate\Database\Query\Builder|\Timesheet whereEndDate($value)
 * @method static \Illuminate\Database\Query\Builder|\Timesheet whereDiscount($value)
 * @method static \Illuminate\Database\Query\Builder|\Timesheet whereHours($value)
 * @method static \Illuminate\Database\Query\Builder|\Timesheet wherePublicId($value)
 */
	class Timesheet {}
}

namespace {
/**
 * Credit
 *
 * @property integer $id
 * @property integer $account_id
 * @property integer $client_id
 * @property integer $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property boolean $is_deleted
 * @property float $amount
 * @property float $balance
 * @property string $credit_date
 * @property string $credit_number
 * @property string $private_notes
 * @property integer $public_id
 * @property-read \Invoice $invoice
 * @property-read \Client $client
 * @method static \Illuminate\Database\Query\Builder|\Credit whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Credit whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\Credit whereClientId($value)
 * @method static \Illuminate\Database\Query\Builder|\Credit whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\Credit whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Credit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Credit whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Credit whereIsDeleted($value)
 * @method static \Illuminate\Database\Query\Builder|\Credit whereAmount($value)
 * @method static \Illuminate\Database\Query\Builder|\Credit whereBalance($value)
 * @method static \Illuminate\Database\Query\Builder|\Credit whereCreditDate($value)
 * @method static \Illuminate\Database\Query\Builder|\Credit whereCreditNumber($value)
 * @method static \Illuminate\Database\Query\Builder|\Credit wherePrivateNotes($value)
 * @method static \Illuminate\Database\Query\Builder|\Credit wherePublicId($value)
 * @method static \EntityModel scope($publicId = false, $accountId = false)
 */
	class Credit {}
}

namespace {
/**
 * Product
 *
 * @property integer $id
 * @property integer $account_id
 * @property integer $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $product_key
 * @property string $notes
 * @property float $cost
 * @property float $qty
 * @property integer $public_id
 * @method static \Illuminate\Database\Query\Builder|\Product whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Product whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\Product whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Product whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Product whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Product whereProductKey($value)
 * @method static \Illuminate\Database\Query\Builder|\Product whereNotes($value)
 * @method static \Illuminate\Database\Query\Builder|\Product whereCost($value)
 * @method static \Illuminate\Database\Query\Builder|\Product whereQty($value)
 * @method static \Illuminate\Database\Query\Builder|\Product wherePublicId($value)
 * @method static \EntityModel scope($publicId = false, $accountId = false)
 */
	class Product {}
}

namespace {
/**
 * PaymentTerm
 *
 * @property integer $id
 * @property integer $num_days
 * @property string $name
 * @method static \Illuminate\Database\Query\Builder|\PaymentTerm whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\PaymentTerm whereNumDays($value)
 * @method static \Illuminate\Database\Query\Builder|\PaymentTerm whereName($value)
 */
	class PaymentTerm {}
}

namespace {
/**
 * EntityModel
 *
 * @method static \EntityModel scope($publicId = false, $accountId = false)
 */
	class EntityModel {}
}

namespace {
/**
 * Timezone
 *
 * @property integer $id
 * @property string $name
 * @property string $location
 * @method static \Illuminate\Database\Query\Builder|\Timezone whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Timezone whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\Timezone whereLocation($value)
 */
	class Timezone {}
}

namespace {
/**
 * ProjectCode
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $account_id
 * @property integer $project_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $name
 * @property string $description
 * @property-read \Account $account
 * @property-read \User $user
 * @property-read \Project $project
 * @property-read \Illuminate\Database\Eloquent\Collection|\TimesheetEvent[] $events
 * @method static \Illuminate\Database\Query\Builder|\ProjectCode whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\ProjectCode whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\ProjectCode whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\ProjectCode whereProjectId($value)
 * @method static \Illuminate\Database\Query\Builder|\ProjectCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\ProjectCode whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\ProjectCode whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\ProjectCode whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\ProjectCode whereDescription($value)
 */
	class ProjectCode {}
}

namespace {
/**
 * Invitation
 *
 * @property integer $id
 * @property integer $account_id
 * @property integer $user_id
 * @property integer $contact_id
 * @property integer $invoice_id
 * @property string $invitation_key
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $transaction_reference
 * @property string $sent_date
 * @property string $viewed_date
 * @property integer $public_id
 * @property-read \Invoice $invoice
 * @property-read \Contact $contact
 * @property-read \User $user
 * @method static \Illuminate\Database\Query\Builder|\Invitation whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invitation whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invitation whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invitation whereContactId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invitation whereInvoiceId($value)
 * @method static \Illuminate\Database\Query\Builder|\Invitation whereInvitationKey($value)
 * @method static \Illuminate\Database\Query\Builder|\Invitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Invitation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Invitation whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Invitation whereTransactionReference($value)
 * @method static \Illuminate\Database\Query\Builder|\Invitation whereSentDate($value)
 * @method static \Illuminate\Database\Query\Builder|\Invitation whereViewedDate($value)
 * @method static \Illuminate\Database\Query\Builder|\Invitation wherePublicId($value)
 * @method static \EntityModel scope($publicId = false, $accountId = false)
 */
	class Invitation {}
}

namespace {
/**
 * Currency
 *
 * @property integer $id
 * @property string $name
 * @property string $symbol
 * @property string $precision
 * @property string $thousand_separator
 * @property string $decimal_separator
 * @property string $code
 * @method static \Illuminate\Database\Query\Builder|\Currency whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Currency whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\Currency whereSymbol($value)
 * @method static \Illuminate\Database\Query\Builder|\Currency wherePrecision($value)
 * @method static \Illuminate\Database\Query\Builder|\Currency whereThousandSeparator($value)
 * @method static \Illuminate\Database\Query\Builder|\Currency whereDecimalSeparator($value)
 * @method static \Illuminate\Database\Query\Builder|\Currency whereCode($value)
 */
	class Currency {}
}

namespace {
/**
 * InvoiceDesign
 *
 * @property integer $id
 * @property string $name
 * @method static \Illuminate\Database\Query\Builder|\InvoiceDesign whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\InvoiceDesign whereName($value)
 */
	class InvoiceDesign {}
}

namespace {
/**
 * DatetimeFormat
 *
 * @property integer $id
 * @property string $format
 * @property string $label
 * @method static \Illuminate\Database\Query\Builder|\DatetimeFormat whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\DatetimeFormat whereFormat($value)
 * @method static \Illuminate\Database\Query\Builder|\DatetimeFormat whereLabel($value)
 */
	class DatetimeFormat {}
}

namespace {
/**
 * Affiliate
 *
 * @property integer $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $name
 * @property string $affiliate_key
 * @property string $payment_title
 * @property string $payment_subtitle
 * @method static \Illuminate\Database\Query\Builder|\Affiliate whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Affiliate whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Affiliate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Affiliate whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Affiliate whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\Affiliate whereAffiliateKey($value)
 * @method static \Illuminate\Database\Query\Builder|\Affiliate wherePaymentTitle($value)
 * @method static \Illuminate\Database\Query\Builder|\Affiliate wherePaymentSubtitle($value)
 */
	class Affiliate {}
}

namespace {
/**
 * TaxRate
 *
 * @property integer $id
 * @property integer $account_id
 * @property integer $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $name
 * @property float $rate
 * @property integer $public_id
 * @method static \Illuminate\Database\Query\Builder|\TaxRate whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\TaxRate whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\TaxRate whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\TaxRate whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\TaxRate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\TaxRate whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\TaxRate whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\TaxRate whereRate($value)
 * @method static \Illuminate\Database\Query\Builder|\TaxRate wherePublicId($value)
 * @method static \EntityModel scope($publicId = false, $accountId = false)
 */
	class TaxRate {}
}

namespace {
/**
 * Gateway
 *
 * @property integer $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string $name
 * @property string $provider
 * @property boolean $visible
 * @property integer $payment_library_id
 * @property integer $sort_order
 * @property boolean $recommended
 * @property string $site_url
 * @property-read \PaymentLibrary $paymentlibrary
 * @method static \Illuminate\Database\Query\Builder|\Gateway whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Gateway whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Gateway whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Gateway whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\Gateway whereProvider($value)
 * @method static \Illuminate\Database\Query\Builder|\Gateway whereVisible($value)
 * @method static \Illuminate\Database\Query\Builder|\Gateway wherePaymentLibraryId($value)
 * @method static \Illuminate\Database\Query\Builder|\Gateway whereSortOrder($value)
 * @method static \Illuminate\Database\Query\Builder|\Gateway whereRecommended($value)
 * @method static \Illuminate\Database\Query\Builder|\Gateway whereSiteUrl($value)
 */
	class Gateway {}
}

namespace {
/**
 * PaymentType
 *
 * @property integer $id
 * @property string $name
 * @method static \Illuminate\Database\Query\Builder|\PaymentType whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\PaymentType whereName($value)
 */
	class PaymentType {}
}

namespace {
/**
 * License
 *
 * @property integer $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property integer $affiliate_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $license_key
 * @property boolean $is_claimed
 * @property string $transaction_reference
 * @method static \Illuminate\Database\Query\Builder|\License whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\License whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\License whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\License whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\License whereAffiliateId($value)
 * @method static \Illuminate\Database\Query\Builder|\License whereFirstName($value)
 * @method static \Illuminate\Database\Query\Builder|\License whereLastName($value)
 * @method static \Illuminate\Database\Query\Builder|\License whereEmail($value)
 * @method static \Illuminate\Database\Query\Builder|\License whereLicenseKey($value)
 * @method static \Illuminate\Database\Query\Builder|\License whereIsClaimed($value)
 * @method static \Illuminate\Database\Query\Builder|\License whereTransactionReference($value)
 */
	class License {}
}

namespace {
/**
 * DateFormat
 *
 * @property integer $id
 * @property string $format
 * @property string $picker_format
 * @property string $label
 * @method static \Illuminate\Database\Query\Builder|\DateFormat whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\DateFormat whereFormat($value)
 * @method static \Illuminate\Database\Query\Builder|\DateFormat wherePickerFormat($value)
 * @method static \Illuminate\Database\Query\Builder|\DateFormat whereLabel($value)
 */
	class DateFormat {}
}

namespace {
/**
 * TimesheetEvent
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $account_id
 * @property integer $timesheet_event_source_id
 * @property integer $timesheet_id
 * @property integer $project_id
 * @property integer $project_code_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $uid
 * @property string $summary
 * @property string $description
 * @property string $location
 * @property string $owner
 * @property string $start_date
 * @property string $end_date
 * @property float $hours
 * @property float $discount
 * @property string $org_code
 * @property string $org_created_at
 * @property string $org_updated_at
 * @property string $org_start_date_timezone
 * @property string $org_end_date_timezone
 * @property string $org_data
 * @property string $import_error
 * @property string $updated_data
 * @property-read \Account $account
 * @property-read \User $user
 * @property-read \TimesheetEventSource $source
 * @property-read \Timesheet $timesheet
 * @property-read \Project $project
 * @property-read \ProjectCode $project_code
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereTimesheetEventSourceId($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereTimesheetId($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereProjectId($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereProjectCodeId($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereUid($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereSummary($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereDescription($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereLocation($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereOwner($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereStartDate($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereEndDate($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereHours($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereDiscount($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereOrgCode($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereOrgCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereOrgUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereOrgStartDateTimezone($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereOrgEndDateTimezone($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereOrgData($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereImportError($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEvent whereUpdatedData($value)
 */
	class TimesheetEvent {}
}

namespace {
/**
 * Project
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $account_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $name
 * @property string $description
 * @property-read \Account $account
 * @property-read \User $user
 * @property-read \Illuminate\Database\Eloquent\Collection|\ProjectCode[] $codes
 * @method static \Illuminate\Database\Query\Builder|\Project whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Project whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\Project whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\Project whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Project whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Project whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Project whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\Project whereDescription($value)
 */
	class Project {}
}

namespace {
/**
 * Account
 *
 * @property integer $id
 * @property integer $timezone_id
 * @property integer $date_format_id
 * @property integer $datetime_format_id
 * @property integer $currency_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $name
 * @property string $ip
 * @property string $account_key
 * @property string $last_login
 * @property string $address1
 * @property string $address2
 * @property string $city
 * @property string $state
 * @property string $postal_code
 * @property integer $country_id
 * @property string $invoice_terms
 * @property string $email_footer
 * @property integer $industry_id
 * @property integer $size_id
 * @property boolean $invoice_taxes
 * @property boolean $invoice_item_taxes
 * @property integer $invoice_design_id
 * @property string $work_phone
 * @property string $work_email
 * @property integer $language_id
 * @property string $pro_plan_paid
 * @property string $custom_label1
 * @property string $custom_value1
 * @property string $custom_label2
 * @property string $custom_value2
 * @property string $custom_client_label1
 * @property string $custom_client_label2
 * @property boolean $fill_products
 * @property boolean $update_products
 * @property string $primary_color
 * @property string $secondary_color
 * @property boolean $hide_quantity
 * @property boolean $hide_paid_to_date
 * @property string $custom_invoice_label1
 * @property string $custom_invoice_label2
 * @property boolean $custom_invoice_taxes1
 * @property boolean $custom_invoice_taxes2
 * @property string $vat_number
 * @property-read \Illuminate\Database\Eloquent\Collection|\User[] $users
 * @property-read \Illuminate\Database\Eloquent\Collection|\Client[] $clients
 * @property-read \Illuminate\Database\Eloquent\Collection|\Invoice[] $invoices
 * @property-read \Illuminate\Database\Eloquent\Collection|\AccountGateway[] $account_gateways
 * @property-read \Illuminate\Database\Eloquent\Collection|\TaxRate[] $tax_rates
 * @property-read \Country $country
 * @property-read \Timezone $timezone
 * @property-read \Language $language
 * @property-read \DateFormat $date_format
 * @property-read \DatetimeFormat $datetime_format
 * @property-read \Size $size
 * @property-read \Industry $industry
 * @method static \Illuminate\Database\Query\Builder|\Account whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereTimezoneId($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereDateFormatId($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereDatetimeFormatId($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCurrencyId($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereIp($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereAccountKey($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereLastLogin($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereAddress1($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereAddress2($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCity($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereState($value)
 * @method static \Illuminate\Database\Query\Builder|\Account wherePostalCode($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCountryId($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereInvoiceTerms($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereEmailFooter($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereIndustryId($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereSizeId($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereInvoiceTaxes($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereInvoiceItemTaxes($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereInvoiceDesignId($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereWorkPhone($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereWorkEmail($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereLanguageId($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereProPlanPaid($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCustomLabel1($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCustomValue1($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCustomLabel2($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCustomValue2($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCustomClientLabel1($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCustomClientLabel2($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereFillProducts($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereUpdateProducts($value)
 * @method static \Illuminate\Database\Query\Builder|\Account wherePrimaryColor($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereSecondaryColor($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereHideQuantity($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereHidePaidToDate($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCustomInvoiceLabel1($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCustomInvoiceLabel2($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCustomInvoiceTaxes1($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereCustomInvoiceTaxes2($value)
 * @method static \Illuminate\Database\Query\Builder|\Account whereVatNumber($value)
 */
	class Account {}
}

namespace {
/**
 * TimesheetEventSource
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $account_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $owner
 * @property string $name
 * @property string $url
 * @property string $type
 * @property string $from_date
 * @property string $to_date
 * @property-read \Account $account
 * @property-read \User $user
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEventSource whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEventSource whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEventSource whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEventSource whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEventSource whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEventSource whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEventSource whereOwner($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEventSource whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEventSource whereUrl($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEventSource whereType($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEventSource whereFromDate($value)
 * @method static \Illuminate\Database\Query\Builder|\TimesheetEventSource whereToDate($value)
 */
	class TimesheetEventSource {}
}

