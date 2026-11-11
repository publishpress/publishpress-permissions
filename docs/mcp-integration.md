# Permissions MCP integration

PublishPress Permissions registers private abilities in the `publishpress-permissions` namespace. Select the actions in PublishPress Hub and connect through the WordPress MCP Adapter. Write actions require Hub write exposure as well as the connected account's native permissions.

## Available actions

| Native area | Discovery and reads | Writes |
| --- | --- | --- |
| Active features | `list-features` | — |
| General, Advanced and Collaboration settings | `get-settings` | `update-settings` |
| Filtered post types and taxonomies | `get-content-types` | `set-content-types` |
| Groups and members | `list-groups`, `get-group` | `create-group`, `update-group`, `update-group-members` |
| Custom-group deletion | `get-group-deletion` | `delete-group` |
| Supplemental roles | `get-extra-roles` | `update-extra-roles` |
| Role Usage | `get-role-usage` | `update-role-usage` |
| Post and term permissions | `get-permissions` | `set-permission` |
| Teaser modes, messages, redirects, RSS and notice styles | `get-teaser` | `update-teaser` |
| Module activation | `get-modules` | `update-modules` |
| Content Visibility shortcode | `get-content-visibility`, `build-content-visibility` | Use native post-editing tools to save the generated shortcode |
| Pro membership schedules | `get-group` | `set-group-membership` |
| Pro visibility and editorial circles | `get-circles` | `set-circles` |
| Pro user/post synchronization | `get-sync-settings`, `get-sync-plan` | `update-sync-settings`, `run-sync` |
| Pro file protection | `get-settings`, `get-file-maintenance` | `update-settings`, `run-file-maintenance` |

Pro actions require the corresponding active module and the shared Free integration bundled with Pro. Circles, Compatibility and File Access checkbox settings appear in shared settings discovery when their modules are active. Missing modules cannot be operated through their MCP actions.

## Read, preview and save

Read the corresponding action before choosing identifiers or values. Updates require its current `revision`; writes support `dry_run: true` to validate without saving. Read the result after saving. If a native operation cannot be confirmed, read the current state before retrying: an error does not promise that earlier native steps rolled back.

Patches preserve omitted fields, unrelated options and other objects. Unknown fields, invalid choices, malformed JSON types and duplicate patch targets are rejected before writes. Temporarily unregistered filtering entries remain intact. Native configuration filters, Advanced locks, active modules and network ownership remain authoritative.

Checkbox inputs are JSON booleans, never strings such as `"false"`. Native settings store checkbox values as `"1"` and either `"0"` or an empty string, according to the native control. Discovery returns exact input schemas and stored values. IDs and lengths use JSON integers; role lists use JSON arrays of strings.

## Groups, roles and permissions

Membership changes explicitly add or remove user IDs. Adding an existing member preserves its schedule. Pro schedules use UTC `YYYY-MM-DD HH:MM:SS`, a JSON `date_limited` boolean, and native active/scheduled/expired status. Omitted membership fields keep their existing values.

Read `get-group-deletion` before deletion. Its revision includes native members, roles, permission cascades and active Pro circles. Built-in metagroups cannot be deleted. Every affected object must be authorized.

Supplemental grants use discovered pattern roles scoped as `base:post:post_type` or `base:term:taxonomy`. Status scopes use `base:post:post_type:post_status:status` with an active native status. User, custom-group and WordPress-role metagroup targets are supported. New administrator-level grants are prohibited; existing grants may be removed.

Role Usage requires native Advanced controls. Choices are `"0"` (no supplemental assignment), `"pattern"` and `"direct"`. Remove existing supplemental assignments before changing an assigned role's interpretation.

For term permissions, `object_id` is the WordPress term ID and `taxonomy` identifies its taxonomy. Native term-taxonomy IDs are resolved internally. `mod_type` is `additional`, `include` or `exclude`; `assign_for` is `item`, `children` or `both`. Change inherited rules at their origin.

## Teaser and content visibility

Omit `post_type` from teaser actions for global application and RSS options. Supply a filtered post type for its mode, messages, redirects, lengths and notice styles. Messages use the native rich-text sanitizer. Redirects to posts require permission to read and edit the destination. URL redirects accept HTTP/HTTPS destinations.

Module discovery distinguishes the configured `enabled` state from the module currently `loaded` in the request. Module changes apply on the next request and use native lifecycle hooks. Required modules cannot be disabled.

Content Visibility uses the native `pp_restrict` shortcode. Its builder accepts typed logged-in/out, role, capability, username and group conditions, `all`/`any` relation and a JSON `hide` boolean. It returns sanitized shortcode text without saving a post. Use native content-editing tools, with that post's permissions, to insert it. Nested visibility shortcodes use native editing.

## Pro synchronization and file maintenance

Synchronization settings have global switches and per-post-type settings for new/existing users, role lists, match fields, parent, quantity and status. Templates accept discovered non-secret user fields. Registered scalar metadata must also pass native metadata permissions; arbitrary private metadata and credential fields are excluded.

Read `get-sync-plan` before `run-sync`. Native synchronization can create posts, change authors, enable filtering and assign scoped Author roles. The connected account must be authorized for every affected user, object and side effect. A plan reports scope and maximum creation count; actual matches can reduce creation. User-field values become post data according to the configured native mapping, so choose fields and publication status deliberately.

