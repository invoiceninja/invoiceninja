<p align="center">
    <img src="https://raw.githubusercontent.com/hillelcoren/invoice-ninja/master/public/images/round_logo.png" alt="Sublime's custom image"/>
</p>

![v5-develop phpunit](https://github.com/invoiceninja/invoiceninja/workflows/phpunit/badge.svg?branch=v5-develop)
![v5-stable phpunit](https://github.com/invoiceninja/invoiceninja/workflows/phpunit/badge.svg?branch=v5-stable)

[![Codacy Badge](https://api.codacy.com/project/badge/Grade/d39acb4bf0f74a0698dc77f382769ba5)](https://www.codacy.com/app/turbo124/invoiceninja?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=invoiceninja/invoiceninja&amp;utm_campaign=Badge_Grade)

# Invoice Ninja version 5! 

## Quick Start

Currently the client portal and API are of alpha quality, to get started:

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

## Current work in progress

Invoice Ninja is currently being written in a combination of Laravel for the API and Client Portal and Flutter for the front end management console. This will allow an immersive and consistent experience across any device: mobile, tablet or desktop.

To manage our workflow we will be creating separate branches for the client (Flutter) and server (Laravel API / Client Portal) and merge these into a release branch for deployments.

## License
Invoice Ninja is released under the Attribution Assurance License.  
See [LICENSE](LICENSE) for details.
