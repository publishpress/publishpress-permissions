---
title: "How to Deactivate PublishPress Permissions"
order: 20
doc_category: presspermit
section: Troubleshooting Permissions
---

# How to Deactivate PublishPress Permissions

Almost all [PublishPress Permissions](https://publishpress.com/links/permissions) features will be safely disabled if you deactivate the plugin.

However, due to the potential damage incurred by accidental deletion, no automatic removal is currently available for data created by the [PublishPress Permissions](https://publishpress.com/links/permissions) plugin.

You can use a SQL editing tool such as phpMyAdmin to drop the tables and delete the pp_ options.

If you set posts to a custom Visibility status, be sure to change them back to Private or Pending before permanently deactivating PublishPress Permissions.

**PublishPress Permissions database tables**:

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

**PublishPress Permissions option_names**:

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

**PublishPress Permissions option_name prefixes**:

```
ppperm_
presspermit_
```
