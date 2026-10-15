# Spec: PublishPress Subscriber Hub (Cloudflare Worker)

| | |
| --- | --- |
| **Status** | Draft for review. Nothing in this document is implemented yet. |
| **Owner** | PublishPress |
| **Related code** | `library-publishpress-welcome` (sender), MailerPress (destination) |
| **Replaces** | The WordPress-hosted `publishpress-subscriber-hub` plugin, as the primary hub |
| **Repository** | `publishpress/mailerpress-connector` (the Worker's home) |
| **Revision** | 2 (2026-10-09): added the claim model (D6, D7), the validation section (§5.5) and statement-ID consent proof |

---

## 1. Summary

PublishPress plugins ship an optional, skippable welcome step that asks users what best describes them and, with explicit
consent, for their email address. Those subscriptions must end up as contacts in **MailerPress**, the platform that stores
PublishPress's customer emails and will drive the personalised campaign engine.

MailerPress's public API needs secret credentials. Credentials cannot ship inside a distributed WordPress plugin, because
anyone can read the plugin's code. This spec describes a small **Cloudflare Worker** that sits between the plugins and
MailerPress: it is the only component that holds the credentials, it validates and rate-limits public traffic, and it
forwards accepted subscriptions to MailerPress reliably.

```
 WordPress site (any user)                 Cloudflare                          PublishPress platform
┌────────────────────────────┐    ┌──────────────────────────────┐    ┌───────────────────────────────┐
│ PublishPress plugin        │    │  Subscriber Hub Worker       │    │  MailerPress                  │
│  └─ welcome library        │    │                              │    │  POST /wp-json/mailerpress/   │
│      └─ EmailSubscriber ───┼───►│  1. validate + rate limit    │    │       v1/contact              │
│          (no credentials)  │ 202│  2. verify plan (optional)   │    │                               │
└────────────────────────────┘    │  3. enqueue                  │    │  - sends double opt-in email  │
                                  │        │                     │    │  - stores contact, tags, lists│
                                  │        ▼                     │    └───────────────▲───────────────┘
                                  │  Cloudflare Queue ───────────┼────────────────────┘
                                  │  (retry + dead-letter)       │  X-MailerPress-API-Key / -Secret
                                  └──────────────────────────────┘  (Worker secrets, never in plugins)
```

## 2. Goals and non-goals

### Goals

- **G1. No credentials in plugins.** MailerPress credentials exist only as Worker secrets.
- **G2. Keep the library contract small.** The library payload is the Worker's contract, with one additive change made
  before the library's first release (`consent_statement`, §5.2). After that, switching hubs is a one-line endpoint change.
- **G3. Consent-first.** Only explicit, consented subscriptions are forwarded, and the consent proof is stored with the contact.
- **G4. Resist abuse.** A public endpoint must not let someone mail-bomb third parties or poison the contact list.
- **G5. Reliable delivery.** A MailerPress outage must not lose a consented signup.
- **G6. Personalisation data.** Contacts arrive with attributes (persona, source plugin, plan) that campaigns can use.
  Only the plan can be verified; the rest are claims and are labelled as such (D6).
- **G7. Operable.** Clear logs, metrics, alerts and a runbook, with no personal data in logs.

### Non-goals (v1)

- Sending email. MailerPress sends the confirmation email and every campaign.
- A subscriber admin UI. MailerPress is the system of record.
- Unsubscribe handling. MailerPress owns unsubscribe links and status.
- Campaign logic or personalisation rules.
- Syncing back from MailerPress to plugins.
- Processing anything that is not an opt-in from the welcome step.

## 3. Decisions already made

| # | Decision | Reason |
| --- | --- | --- |
| D1 | Credentials live only in Worker secrets | Plugins are public code; any embedded secret leaks. |
| D2 | MailerPress sends the double opt-in email | One email, one place to manage unsubscribes. The Worker only sets `subscription_status`. |
| D3 | Default status is `pending` for everyone | An email typed into a form is not proof of ownership. |
| D4 | Pro users may be set to `subscribed` only after server-side verification (see §8) | The client's claim to be Pro is not trustworthy. |
| D5 | Plan (`free`/`pro`) is set by the Worker as a tag, never copied from the client | Segmentation must be based on verified data. |
| D6 | The Worker does not authenticate the calling site, so every client-supplied field except the email and the consent flag is a **claim** | A public endpoint with no secret in plugins cannot tell a real plugin from a script. Campaigns must treat persona, source plugin and claimed plan as claims, not facts. |
| D7 | The Worker validates and normalises before enqueueing, and MailerPress receives only the sanitised payload, built from allow-listed values and Worker-chosen constants | No client string other than the normalised email can reach MailerPress. |

## 4. Open questions (must be answered before implementation)

| # | Question | Why it matters | Default if unanswered |
| --- | --- | --- | --- |
| Q1 | Which license system do the Pro plugins use (EDD, Freemius, custom)? | Needed for plan verification (§8). | Ship v1 with everyone `pending`; add verification later. |
| Q2 | What is the MailerPress host and is its REST API reachable from Cloudflare (WAF, bot rules)? | The Worker must be allowed through. | Assume reachable; test early. |
| Q3 | What are the MailerPress list ID(s) and tag IDs, or can tags be created by name? | The API takes tag/list **IDs**. | Configurable mapping; tags pre-created manually. |
| Q4 | Which custom field key holds the persona? | Persona is the main segmentation signal. | `persona` custom field, created in MailerPress. |
| Q5 | **How does `POST /contact` treat an existing contact that is `unsubscribed`, or one that is `subscribed` and receives `pending` again, or the same address in a different letter case?** | If it re-subscribes them, the Worker would violate an earlier unsubscribe. See §7.4. | Must be tested against a real instance before launch. |
| Q6 | Public hostname for the Worker (for example `subscribe.publishpress.com`)? | Becomes the plugin default endpoint and is hard to change later. | Needs a decision. |
| Q7 | Does the account plan include Durable Objects and Queues? | Both are used in this design. | Verify plan requirements before building. |
| Q8 | What is the retention period for audit records, and who is the data controller named in the consent text? | Legal wording and §12. | 24 months; the PublishPress legal entity. |
| Q9 | What happens to the WordPress-hosted hub plugin? | Avoids maintaining two hubs. | Keep as an optional self-hosted fallback; stop active development. |
| Q10 | Is the consent wording owned by the library (one reviewed text per statement ID) or may each plugin supply its own? | A fixed statement ID only proves what the user saw if the wording behind the ID is fixed (§5.5, §12). | Library-owned; the per-plugin `consent_label` override is removed (§16). |
| Q11 | Is the persona list shared by all plugins? | The Worker allow-list must contain every persona any plugin sends; the rest are dropped. | The library's five defaults (`site-owner`, `content-team`, `agency`, `developer`, `other`); custom personas are dropped until registered. |

## 5. Public API (Worker)

### 5.1 Endpoint

```
POST https://<hub-host>/v1/subscribe
Content-Type: application/json
```

Versioned path. Breaking changes ship as `/v2/…`; `/v1` stays available for as long as plugin versions that call it are
supported. Only `POST` and `OPTIONS` are accepted; every other method returns `405`.

### 5.2 Request body (v1)

This is what the library sends, plus two optional fields a Pro plugin may add through the library's
`publishpress_welcome_{id}_subscribe_payload` filter. Every field except `email` and `consent` is a **claim** (D6).
The library currently also sends `consent_text` and `consented_at`; the Worker ignores both, and `consent_statement`
replaces them (§16).

| Field | Type | Required | Rules |
| --- | --- | --- | --- |
| `plugin` | string | no | Claim. `^[a-z0-9_-]{1,64}$`, then exact match against the plugin allow-list (§9.3). An unknown value is **dropped**, not rejected (§5.5). |
| `email` | string | yes | Exactly one plain address, normalised as in §5.5. |
| `persona` | string | no | Claim. Exact match against the persona allow-list (§5.5). Anything else is **dropped**. |
| `consent` | boolean | yes | Must be exactly the JSON boolean `true`. Anything else is rejected. |
| `consent_statement` | string | yes | ID of the consent wording the user saw, for example `welcome-email-v1`. `^[a-z0-9-]{1,32}$`, exact match against the registered statements (§5.5). Missing or unknown is rejected: consent cannot be recorded for wording the Worker does not know. |
| `locale` | string | no | `^[a-z]{2,3}([_-][A-Za-z0-9]{2,8})?$`. Only used to derive MailerPress `lang`; the raw value is not stored. Invalid is **dropped**. |
| `license_key` | string | no | Pro plugins only. `^[A-Za-z0-9_-]{8,128}$`. Used for plan verification (§8) and nothing else. Never stored or logged. Invalid is **dropped**. |
| `plan_claim` | string | no | Claim. Exactly `free` or `pro`, else **dropped**. Stored only as `claimed_plan`; it never affects status or the verified plan (§8). |

Unknown fields are ignored and never copied anywhere (§5.5). Body limit: **4 KB**. Content type must be
`application/json`.

Example:

```json
{
  "plugin": "presspermit",
  "email": "alex@example.com",
  "persona": "agency",
  "consent": true,
  "consent_statement": "welcome-email-v1",
  "locale": "en_US"
}
```

### 5.3 Responses

The Worker answers **after validation and enqueueing**, not after MailerPress has responded, so plugins never wait on
MailerPress. It never reveals whether an address already exists.

| Status | Body | When |
| --- | --- | --- |
| `202` | `{"status":"accepted"}` | Valid, accepted for delivery. Also returned for duplicates (idempotent). |
| `400` | `{"code":"invalid_request","message":"…","field":"email"}` | Validation failed. `field` names the first bad field. |
| `405` | `{"code":"method_not_allowed"}` | Wrong HTTP method. |
| `413` | `{"code":"payload_too_large"}` | Body over 4 KB. |
| `415` | `{"code":"unsupported_media_type"}` | Not `application/json`. |
| `429` | `{"code":"rate_limited"}` + `Retry-After` | Any rate limit in §9 tripped. |
| `503` | `{"code":"unavailable"}` + `Retry-After` | Queue or a required binding is unavailable. |

All responses are JSON with `Cache-Control: no-store`. Error messages never echo the submitted email.

### 5.4 Health

`GET /healthz` returns `200 {"ok":true,"version":"<git sha>"}` with no dependency checks, for uptime monitoring.

### 5.5 Validation and sanitisation

The Worker validates and normalises **before** anything is enqueued. The queue message and the MailerPress request are
built from the sanitised result only (D7). The only client-originated string that ever reaches MailerPress is the
normalised email; everything else is chosen by the Worker from allow-lists and constants.

**Request**

- `Content-Type` must be `application/json` (`415`), the body at most 4 KB (`413`), and the parsed JSON must be an
  object (`400`).
- Unknown keys are ignored: never copied, stored, logged or echoed. That includes display names, site URLs, free-text
  consent wording and client timestamps.
- Every accepted string has a length cap, checked before any comparison.

**Per field**

| Field | Rule | On failure |
| --- | --- | --- |
| `email` | Normalised and checked as below | Reject `400` |
| `consent` | JSON boolean `true` only. Missing, `false`, `"true"`, `"yes"`, `1` and `null` all fail | Reject `400` |
| `consent_statement` | Exact match to a registered ID | Reject `400` |
| `persona` | Exact, case-sensitive match to the persona allow-list | Drop the field, continue |
| `plugin` | Exact match to the plugin allow-list (§9.3) | Drop the field, continue |
| `plan_claim` | Exactly `free` or `pro` | Drop the field, continue |
| `locale` | Shape check, then reduced to a two-letter `lang` | Drop the field, continue |
| `license_key` | Shape check (§5.2); used only by §8 | Drop the field, continue |

Dropping a claim never blocks a valid opt-in: if the email and consent are valid, the contact is created. Each drop
increments `dropped_claim{field}`, so a plugin that shipped without being registered is noticed instead of silently
losing its attribution (§14).

**Email**

1. Trim surrounding whitespace.
2. Reject if longer than 254 characters, if the local part is longer than 64, or if it does not contain exactly one `@`.
3. Reject anything that is not a single plain address: whitespace or control characters anywhere (this also blocks CR/LF
   header injection), commas, semicolons, angle brackets, quotes, backslashes and parentheses. That rules out the
   display-name form (`Name <a@b.com>`), address lists, quoted local parts and comments.
4. Local part: ASCII letters, digits and `. _ + -`, with no leading, trailing or doubled dot.
5. Domain: lower-cased and ASCII only (internationalised domains must arrive as punycode `xn--…`). Two or more
   dot-separated labels of 1–63 `[a-z0-9-]` characters, none starting or ending with `-`, and not an IP literal.
6. Store and forward the normalised value: domain lower-cased, local part as submitted. Idempotency, rate-limit and log
   keys always use the **fully** lower-cased form, so changing the case of the local part cannot bypass a limit. Whether
   to forward the fully lower-cased address depends on how MailerPress matches case (check in the §7.4 spike).

This profile is deliberately stricter than RFC 5322 and WordPress's `is_email()`. It rejects a very small number of valid
but unusual addresses (apostrophes, quoted or non-ASCII local parts) in exchange for removing every character that could
matter in an HTML page, a template or a mail header. Such rejections are counted in `rejected_by_reason`, so the
trade-off can be revisited with data.

**Claims**

- Persona, plugin and plan are compared with `Map.has()` or `Object.hasOwn()` against the configured sets, never
  `obj[value]` on a plain object. That way `__proto__`, `constructor` and `toString` are just unknown values.
- Stored claim values are the allow-list entries themselves (or their configured tag IDs), never the string the client
  sent. There is no free text in anything the Worker stores.
- Claims are written to MailerPress under `claimed_*` fields and `claim:*` tags (§7.2). The verified plan is the only
  `plan:*` tag, and only the Worker sets it (§8).

**Consent proof**

The proof is `{statement ID, Worker receive time, hub version}`. It never contains text or timestamps from the client
(§7.2, §12).

**Defence in depth**

- The MailerPress body is constructed field by field, never by spreading or merging the validated object.
- The queue consumer validates the message shape again before posting, because messages can be replayed from the
  dead-letter queue by people and tools.

## 6. Processing pipeline

```
request ─► method/type/size ─► parse + validate ─► allow-list plugin ─► rate limits (§9)
        ─► idempotency check ─► plan verification (§8, optional) ─► build MailerPress payload
        ─► enqueue ─► 202
                          │
                     queue consumer ─► POST MailerPress ─► ack
                                          │ failure
                                          └─► retry with backoff ─► dead-letter queue after N tries
```

1. **Validate and sanitise** per §5.2 and §5.5. Reject early and cheaply; no external calls before validation passes.
   Only the sanitised result moves on.
2. **Rate limit** (§9). Checked before anything expensive.
3. **Idempotency.** Compute `sha256(lower(email) + "|" + utc-date)`; if seen in the last 24 h, return `202` without enqueueing again. This protects against plugin-side retries and a second plugin on the same site asking for the same address.
4. **Plan verification** (§8) decides `subscription_status` and the plan tag.
5. **Enqueue** one message containing the final MailerPress payload (no license key).
6. **Consumer** posts to MailerPress (§7). Success acknowledges the message; failures retry (§10).

## 7. MailerPress integration

Reference: <https://mailerpress.com/docs/mailerpress-public-api/>

### 7.1 Request

```
POST https://<mailerpress-host>/wp-json/mailerpress/v1/contact
X-MailerPress-API-Key:    mpk_…        (Worker secret)
X-MailerPress-API-Secret: mps_…        (Worker secret)
Content-Type: application/json
```

Use a dedicated API key restricted to the **`contacts:write`** scope only. Rotate it on a schedule (§13).

### 7.2 Field mapping

| MailerPress field | Value |
| --- | --- |
| `email` | Normalised email. |
| `subscription_status` | `pending` or `subscribed` per §8. Never `unsubscribed`. |
| `lists` | `[{ "id": <MAILERPRESS_LIST_ID> }]` (one default list, configurable per plugin if needed). |
| `tags` | `[{ "id": … }]` chosen by the Worker from configuration (Q3): the verified plan (`plan:free` / `plan:pro`, §8) and, when the claims were valid, the claimed source plugin (`claim:source:presspermit`) and claimed persona (`claim:persona:agency`). |
| `custom_fields` | `{ "claimed_persona": "agency", "claimed_source_plugin": "presspermit", "claimed_plan": "free", "consent_statement": "welcome-email-v1" }`. Claims that were dropped are omitted. Key names per Q4. |
| `opt_in_source` | `publishpress-welcome` |
| `optin_details` | JSON string built by the Worker: `{"statement":"welcome-email-v1","received_at":"…","hub_version":"…"}`. This is the consent proof: a fixed statement ID and the Worker's own clock, never client text. |
| `lang` | Derived from `locale` (`en_US` → `en`). |
| `first_name`, `last_name` | Not collected in v1. |

Persona as tag **or** custom field is configurable; the recommendation is **both**: a custom field for reporting and a tag
for campaign targeting.

Claims are stored under the `claimed_` / `claim:` prefix on purpose: anyone building a segment can see the value is
self-reported. Verified values (`plan:*`) are set only by the Worker. Campaign rules must not gate entitlements,
discounts or support decisions on a `claimed_*` field.

### 7.3 Response handling

| MailerPress result | Worker behaviour |
| --- | --- |
| `200` + `success: true` | Acknowledge the queue message. |
| `400` | Permanent failure: do not retry; send to the dead-letter queue with the error `code` (no email in the record) and raise an alert (likely a mapping bug). |
| `401` / `403` | Credential or scope problem: retry a few times, then dead-letter and **page** (§14). |
| `429` | Respect `Retry-After`; retry with that delay. |
| `5xx` / network error | Retry with exponential backoff (§10). |

### 7.4 Existing contacts and unsubscribes (blocking verification item)

The API documentation says `POST /contact` *creates or updates by email*. It does not say what happens when the existing
contact is `unsubscribed`. Re-subscribing someone who unsubscribed breaks the law in many jurisdictions and destroys trust.

**Before launch, test against a real MailerPress instance** (Q5):

1. Create a contact, unsubscribe it, then `POST /contact` again with `pending` and with `subscribed`.
2. Record the resulting status.
3. Confirm a contact (`subscribed`), then `POST /contact` with `pending`. It must not be downgraded or sent a second
   confirmation email. (A second plugin on the same site, or the same person later, will do exactly this.)
4. Submit the same address in a different letter case. It must not create a second contact; if it does, the Worker
   forwards the fully lower-cased address (§5.5).

If MailerPress re-subscribes: the Worker must first look the contact up (if a lookup endpoint exists) and skip forwarding
when the status is `unsubscribed`, or omit `subscription_status` for existing contacts. If neither is possible, escalate to
the MailerPress vendor; do not launch.

## 8. Subscription status and plan verification

### 8.1 Rule

```
status = "pending"      // default; MailerPress sends double opt-in
plan   = "free"

if license_key present AND license is valid for this plugin
   AND payload.email == license's purchase/customer email   // case-insensitive
then
   plan   = "pro"
   status = "subscribed"
elif license_key present AND license is valid
   plan   = "pro"
   status = "pending"     // valid license but a different email: the email is unverified
```

`plan_claim` from the client is ignored.

### 8.2 Why

An email typed into a form is not verified. A paying customer's purchase email *is* verified (they paid and received a
receipt), so matching it is a sound basis for skipping double opt-in. A mismatching email is treated like any other.

