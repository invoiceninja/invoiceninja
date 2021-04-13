# Release notes

## [Unreleased (daily channel)](https://github.com/invoiceninja/invoiceninja/tree/v5-develop)

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
