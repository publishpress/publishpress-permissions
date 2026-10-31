---
name: publishpress-mcp
description: Implement or extend PublishPress Permissions MCP abilities, native settings, group membership, supplemental roles, and content permissions.
---

# PublishPress Permissions MCP integration

Read `docs/mcp-integration.md` for supported actions, values and usage. Integration services live in `classes/PublishPress/Permissions/Mcp`; Pro extensions live in `includes-pro/Mcp` in the Pro repository.

- Register private abilities on the WordPress Abilities API hooks independently of Hub. Keep optional API guards, duplicate registration checks and all three private exposure metadata flags. Hub owns transport and action selection.
- Reuse native `presspermit()` getters and setters, group APIs, scoped-role assignments and exception lifecycle methods. Keep metadata discovery free of rendering and persistence side effects.
- Compare each schema with native controls, defaults, sanitizers and storage. Require exact JSON types; never coerce string booleans. Preserve omitted values and unrelated map entries.
- Validate the entire patch, native locks, current revision and affected-object scope before saving. Offer previews and verify native read-back. Report unconfirmed partial outcomes without promising rollback.
- Enforce actual capabilities and native group filters even for administrators. Check related users, posts, terms and descendants. Never expose account passwords, protected-file keys or arbitrary metadata.
- Reject new administrator-level supplemental grants. Keep scoped grants bounded and validate every affected object. Resolve term IDs to native term-taxonomy IDs.
- Gate Pro behavior on the actual active module. Do not edit bundled vendor code; update the Free source and dependency reference separately when needed.
- Run native standalone tests and `tests/mcp/integration.php` through a released Adapter on isolated fixtures. Cover malformed mixed patches, stale revisions, denied administrator capabilities, native storage round trips and edition gates.

- Keep teaser and synchronization patches in the shared NativeOptions contract. Preserve native scalar/map storage and slashing. Use the native module and Role Usage lifecycle setters; do not render or submit admin forms.
- Check the entire group-deletion cascade, including inverse include scopes and active circles. Module switches apply on the next request. Visibility authoring returns shortcode text; post saving remains object-authorized native editing.
