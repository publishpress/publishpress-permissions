---
title: "Editorial Circle Examples (Pro)"
order: 14
doc_category: presspermit
---

# Editorial Circle Examples (Pro)

Table of Contents

- [Example: Only Edit Posts from the Same Role](#h-restrict-editors-to-editing-posts-from-other-editors)
- [Example: Create Circles for Multiple Roles](#h-create-more-circles-similar-to-the-editor-role)

This feature requires the Pro version of the PublishPress Permissions plugin.

In this guide, we’ll show you some example uses for the [Editorial Circles](https://publishpress.com/knowledge-base/editorial-circles/) feature in [PublishPress Permissions Pro](https://publishpress.com/links/permissions).

## Example: Only Edit Posts from the Same Role [#](#h-restrict-editors-to-editing-posts-from-other-editors)

In this example, all the users in the Editor role will only be able to edit posts that were authored by other members of the same role.

- Start with the [PublishPress Permissions Pro](https://publishpress.com/links/permissions) plugin installed on your site.
- Go to “Permissions”, then “Settings” in your WordPress admin menu.
- Click the “Features” tab and make sure the “Access Circles” box is checked.
- Go to “Permissions” then “Groups” in your WordPress admin menu.
- Edit the “Editor” group.
- Check the box, “This group is a Editorial Circle”.
- Click “Update Group”.

![This is an Editorial Circle](https://docs-media.publishpress.com/publishpress-permissions/editorial-circle-enable-95e5d36889.webp)

Next, you can choose to create an Editorial Circle only for some post types. In the screenshot below, this feature is enabled for Posts and Pages.

- Click “Update Group”.
- Now that this is complete, all the users in the Editor role will only be able to edit posts that were authored by other members of the same role.

![Editorial Circle for Posts and Pages](https://docs-media.publishpress.com/publishpress-permissions/editorial-circles-post-type-6073ae1369.webp)

## Example: Create Circles for Multiple Roles [#](#h-create-more-circles-similar-to-the-editor-role)

In the example above, we only had one Editor role.

In some situations, you may want many roles that act in a similar way. For example, imagine a university website has 30+ departments. Each department wants to create and edit it’s own pages.

The best way to do this is to duplicate the Editor role and use these roles for Editorial Circles. This example builds on the previous example. This time we are going to create 30+ Editorial Circles.

- Install the [PublishPress Capabilities](https://publishpress.com/links/capabilities) plugin.
- Go to “Capabilities” then “Roles” in the WordPress admin menu.
- Click “Copy” next to the Editor role, as in this screenshot below:

![Copy the Editor role](https://docs-media.publishpress.com/publishpress-permissions/click-copy-roles-79edd8c69a.webp)

- Enter the name of a department. For example, enter “History Department”.
- Click “Save Changes”.
- Repeat for other departments.

![Create new roles](https://docs-media.publishpress.com/publishpress-permissions/history-department-role-1-502f07845e.webp)

After creating a role for each department, you can now go back to the example above and turn these new roles into Editorial Circles.
