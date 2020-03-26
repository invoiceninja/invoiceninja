# Contributing to Invoice Ninja

Thanks for your contributions!

## Submit bug reports or feature requests

Please discuss the changes with us ahead of time to ensure they will be merged.

### Submit pull requests
 * [Fork](https://github.com/invoiceninja/invoiceninja#fork-destination-box) the [Invoice Ninja repository](https://github.com/invoiceninja/invoiceninja)
 * Create a new branch with the name `#issue_number-Short-description`
   * _Example:_ `#100-Add-GoogleAnalytics`
 * Make your changes and commit
 * Check if your branch is still in sync with the repositorys **`develop`** branch
   * _Read:_ [Syncing a fork](https://help.github.com/articles/syncing-a-fork/)
   * _Also read:_ [How to rebase a pull request](https://github.com/edx/edx-platform/wiki/How-to-Rebase-a-Pull-Request)
 * Push your branch and create a PR against the Invoice Ninja **`develop`** branch
 * Update the [Changelog](CHANGELOG.md)

### Some rules
To make the contribution process nice and easy for anyone, please follow some rules:
 * Each contribution(bug or feature) should have an [issue on Github](https://github.com/invoiceninja/invoiceninja/issues)
to give a more detailed explanation.
 * Only one feature/bugfix per issue. If you want to submit more, create multiple issues.
 * Only one feature/bugfix per PR(pull request). Split more changes into multiple PRs.

#### Coding Style
Try to follow the [PSR-2 guidlines](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)

_Example styling:_
```php
/**
 * Gets a preview of the email
 *
 * @param TemplateService $templateService
 *
 * @return \Illuminate\Http\Response
 */
public function previewEmail(TemplateService $templateService)
{
    //
}
```


## Translations
For helping us with translating Invoice Ninja, please use [Transifex](https://www.transifex.com/invoice-ninja/invoice-ninja/).
