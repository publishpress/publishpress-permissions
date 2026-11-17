---
title: "Editing Permissions for Posts With Specific Terms"
order: 12
doc_category: presspermit
section: Edit Permissions
---

# Editing Permissions for Posts With Specific Terms

Table of Contents

- [Editing permissions for individual terms](#0-toc-title)
- [Editing permissions for multiple terms](#1-toc-title)
- [Block users from editing posts with any category](#2-toc-title)

The [PublishPress Permissions](https://publishpress.com/links/permissions) plugin makes it possible to control who can edit content with a specific taxonomy term attached. For example, you can create a tag called “Admin Editing Only” and make sure that only people in the “Administrator” role can edit posts with that tag.

## Editing permissions for individual terms [#](#0-toc-title)

These steps will show you how to control editing access for posts with a single term.

- Install the [PublishPress Permissions](https://publishpress.com/links/permissions) plugin.
- Go to Posts > Categories.
- Open the category that you want to control editing access for.
- Find the “Permissions: Posts in Category” box.
- Click the “View Posts” tab.
- This feature allows you to choose “Enabled” or “Blocked” for any user role or login state.  Enabled: Anyone in this user role can edit posts with this Category. Blocked: Anyone in this user role can NOT edit posts with this Category.

![Edit posts with term](https://docs-media.publishpress.com/publishpress-permissions/edit-posts-specific-term-ce54a8236c.webp)

## Editing permissions for multiple terms [#](#1-toc-title)

It’s also possible to control viewing permissions for multiple terms and the posts they’re associated with. With these steps below, you can control editing access for all the terms that you select.

- Go to “Permissions” in your WordPress admin menu.
- Choose the user role that you want to control editing access for.
- **Post Type**: Choose the post type that you want to control editing access for.
- **Operation**: Select “Edit”.
- **Adjustment**: Select “Enable” or “Block”.
- **Qualification**: Choose the taxonomy.
- **Select Categories**: Choose the terms you want to control editing access for.
- Click “Add Selected Permissions”.
- Click “Save Permissions”.

![Edit terms permissions](https://docs-media.publishpress.com/publishpress-permissions/edit-terms-permission-17cba61fc6.webp)

## Block users from editing posts with any category [#](#2-toc-title)

The steps above allow you to enable or block access to posts with specific terms. You can also use the “Limit to” feature to control access to ALL your terms. In the screenshot below we’ve made two different choices:

- **Adjustment**: Limit to
- **Select Categories**: None

Following these steps allows you to automatically block editing access to posts with ANY category. This means you won’t to remember to block access to new categories when they are added.

![Block Users from Editing Posts with Any Category](https://docs-media.publishpress.com/publishpress-permissions/limit-to-none-edit-term-3679a9f39e.webp)