File maintenance supports current-site `rebuild`, `rotate_keys` and `scan_attachments`. Read its plan and revision first. Scanning uses the native linked-upload utility; it validates existing files without saving during previews. Key rotation changes native attachment access keys. Keys, protected URLs and filesystem paths are not returned. Busy or unsuccessful native rewrites are reported as unconfirmed.

## Authorization and scope limits

Every action checks its operation capability at execution. Related users, metadata, posts, terms, descendants and groups require their own checks. Administrator status does not override an explicitly denied capability. Membership reads return a fixed projection without passwords or email addresses.

Affected scopes are bounded to 1,000 objects or users; synchronization also bounds possible creation. Use native administration for larger scopes, legacy multisite upload rewrites, network-wide file maintenance, network-wide group membership/deletion, third-party group providers and interpreting already-assigned roles. Credentials, license management and arbitrary option/metadata writes are outside these actions. Circles restrict access and do not grant editing capabilities; native role exemptions lock their operations.

## Example requests

- List the active Permissions modules and the actions I can use.
- Preview a Review Team group, then add an existing user without changing other members.
- Read this page's Review Team permissions and preview additional read access.
- Show the teaser settings for Pages and preview an excerpt with 120 characters.
- Build a visibility shortcode for logged-in Editors without saving content.
- Preview disabling an optional module and show when the change applies.
- With Pro active, preview a membership schedule or visibility circle.
- Show the sync plan for a configured post type before running it.
- Preview native attachment scanning or current-site file-key rotation.

## Verification

`tests/mcp/integration.php` uses the released Adapter in an isolated `permissions_mcp_*` database. Set `MCP_ADAPTER_TEST_BOOTSTRAP` to its bootstrap and run with `wp eval-file`. The fixture requires the isolated `mcpadmin` account. Pro coverage requires its optional modules enabled. Run `tests/mcp/file-rules.php` with `MCP_FILE_CASE=setup` before each separate `rotate` or `failure` request to verify native file-write outcomes. `tests/mcp/module-gates.php` uses separate `MCP_GATE_CASE=disable` and `verify` requests to check native module deactivation. These fixtures must never run against production data.

## Editor controls

Use `get-editor-options` with an enabled `post_type` to read `metabox_enabled` and `permission_screen_enabled`. Use `update-editor-options` with its revision and a boolean patch. These are the native per-type metabox and Permissions screen switches; they do not grant access to an otherwise unauthorized user.

See [feature coverage](mcp-feature-coverage.md) for native areas and administration boundaries. Synchronization requires explicit match fields and post status before it is enabled or run.

The comparison fixture can exercise native Capabilities application-password scoping when `CAPABILITIES_MCP_SOURCE` points to that plugin's source. `tests/mcp/network-scope.php` checks network group ownership on a dedicated multisite fixture. These checks use isolated credentials and data; they do not test an external AI client's cached tools or HTTP reconnection behavior.

## Operation choices and validation

`get-features` reports native active operations. `operation: "publish"` requires Collaboration and the native separate Publish Permissions setting, plus `pp_set_edit_permissions`. Native group-management restrictions apply to API requests as well as admin screens. Term inclusion rules and their deletion require authorization over the inverse taxonomy scope.

Role Usage writes require native Advanced Settings. Teaser redirect post types must be current public types other than attachments. Empty stored notice colors inherit the native default. Supplied membership dates must be real UTC dates or the native minimum/maximum boundary values; disabling the date limit preserves omitted dates.

The `tests/mcp/security-contract.php` fixture runs as part of integration verification. It exercises all discovered writable General/Advanced checkboxes, global and per-type teaser fields, previews, native read-back, malformed patches, inverse term scopes, native group restrictions and partial settings-write failures. Optional application-password tests exercise the native restriction service through Adapter execution. External AI-client sessions, third-party integration behavior and live web-server delivery of protected files require separate environment tests.

## Direct roles, delegation and batches

`get-extra-roles` returns `direct_roles` enabled by native Role Usage. Assign their plain names with `update-extra-roles`; scoped pattern/status roles retain their existing formats. Direct grants require `promote_users`, native editable-role eligibility and access to affected posts/terms. They preserve WordPress primary roles and cannot grant administrator-level capabilities.

Use `get-permissions` and `set-permission` with `source: "pp_group"`, `target_source: "pp_group"`, `object_id` identifying a custom group, `operation: "manage"` and `assign_for: "item"` for delegation. Collaboration and Display group permissions must be enabled. Group editing, membership, deletion and permission-management capabilities apply. Existing target-group roles/content grants must be authorized before management is granted. Network/provider groups and transitive delegation graphs retain native administration. Remove incoming delegation rules before deleting a target group.

`get-permission-matrix` accepts `items` containing up to 25 explicit permission scopes. Each result includes its individual revision. `bulk-set-permissions` accepts the same write items as `set-permission`; `bulk-update-extra-roles` accepts the same write items as `update-extra-roles`. Place `dry_run` on the batch, not individual items. Every item must be valid, authorized and current before writes begin. Permission batches are bounded to 1,000 affected objects; matrices return at most 1,000 rules. Intersecting descendant scopes require separate requests.

Read `completed` and `confirmed_items` after a batch. On a native failure, `failed_index` identifies the zero-based unconfirmed item, and `results` contains confirmed preceding outcomes. Later items are not attempted. Read every affected scope before retrying; native persistence is not transactional.

The shared `tests/mcp/extended-features.php` fixture verifies these actions with both editions, including native role storage, agent-namespace preservation, primary-role preservation, delegation references, stale/denied/malformed batches and a forced second-item write failure.
