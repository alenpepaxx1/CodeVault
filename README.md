# CodeVault 4.0

**A modern, multilingual PHP marketplace for selling source code and digital products.**

CodeVault provides a complete storefront, secure checkout flow, private customer portal, license delivery, protected ZIP downloads, and an administration dashboard. It is built with PHP 8.1+, SQLite, vanilla JavaScript, and modern CSS, with no framework or build step required.

Copyright © 2026 Alen Pepa. All rights reserved.

## Highlights

- Modern, responsive dark interface with an animated branded loading screen
- English, German, and Albanian storefront translations
- Live product search, category filters, sorting, and multi-product cart
- PayPal Orders API integration with Sandbox and Live modes
- Bitcoin and Lightning payments through BTCPay Server
- Configurable manual payments, including bank transfer instructions
- Private order and license portal for customers
- Versioned ZIP packages with controlled, expiring download links
- Advanced administration dashboard for products, orders, payments, and security events
- TOTP multi-factor authentication and one-time recovery codes for administrators
- SQLite database with automatic schema creation and migrations

## Technology

| Component | Technology |
| --- | --- |
| Backend | PHP 8.1+ |
| Database | SQLite through PDO |
| Frontend | HTML5, modern CSS, vanilla JavaScript |
| Payments | PayPal Orders API, BTCPay Server, manual payments |
| Authentication | Password hashing, sessions, TOTP MFA |
| Package format | Private, versioned ZIP archives |

## Requirements

- PHP 8.1 or newer from a currently supported PHP release branch
- PHP extensions: `pdo_sqlite`, `sqlite3`, `curl`, `openssl`, `fileinfo`, and `zip`
- A writable private data directory for the database, encryption key, and product packages
- HTTPS for every production deployment
- Apache with `mod_rewrite`, Nginx, or another PHP-capable web server

## Quick Start

1. Clone or download the project.
2. Open a terminal in the `codevault` directory.
3. Start the PHP development server:

   ```bash
   php -S 127.0.0.1:8000 router.php
   ```

4. Open `http://127.0.0.1:8000/setup.php`.
5. Create the first administrator account. The setup page disables itself after the account is created.
6. Sign in at `http://127.0.0.1:8000/admin.php`.
7. Open **Security**, enable TOTP MFA, connect an authenticator app, and save the recovery codes offline.
8. Configure the public site URL and payment methods under **Payments**.

> The built-in PHP server is intended only for local development. Use Apache, Nginx, or an equivalent production server for a public deployment.

## XAMPP Installation

1. Copy the `codevault` directory into XAMPP's `htdocs` directory.
2. Enable Apache `mod_rewrite` and allow `.htaccess` overrides.
3. Start Apache.
4. Visit `http://localhost/codevault/setup.php`.
5. Complete the administrator setup and enable MFA.

The included `.htaccess` files deny direct access to private application data and product packages. Confirm that these rules are active before accepting real orders.

## Environment Configuration

CodeVault supports two optional environment variables:

| Variable | Purpose |
| --- | --- |
| `CODEVAULT_DATA_DIR` | Absolute path to a writable directory outside the public web root. Recommended in production. |
| `CODEVAULT_APP_KEY` | Stable secret used to derive the encryption and HMAC key. Store it in the host's secret manager. |

If `CODEVAULT_APP_KEY` is not supplied, CodeVault generates a random key in `data/.appkey`. Back up this file securely. Replacing or losing the key makes previously encrypted payment credentials unreadable and invalidates token hashes that depend on it.

Example Apache environment configuration:

```apacheconf
SetEnv CODEVAULT_DATA_DIR "/srv/codevault-private"
SetEnv CODEVAULT_APP_KEY "replace-with-a-long-random-secret"
```

Environment configuration varies by host and PHP process manager. Do not commit production secrets to Git.

## Payment Configuration

Payment settings are managed in **Admin → Payments**. Updating sensitive settings requires the current administrator password.

### PayPal

1. Create an application in the PayPal Developer Dashboard.
2. Enter its Client ID and Client Secret.
3. Select **Sandbox** while testing.
4. Complete successful, cancelled, and failed test payments.
5. Switch to **Live** only after the full order flow has been verified.

CodeVault creates and captures PayPal orders on the server, then validates the captured amount and EUR currency before issuing licenses.

### Bitcoin and Lightning

1. Create or select a store in BTCPay Server.
2. Generate an API key with only these required permissions:
   - `btcpay.store.cancreateinvoice`
   - `btcpay.store.canviewinvoices`
3. Enter the HTTPS BTCPay URL, Store ID, and API key.
4. Create a webhook pointing to:

   ```text
   https://your-domain.example/btcpay_webhook.php
   ```

