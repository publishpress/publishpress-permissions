# PublishPress Welcome

Reusable welcome tours, activation notices and setup checklists for PublishPress WordPress plugins.

The library owns the shared presentation, navigation and per-user state. Each consumer supplies its own translated content, branding, routes, capability checks, product artwork and completion callbacks.

## Installation

```bash
composer require publishpress/publishpress-welcome
```

Load Composer's autoloader in the plugin's main context. The generated version loader selects the newest bundled copy on `plugins_loaded`. Register consumer experiences at priority `-20` or later.

```php
add_action('plugins_loaded', function () {
    $config = new \PublishPress\Welcome\ExperienceConfig([
        'id' => 'example-plugin',
        'page_slug' => 'example-welcome',
        'parent_slug' => 'example-settings',
        'page_title' => __('Welcome to Example', 'example-plugin'),
        'capability' => 'manage_options',
        'exit_url' => admin_url('admin.php?page=example-settings'),
        'checklist_pages' => ['example-settings'],
    ]);

    $steps = [
        new \PublishPress\Welcome\Step\HeroStep('welcome', [
            'title' => __('Welcome to Example', 'example-plugin'),
            'lede' => __('Learn the essentials in a few short steps.', 'example-plugin'),
        ]),
    ];

    (new \PublishPress\Welcome\WelcomeController($config, $steps))->register();
}, -20);
```

Consumers may implement `PublishPress\Welcome\Contract\StepInterface` for product-specific step markup. Custom renderers are responsible for escaping their own HTML.

## Translations

All library strings use the `publishpress-welcome` text domain. `languages/publishpress-welcome.pot` is the translation template; regenerate it after changing strings with `composer make:pot` (needs GNU gettext). Place `publishpress-welcome-{locale}.mo` files in `languages/` and they load automatically. Consumers pass their own translated copy for everything plugin-specific.

## Display modes

Set `mode` in `ExperienceConfig` to choose the layout:

| Mode | Look |
| --- | --- |
| `screen` (default) | A card inside the normal WordPress admin, like Yoast or Site Kit. |
| `fullscreen` | Covers the admin menu and toolbar with a wizard-style stage, top bar and sticky footer, like Elementor, WooCommerce or WPForms. |

Other presentation options: `brand_name`, `logo` (`url`, `alt`, `show_name`, or `dashicon`), `notice_icon_url` and `accent_color` (any hex colour; everything is derived from it). Set `allow_mode_override` to `true` during development to preview either layout with `&ppw_mode=screen|fullscreen` on the welcome URL.

Built-in steps: `HeroStep`, `FeaturesStep` (icon grid), `ConceptsStep`, `LessonStep` (screenshot with numbered pins) and `FinishStep`. A step may define `hasOwnNavigation()` returning `true` to hide the shared footer's "Next" button when it renders its own call to action.

## Email capture

An optional, skippable step asks "what best describes you?" and, with explicit consent, for an email address. It is **enabled by default** and inserted just before the final step. Nothing is pre-ticked, nothing is sent until the user clicks Subscribe, and "No thanks" is always available.

Configure it with the `email_capture` option, or pass `false` to turn it off:

```php
'email_capture' => [
    'position' => 'before_last',          // or 'after_first', or a 1-based step number
    'privacy_url' => 'https://example.com/privacy/',
    'personas' => ['blogger' => __('Blogger', 'example'), 'agency' => __('Agency', 'example')],
    'copy' => ['title' => __('Stay in the loop', 'example')],
    'handler' => function (array $payload) { return true; }, // replaces the HTTP request
],
```

Consumers can also adjust it at runtime with the `publishpress_welcome_{id}_email_capture` filter, and the outgoing data with `publishpress_welcome_{id}_subscribe_payload`.

If delivery fails (endpoint down, timeout), the subscription is queued and retried in the background with a growing delay (5 minutes up to a day, five attempts, discarded after 7 days), so the user never sees an error and the lead is not lost. Retries run through WP-Cron and, as a fallback, on admin requests. Queued addresses are included in the personal data export and erasure.

The subscribe/decline choice is stored per user in plugin-independent options, so a person who already answered in one PublishPress plugin is not asked again by another on the same site (`decline_snooze_days` can re-ask after a decline; `0` never does). Register experiences on `init` so the current user is known.

Delivery uses `endpoint` (JSON `POST`), defaulting to `https://connect.publishpress.com/v1/subscribe`. Set an empty endpoint or `EmailSubscriber::PLACEHOLDER_ENDPOINT` for an explicit dry run. Do not release a plugin with the live default until the connector is deployed and its real forwarding checks pass. HTTP acceptance is not confirmation: MailerPress owns the confirmation email and the user must click its link before receiving campaigns.

The payload contains `email`, `consent: true`, `consent_statement`, `plugin`, `persona` and `locale`; no client consent wording or timestamp is sent. Default wording uses `welcome-email-v1`. A `copy.consent_label` override must be paired with a distinct `consent_statement` ID registered with its exact wording in the connector repository, or email capture is disabled. Permanent HTTP 4xx errors (except 408/429) are dropped, while 429/503 `Retry-After` delays are respected. Unknown payload filter keys and license keys are excluded from HTTP and retry storage in this version. Retryable delivery failures remain queued locally; their addresses are included in privacy export/erasure.

## What's new

Show release highlights once per version, after an update:

```php
'whats_new' => [
    'version' => '4.9.1',
    'title' => __('What is new in Example 4.9.1', 'example'),
    'items' => [['title' => __('Faster lists', 'example'), 'body' => __('Large tables load twice as fast.', 'example')]],
    'url' => 'https://example.com/changelog/',
],
```

At most three items are shown, in a dismissible card on the plugin's own screens (`notice_pages`). It stays out of the way: it never appears next to the welcome notice, expires after `notice_days`, and is skipped for anyone who has just finished or dismissed the guide.

## WordPress.org compliance

The defaults follow the [plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) for plugins hosted on WordPress.org:

- **Guideline 11 (no admin hijacking).** The activation notice is dismissible, appears only on the plugin's own screens (`notice_pages`, defaulting to `checklist_pages`; the Dashboard needs `notice_on_dashboard`), hides itself once the guide is finished and stops after `notice_days` (14) days. The only redirect happens once, right after a confirmed first install.
- **Guideline 7 (consent and external services).** Nothing contacts a server without an explicit, unticked-by-default opt-in and a click on Subscribe. Declining or skipping sends nothing. The consuming plugin must also document the service in its `readme.txt`, for example:

```
== External services ==
This plugin can send your email address and the answer to "what best describes you?" to PublishPress
(publishpress.com) if you opt in on the optional welcome screen. Nothing is sent unless you tick the consent
box and click Subscribe. Privacy policy: https://publishpress.com/privacy-policy/
```

## First-install enrollment

The library never assumes that activation means a first installation. A consumer should set the configured enrollment and redirect options only when its own installation logic confirms a new install:

```php
update_option('example-plugin_welcome_enrolled', 1, false);
update_option('example-plugin_welcome_redirect_pending', 1, false);
```

Existing sites can always open the registered welcome page manually without being enrolled for automatic notices or redirects.

## Version loading

`src/include.php` and `src/VersionLoader.php` are generated by `publishpress/version-loader-generator`. Do not edit those files manually. Every bundled copy registers at `plugins_loaded` priority `-200`; the newest initializes at `-190`.