### 8.3 License verification

- Pluggable adapter: `verifyLicense({ licenseKey, plugin }) → { valid, plan, customerEmail } | null`.
- The implementation depends on Q1 (EDD Software Licensing API, Freemius API or a custom service).
- Timeout 2 s. **Fail closed:** any error, timeout or invalid license results in `pending` + `plan:free`. A verification
  outage must never cause an unverified `subscribed`.
- Cache valid results briefly (≤ 5 min, in-memory per isolate) to limit load on the license server.
- The license key is used only for this call. It is **never** put in the queue message, MailerPress payload, logs or metrics.
- **v1 option:** ship without the adapter (always `pending`, `plan:free`) and add it later; the contract above does not change.

## 9. Abuse protection

The endpoint is public and unauthenticated by design. Threats: mail-bombing a victim through confirmation emails, filling
MailerPress with junk, probing for existing addresses, and exhausting quotas.

### 9.1 Limits (initial values, tune after launch)

| Scope | Limit | Mechanism | Notes |
| --- | --- | --- | --- |
| Per IP | 30 requests / minute, 300 / hour | Cloudflare WAF rate-limiting rule at the edge | Many plugins share hosting IPs, so keep generous. |
| Per email | 1 accepted request / hour, 5 / day | Durable Object keyed by `sha256(email)` (strong consistency) | Stops mail-bombing one victim. Over-limit returns `202` (idempotent-looking) and drops silently, so an attacker cannot tell. |
| Per plugin ID | Metric and alert only, **not enforced** | Counter | The plugin ID is a claim (D6). Enforcing a limit would let anyone throttle a real plugin's signups just by sending its ID. |
| Global | Daily cap (for example 20 000 accepted) | Durable Object | On breach: stop forwarding, alert, queue for manual review. |

