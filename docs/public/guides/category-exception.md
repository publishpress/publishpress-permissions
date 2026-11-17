---
title: "Viewing Permissions for Posts With Specific Terms"
order: 9
doc_category: presspermit
section: View Permissions
---

# Viewing Permissions for Posts With Specific Terms

Table of Contents

- [Control viewing for individual terms](#0-toc-title)
- [Control viewing for multiple terms](#1-toc-title)
- [Block users from viewing posts with any Category](#2-toc-title)

The [PublishPress Permissions](https://publishpress.com/links/permissions) plugin makes it possible to control who can view content with a specific taxonomy term attached. For example, you can create a tag called “Admin Access Only” and make sure that only people in the “Administrator” role can view posts with that tag.

## Control viewing for individual terms [#](#0-toc-title)

These steps will show you how to control viewing access for posts with a specific term.

- Go to Posts > Categories.
- Open the category that you want to control viewing access for.
- Find the “Permissions: Posts in Category” box.
- Click the “View Posts” tab.
- This feature allows you to choose “Enabled” or “Blocked” for any user role or login state.  Enabled: Anyone in this user role can view posts with this Category. Blocked: Anyone in this user role can NOT view posts with this Category.

![](https://docs-media.publishpress.com/publishpress-permissions/view-posts-category-1-2dd78e94ba.webp)

*Read Post Category*

## Control viewing for multiple terms [#](#1-toc-title)

It’s also possible to control viewing permissions for multiple terms and the posts they’re associated with. With these steps below, you can control viewing access for all the terms that you select.

- Go to “Permissions” in your WordPress admin menu.
- Choose the user role that you want to control viewing access for.
- **Post Type**: Choose the post type that you want to control viewing access for.
- **Operation**: Select “View”.
- **Adjustment**: Select “Enable” or “Block”.
- **Qualification**: Choose the taxonomy.
- **Select Categories**: Choose the terms you want to control viewing access for.
- Click “Add Selected Permissions”.
- Click “Save Permissions”.

![](https://docs-media.publishpress.com/publishpress-permissions/select-categories-permissions-6a602f28fe.webp)

## Block users from viewing posts with any Category [#](#2-toc-title)

The steps above allow you to enable or block access to posts with specific terms. You can also use the “Limit to” feature to control access to ALL your terms. In the screenshot below we’ve made two different choices:

- **Adjustment**: Limit to
- **Select Categories**: None

Following these steps allows you to automatically block viewing access to posts with ANY category. This means you won’t to remember to block access to new categories when they are added.

![Permissions Limit to None](https://docs-media.publishpress.com/publishpress-permissions/limit-to-none-c04d38dcfc.webp)
