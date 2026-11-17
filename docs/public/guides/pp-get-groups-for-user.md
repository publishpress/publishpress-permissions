---
title: "pp_get_groups_for_user"
order: 37
doc_category: presspermit
---

# pp_get_groups_for_user

Table of Contents

- [Example usage](#0-toc-title)
- [Notes](#1-toc-title)

```
/**
 * Retrieve groups for a specified user

 * @param int user_id
 * @param string agent_type
 * @param array args :
 *   - cols ('all' | 'id')
 *   - status ('active' | 'scheduled' | 'expired' | 'any')
 *   - metagroup_type (default null)
 *   - force_refresh (default false)
 * @return array (object or storage date string, with group id as array key)
 */
function pp_get_groups_for_user( $user_id, $agent_type = 'pp_group', $args = array() )
```

## Example usage [#](#0-toc-title)

```
$user_id = '321';

// get this user's custom groups and WP role metagroups
$user_groups = pp_get_groups_for_user( $user_id );
$user_group_ids = array_keys( $user_groups );

// get this user's custom groups, excluding metagroups but including scheduled and expired memberships
$user_custom_groups = pp_get_groups_for_user( $user_id, 'pp_group', array( 'metagroup_type' => '', 'status' => 'any' ) );
$user_custom_group_ids = array_keys( $user_custom_groups );

// get this user's custom network groups
$user_net_groups = pp_get_groups_for_user( $user_id, 'pp_net_group' );
$user_net_group_ids = array_keys( $user_net_groups );
```

## Notes [#](#1-toc-title)

- If you are retrieving groups for the currently logged user using non-default $args, it may be necessary to also include force_refresh => true in the arguments array to override internal caching.
- scheduled and expired statuses require the PP Membership extension.