Eventually consistent stores (for example KV) are **not** suitable for the per-email limit because counters can be
exceeded under concurrency; use Durable Objects. The Workers Rate Limiting binding is acceptable for coarse per-IP
protection but has short fixed windows and per-location counting; confirm current behaviour at implementation time.

### 9.2 Content checks

- Reject disposable-email domains (maintained list, updated by a scheduled job).
- Reject syntactically valid but obviously invalid addresses (no dot in domain, IP-literal domains, role accounts are allowed).
- Alert, without rejecting, when many accepted requests in a short window share an IP or an email domain (heuristic, v1).

### 9.3 Plugin allow-list

The `plugin` ID is matched against configuration (one entry per PublishPress plugin, mapped to a MailerPress tag ID). It
is a claim (D6), so an unknown ID is **dropped** and the opt-in continues without a source tag. Rejecting would not stop
anyone, because a client can simply send a valid ID, and it would lose real signups from a plugin that shipped before its
ID was registered. Dropped IDs are counted (`dropped_claim{field=plugin}`) and alerted on, so a missing registration is
noticed (§14).

### 9.4 Not used

- **No shared secret / HMAC from the plugin.** Anything in a distributed plugin is extractable, so it adds no security and
  gives a false sense of it. Defence is rate limiting, validation, the allow-list and double opt-in.
