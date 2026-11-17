---
title: "Hide the PublishPress Permissions Metabox"
order: 19
doc_category: presspermit
section: Troubleshooting Permissions
---

# Hide the PublishPress Permissions Metabox

Table of Contents

- [How to Remove the Metabox](#h-how-to-remove-the-metabox)
- [How to Hide the Metabox and Retain the Permissions](#1-toc-title)

[PublishPress Permissions](https://publishpress.com/links/permissions) allows you to control editing and reading access in WordPress. This plugins allows you to give very specific control, even to specific post and pages. You can do this using the [PublishPress Permissions](https://publishpress.com/links/permissions) metabox shown below:

![](https://docs-media.publishpress.com/publishpress-permissions/permissions-metabox-1d9b9c7517.webp)

However, PublishPress Permissions have a variety of features beyond editing and reading access. Some users want to make use of the “Teaser” feature, the “Groups” feature, or use other options in the plugin. These users sometimes want to hide the PublishPress Permissions metabox.

## How to Remove the Metabox [#](#h-how-to-remove-the-metabox)

You can completely hide the PublishPress Permissions metabox by inserting some code into your site’s wp-config.php file. This approach will also disable any associated restrictions, so if you’ve used these metaboxes to restrict access to content, those restrictions will be removed.

These are a few constants that you can use to hide PublishPress Permissions metabox:

- ***define(‘PP_NO_POST_EXCEPTIONS’, true );***  
This constant will hide the PublishPress Permissions metabox on posts.
- ***define(‘PP_NO_PAGE_EXCEPTIONS’, true );***This constant will hide the PublishPress Permissions metabox on pages.
- ***define(‘PP_NO_ATTACHMENT_EXCEPTIONS’, true );***  
This constant will hide PublishPress Permissions metabox for media files.

You can apply these constants right before ***/* That’s all, stop editing! Happy publishing. */***

![](https://docs-media.publishpress.com/publishpress-permissions/exception-wp-config-08272825bd.webp)

## How to Hide the Metabox and Retain the Permissions [#](#1-toc-title)

In the guide above, we removed the metaboxes and all associated permissions. If you want to hide the metabox, but also keep any restrictions previously created with the metaboxes, use this code below.

In this code below, we are hiding the metaboxes on the “page” post type:

```
/* 
 * PublishPress Permissions: Hide Permissions metabox on Edit Attachment Screen
 *
 * https://publishpress.com
 */
add_filter(
    'presspermit_disable_exception_ui',
    function($disable, $item_source, $item_id, $item_type) {
        if ('page' == $item_type) {
            return true;
        }
    },
    10, 4
);
```
