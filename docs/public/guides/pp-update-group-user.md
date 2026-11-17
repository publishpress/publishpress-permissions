---
title: "pp_update_group_user"
order: 40
doc_category: presspermit
---

# pp_update_group_user

Table of Contents

- [Example usage](#0-toc-title)

```
/**
 * Update Group Membership for User(s)

 * @param int group_id
 * @param array user_ids
 * @param array args :
 *   - group_type (default 'pp_group')
 *   - status ('active' | 'scheduled' | 'expired' | 'any')
 *   - date_limited (default false)
 *   - start_date_gmt
 *   - end_date_gmt
 */
function pp_update_group_user( $group_id, $user_ids, $args = array() )
```

## Example usage [#](#0-toc-title)

```
$group = pp_get_group_by_name( 'Cool Group', 'pp_group' );

// make one user an active member (with perpetual membership)
$user_id = 321;
pp_update_group_user( $group->ID, $user_id, array( 'status' => 'active', 'date_limited' => false ) );

// set three users to active membership with an expiration date
$user_ids = array( '101', '109', '103' );
pp_update_group_user( $group->ID, $user_ids, array( 'status' => 'active', 'date_limited' => true, 'start_date_gmt' => constant('PP_MIN_DATE_STRING'), 'end_date_gmt' => '2015-01-01 00:00:00' ) );

// set two members to scheduled membership
$user_ids = array( '105', '108' );
pp_update_group_user( $group->ID, $user_ids, array( 'status' => 'scheduled', 'date_limited' => true, 'start_date_gmt' => '2014-09-01 00:00:00', 'end_date_gmt' => constant('PP_MAX_DATE_STRING') ) );
```