- **No CAPTCHA / Turnstile.** The caller is a server, not a browser.

## 10. Reliability

- **Queue.** Cloudflare Queues between accept and delivery. The Worker returns `202` once the message is durably enqueued.
- **Retries.** `max_retries: 5` with delays 1 min, 5 min, 30 min, 2 h, 12 h (explicit `retry({ delaySeconds })`; honour
  `Retry-After` for `429`).
- **Dead-letter queue.** Messages that exhaust retries or fail permanently go to a DLQ. A scheduled Worker (daily) reports
  DLQ depth; a runbook (§14) covers replay.
- **Idempotent delivery.** MailerPress creates-or-updates by email, so redelivery is safe. The consumer must not create
  duplicates on retry.
- **Plugin-side safety net.** The library already queues and retries failed sends for up to 7 days, so a Worker outage does
  not lose signups either.
- **Message contents.** The queue message contains the final MailerPress payload (it includes the email). Queue retention
  is the shortest that satisfies the retry schedule (4 days maximum is plenty; set to match the 12 h final delay).

## 11. Configuration and secrets

### 11.1 Secrets (`wrangler secret put`)

| Name | Description |
| --- | --- |
| `MAILERPRESS_API_KEY` | `mpk_…`, scope `contacts:write` only. |
| `MAILERPRESS_API_SECRET` | `mps_…`. |
| `LICENSE_API_SECRET` | Credential for the license service (only when §8.3 is enabled). |

