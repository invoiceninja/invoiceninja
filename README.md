<p align="center">
    <img src="https://raw.githubusercontent.com/hillelcoren/invoice-ninja/master/public/images/round_logo.png" alt="Sublime's custom image"/>
</p>

![v5-develop phpunit](https://github.com/invoiceninja/invoiceninja/workflows/phpunit/badge.svg?branch=v5-develop)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/d16c78aad8574466bf83232b513ef4fb)](https://www.codacy.com/gh/turbo124/invoiceninja/dashboard?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=turbo124/invoiceninja&amp;utm_campaign=Badge_Grade)
<a href="https://cla-assistant.io/invoiceninja/invoiceninja"><img src="https://cla-assistant.io/readme/badge/invoiceninja/invoiceninja" alt="CLA assistant" /></a>

# Invoice Ninja 5

## [Hosted](https://www.invoiceninja.com) | [Self-Hosted](https://www.invoiceninja.org)

Join us on [Slack](http://slack.invoiceninja.com), [Discord](https://discord.gg/ZwEdtfCwXA), [Support Forum](https://forum.invoiceninja.com)

## Introduction

Version 5 of Invoice Ninja is here!
We took the best parts of version 4 and add the most requested features 
to produce a invoicing application like no other. 

All Pro and Enterprise features from the hosted app are included in the open code.
We offer a $30 per year white-label license to remove the Invoice Ninja branding from client facing parts of the app.

* [Videos](https://www.youtube.com/@appinvoiceninja)
* [API Documentation](https://api-docs.invoicing.co/)
* [APP Documentation](https://invoiceninja.github.io/)
* [Support Forum](https://forum.invoiceninja.com)

## Setup

### Mobile Apps
* [iPhone](https://apps.apple.com/app/id1503970375?platform=iphone)
* [Android](https://play.google.com/store/apps/details?id=com.invoiceninja.app)
* [F-Droid](https://f-droid.org/en/packages/com.invoiceninja.app)

### Desktop Apps
* [macOS](https://apps.apple.com/app/id1503970375?platform=mac)
* [Windows](https://microsoft.com/en-us/p/invoice-ninja/9n3f2bbcfdr6)
* [Linux - Snap](https://snapcraft.io/invoiceninja)
* [Linux - Flatpak](https://flathub.org/apps/com.invoiceninja.InvoiceNinja)

### Installation Options
* [Docker File](https://hub.docker.com/r/invoiceninja/invoiceninja/)
* [Cloudron](https://cloudron.io/store/com.invoiceninja.cloudronapp.html)
* [Softaculous](https://www.softaculous.com/apps/ecommerce/Invoice_Ninja)
 
### Recommended Providers
* [Stripe](https://stripe.com/)
* [Postmark](https://postmarkapp.com/)

## Quick Hosting Setup

```sh
git clone --single-branch --branch v5-stable https://github.com/invoiceninja/invoiceninja.git
cp .env.example .env
composer i -o --no-dev
php artisan key:generate
```

Please Note: 
Your APP_KEY in the .env file is used to encrypt data, if you lose this you will not be able to run the application.

Run if you want to load sample data, remember to configure .env
```sh
php artisan migrate:fresh --seed && php artisan db:seed && php artisan ninja:create-test-data
```

To run the web server
```sh
php artisan serve 
```

Navigate to (replace localhost with the appropriate domain)
```
http://localhost:8000/setup - To setup your configuration if you did not load sample data.
http://localhost:8000/ - For Administrator Logon

user: small@example.com
pass: password

http://localhost:8000/client/login - For Client Portal

user: user@example.com
pass: password
```
## Developers Guide


### App Design

The API and client portal have been developed using [Laravel](https://laravel.com) if you wish to contribute to this project familiarity with Laravel is essential.

When inspecting functionality of the API, the best place to start would be in the routes/api.php file which describes all of the availabe API endpoints. The controller methods then describe all the entry points into each domain of the application, ie InvoiceController / QuoteController

The average API request follows this path into the application.

* Middleware processes the request initially inspecting the domain being requested + provides the authentication layer.
* The request then passes into a Form Request (Type hinted in the controller methods) which is used to provide authorization and also validation of the request. If successful, the request is then passed into the controller method where it is digested, here is an example:

```php
public function store(StoreInvoiceRequest $request)
{

    $invoice = $this->invoice_repo->save($request->all(), InvoiceFactory::create(auth()->user()->company()->id, auth()->user()->id));

    $invoice = $invoice->service()
                        ->fillDefaults()
                        ->triggeredActions($request)
                        ->adjustInventory()
                        ->save();

    event(new InvoiceWasCreated($invoice, $invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

    return $this->itemResponse($invoice);

}
```

Here for example we are storing a new invoice, we pass the validated request along with a factory into the invoice repository where it is processed and saved.

The returned invoice then passes through its service class (app/Services/Invoice) where various actions are performed.

A event is then fired which notifies listeners in the application (app/Providers/EventServiceProvider) which perform non blocking sub tasks 

Finally the invoice is transformed (app/Transformers/) and returned as a response via Fractal.

### Developer environment

Using the Quick Hosting Setup describe above you can quickly get started building out your development environment. Instead of using 

```
composer i -o --no-dev
``` 

use

```
composer i -o
```

This provides the developer tools including phpunit which allows the test suite to be run.

If you are considering contributing back to the main repository, please add in any tests for new functionality / modifications. This will greatly increase the chances of your PR being accepted

Also, if you plan any additions for the main repository, you may want to discuss this with us first on Slack where we can assist with any technical information and provide advice.

## Credits
* [Hillel Coren](https://hillelcoren.com/)
* [David Bomba](https://github.com/turbo124)
* [Benjamin Beganović](https://github.com/beganovich)
* [All Contributors](https://github.com/invoiceninja/invoiceninja/graphs/contributors)

## Security

If you find a security issue with this application, please send an email to contact@invoiceninja.com.
Please follow responsible disclosure procedures if you detect an issue.
For further information on responsible disclosure please read [here](https://cheatsheetseries.owasp.org/cheatsheets/Vulnerability_Disclosure_Cheat_Sheet.html).

## License
Invoice Ninja is released under the Elastic License.  
See [LICENSE](LICENSE) for details.
