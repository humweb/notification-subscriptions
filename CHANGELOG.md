# Changelog

All notable changes to `notification-subscriptions` will be documented in this file.

## Unreleased

-   Added structured digest builder via `Humweb\Notifications\Digest\DigestMessage` with support for line, heading, panel, button, list, separator
-   `UserNotificationDigest` now detects `toDigest($notifiable, DigestMessage $builder, $data)` on original notifications to compose rich digests
-   New markdown view `notification-subscriptions::digest` to render digest components; views registered by the service provider
-   Config options: `digest_subject`, `digest_markdown_view` for easy customization
-   Backwards compatible with existing `toDigestFormat()` and `toArray()` fallback behavior