### 11.2 Plain variables (`wrangler.toml` `[vars]` or per environment)

| Name | Example | Description |
| --- | --- | --- |
| `MAILERPRESS_BASE_URL` | `https://platform.example.com/wp-json/mailerpress/v1` | No trailing slash. |
| `MAILERPRESS_LIST_ID` | `5` | Default list. |
| `PLUGIN_MAP` | JSON | `{ "presspermit": { "tag_id": 12 }, … }` the allow-list and tag mapping (§9.3). |
| `PLAN_TAG_IDS` | JSON | `{ "free": 20, "pro": 21 }` |
| `PERSONA_TAG_IDS` | JSON | `{ "agency": 30, "developer": 31, … }` (if personas are tags) |
| `PERSONA_FIELD_KEY` | `claimed_persona` | Custom field key (Q4). The `claimed_` prefix is intentional (§7.2). |
| `PERSONAS` | JSON | `["site-owner","content-team","agency","developer","other"]` the persona allow-list (§5.5, Q11). |
| `CONSENT_STATEMENTS` | JSON | `["welcome-email-v1"]` the registered statement IDs (§5.5). The text for each ID lives in the repo under `consent/`. |
| `RATE_LIMITS` | JSON | Overrides for §9.1 values. |
| `ENVIRONMENT` | `production` / `staging` | Used in logs and metrics. |

