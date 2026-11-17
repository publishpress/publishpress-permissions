---
title: "pp_remove_group_user"
order: 39
doc_category: presspermit
section: Developer
---

# pp_remove_group_user

Table of Contents

- [Example usage](#0-toc-title)

```
/**

Remove User(s) from a Permission Group
@param int group_id
@param array user_ids
@param array args :
- agent_type (default 'pp_group')

*/

function pp_remove_group_user( $group_id, $user_ids, $args = array() )
```

## Example usage [#](#0-toc-title)

$group = pp_get_group_by_name( ‘Cool Group’, ‘pp_group’ );

// remove one user from the group  
 $user_id = 321;  
 pp_remove_group_user( $group->ID, $user_id );

// remove three users from the group  
 $user_ids = array( ‘101’, ‘109’, ‘103’ );  
 pp_remove_group_user( $group->ID, $user_ids );
