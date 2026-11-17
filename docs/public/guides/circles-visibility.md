---
title: "Getting Started With Visibility Circles (Pro)"
order: 10
doc_category: presspermit
---

# Getting Started With Visibility Circles (Pro)

Table of Contents

- [How to Enable Visibility Circles](#0-toc-title)
- [Visibility Circles for Default Roles](#1-toc-title)

This feature requires the Pro version of the PublishPress Permissions plugin.

Visibility Circles are a feature in [PublishPress Permissions Pro](https://publishpress.com/links/permissions) that restrict users to viewing posts that were authored by other users in the same group.

## How to Enable Visibility Circles [#](#0-toc-title)

To enable this feature, follow this steps:

- Go to “Permissions, then “Settings”, then “Features”.
- Check the box called “Access Circles”.
- Click “Save Changes”.

Follow these steps to enable a Visibility Circle:

- Go to “Permissions” in the WordPress admin menu.
- Choose a group to edit.
- Check the box for “This group is a Visibility Circle”.

![Enable Visibility Circle](https://docs-media.publishpress.com/publishpress-permissions/enable-visibility-circle-1-1bda00c3f5.webp)

- You will now be able to apply the restrictions based on post type. In the screenshot below, users in this role will only be able to see Posts and Pages written by other users in the same role.

![Visibility Circle for post type](https://docs-media.publishpress.com/publishpress-permissions/visibility-circle-post-types-215bd89b27.webp)

## Visibility Circles for Default Roles [#](#1-toc-title)

There’s one key thing to understand with Visibility Circles: the default behavior in WordPress is for all posts to be visible to all users. So if you want to create a Visibility Circle using default user roles such as Subscriber, Editor, or Author, you will need to modify those those roles.

Here’s an example of how to modify a default role:

- Go to “Permissions” then “Permissions” in your WordPress admin menu.
- Click on the “Editor” role.
- You’ll see a message, “Visibility Circle restrictions do not apply to this role, since it has the pp_exempt_read_circle capability.”

![](https://docs-media.publishpress.com/publishpress-permissions/vis-circles-dont-apply-a437a99823.webp)

Here’s how to change that setting for a user role:

- Install our [PublishPress Capabilities](https://publishpress.com/links/capabilities) plugin
- Go to “Capabilities”, then “Capabilities”
- Select the role you want to modify.
- Click on the “[PublishPress Permissions](https://publishpress.com/links/permissions)” tab
- Uncheck the “pp_exempt_read_circle” capability you can see in the screenshot below.
- Click the “Save Changes” button.

![pp_exempt_read_circle capability](https://docs-media.publishpress.com/publishpress-permissions/pp-exempt-ee2a0f6d46.webp)
