<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Account;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Account\BlackListRule;
use App\Http\ValidationRules\Account\EmailBlackListRule;
use App\Http\ValidationRules\NewUniqueUserRule;
use App\Utils\Ninja;

class CreateAccountRequest extends Request
{

    private array $fake_domains = [
        'generator.email',
        'emailfake.com',
        'email-fake.com',
        '10minutemail.com',
        '10minutemail.net',
        '10minmail.com',
        'tempmail.com',
        'temp-mail.org',
        'tempmail.net',
        'tempmailo.com',
        'tempmail.ws',
        'tempmail.ninja',
        'tempmail.plus',
        'guerrillamail.com',
        'guerrillamail.net',
        'guerrillamail.org',
        'guerrillamail.de',
        'mailinator.com',
        'yopmail.com',
        'yopmail.fr',
        'yopmail.net',
        'maildrop.cc',
        'mailnesia.com',
        'throwawaymail.com',
        'fakemailgenerator.com',
        'trashmail.com',
        'trashmail.de',
        'spamgourmet.com',
        'mailondeck.com',
        'getnada.com',
        'nada.ltd',
        'dispostable.com',
        'mohmal.com',
        '33mail.com',
        'anonaddy.com',
        'simplelogin.io',
        'burnermail.io',
        'burnermail.com',
        'mytempmail.org',
        'fakeinbox.com',
        'mail-temp.com',
        '20minutemail.com',
        '30minutemail.com',
        '60minutemail.com',
        '0wnd.net',
        '0clickemail.com',
        '0-mail.com',
        'mailcatch.com',
        'mailcatch.org',
        'mailforspam.com',
        'getairmail.com',
        'mailtothis.com',
        'spam4.me',
        'spamdecoy.net',
        'mailnull.com',
        'inboxkitten.com',
        'mailimate.com',
        'instantemailaddress.com',
        'mailfreeonline.com',
        'mailsac.com',
        'fakebox.org',
        'nowmymail.com',
        'mail-temporaire.fr',
        'trashmail.ws',
        'spamspot.com',
        'mail-temporaire.com',
        'tempinbox.com',
        'mailcatch.io',
        'inboxalias.com',
        'get-mail.org',
        'mailpoof.com',
        'temporary-mail.net',
        'mytrashmail.com',
        'spambog.com',
        'spambog.de',
        'spambox.us',
        'spamcorptastic.com',
        'temporarymail.com',
        'mail-tester.com',
        'temporarymail.org',
        'emailondeck.com',
        'easytrashmail.com',
        'mailsucker.net',
        'fakeemailgenerator.com',
        'mail7.io',
        'sharklasers.com',
        'spamgourmet.net',
        'fakermail.com',
        'dodsi.com',
        'spamstack.net',
        'byom.de',
        'temporarymailaddress.com',
        'mail-temp.org',
        'spambox.info',
        'luxusmail.org',
        'e-mail.com',
        'trash-me.com',
        'fexbox.org',
        'getonemail.com',
        'mailhub.pro',
        'cryptogmail.com',
        'mailjunkie.com',
        'fake-mail.io',
        'disposablemail.com',
        'disposableinbox.com',
        'mailswipe.net',
        'instantmailaddress.com',
        'dropmail.me',
        'trashmails.com',
        'spambog.ru',
        'fakeinbox.org',
        'meltmail.com',
        'mail-temporaire.info',
        'mailnesia.net',
        'mailscreen.com',
        'spambooger.com',
        'tempr.email',
        'emailondeck.net',
        'throwawayaddress.com',
        'mail-temp.info',
        'mailinator.net',
        'emailtemporal.org',
        'tempmailgen.com',
        'temporaryemail.net',
        'temporaryinbox.com',
        'tempmailer.com',
        'tempmailer.de',
        'mymail-in.net',
        'trashmail.net',
        'mailexpire.com',
        'mailhazard.com',
        'guerrillamailblock.com',
        'temporary-mail.org',
        'mailcatch.io',
        'emailtemporal.com',
        'dropmail.ga',
        'tempail.com',
        'spambox.org',
        'mytemp.email',
        'mailspeed.ru',
        'mailimate.org',
        'getairmail.com',
        'dispostable.org',
        'emailfake.org',
        'fakemail.net',
        'tempmailaddress.com',
        'tempmailbox.com',
        'mailbox92.biz',
        'tempmailgen.org',
        'mail-temporaire.net',
        'tempmail24.com',
        'inboxbear.com',
        'maildrop.cf',
        'maildrop.ga',
        'maildrop.gq',
        'maildrop.ml',
        'maildrop.tk',
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if (Ninja::isHosted()) {
            $email_rules = ['bail', 'required', 'max:255', 'email:rfc,dns', new NewUniqueUserRule(), new BlackListRule(), new EmailBlackListRule()];
        } else {
            $email_rules = ['bail', 'required', 'max:255', 'email:rfc,dns', new NewUniqueUserRule()];
        }

        return [
            'first_name'        => 'string|max:100',
            'last_name'         =>  'string:max:100',
            'password'          => 'required|string|min:6|max:100',
            'email'             =>  $email_rules,
            'privacy_policy'    => 'required|boolean',
            'terms_of_service'  => 'required|boolean',
            'utm_source'        => 'sometimes|nullable|string',
            'utm_medium'        => 'sometimes|nullable|string',
            'utm_campaign'      => 'sometimes|nullable|string',
            'utm_term'          => 'sometimes|nullable|string',
            'utm_content'       => 'sometimes|nullable|string',
            // 'cf-turnstile'      => 'required_if:token_name,web_client|string',
        ];
    }

    public function withValidator($validator)
    {

        $validator->after(function ($validator) {
        

        try {
            $domain = explode("@", $this->input('email'))[1] ?? "";
            $dns = dns_get_record($domain, DNS_MX);
            $server = $dns[0]["target"] ?? null;

            if($server && in_array($server, $this->fake_domains)){
                $validator->errors()->add('email', 'Account Already Exists.');
            }
        } catch (\Throwable $e) {
        
            nlog($e->getMessage());
            nlog("I could not check the email address => ".$this->input('email'));
        }

        });
    }

    public function prepareForValidation()
    {

        nlog(array_merge(['signup' => 'true', 'ipaddy' => request()->ip(), 'headers' => request()->headers->all()], $this->all()));

        $input = $this->all();

        $input['user_agent'] = request()->server('HTTP_USER_AGENT');

        $this->replace($input);
    }
}
