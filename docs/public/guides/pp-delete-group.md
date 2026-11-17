---
title: "pp_delete_group"
order: 33
doc_category: presspermit
---

# pp_delete_group

Table of Contents

- [Example usage](#0-toc-title)

```
/**
* Delete a Permission Group

* @param array group_id
* @param array agent_type (pp_group, bp_group, etc.)
*/
function pp_delete_group( $group_id, $agent_type )
```

## Example usage [#](#0-toc-title)

```
$my_group_id = 123;
pp_delete_group( $my_group_id, 'pp_group' );
```
