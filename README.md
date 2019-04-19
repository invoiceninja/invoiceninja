<p align="center">
    <img src="https://raw.githubusercontent.com/hillelcoren/invoice-ninja/master/public/images/round_logo.png" alt="Sublime's custom image"/>
</p>

[![Build Status](https://travis-ci.org/invoiceninja/invoiceninja.svg?branch=v2)](https://travis-ci.org/invoiceninja/invoiceninja)
[![codecov](https://codecov.io/gh/invoiceninja/invoiceninja/branch/v2/graph/badge.svg)](https://codecov.io/gh/invoiceninja/invoiceninja)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/d39acb4bf0f74a0698dc77f382769ba5)](https://www.codacy.com/app/turbo124/invoiceninja?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=invoiceninja/invoiceninja&amp;utm_campaign=Badge_Grade)

**Invoice Ninja v 2** is coming soon!

We will be using the lessons learnt in Invoice Ninja 4.0 to build a bigger better platform to work from. If you would like to contribute to the project we will gladly accept contributions for code, user guides, bug tracking and feedback! Please consider the following guidelines prior to submitting a pull request:

# Contribution guide.

Code Style to follow [PSR-2](https://www.php-fig.org/psr/psr-2/) standards.

All methods names to be in CamelCase

All variables names to be in snake_case

Where practical code should be strongly typed, ie your methods must return a type ie

`public function doThis() : void`

PHP >= 7.1 allows the return type Nullable so there should be no circumstance a type cannot be return by using the following:

`public function doThat() ?:string`

To improve chances of PRs being merged please include teststo ensure your code works well and integrates with the rest of the project.
