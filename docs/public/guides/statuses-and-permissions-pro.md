---
title: "PublishPress Statuses and PublishPress Permissions (Pro)"
order: 51
doc_category: presspermit
section: Integrations
---

# PublishPress Statuses and PublishPress Permissions (Pro)

This feature requires the Pro version of the PublishPress Permissions plugin.

This integration is available with the [PublishPress Statuses](https://publishpress.com/links/statuses) plugin and the Pro version of [PublishPress Permissions](https://publishpress.com/links/permissions).

Each custom status is created in [PublishPress Statuses](https://publishpress.com/links/statuses). Each status can have editing access that is specific to each post type and status. In the screenshot below, you can see an example with the “Pitch” status.

This feature is available by going to Statuses > Statuses > Pitch > Post Access in the WordPress admin menu.

![PublishPress Statuses and Permissions](https://docs-media.publishpress.com/publishpress-permissions/image-55704938b2.webp)

There are five capabilities options:

- Set
- Edit
- Edit Others
- Delete
- Delete Others

These capabilities can be given to different user roles and on different post types.

This approach follows the WordPress core. For example, these capabilities are available in the WordPress core:

```
edit_published_postsedit_published_pagesedit_private_postsedit_private_pages
```

The feature is introducing the same thing for custom statuses such as Pitch, Approved, In Progress, etc:

```
edit_pitch_posts
edit_pitch_pages
edit_in_progress_posts
edit_in_progress_pages
```
