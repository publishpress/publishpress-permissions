---
title: "ppc_get_roles()"
order: 45
doc_category: presspermit
section: Developer
---

# ppc_get_roles()

Table of Contents

- [Example usage](#0-toc-title)
- [Notes](#1-toc-title)

```
/**
 * Retrieve supplemental roles for a user or group

 * @param string agent_type
 * @param int agent_id
 * @param array args :
 *   - post_types (default true)
 *   - taxonomies (default true)
 *   - force_refresh (default false)
 * @return array : $roles[role_name] = role_assignment_id
 */
function ppc_get_roles( $agent_type, $agent_id, $args = array() )
```

## Example usage [#](#0-toc-title)

```
$group = pp_get_group_by_name( 'Cool Group', 'pp_group' );

$roles = ppc_get_roles( 'pp_group', $group->ID );
$group_role_names = array_keys( $roles );
```

## Notes [#](#1-toc-title)

- Calling this function for a user returns only roles assigned directly to the user (not roles assigned to the user’s groups).
- If you are retrieving roles for the currently logged user using non-default $args, it may also be necessary to include force_refresh => true in the $args array.
- You may pass an array of post_types or taxonomies to limit the returned roles to corresponding types.
