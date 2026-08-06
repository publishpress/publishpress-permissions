# Milestone 47 ("4.8.3: Clean up") — Fix Plan

Milestone: https://github.com/publishpress/publishpress-permissions/milestone/47
Generated: 2026-08-07

## Scope note

The milestone lists 15 open items. One of them, **#2419**, is actually an
already-open pull request (not an issue) with its own branch
`fix/user-management-role-level-restrictions` — it's excluded from this plan
since it's already being worked on.

That leaves **14 real issues**. Each will be fixed on its own branch off
`development`, one branch per issue, per the workflow requested. Branches are
named `fix/<slug>-<issue-number>`. No `Co-Authored-By: Claude` trailer on any
commit for this work.

## Execution order and per-issue analysis

### 1. #2345 — CSP: `fltHideEmptyMenus()` prints raw `<script>` tag
- **Confidence:** High — root cause and exact fix confirmed by reading the code.
- **File:** `classes/PublishPress/Permissions/FrontFilters.php` (~lines 43-66)
- **Fix:** Replace the inline `<script>...</script>` PHP template block with
  `wp_print_inline_script_tag()` (WP 5.7+), which lets WP's CSP-nonce
  mechanism see and tag the script.
- **Branch:** `fix/csp-inline-script-2345`

### 2. #2422 — Attachment term counts show 0 for admins in `tallyTermCounts()`
- **Confidence:** High — confirmed in `classes/PublishPress/Permissions/TermQuery.php`,
  `tallyTermCounts()`. The unfiltered-user branch builds `post_status IN (...)`
  from `get_post_stati(['public'=>true,'private'=>true])`, which excludes
  `inherit` (attachments' status), so attachment term counts are zeroed for
  admins/unfiltered users.
- **Fix:** When the taxonomy's object types include `attachment`, include
  `inherit` in the accepted status list before building `$status_clause`.
- **Branch:** `fix/attachment-term-counts-2422`

### 3. #2421 — Term checklist hierarchy breaks for non-top-level Assign-Term restriction
- **Confidence:** High — root cause confirmed (name-ordered `get_terms()` +
  single-pass `Walker_Category_Checklist`, restricted set lacks ancestor).
- **Fix (option a, the lighter one confirmed by the reporter):** reorder the
  filtered term set into parent-before-children order before it reaches the
  checklist walker, treating any term whose parent is absent from the set as
  a subtree root. Likely touches the term-filtering code in
  `classes/PublishPress/Permissions/TermFilters.php` (or wherever the
  Assign-Term restricted term set is built for the post-editor checklist).
- **Branch:** `fix/term-checklist-hierarchy-2421`

### 4. #2314 — Nav menu parent update bypasses metadata cache invalidation
- **Confidence:** High — confirmed in
  `modules/presspermit-collaboration/classes/Permissions/Collab/NavMenus.php`,
  `fltUpdateNavMenuItemParent()` hooked to `update_post_metadata`, writing
  postmeta directly via `$wpdb` instead of `update_post_meta()`.
- **Fix:** Use `update_post_meta()` internally (with a static re-entrancy
  guard to avoid an infinite loop back through the same filter), as
  suggested in the issue, so WP's normal meta-cache invalidation runs.
- **Branch:** `fix/nav-menu-cache-invalidation-2314`

### 5. #2315 — Broad `wp_cache_flush()` usage clears shared object cache
- **Confidence:** Medium-high — two call sites confirmed in this repo:
  `classes/PublishPress/PermissionsHooksAdmin.php:~418` and
  `modules/presspermit-collaboration/classes/Permissions/Collab/UI/Dashboard/PostsListingNonAdministrator.php:18`.
- **Fix:** Replace full-cache flushes with targeted invalidation
  (`clean_post_cache()`, `wp_cache_delete()` for the specific keys/groups
  actually affected), matching the pattern already used elsewhere in the
  codebase. Needs care to confirm what each flush call was actually trying
  to invalidate before narrowing it, to avoid stale-cache regressions.
- **Branch:** `fix/targeted-cache-invalidation-2315`

### 6. #2218 — Page count doesn't match the filtered list count
### 7. #2436 — Contributors can see (unfiltered) post totals
### 8. #2343 — Improper limit/offset handling on the permissions post/page list
- **Confidence:** Medium — these three look like symptoms of the same root
  cause: `PostsListing::fltCountPosts()` /
  `PostsListing::fltCountPostsQuery()` in
  `classes/PublishPress/Permissions/UI/Dashboard/PostsListing.php` only
  filters `wp_count_posts()` results by *registered status*, not by the
  permission-based post visibility join/where that the actual list query
  gets (via `PostFilters::fltPostsJoin()` / `getPostsWhere()`, same
  mechanism used in the `tallyTermCounts()` fix above). That's why the
  status-link/page counts show the unfiltered total while the list itself
  is correctly filtered.
  - #2343's issue body is an empty/unfilled template — its title suggests
    it may be the same pagination-count mismatch, or a distinct
    limit/offset bug in list-table query building. Will confirm scope
    during that branch's investigation; if it turns out to be a duplicate
    of #2218, the fix will be minimal/point to the same root cause but
    still delivered as its own commit on its own branch per the requested
    workflow.
- **Fix approach:** extend `fltCountPostsQuery()` (or `fltCountPosts()`) to
  apply the same `PostFilters` join/where used elsewhere when the current
  user is not unfiltered, so the counts used for status links / pagination
  match the actual visible list.
- **Branches:** `fix/post-count-sync-2218`, `fix/contributor-post-totals-2436`,
  `fix/list-pagination-offset-2343`

### 9. #2420 — Legacy category metabox settings not shown in new tabbed metabox UI
- **Confidence:** Medium — confirmed the two metabox code paths both key off
  `presspermit()->getOption('use_tabbed_metabox')` in
  `classes/PublishPress/Permissions/UI/Dashboard/TermEdit.php`, but the
  actual data-loading bug (why the tabbed UI's JS reads back "No setting"
  instead of the stored exception values) is in the newer tabbed-metabox
  JS/AJAX path introduced for `item-edit-tabbed.js`. Needs closer tracing of
  how that JS fetches/renders existing exceptions before a targeted fix.
- **Branch:** `fix/tabbed-metabox-legacy-settings-2420`

### 10. #2438 — Teaser area has design issues in WordPress 7.0
- **Confidence:** Low without visual inspection — issue body is screenshots
  only, no text description of the specific layout break (radio button
  styling mentioned). Will pull the referenced screenshots to see the exact
  visual defect before touching CSS.
- **Branch:** `fix/teaser-design-wp7-2438`

### 11. #2418 — Update the metabox to support WordPress 7.1 real-time collab
- **Confidence:** Low — this is a compatibility/feature item, not a
  contained bug. Needs research into how WP 7.1's real-time collaborative
  editing affects metabox rendering/saving (likely REST-based autosave or
  block-editor sidebar changes) before scoping an actual code change.
- **Branch:** `fix/wp71-collab-metabox-2418`

### 12. #2316 — Network-wide site loops create scalability risk
- **Confidence:** Low impact *in this repo*. Of the 6 locations cited in the
  issue, 4 are in the separate `publishpress-permissions-pro` repository and
  out of scope here. The only match in this repo is `uninstall.php`'s
  `get_sites()` loop — but uninstall must complete synchronously in the
  same request (deferring via `wp_schedule_single_event` as suggested won't
  work post-uninstall, since the plugin code triggering it will already be
  gone). Will look for a safe, in-repo improvement (e.g., batching the
  `get_sites()` call itself to bound memory) rather than applying the
  cron-based suggestion verbatim.
- **Branch:** `fix/multisite-uninstall-batching-2316`

### 13. #2322 — "Check reports"
- **Confidence:** Blocked — the issue body is only a link to a private
  Slack conversation (`rambleventures.slack.com`), which isn't accessible.
  No title/description beyond "Check reports" to derive a concrete bug or
  task from. Will do a best-effort review of the plugin's "Reports" feature
  code for obvious issues, but this may need the reporter/maintainer to add
  detail to the issue before a real fix can be scoped.
- **Branch:** `fix/check-reports-2322`

## Workflow per issue

1. `git checkout development && git pull`
2. `git checkout -b fix/<slug>-<n>`
3. Implement + manually verify the fix (read affected code paths; add/adjust
   tests where the repo has test coverage for the area)
4. Commit with a plain, descriptive message (no Claude co-author trailer)
5. Move to the next issue's branch

Branches are left for the user to review/push/PR — this plan doesn't assume
push or PR creation unless separately requested.
