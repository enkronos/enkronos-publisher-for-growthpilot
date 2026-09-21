=== Enkronos Publisher for GrowthPilot.cloud ===
Contributors: enkronos
Tags: rest-api, publishing, api, hmac, security
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure REST API connector for publishing and managing WordPress content from trusted external applications.

== Description ==

Enkronos Publisher for GrowthPilot.cloud adds a dedicated REST API namespace (`growthpilot/v1`) that enables trusted external applications (e.g., GrowthPilot) to publish and manage WordPress content on behalf of the site owner.

Security-first design:
* API Key + HMAC request signing (timestamp + nonce anti-replay)
* Per-key scopes/permissions
* Optional IP allowlist (CIDR supported)
* Rate limiting
* Audit logs for all API requests
* Idempotent post and page creation with `Idempotency-Key`

Content features:
* Create/update/list/delete posts and pages
* Upload media and set featured images
* Create/list categories and tags
* Optional SEO meta integration (Yoast SEO / Rank Math), with safe fallbacks

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/enkronos-publisher-for-growthpilot/` (use the code from `trunk/`).
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Enkronos Publisher for GrowthPilot.cloud** in wp-admin to generate an API key and configure defaults.

== Frequently Asked Questions ==

= Does this plugin collect personal data or phone home? =
No. The plugin does not collect personal data and does not perform outbound requests unless explicitly triggered by authenticated API calls.

= How is access secured? =
Requests must be signed using an API Key + HMAC signature. Keys can be restricted by scopes and optional IP allowlists, and can be revoked at any time.

= Where can I find the API endpoints? =
All endpoints are under `/wp-json/growthpilot/v1/`. See examples below.

== API Authentication (HMAC) ==

Each request must include:
* `X-GP-KEY-ID`
* `X-GP-TIMESTAMP` (unix ms)
* `X-GP-NONCE` (uuid)
* `X-GP-SIGNATURE` (base64)

Signature string:

`TIMESTAMP + "\n" + NONCE + "\n" + METHOD + "\n" + PATH + "\n" + BODY_SHA256`

* `BODY_SHA256 = sha256(raw_body)` (hex), empty body => sha256("")
* `PATH` excludes query string and includes `/wp-json/growthpilot/v1/...`

== Idempotent publishing ==

Requests to `POST /wp-json/growthpilot/v1/posts` and `POST /wp-json/growthpilot/v1/pages` must include a non-empty `Idempotency-Key` header. The plugin stores the key and request-body hash on the created object. Retrying the same key and body returns the original object with HTTP 200 and `idempotent_replay: true`; reusing a key with a different body returns HTTP 409. This allows governed workers to retry safely without creating duplicate content.

== Screenshots ==

1. Connection page (generate/revoke API keys, scopes, IP allowlist).
2. Logs page (audit log with filters and CSV export).

== Changelog ==

= 1.1.1 =
* Fixed HMAC key persistence by encrypting the recoverable signing secret with the WordPress auth salt.
* Return a controlled 401 response when a legacy key has no recoverable secret instead of producing a server error.

= 1.1.0 =
* Add idempotent post and page creation for safe publication retries.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.1 =
Regenerate API keys created before 1.1.1; their signing secret cannot be recovered from the legacy hash-only record.

= 1.1.0 =
Enable safe retry handling for governed publishers.

= 1.0.0 =
Initial release.
