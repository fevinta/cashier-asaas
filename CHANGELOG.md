# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
