---
title: "pp_create_group"
order: 32
doc_category: presspermit
section: Developer
---

# pp_create_group

Table of Contents

- [Example usage](#0-toc-title)
- [Notes](#1-toc-title)

```
/**
 * Create a new Permission Group

 * @param array group_vars_arr :
 *   - group_name
 *   - group_description (optional)
 *   - metagroup_type (optional, for internal use)
 * @return int ID of new group
 */
function pp_create_group ($group_vars_arr)
```

## Example usage [#](#0-toc-title)

```
$group_id = pp_create_group( 'group_name' => 'Cool Group', 'group_description' => 'Users who are cool' );
```

## Notes [#](#1-toc-title)

The metagroup_type parameter is used internally (wp_role for auto-created WordPress role groups and rvy_notice for [PublishPress Revisions](https://publishpress.com/links/revisions) Monitor groups). External usage of this parameter is not expected nor currently supported.
