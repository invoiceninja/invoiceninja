# Changelog
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).


## [Unreleased]

### Changed
- Auto billing uses credits if they exist


## [2.6.4] - 2016-07-19

### Added
- Added 'Buy Now' buttons

### Fixed
- Setting default tax rate breaks invoice creation #974


## [2.6] - 2016-07-12

### Added
- Configuration for first day of the week #950
- StyleCI configuration #929
- Added expense category

### Changed
- Removed `invoiceninja.komodoproject` from Git #932
- `APP_CIPHER` changed from `rinjdael-128` to `AES-256-CBC` #898
- Improved options when exporting data

### Fixed
- "Manual entry" untranslatable #562
- Using a database table prefix breaks the dashboard #203
- Request statically called in StartupCheck.php #977
