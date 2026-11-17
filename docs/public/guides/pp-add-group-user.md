---
title: "pp_add_group_user"
order: 31
doc_category: presspermit
section: Developer
---

# pp_add_group_user

Table of Contents

- [Example usage](#0-toc-title)
- [Notes](#1-toc-title)

```
/**
 * Add User(s) to a Permission Group

 * @param int group_id
 * @param array user_ids
 * @param array args :
 *   - group_type (default 'pp_group')
 *   - status ('active' | 'scheduled' | 'expired' | 'any')
 *   - date_limited (default false)
 *   - start_date_gmt
 *   - end_date_gmt
 */
function pp_add_group_user( $group_id, $user_ids, $args = array() )
```

## Example usage [#](#0-toc-title)

```
$group = pp_get_group_by_name( 'Cool Group', 'pp_group' );

// make one user an active member (with perpetual membership)
$user_id = 321;
pp_add_group_user( $group->ID, $user_id );

// add three users, specifying membership expiration date
$user_ids = array( '101', '109', '103' );
pp_add_group_user( $group->ID, $user_ids, array( 'date_limited' => true, 'end_date_gmt' => '2015-01-01 00:00:00' ) );

// schedule membership for two members
$user_ids = array( '105', '108' );
pp_add_group_user( $group->ID, $user_ids, array( 'status' => 'scheduled', 'date_limited' => true, 'start_date_gmt' => '2014-09-01 00:00:00' ) );
```

## Notes [#](#1-toc-title)

- User(s) will be added as active members, unless a different status argument is specified.
- *Membership scheduling and expiration requires the PP Membership exten*sion
