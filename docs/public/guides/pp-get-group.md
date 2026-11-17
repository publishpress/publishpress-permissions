---
title: "pp_get_group"
order: 36
doc_category: presspermit
section: Developer
---

# pp_get_group

Table of Contents

- [Example usage](#0-toc-title)
- [Notes](#1-toc-title)

```
/**
 * Retrieve a Permission Group object by providing its ID

 * @param int group_id
 * @param string agent_type (pp_group, bp_group, etc.)
 * @return object Permission Group
 *  - ID
 *  - group_name
 *  - group_description
 *  - metagroup_type
 *  - metagroup_id
 */
function pp_get_group( $group_id, $agent_type = 'pp_group' )
```

## Example usage [#](#0-toc-title)

```
$group_id = 123;
$group = pp_get_group( $group_id );
```

## Notes [#](#1-toc-title)

metagroup_type and metagroup_id do not apply to custom-defined groups.
