# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-08-15

### Added

- Laravel 13 support
- PHP 8.5 support

### Changed

- **BREAKING**: Minimum PHP version raised to 8.3 (required by Laravel 13)
- **BREAKING**: Dropped Laravel 11 support (reached end of security support in March 2026)
- Test suite upgraded to Pest 4 / PHPUnit 12
- Dev dependencies raised: `orchestra/testbench` to `^10.0|^11.0`, `larastan/larastan` to `^3.10`,
  `moneyphp/money` to `^4.9`, `laravel/pint` to `^1.30`
- CI matrix now covers PHP 8.3/8.4/8.5 against Laravel 12 and 13

### Fixed

- Coverage reporting is no longer hard-wired into `phpunit.xml`. Under PHPUnit 12 a configured
  coverage report with no available driver (Xdebug/PCOV) aborted the run, so contributors without
  a coverage driver silently ran zero tests. Coverage is now requested explicitly via
  `composer test-coverage`.

## [1.0.0] - 2024-12-28

### Added

- Initial release
- Billable trait for Laravel models
- Subscription management (create, update, cancel, resume, swap)
- Support for Brazilian payment methods: PIX, Boleto, Credit Card
- Trial period support
- Webhook handling for Asaas events
- Payment events: PaymentCreated, PaymentReceived, PaymentConfirmed, PaymentOverdue, PaymentRefunded, PaymentDeleted
- Subscription events: SubscriptionCreated, SubscriptionUpdated, SubscriptionDeleted
- Brazilian-specific events: PixGenerated, BoletoGenerated, BoletoRegistered
- Single charge support with `charge()` and `chargeInstallments()`
- Payment split functionality for revenue sharing
- Webhook signature verification middleware
- EnsureUserIsSubscribed middleware
- Comprehensive exception classes for error handling
- Full test coverage (99.3%)
- Laravel 11 and 12 support
- PHP 8.2+ support
