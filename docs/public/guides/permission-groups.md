---
title: "Developer Information for Permission Groups"
order: 27
doc_category: presspermit
section: Developer
---

# Developer Information for Permission Groups

Table of Contents

- [Types of groups](#h-types-of-groups)
- [Functions for groups](#h-functions-for-groups)
- [Functions for roles](#h-functions-for-roles)

Permission Groups are feature of the [PublishPress Permissions](https://publishpress.com/links/permissions) plugins. These are user groups with customizable permissions.

You can customize the permissions by several methods:

- Adding a supplemental role to a user.
- Adding exceptions to a role.
- Using Editorial Circles or Visibility Circles.

## Types of groups [#](#h-types-of-groups)

There are several types of Permission Groups available:

- **pp_group**: groups manually created, with membership custom-defined
- **pp_group** (metagroup_type = wp_role): groups auto-created to correspond to the site’s WordPress roles.
- **pp_net_group**: groups manually created and accessible to all sites on a multi-site network.
- **bp_group**: references to existing BuddyPress groups*(Pro version only)*

## Functions for groups [#](#h-functions-for-groups)

[PublishPress Permissions](https://publishpress.com/links/permissions) provides several functions to help developers integrate with group creation, access, and membership:

- [pp_create_group](https://publishpress.com/knowledge-base/pp_create_group) ( $group_vars_arr )
- [pp_delete_group](https://publishpress.com/knowledge-base/pp_delete_group) ( $group_id, $agent_type )
- [pp_get_group](https://publishpress.com/knowledge-base/pp_get_group) ( $group_id, $agent_type = ‘pp_group’ )
- [pp_get_group_by_name](https://publishpress.com/knowledge-base/pp_get_group_by_name) ( $name, $agent_type = ‘pp_group’ )
- [pp_get_metagroup](https://publishpress.com/knowledge-base/pp_get_metagroup) ( $metagroup_type, $metagroup_id, $args = array() )
- [pp_get_groups](https://publishpress.com/knowledge-base/pp_get_groups) ( $agent_type = ‘pp_group’, $args = array() )
- [pp_get_group_members](https://publishpress.com/knowledge-base/pp_get_group_members) ( $group_id, $agent_type = ‘pp_group’, $cols = ‘all’, $args = array() )
- [pp_get_groups_for_user](https://publishpress.com/knowledge-base/pp_get_groups_for_user) ( $user_id, $agent_type = ”, $args = array() )
- [pp_add_group_user](https://publishpress.com/knowledge-base/pp_add_group_user) ( $group_id, $user_ids, $args = array() )
- [pp_remove_group_user](https://publishpress.com/knowledge-base/pp_remove_group_user) ($group_id, $user_ids, $args = array()
- [pp_update_group_user](https://publishpress.com/knowledge-base/pp_update_group_user) ( $group_id, $user_ids, $args = array() )

## Functions for roles [#](#h-functions-for-roles)

PublishPress Permissions provides several functions to help developers integrate with supplemental roles and exception assignment:

- [ppc_get_roles](https://publishpress.com/knowledge-base/ppc_get_roles) ( $agent_type, $agent_id, $args = array() )
- [ppc_assign_roles](https://publishpress.com/knowledge-base/ppc_assign_roles) ( $roles, $agent_type = ‘pp_group’, $args = array() )
- [ppc_get_exceptions](https://publishpress.com/knowledge-base/ppc_get_exceptions) ( $args = array() )
- [ppc_assign_exceptions](https://publishpress.com/knowledge-base/ppc_assign_exceptions) ( $agents, $agent_type = ‘pp_group’, $args = array() )