5. Enable invoice events and save the webhook secret in CodeVault.
6. Test with BTCPay testnet or regtest before accepting real Bitcoin.

BTCPay callbacks are HMAC-verified. Invoice metadata, amount, and currency are checked before an order is marked as paid.

### Manual Payments

Enable manual payments and enter the customer-facing payment name and instructions. A manual order remains pending until an administrator independently verifies the transfer and changes the order status to **Paid**.

## Publishing Products

1. Open **Admin → Products**.
2. Add the product title and description in English, German, and Albanian.
3. Enter its category, technology stack, EUR price, and featured status.
4. Upload a ZIP package and assign a version number.

Packages are stored privately and are never exposed as public URLs. Uploads are limited to 50 MB, 5,000 entries, and 250 MB of uncompressed content. CodeVault rejects path traversal, absolute paths, symbolic links, suspicious compression ratios, and invalid ZIP files.

When a payment is confirmed, CodeVault creates a unique license for every purchased item. Each license remains linked to the package version that was active at the time of fulfillment.

## Customer Delivery Flow

```text
Catalog → Cart → Checkout → Payment verification → Private order portal
                                                        ↓
                                              License + protected ZIP
```

- Order portal access uses a random 256-bit secret whose HMAC hash is stored in the database.
- Download grants expire after 24 hours and allow up to three uses per generated link.
- Each license allows up to ten completed downloads by default.
- Downloads are rate-limited, audited, served with private cache headers, and checked against the stored SHA-256 package hash.
- A paid multi-product order receives one independent license per item.

## Security Architecture

CodeVault uses layered controls, including:

- Argon2id password hashing when supported by the installed PHP build
- TOTP administrator MFA, replay protection, and hashed one-time recovery codes
- CSRF protection on state-changing forms
- Text-based CAPTCHA, honeypot fields, and persistent throttling for sensitive forms
- Strict, `HttpOnly`, `SameSite=Lax` sessions with inactivity expiry and browser binding
- Prepared database statements, input length limits, and server-side price calculation
- Content Security Policy without inline JavaScript, anti-clickjacking headers, MIME protection, referrer policy, and permissions policy
- AES-256-GCM encryption for payment API secrets with random nonces
- TLS verification and destination allowlists for payment-provider redirects
- Signed BTCPay webhooks and server-side PayPal capture verification
- Random public order identifiers and hashed private access tokens
- Immutable order item snapshots and constrained order-status transitions
- Audit logs for administrator, payment, authentication, upload, and download activity
- Soft deletion for products to preserve historical orders

No application can guarantee absolute security. Production safety also depends on server configuration, operating-system patching, TLS, secret management, backups, monitoring, payment-provider configuration, and regular security testing.

## Project Structure

```text
codevault/
├── admin.php              # Administration dashboard and MFA flow
├── cart.php               # Server-side shopping cart
├── checkout.php           # Checkout and order creation
├── config.php             # Application bootstrapping and database schema
├── download.php           # Protected package delivery
├── i18n.php               # English, German, and Albanian translations
├── order.php              # Private order and license portal
├── payments.php           # PayPal and BTCPay API clients
├── security.php           # Security, upload, license, and session helpers
├── setup.php              # One-time administrator setup
├── assets/                # Styles, JavaScript, and favicon
├── data/                  # Default private data directory
│   └── packages/          # Versioned product ZIP files
└── partials/              # Shared page components
```

## Production Checklist

- [ ] Use a supported PHP version and install every required extension.
- [ ] Serve the application exclusively over HTTPS.
- [ ] Move `CODEVAULT_DATA_DIR` outside the public web root.
- [ ] Store `CODEVAULT_APP_KEY` and payment secrets in a secret manager.
- [ ] Verify that HTTP access to `/data` is denied.
- [ ] Set `display_errors=Off` and keep secure error logging enabled.
- [ ] Restrict file permissions to the PHP service account.
- [ ] Enable administrator MFA and store recovery codes offline.
- [ ] Test PayPal Sandbox and BTCPay testnet/regtest end to end.
- [ ] Back up the database, packages, and application key using encrypted backups.
- [ ] Add antivirus or content-disarm scanning to the upload pipeline where appropriate.
- [ ] Configure monitoring, log rotation, alerting, and rate limits at the reverse proxy.
- [ ] Complete an independent security review and penetration test before launch.

## License and Copyright

Copyright © 2026 Alen Pepa. All rights reserved.

This repository does not grant permission to copy, redistribute, resell, or modify the software unless a separate written license from the copyright holder explicitly allows it.
