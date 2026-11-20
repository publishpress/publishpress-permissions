# Permissions MCP feature coverage

Use `get-features` and the relevant getter to discover the active edition, supported fields, choices, current values and revision. Hub must expose each action. Native capabilities and configuration locks apply on every call, including previews.

| Native area | MCP coverage | Authorization and scope |
| --- | --- | --- |
| General and Advanced settings | Allowlisted settings with native defaults and stored representations | `pp_manage_settings`; additional native/module/network capabilities as applicable |
| Enabled post types and taxonomies | Discover and patch filtering selections | Settings capability, native type locks and code filters |
| Editor controls | Per-type Permissions metabox and Permissions screen switches | Settings capability and the post type's edit-others capability |
| Custom groups | Search, create, rename, membership additions/removals, deletion plan and deletion | Native group capabilities, group-specific access, affected users and permissions |
| Supplemental roles | Read and patch native direct, post-type, taxonomy and status role assignments | Permissions capability, native role eligibility and every affected object/user |
| Group delegation | Read and patch native management rules on custom groups | Collaboration, Display group permissions, permissions/group/member/deletion capabilities and related-object checks |
| Role Usage | Read and configure unused role interpretations | Collaboration and Advanced settings; settings and permissions capabilities |
| Post and term permissions | Read and patch permissions, including separate Publish permissions when enabled, inherited and inverse scopes | Native operation, object, group and related-object checks |
| Teaser | Global/RSS and per-type modes, messages, redirects, lengths and presentation | Teaser/settings capabilities; target type, terms and redirect destinations |
| Content Visibility | Build a native visibility shortcode | Permissions capability and referenced users, groups, roles and capabilities; does not save content |
| Modules | Discover configured/loaded states and enable optional modules | Native module-management capabilities and dependencies; next-request lifecycle |
| Pro membership | Existing custom-group membership schedules | Active Membership module; group/member capabilities and user access |
| Pro circles | Per-type visibility and editorial circles | Active Circles module; native exemptions and affected group/object access |
| Pro synchronization | Global/per-type settings, bounded plan and execution | Active Sync module; settings, permissions, users, metadata, creation/publication and affected objects |
| Pro File Access | Settings, bounded scanning, rule rebuilding and key rotation | Active File Access module; native configuration and affected attachments/parents |
| Pro Compatibility | Allowlisted native settings | Active module and native settings capabilities |

## Native administration boundaries

Use native administration for network-wide memberships/deletion and file maintenance, third-party group-provider mutations, scopes larger than 1,000 objects, and changing the interpretation of already-assigned roles. Native content editing handles insertion and nesting of visibility shortcodes.

License keys, credential management, diagnostic constant listings, arbitrary options/metadata and privilege-broadening legacy switches (`anonymous_unfiltered`, `suppress_administrator_metagroups`, `pattern_roles_include_generic_rolecaps`) are not exposed. These boundaries apply even when the connected user is an administrator. MCP does not replace the plugin's native runtime enforcement or expose every internal hook as an action.

## Settings and synchronization

Getters describe exact JSON types; send booleans as booleans, IDs as integers and lists as JSON arrays. Omit unchanged fields. Empty lists clear supported selections. A code-forced value is locked. Revisions describe the relevant current state; obtain a new revision after a successful write or conflict.

Editor switches use native string `0` for disabled storage; the MCP value is a JSON boolean. Per-type patches preserve unrelated stored entries even when filters alter their displayed values.

Before enabling or running synchronization, save explicit user/post match fields and a post status. Plans use the native filtered user selection and native post matching. User-field values become post data under that mapping. Execution validates the approved targets and reports unconfirmed outcomes if native writes fail; re-read state before retrying.

## Operations retaining their native interface

Native direct supplemental roles use the discovered Role Usage direct-role names and preserve primary WordPress roles. They require promote_users, native role eligibility and authorization over affected content; administrator-level grants and anonymous/all-user metagroup direct grants are rejected. Custom-group management delegation uses native manage permissions and requires access to the target group's roles, members and existing content grants. Transitive delegation graphs, network groups and third-party providers retain native administration.

Bounded permission matrices read explicit scopes. Bulk permission and supplemental-role actions accept up to 25 items with individual revisions, previews and full validation before writes. Permission batches reject duplicate or intersecting descendant scopes; role batches use one item per subject. Results distinguish confirmed preceding writes from an unconfirmed failed item; no rollback is promised.

Teaser live-page rendering and browser previews retain the native UI. Optional page-builder, forum, commerce and network integrations can affect native runtime permissions; their external systems are not administered through these actions. Network-wide groups, large scopes and legacy file rewrites retain native administration.

Group reads include authorized member IDs and display names. Teaser reads include configured messages and redirect destinations. Synchronization can copy selected user fields, including email, into post data; selecting a public publication status can make those values public. These are permission-gated features that require deliberate action selection, not secret-free substitutes for content review. Credential fields, protected-file keys, raw filesystem paths and arbitrary user metadata are excluded.
