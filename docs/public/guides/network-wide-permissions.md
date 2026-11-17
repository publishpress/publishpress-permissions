---
title: "Network-Wide Settings in PublishPress Permissions"
order: 24
doc_category: presspermit
---

# Network-Wide Settings in PublishPress Permissions

If you’re using [PublishPress Permissions](https://publishpress.com/links/permissions) on a multisite network, it is possible to choose default settings for your entire network.

To modify one or more default settings network-wide, **copy** the following code into your theme’s **functions.php** file (or some other file which is always executed and not auto-updated) and modify as desired:

```
    // Use this filter if you want to change the default, but still allow manual setting
    add_filter( 'presspermit_default_options', 'my_presspermit_default_options', 99 );

    public function my_presspermit_default_options( $def_options ) {
        // Array key corresponds to name attributes of checkboxes, dropdowns and input boxes. Modify for desired default settings.

        $def_options['new_user_groups_ui'] = 0;

        return $def_options;
    }
```

To **force**the value of one or more settings network-wide, **copy** the following code into your theme’s **functions.php** file (or some other file which is always executed and not auto-updated) and modify as desired:

```
// Use this filter if you want to force an option, blocking/disregarding manual setting
add_filter( 'presspermit_options', 'my_presspermit_options', 99 );

// Use this filter if you also want to hide an option from the PP settings screen (works for most options)
add_filter( 'presspermit_hide_options', 'my_pp_options', 99 );

public function my_presspermit_options( $options ) {
    // Array key corresponds to pp_prefixed name attributes of checkboxes, dropdowns and input boxes. 
    // Modify for desired settings.

    // note: advanced options can be forced here even if advanced settings are disabled
    $options['presspermit_new_user_groups_ui'] = 1;
    $options['presspermit_display_hints'] = 0;
```