Secrets are never committed. Staging and production use **separate** MailerPress keys, preferably pointing at a staging
MailerPress instance, so tests never create real contacts.

## 12. Privacy and compliance

- **Data minimisation.** The Worker stores no subscriber database. Personal data exists only transiently in the queue
  message and the idempotency/rate-limit records (which store a **hash** of the email, never the address).
- **Consent proof** lives in MailerPress (`optin_details`), alongside the contact.
- **Logs** must never contain email addresses, license keys, consent text or full payloads. Log only: request ID, plugin ID,
  outcome code, latency, and a short email hash prefix (first 8 hex chars) for correlation.
- **Erasure.** Deleting a contact in MailerPress is the erasure mechanism. The Worker holds nothing else; hashed rate-limit
  records expire automatically (≤ 24 h).
- **Transfers.** Cloudflare processes requests globally; state this in the privacy policy and consider EU-only handling if
  required (Q8). Durable Objects support jurisdiction restrictions.
- **Consent wording** is owned centrally, not by each plugin (Q10). Each wording has an immutable statement ID
  (`welcome-email-v1`); the exact English text for every ID is kept in the connector repository (`consent/`), and
  translations are translations of that same ID. Changing the wording means a new ID. A contact's proof is the ID plus
  the Worker's receive time, so it can always be matched to the text the user was shown.
- **Policy.** Plugins hosted on WordPress.org must disclose this external service in `readme.txt` (template in the library
  README). The Worker must only receive data after explicit opt-in, which the library enforces.

## 13. Security

