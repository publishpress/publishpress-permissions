---
title: "ppc_get_exceptions()"
order: 44
doc_category: presspermit
---

# ppc_get_exceptions()

Table of Contents

- [Example usage](#0-toc-title)

```
/**
 * Retrieve exceptions for a user or group

 * @param array args :
 *  - agent_type         ('user'|'pp_group'|'pp_net_group'|'bp_group')
 *  - agent_id           (group or user ID)
 *  - operations         ('read'|'edit'|'associate'|'assign'...)
 *  - for_item_source    ('post' or 'term' - data source to which the roles may apply)
 *  - post_types         (post_types to which the roles may apply)
 *  - taxonomies         (taxonomies to which the roles may apply)
 *  - for_item_status    (status to which the roles may apply i.e. 'post_status:private'; default '' means all stati)
 *  - via_item_source    ('post' or 'term' - data source which the role is tied to)
 *  - item_id            (post ID or term_taxonomy_id)
 *  - assign_for         (default 'item'|'children'|'' means both)
 *  - inherited_from     (base exception assignment ID to retrieve propagated assignments for; default '' means N/A)
 */
function ppc_get_exceptions( $args = array() )
```

## Example usage [#](#0-toc-title)

```
$args = array();

// specify your user or group
$args['agent_id'] = 123; 
$args['agent_type'] = 'user';

// can be an array of operations
$args['operations'] = 'edit'; 

// specify your post or term
$args['for_item_source'] = 'post'; // exception affects posts
$args['via_item_source'] = 'term'; // exception is tied to a term
$args['item_id'] = 456; // term_taxonomy_id or post ID
$args['assign_for'] = 'item';

$arr = ppc_get_exceptions( $args );
```
