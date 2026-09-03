# Onboarding wizard — install notes

Implements wireframe `1a`: a six-step, education-only welcome wizard, plus the
admin notice that gets people into it and the progress card that pulls them
back. Nothing in this flow writes a Permissions setting.

## Files

Copy into your plugin folder, keeping the paths:

| From | To |
| --- | --- |
| `classes/PublishPress/Permissions/UI/Welcome.php` | replaces the existing file |
| `classes/PublishPress/Permissions/UI/WelcomeOnboarding.php` | new file |
| `common/css/welcome.css` | new file |

## One code change

`classes/PublishPress/PermissionsHooksAdmin.php` — add to the end of
`__construct()`:

```php
require_once(PRESSPERMIT_CLASSPATH . '/UI/WelcomeOnboarding.php');
new Permissions\UI\WelcomeOnboarding();
```

That is the only edit. The existing pieces already work:

- `actMaybeRedirectToWelcome()` still sends people to the wizard on activation —
  step 1 is the welcome screen, so activation lands on a greeting.
- `DashboardFilters::actMenuHandler()` already routes `presspermit-welcome` to
  the `Welcome` class.
- `actBuildMenu()` already registers the `- Welcome` submenu while the page is open.

## Artwork

The wizard looks in `common/img/welcome/` and takes `{slug}.svg` first, then
`{slug}.png`. If neither exists it draws a labelled placeholder, so it never
shows a broken image.

Step 1 needs no file — it draws the `dashicons-lock` glyph in brand purple over
a three-bar hint of content. Swap it for `dashicons-unlock` (the plugin's own
menu icon) in `Welcome::heroArt()` if you prefer that read.

Three diagrams are included and need nothing from you:

| File | Shows |
| --- | --- |
| `concept-roles.svg` | the same access everywhere |
| `concept-groups.svg` | people bundled, granted once |
| `concept-exceptions.svg` | one item overridden |

Three screenshots are still yours to take. Save them as PNG:

| File | Shows |
| --- | --- |
| `step-features.png` | Settings › Features tab |
| `step-groups.png` | Permissions groups list |
| `step-post-edit.png` | Permissions panel on the post editor |

How to shoot them:

- Crop tight to the region you are describing, not the whole browser. Collapse
  the admin sidebar first.
- Shoot at 2× and save around 1200px wide; they display at about 440px.
- Default admin colour scheme, light mode.
- Same width on all three, or the layout jumps between steps.
- Use believable demo content, not "Test Page 1".
- Keep version numbers and promo banners out of frame so they do not go stale.

Steps 3-5 overlay numbered pins 1, 2 and 3 at fixed corners (top-left,
top-right, bottom-right). Compose so the three things your callouts describe sit
near those corners, or adjust `.pp-wc-pin-1/2/3` in `welcome.css`.

## Flow

```
activation  ──▶  step 1  Welcome
                 step 2  Three ideas to know (roles / groups / exceptions)
                 step 3  Choose the content you want to control  → Settings
                 step 4  Put people into a group                 → Permissions
                 step 5  Set who can read one page               → a page
                 step 6  What to do first + Pro
```

Navigation is plain links (`?page=presspermit-welcome&pp_step=N`), so it works
without JavaScript and the browser back button behaves. The current step is
stored per user in `presspermit_welcome_step`; reaching step 6 sets
`presspermit_welcome_complete`.

## Progress card

Shown on the Permissions and Settings screens, dismissible per user. It counts
configuration state, not pages read:

| Task | Condition |
| --- | --- |
| Choose the content you want to control | `presspermit_enabled_post_types` differs from the `post` + `page` install default |
| Create your first permission group | a row in `pp_groups` with an empty `metagroup_id` |
| Set permissions on one post or page | any row in `ppc_exceptions` |

## Filters

- `presspermit_welcome_lessons` — the step 3-5 content array (title, sub,
  callouts, link, screenshot slug).
- `presspermit_welcome_tasks` — the progress card task list.

## Not included

The step 3-5 "Show me on the real screen" hand-off from wireframe `2b`. It needs
a JS pointer layer over the real admin screens; the wizard links out in a new tab
instead, which reaches the same screens without that work.
