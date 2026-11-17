---
title: "Getting Started With Editorial Circles (Pro)"
order: 15
doc_category: presspermit
section: Circles
---

# Getting Started With Editorial Circles (Pro)

Table of Contents

- [How to Enable Editorial Circles](#h-restrict-editors-to-editing-posts-from-other-editors)
- [Understanding Editorial Circles](#1-toc-title)

This feature requires the Pro version of the PublishPress Permissions plugin.

Editorial Circles are a feature in [PublishPress Permissions Pro](https://publishpress.com/links/permissions) that restrict users to editing posts that were authored by other users in the same group.

For example, with the Editorial Circles feature you can restrict users in the “Contributor” role so that they can only edit posts written by Contributors.

The most common way to use this feature is to restrict users in the Editor role so that they can only edit posts written by other Editors. This is because Editors are the only default WordPress role that can edit content (except for Administrators). However, there are some more advanced use-cases.

## How to Enable Editorial Circles [#](#h-restrict-editors-to-editing-posts-from-other-editors)

To enable this feature, follow this steps:

- Go to “Permissions, then “Settings”, then “Features”.
- Check the box called “Access Circles”.
- Click “Save Changes”.

Follow these steps to enable a Editorial Circle:

- Go to “Permissions” in your WordPress admin menu.
- Choose a group to edit.
- Check the box for “This group is a Editorial Circle”.
- Click “Update Group”.

![This is an Editorial Circle](https://docs-media.publishpress.com/publishpress-permissions/editorial-circle-enable-95e5d36889.webp)

- You will now be able to apply the restrictions based on post type. In the screenshot below, users in this role will only be able to see Posts and Pages written by other users in the same role.

![Editorial Circles for post types](https://docs-media.publishpress.com/publishpress-permissions/editorial-circles-post-type-6073ae1369.webp)

## Understanding Editorial Circles [#](#1-toc-title)

There’s one key thing to understand with Editorial Circles: the default behavior in WordPress is that most users can NOT edit posts from other users. So if you want to create an Editorial Circle with default user role such as Subscriber, Editor, or Author, you will need to modify those those roles. Here’s an example:

- Install our [PublishPress Capabilities](https://publishpress.com/links/capabilities) plugin.
- Go to the “Capabilities” screen.
- Select the role you want to modify.
- Under the “Edit others” column, check the post types you want to restrict.
- Click the “Save Changes” button.

![Edit others capability](https://docs-media.publishpress.com/publishpress-permissions/edit-others-769e0cf912.webp)
