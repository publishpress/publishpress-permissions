---
title: "WPML and PublishPress Permissions (Pro)"
order: 57
doc_category: presspermit
section: Integrations
---

# WPML and PublishPress Permissions (Pro)

This feature requires the Pro version of the PublishPress Permissions plugin.

The Pro version of [PublishPress Permissions](https://publishpress.com/links/permissions) has support for [the WPML plugin](https://wpml.org/).

By default, [PublishPress Permissions Pro](https://publishpress.com/links/permissions) will automatically mirror your post / category permissions to the translated content. So, if block users in the “Author” from editing a post, they will also be blocked from editing any translations of that post. Normally you will set these permissions under each post, using the metaboxes you can see in the screenshot below:

When you first install [PublishPress Permissions](https://publishpress.com/links/permissions), these metaboxes **will not be enabled for translations** because the default is for translations to use the same permissions as the original post.

![Edit Post Permisisons](https://docs-media.publishpress.com/publishpress-permissions/edit-post-permissions-metabox-cb69aed4f7.webp)

To allow different user roles to edit different translations, follow these steps:

- Go to “Permissions” then “Settings” in the WordPress admin menu.
- Click the “Editing” tab and find the “WPML Integration” area
- Uncheck these two boxes shown in this screenshot below.

![](https://docs-media.publishpress.com/publishpress-permissions/mirror-post-exceptions-b1755d3198.webp)

After you uncheck these “WPML Integration” boxes, each translation will have separate permissions boxes. You can see how this works in the two screenshots below:

- On the left side is the Spanish version of a post. Editing access to the Spanish version is available to Administrators, Authors, Editors, and Subscribers.
- On the right side is the English version of the same post. Editing access to the Spanish version is available to Administrators and Editors.

![Spanish version of WPML post](https://docs-media.publishpress.com/publishpress-permissions/spanish-editing-1f48a72291.webp)

![English version of WPML post](https://docs-media.publishpress.com/publishpress-permissions/english-editing-8ab9e2045b.webp)
