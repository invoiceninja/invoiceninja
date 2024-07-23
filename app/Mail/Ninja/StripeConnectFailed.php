<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Ninja;

use App\Models\Company;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class StripeConnectFailed extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public User $user, public Company $company)
    {
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: "Stripe Connect not configured, please login and connect.",
            from: config('ninja.contact.email'),
            to: $this->user->email, //@phpstan-ignore-line
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {

        return new Content(
            view: 'email.admin.stripe_connect_failed',
            text: 'email.admin.stripe_connect_failed_text',
            with: [
                'text_body' => $this->textBody(), //@todo this is a bit hacky here.
                'body' => $this->htmlBody(),
                'title' => 'Connect your Stripe account',
                'settings' => $this->company->settings,
                'logo' => $this->company->present()->logo(),
                'signature' => '',
                'company' => $this->company,
                'greeting' => '',
                'links' => [],
                'url' => 'https://www.loom.com/share/a3dc3131cc924e14a34634d5d48065c8?sid=b1971aa2-9deb-4339-8ebd-53f9947ef633',
                'button' => "texts.view"
            ]
        );
    }

    private function textBody()
    {
        return "
        We note you are yet to connect your Stripe account to Invoice Ninja. Please log in to Invoice Ninja and connect your Stripe account.\n\n
        Once logged in you can use the following resource to connect your Stripe account: \n\n
        ";
    }

    private function htmlBody()
    {
        return "
        We note you are yet to connect your Stripe account to Invoice Ninja. Please log in to Invoice Ninja and connect your Stripe account.<br><br>

        Once logged in you can use the following resource to connect your Stripe account: <br><br>

        ";
    }
    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }

    /**
     * Get the message headers.
     *
     * @return \Illuminate\Mail\Mailables\Headers
     */
    public function headers()
    {
        return new Headers(
            messageId: null,
            references: [],
            text:['' => ''],
        );
    }
}
