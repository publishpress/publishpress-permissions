---
title: "pp_get_group_members"
order: 35
doc_category: presspermit
---

# pp_get_group_members

Table of Contents

- [Example usage](#0-toc-title)
- [Notes](#1-toc-title)

```
/**
 * Retrieve users who are members of a specified group

 * @param int group_id
 * @param string agent_type
 * @param string cols ('all' | 'id')
 * @param array args :
 *   - status ('active' | 'scheduled' | 'expired' | 'any')
 * @return array of objects or IDs
 */
function pp_get_group_members( $group_id, $agent_type = 'pp_group', $cols = 'all', $args = array() )
```

## Example usage [#](#0-toc-title)

```
$group = pp_get_group_by_name( 'Cool Group' );

$active_member_ids = pp_get_group_members( $group->ID, 'pp_group', 'id' );
$former_member_ids = pp_get_group_members( $group->ID, 'pp_group', 'id', array( 'status' => 'expired' ) );
```

## Notes [#](#1-toc-title)

- Only active group members are returned, unless a different status argument is specified.
- scheduled and expired statuses require PP Membership extension
