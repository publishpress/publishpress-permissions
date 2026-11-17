---
title: "pp_get_metagroup"
order: 38
doc_category: presspermit
section: Developer
---

# pp_get_metagroup

Table of Contents

- [Example usage](#0-toc-title)
- [Notes](#1-toc-title)

```
/**
 * Retrieve the Permission Group object for a WP Role or other metagroup, by providing its name

 * @param string metagroup_type
 * @param string metagroup_id
 * @param array args :
 *   - cols (return format - 'all' | 'id')
 * @return object Permission Group (unless cols = 'id')
 *  - ID
 *  - group_name
 *  - group_description
 *  - metagroup_type
 *  - metagroup_id
 */
function pp_get_metagroup( $metagroup_type, $metagroup_id, $args = array() )
```

## Example usage [#](#0-toc-title)

```
// retrieve group ID for Subscriber, Contributor and Author metagroups
$subscriber_group_id = pp_get_metagroup( 'wp_role', 'subscriber', array( 'cols' => 'id' ) );
$contrib_group_id = pp_get_metagroup( 'wp_role', 'contributor', array( 'cols' => 'id' ) );
$author_group_id = pp_get_metagroup( 'wp_role', 'author', array( 'cols' => 'id' ) );

// assign a supplemental role, allowing WP role group members to also author a custom post type
$groups = array( $subscriber_group_id => true, $contrib_group_id => true, $author_group_id => true );
ppc_assign_roles( array( 'author:post:my_custom_type' => $groups ) ), 'pp_group' )
```

## Notes [#](#1-toc-title)

metagroup_type and metagroup_id do not apply to custom-defined groups.
