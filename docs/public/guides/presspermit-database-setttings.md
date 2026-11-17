---
title: "PublishPress Permissions Database Tables and Settings"
order: 46
doc_category: presspermit
---

# PublishPress Permissions Database Tables and Settings

Table of Contents

- [Database tables](#h-database-tables)
- [Permissions options](#h-options-in-the-wp_options-table)
- [The Exceptions tables](#h-the-ppc_exceeptions-table)

The [PublishPress Permissions](https://publishpress.com/links/permissions) plugin creates and uses database tables on your site. This is a high-level overview of those tables.

## Database tables [#](#h-database-tables)

Here are the database tables that are created on a standard installation of [PublishPress Permissions](https://publishpress.com/links/permissions) Free:

```
pp_groups
pp_group_members
ppc_roles
ppc_exceptions
ppc_exception_items
```

Here are all PublishPress Permissions database tables that *could* be present on your site if you enable all the features. This includes both the Free and Pro versions. Most sites will not have all these tables.

```
pp_circles
pp_conditions
pp_groups
pp_group_members
ppc_roles
ppc_exceptions
ppc_exception_items
ppi_errors
ppi_imported
ppi_runspp_circles
pp_conditions
pp_groups
pp_group_members
ppc_roles
ppc_exceptions
ppc_exception_items
ppi_errors
ppi_imported
ppi_runs
```

## Permissions options [#](#h-options-in-the-wp_options-table)

You can identify options created by the PublishPress Permissions plugin. You will will find them in the wp_options table with these prefixes:

```
ppperm_
presspermit_
```

You may also find these options if you enable more features:

```
pp_c_version
pp_support_key
pp_wp_role_sync
ppcc_version
ppce_version
ppi_version
ppm_version
ppp_version
pps_version
ppsync_version
```

## The Exceptions tables [#](#h-the-ppc_exceeptions-table)

The **ppc_exceptions** and **ppc_exception_items** tables are the most important. These tables controls many key features in PublishPress Permissions. They record which users and user roles have access to which content inside your WordPress site.

Here is a detailed breakdown of the **ppc_exceptions** table:

- **agent_type:** user / pp_group / bp_group / etc.
- **agent_id**: ID of user or group who the exception is applied to.
- **for_item_source**: data source of items which are affected by the exception.
- **for_item_type**: which post or taxonomy type (post / page / category / etc) is affected by the exception (nullstring means all types that use specified for_item_source).
- **for_item_status**: posts of this status (or other property) are affected by the exception. Storage is “post_status:private” (nullstring means all stati)
- **operation**: read / edit / assign / parent / revise / etc.
- **mod_type**: include / exclude / additional.
- **via_item_source**: data source of the item which triggers the exception (if for_item_type is page, via_item_source could be term).
- **via_item_type**: post type or taxonomy which triggers the exception (Needed for when post exceptions are based on term assignment. Nullstring means redundant / not applicable).
- **assigner_id**: ID of user who stored the exception.
