---
title: "pp_get_group_by_name"
order: 34
doc_category: presspermit
section: Developer
---

# pp_get_group_by_name

Table of Contents

- [Example usage](#0-toc-title)
- [Notes](#1-toc-title)

```
/**
 * Retrieve a Permission Group object by providing its name

 * @param int group_name
 * @param string agent_type (pp_group, bp_group, etc.)
 * @return object Permission Group
 *  - ID
 *  - group_name
 *  - group_description
 *  - metagroup_type
 *  - metagroup_id
 */
function pp_get_group_by_name( $group_name, $agent_type = 'pp_group' )
```

## Example usage [#](#0-toc-title)

```
$group = pp_get_group_by_name( 'Cool Group' );
```

## Notes [#](#1-toc-title)

metagroup_type and metagroup_id do not apply to custom-defined groups.
