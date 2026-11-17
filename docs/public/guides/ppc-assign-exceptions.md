---
title: "ppc_assign_exceptions()"
order: 42
doc_category: presspermit
section: Developer
---

# ppc_assign_exceptions()

Table of Contents

- [Example Usage](#h-example-usage)

```
/**
 * Assign exceptions for a user or group

 * @param array agents : agents['item'|'children'][agent_id] = true|false
 * @param string agent_type : 'pp_group' or 'user'
 * @param array args :
 *  - operation          ('read'|'edit'|'associate'|'assign'...)
 *  - mod_type           ('additional'|'exclude'|'include')
 *  - for_item_source    ('post' or 'term' - data source to which the role applies)
 *  - for_item_type      (post_type or taxonomy to which the role applies)
 *  - for_item_status    (status which the role applies to i.e. 'post_status:member'; default '' means all stati)
 *  - via_item_source    ('post' or 'term' - data source which the role is tied to)
 *  - item_id            (post ID or term_taxonomy_id)
 *  - via_item_type      (post_type or taxonomy of item which the role is tied to; default '' means unspecified when via_item_source is 'post')
 */
function ppc_assign_exceptions( $agents, $agent_type = 'pp_group', $args = array() )
```

## Example Usage [#](#h-example-usage)

```
$group = pp_get_group_by_name( 'Cool Group', 'pp_group' );
$term = get_term_by( 'slug', 'cool', 'my_custom_taxonomy' );

// enable all members of this group to read pages which are in the specified term
$args = array(
  'operation' => 'read',
  'mod_type' => 'additional',
  'for_item_source' => 'post',
  'for_item_type' => 'page', 
  'via_item_source' => 'term',
  'item_id' => $term->term_taxonomy_id, 
  'via_item_type' => 'my_custom_taxonomy',
);

$agents = array();
$agents['item'][$group->ID] = true;

ppc_assign_exceptions ( $agents, 'pp_group', $args );
```
