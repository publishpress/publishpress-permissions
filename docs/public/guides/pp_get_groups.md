---
title: "pp_get_groups"
order: 41
doc_category: presspermit
---

# pp_get_groups

```
/**
 * Retrieve Permission Groups, optionally specifying limiting criteria

 * @param string agent_type (pp_group, bp_group, etc.)
 * @param array args :
 *   - agent_type
 *   - cols
 *   - omit_ids
 *   - ids
 *   - where
 *   - require_meta_types
 *   - skip_meta_types
 *   - search
 * @return array Permission Groups
 */
function pp_get_groups( $agent_type = 'pp_group', $args = array() )
```
