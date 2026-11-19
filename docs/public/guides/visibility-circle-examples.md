---
title: "Visibility Circle Examples (Pro)"
order: 56
doc_category: presspermit
section: Circles
---

# Visibility Circle Examples (Pro)

Table of Contents

- [Example: Restrict Subscribers to Reading Posts from Other Subscribers](#h-example-restricting-authors-to-reading-posts-from-other-authors)
- [Create More Circles Similar to the Subscriber Role](#h-create-more-circles-similar-to-the-author-role)

This feature requires the Pro version of the PublishPress Permissions plugin.

In this guide, we’ll show you some example uses for the [Visibility Circles](https://publishpress.com/knowledge-base/circles-visibility/) feature in [PublishPress Permissions Pro](https://publishpress.com/links/permissions).

## Example: Restrict Subscribers to Reading Posts from Other Subscribers [#](#h-example-restricting-authors-to-reading-posts-from-other-authors)

In this example, all the users in the “Subscriber” role will only be able to view posts that were authored by other members of the same role.

- Start with the [PublishPress Permissions Pro](https://publishpress.com/links/permissions) plugin installed on your site.
- Go to “Permissions” then “Settings” in your WordPress admin menu. Make sure the “Access Circles” box is checked.

- Go to “Permissions”.
- Edit the “Subscriber” group.
- Check the box, “This group is a Visibility Circle”.
- Click “Update Group”.

Next, you can choose to create Visibility Circles only for some post types.

- Choose “Post”.
- Click “Update Group”.

![Visibility circles Subscriber](https://docs-media.publishpress.com/publishpress-permissions/visibility-circles-subscriber-7e0b74e322.webp)

## Create More Circles Similar to the Subscriber Role [#](#h-create-more-circles-similar-to-the-author-role)

In the example above, we only had one Subscriber role. In some situations, you may want many roles that act in a similar way. Imagine a university website that has 30+ departments. Each department wants to read only it’s own pages.

The best way to do this is to duplicate the Subscriber role and use these roles for Visibility Circles. This example builds on the previous example. This time we are going to create 30+ Visibility Circles.

- Install the [PublishPress Capabilities](https://publishpress.com/links/capabilities) plugin.
- Go to “Capabilities” then “Roles” in the WordPress admin menu.
- Click “Copy” next to the Subscriber role, as in this screenshot below:

![Copy the subscriber role](https://docs-media.publishpress.com/publishpress-permissions/subscriber-role-copy-7c4759d64a.webp)

- Enter the name of a department. For example, enter “History Department”.
- Click “Save Changes”.
- Repeat for other departments.

![Create new roles](https://docs-media.publishpress.com/publishpress-permissions/history-department-role-1-502f07845e.webp)

You can now go back to the tutorial above and turn these new roles into Visibility Circles.
