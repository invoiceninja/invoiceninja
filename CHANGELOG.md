# Release notes

## [Unreleased (daily channel)](https://github.com/invoiceninja/invoiceninja/tree/v5-develop)

## [v5.2.0-release](https://github.com/invoiceninja/invoiceninja/releases/tag/v5.2.0-release)
## Added:
- Timezone Offset: Schedule emails based on timezone and time offsets.
- Force client country to system country if none is set.
- GMail Oauth via web

## Fixed:
- Add Cache-control: no-cache to prevent overaggressive caching of assets
- Improved labelling in the settings (client portal)
- Client portal: Multiple accounts access improvements (#5703)
- Client portal: "Credits" updates (#5734)
- Client portal: Make sidebar white color, in order to make logo displaying more simple. (#5753)
- Inject small delay into emails to allow all resources to be produced (ie PDFs) prior to sending
- Fixes for endless reminders not firing

## [v5.1.56-release](https://github.com/invoiceninja/invoiceninja/releases/tag/v5.1.56-release)
## Fixed:
- Fix User created/updated/deleted Actvity display format
- Fix for Stripe autobill / token regression

## Added:
- Invoice / Quote / Credit created notifications
- Logout route - deletes all auth tokens

## [v5.1.54-release](https://github.com/invoiceninja/invoiceninja/releases/tag/v5.1.54-release)
## Fixed:
- Fixes for e-mails, encoding & parsing invalid HTML

## [v5.1.50-release](https://github.com/invoiceninja/invoiceninja/releases/tag/v5.1.50-release)
## Fixed:
- Refactor of e-mail templates
- Client portal: Invoices & recurring invoices are now sorted by date (by default)

## Added:
- Public notes of entities will now show in #footer section of designs (previously totals table).

## [v5.1.47-release](https://github.com/invoiceninja/invoiceninja/releases/tag/v5.1.47-release)

### Added:
- Subscriptions are now going to show the frequency in the table (#5412)
- Subscriptions: During upgrade webhook request message will be shown for easier debugging (#5411)
- PDF: Custom fields now will be shared across invoices, quotes & credits (#5410)
- Client portal: Invoices are now sorted in the descending order (#5408)
- Payments: ACH notification after the initial bank account connecting process (#5405)

### Fixed:
- Fixes for counters where patterns without {$counter} could causes endless recursion.
- Fixes for surcharge tax displayed amount on PDF.
- Fixes for custom designs not rendering the custom template
- Fixes for missing bulk actions on Subscriptions
- Fixes CSS padding on the show page for recurring invoices (#5412)
- Fixes for rendering invalid HTML & parsing invalid XML (#5395)

### Removed:
- Removed one-time payments table (#5412)

## v5.1.43

### Fixed:
- Whitelabel regression.
