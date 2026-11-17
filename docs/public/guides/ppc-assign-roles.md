---
title: "ppc_assign_roles()"
order: 43
doc_category: presspermit
section: Developer
---

# ppc_assign_roles()

Table of Contents

- [Example usage](#0-toc-title)

```
/**
 * Assign supplemental roles for a user or group

 * @param array roles : roles[role_name][agent_id] = true
 * @param string agent_type
 */
function ppc_assign_roles( $roles, $agent_type = 'pp_group' )
```

## Example usage [#](#0-toc-title)

```
$group = pp_get_group_by_name( 'Cool Group', 'pp_group' );

// give all members of this group a supplemental role of Thing Contributor
$roles = array();
$roles['contributor:post:thing'] = array( $group->ID => true );
ppc_assign_roles( $roles, 'pp_group' );

// also give two users the same supplemental role (regardless of group membership)
$user_ids = array( '234', '432' );
$roles = array();
$roles['contributor:post:thing'] = array_fill_keys( $user_ids, true );
ppc_assign_roles( $roles, 'user' );
```