| Threat | Mitigation |
| --- | --- |
| Credential leak from plugins | None are present in plugins (D1). |
| Credential leak from Worker | Secrets only via `wrangler secret`; scope-limited key; rotate every 90 days; separate staging/production keys. |
| Mail-bombing a victim | Per-email limits (§9.1), double opt-in (D3), silent drop on over-limit. |
| List poisoning | Allow-list, disposable-domain block, per-IP/global limits, double opt-in. |
| Enumeration of existing contacts | Uniform `202` responses; no existence information anywhere. |
| Forged "Pro" claim | `plan_claim` never affects status or `plan:*`; server-side license + email match (§8). |
| Spoofed persona, plugin or plan to steer campaigns | They are claims (D6): allow-listed, stored as `claimed_*` / `claim:*`, never used for entitlements. |
| Stored injection through client strings (XSS in the MailerPress admin, template or merge-tag injection, mail-header injection) | No client string except the normalised email reaches MailerPress (D7). The email profile excludes quotes, angle brackets, whitespace and control characters (§5.5). |
| Prototype keys (`__proto__`, `constructor`) used as allow-list lookups | Allow-lists are `Map` / `Object.hasOwn` lookups, with explicit tests (§5.5). |
| Throttling a plugin's real signups by spoofing its ID | The per-plugin limit is observe-only (§9.1). |
| Re-subscribing unsubscribed users | §7.4 verification; never send `unsubscribed`/override status. |
| Request smuggling / oversized bodies | 4 KB limit; strict content type; schema validation. |
| Replay | Idempotency key (§6.3); MailerPress create-or-update is idempotent. |
| SSRF | The Worker only calls fixed, configured hosts; no user-supplied URLs. |
| Supply chain | Minimal dependencies; lockfile; dependency audit in CI. |
| Log leakage | Redaction rules in §12; tests assert logs contain no PII. |

CORS: not needed (server-to-server). Respond to `OPTIONS` with `204` and no CORS headers, so browsers cannot call it.

## 14. Observability and operations

### Metrics (Workers Analytics Engine or logs → dashboard)

`accepted`, `rejected_by_reason`, `dropped_claim_by_field`, `rate_limited_by_scope`, `idempotent_duplicates`, `queue_depth`, `delivery_success`,
`delivery_retry`, `delivery_dead_letter`, `mailerpress_status_by_code`, `latency_p50/p95`, `license_verify_outcome`.

### Alerts

| Alert | Condition | Severity |
| --- | --- | --- |
| MailerPress auth failing | Any `401`/`403` from MailerPress | **Page** |
| Dead-letter growing | DLQ depth > 0 for 15 min | High |
| Delivery failing | Success rate < 95 % over 15 min | High |
| Global cap approached | > 80 % of daily cap | Medium |
| Traffic spike | Accepted rate > 5× 7-day baseline | Medium (possible attack) |
| Worker errors | Unhandled exception rate > 1 % | High |
| Plugin ID dropped | Any `dropped_claim{field=plugin}` for 1 h (a plugin shipped without being registered) | Medium |

### Runbook topics (to be written during implementation)

Rotate MailerPress credentials · replay the dead-letter queue · raise or lower rate limits · pause forwarding (kill switch
environment variable returns `503` + `Retry-After`) · investigate an abuse spike · add a new plugin ID.

## 15. Technical design

### 15.1 Stack

- Cloudflare Workers, **TypeScript**, `wrangler` for config and deploy.
- Bindings: **Queue** (producer + consumer), **Durable Object** class `Limiter` (per-email, per-plugin, global counters and
  idempotency keys), optional **Analytics Engine** dataset, secrets, vars.
- Tests: **Vitest** with the Cloudflare Workers test pool (Miniflare), plus a mock MailerPress server.
- CI: type-check, lint, unit/integration tests, dependency audit, deploy to staging on merge, manual approval for production.

### 15.2 Repository layout (repository `publishpress/mailerpress-connector`)

```
src/
  index.ts              # fetch + queue + scheduled entry points
  http.ts               # routing, JSON responses, request IDs
  validate.ts           # validation, allow-lists and normalisation (§5.2, §5.5)
  limits.ts             # Limiter Durable Object + client
  plan.ts               # status/plan decision (§8)
  license/              # adapters (edd.ts, freemius.ts, none.ts)
  mailerpress.ts        # payload builder + HTTP client (§7)
  consumer.ts           # queue consumer, retry/backoff (§10)
  log.ts                # redacting logger
  config.ts             # parse and validate env at startup
test/
  validate.test.ts  plan.test.ts  limits.test.ts  mailerpress.test.ts  e2e.test.ts
wrangler.toml
consent/  welcome-email-v1.md   # canonical wording per statement ID (§12)
docs/  runbook.md  api.md
```

### 15.3 Key behaviours to unit test

- The validation matrix in §5.5 (accept and reject cases): an email fuzz list (display-name form, two addresses,
  quoted local part, CR/LF, `<script>`, 255+ characters, non-ASCII domain, IP literal, doubled dots) and `consent` as
  `"true"`, `"yes"`, `1`, `null`, `false` and missing.
- Claims: unknown persona, plugin and plan are dropped, the opt-in still produces a `pending` contact, and the
  MailerPress body contains none of them. `__proto__`, `constructor` and `toString` are treated as unknown.
