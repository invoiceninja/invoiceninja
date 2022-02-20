<p align="center">
    <img src="https://raw.githubusercontent.com/hillelcoren/invoice-ninja/master/public/images/round_logo.png" alt="Sublime's custom image"/>
</p>

![v5-develop phpunit](https://github.com/invoiceninja/invoiceninja/workflows/phpunit/badge.svg?branch=v5-develop)
![v5-stable phpunit](https://github.com/invoiceninja/invoiceninja/workflows/phpunit/badge.svg?branch=v5-stable)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/d16c78aad8574466bf83232b513ef4fb)](https://www.codacy.com/gh/turbo124/invoiceninja/dashboard?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=turbo124/invoiceninja&amp;utm_campaign=Badge_Grade)
<a href="https://cla-assistant.io/invoiceninja/invoiceninja"><img src="https://cla-assistant.io/readme/badge/invoiceninja/invoiceninja" alt="CLA assistant" /></a>

# Invoice Ninja 5

## [Hosted](https://www.invoiceninja.com) | [Self-Hosted](https://www.invoiceninja.org)

### We're on Slack, join us at [slack.invoiceninja.com](http://slack.invoiceninja.com), [forum.invoiceninja.com](https://forum.invoiceninja.com) or if you like [StackOverflow](https://stackoverflow.com/tags/invoice-ninja/)

Just make sure to add the `invoice-ninja` tag to your question.

## Introduction

Version 5 of Invoice Ninja is here! We've taken the best parts of version 4 and bolted on all of the most requested features to produce a invoicing application like no other. 

The new interface has a lot more functionality so it isn't a carbon copy of v4, but once you get used to the new layout and functionality we are sure you will love it!

## Referral Program
* Earn 50% of Pro & Enterprise Plans up to 4 years - [Learn more](https://www.invoiceninja.com/referral-program/)

## Recommended Providers
* [Stripe](https://stripe.com/)
* [Postmark](https://postmarkapp.com/)

## Development
* [API Documentation](https://app.swaggerhub.com/apis/invoiceninja/invoiceninja)
* [APP Documentation](https://invoiceninja.github.io/)

## Quick Start

```bash
git clone https://github.com/invoiceninja/invoiceninja.git
git checkout v5-stable
cp .env.example .env
composer update
php artisan key:generate
```

Please Note: Your APP_KEY in the .env file is used to encrypt data, if you lose this you will not be able to run the application.

Run if you want to load sample data, remember to configure .env
```
php artisan migrate:fresh --seed && php artisan db:seed && php artisan ninja:create-test-data
```

To run the web server
```
php artisan serve 
```

Navigate to (replace ninja.test as required)
```
http://ninja.test:8000/setup - To setup your configuration if you didn't load sample data.
http://ninja.test:8000/ - For Administrator Logon

user: small@example.com
pass: password

http://ninja.test:8000/client/login - For Client Portal

user: user@example.com
pass: password
```

## Contribution guide.

Code Style to follow [PSR-2](https://www.php-fig.org/psr/psr-2/) standards.

All methods names to be in CamelCase

All variables names to be in snake_case

Where practical code should be strongly typed, ie your methods must return a type ie

`public function doThis() : void`

PHP >= 7.3 allows the return type Nullable so there should be no circumstance a type cannot be return by using the following:

`public function doThat() ?:string`

To improve chances of PRs being merged please include tests to ensure your code works well and integrates with the rest of the project.

## Documentation

API documentation is hosted using Swagger and can be found [HERE](https://app.swaggerhub.com/apis/invoiceninja/invoiceninja)

Installation, Configuration and Troubleshooting documentation can be found [HERE] (https://invoiceninja.github.io)

## Credits
* [Hillel Coren](https://hillelcoren.com/)
* [David Bomba](https://github.com/turbo124)
* [All contributors](https://github.com/invoiceninja/invoiceninja/graphs/contributors)

**Special thanks to:**
* [Holger Lösken](https://github.com/codedge) - [codedge](http://codedge.de)
* [Samuel Laulhau](https://github.com/lalop) - [Lalop](http://lalop.co/)
* [Alexander Vanderveen](https://blog.technicallycomputers.ca/) - [Technically Computers](https://www.technicallycomputers.ca/)
* [Efthymios Sarmpanis](https://github.com/esarbanis)
* [Gianfranco Gasbarri](https://github.com/gincos)
* [Clemens Mol](https://github.com/clemensmol)
* [Benjamin Beganović](https://github.com/beganovich)

## Security

If you find a security issue with this application please send an email to contact@invoiceninja.com Please follow responsible disclosure procedures if you detect an issue. For further information on responsible disclosure please read [here](https://cheatsheetseries.owasp.org/cheatsheets/Vulnerability_Disclosure_Cheat_Sheet.html)

## License
Invoice Ninja is released under the Elastic License.  
See [LICENSE](LICENSE) for details.