- Payload isolation: for fuzzed input, the only client-originated string in the MailerPress body is the normalised email.
- Email normalisation and disposable-domain rejection.
- Status/plan matrix in §8.1, including license timeout failing closed.
- Per-email limiter: second request within an hour is dropped but still returns `202`; counters reset after the window.
- Idempotency: identical requests within 24 h enqueue once.
- MailerPress mapping: exact payload for sample inputs; no `unsubscribed`; license key absent from the queue message.
- Consumer: `200` acks; `400` dead-letters without retry; `429` honours `Retry-After`; `5xx` retries with the schedule.
- Logs contain no email, license key or consent text.

## 16. Changes needed in the welcome library

**Required before the library's first release.** Send `consent_statement` (default `welcome-email-v1`) and stop sending
`consent_text` and `consented_at` to the hub; the Worker ignores them (§5.2). The consent wording becomes library-owned
(Q10): remove `consent_label` as a per-plugin override (the Permissions plugin currently sets one), or make any custom
wording a registered statement ID whose text is added to the connector repository. This is cheapest now, because no
released plugin sends the old shape yet.

**Recommended small changes:**

1. **Do not retry permanent client errors.** `EmailSubscriber` should return the HTTP status in its `WP_Error`, and
   `EmailQueue` should drop items on `4xx` other than `408`/`429` instead of retrying five times.
2. **Default endpoint.** Replace the placeholder with the production hub URL once decided (Q6); keep the dry-run behaviour
   when the endpoint is empty.
3. **Honour `Retry-After`.** On `429`/`503`, use the header for the next retry time.
4. **Pro plugins.** Document how a Pro plugin adds `license_key` through the payload filter (never from the free plugin).
5. **Send `User-Agent`/version header** (for example `PublishPress-Welcome/1.x`) to help debugging; contains no personal data.

## 17. Rollout plan

1. **Spike (½ day).** Test MailerPress `POST /contact` against a staging instance: statuses, double opt-in email, tags, custom
   fields, and especially the existing-contact behaviours in §7.4 (unsubscribed, downgrade, letter case). Resolve Q2–Q5.
2. **Build the Worker (staging).** Implement §5–§11 without license verification (everyone `pending`).
3. **Library changes (§16)** and an end-to-end test from a dev WordPress site through staging to a staging MailerPress.
4. **Abuse testing.** Scripted bursts against staging: per-IP, per-email, global cap, oversized bodies, malformed JSON.
5. **Production deploy** behind the final hostname; first release with a single plugin (Permissions); monitor for a week.
6. **Enable all plugins** by adding their IDs to `PLUGIN_MAP`.
7. **Phase 2:** license verification and `subscribed` for matching Pro emails (§8), after Q1 is answered.
8. **Retire or demote** the WordPress-hosted hub plugin (Q9).

## 18. Acceptance criteria

- [ ] A valid request from the library results in a contact in MailerPress with the correct status, list, tags, persona,
      `opt_in_source` and `optin_details`.
- [ ] No MailerPress credential appears in any plugin, log, queue message or repository.
- [ ] A MailerPress outage of several hours loses no accepted signup; recovery delivers everything.
- [ ] Per-email, per-IP and global limits behave as specified in automated tests.
- [ ] An unsubscribed contact is never re-subscribed (verified against a real MailerPress, §7.4).
- [ ] A forged Pro claim never produces `subscribed` or `plan:pro`.
- [ ] Unknown or spoofed persona, plugin and plan values are dropped and the opt-in still creates a `pending` contact.
- [ ] Fuzz test: the only client-originated string in any MailerPress request body is the normalised email.
- [ ] The consent proof stored in MailerPress is a registered statement ID plus the Worker's timestamp, never client text.
- [ ] A confirmed contact is never downgraded to `pending` by a later request (§7.4).
- [ ] Logs and metrics contain no email addresses, license keys or consent text.
- [ ] Dead-letter and credential-failure alerts fire in staging drills.
- [ ] The runbook exists and the credential rotation procedure has been rehearsed.

## 19. Glossary

- **Hub:** the Worker described here.
- **Double opt-in (DOI):** the user confirms their address from an email before being subscribed. MailerPress sends it when
  a contact is `pending`.
- **Persona:** the user's answer to "what best describes you?" (`site-owner`, `content-team`, `agency`, `developer`, `other`).
- **Plan:** `free` or `pro`, derived server-side.
- **Consent proof:** the registered consent statement ID and the Worker's receive time, stored with the contact.
- **Claim:** a value the client supplies that the Worker cannot verify (persona, source plugin, claimed plan). It is
  allow-listed, stored under a `claimed_` / `claim:` name, and never trusted for entitlements.
