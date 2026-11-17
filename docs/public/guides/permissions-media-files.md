---
title: "Control Permissions for Media Files"
order: 29
doc_category: presspermit
section: Media Library
---

# Control Permissions for Media Files

Table of Contents

- [How to Enable Media Permissions](#0-toc-title)
- [An Example with the Media settings](#h-an-example-with-the-media-settings)

The [PublishPress Permissions](https://publishpress.com/links/permissions) plugin allows you to control permissions for media files on your site. This feature allows you to control access to files the Media Library for users who are in the WordPress admin area. This feature is available in the Free and Pro version of [PublishPress Permissions](https://publishpress.com/links/permissions).

## How to Enable Media Permissions [#](#0-toc-title)

- Go to Permissions > Settings.
- Click the “Core” tab and make sure the “Media” box is checked:
- Click the “Media Library” tab:

![](https://docs-media.publishpress.com/publishpress-permissions/media-library-tab-0eaad0463a.webp)

Here you’re going to see options you can use to control access to files inside the Media Library:

- **Other users’ unattached uploads listed by default**: If this boxed is checked, users can *view* other people’s media files
- **List other users’ uploads if attached to a readable post**: If this boxed is checked, users can *view* other people’s media files if they are attached a post they can *read*.
- **List other users’ uploads if attached to an editable post**: If this boxed is checked, users can *view* other people’s media files if they are attached a post they can *edit*.
- **Allow editing other user’ uploads if attached to an editable post**: If this boxed is checked, users can *edit* other people’s media files if they are attached a post they can *edit*.

There are also two extra settings available:

- **Prevent editing uploads if attached to a non-editable post**: If this boxed is checked, users can always edit media files if they are attached to a post that they can not edit.
- **Users can always edit their own attachments**: If this boxed is checked, users can always edit attachments they have uploaded, even if they are later attached to a post which the user cannot edit.

## An Example with the Media settings [#](#h-an-example-with-the-media-settings)

I’ll walk you through an example of using the Media options in PublishPress Permissions.

- Go to Permissions > Settings.
- Click the “Editing” tab.
- Make sure “**Other users’ unattached uploads listed by default**” is unchecked, If you uncheck this, you will automatically hide other people’s files for most users:

I logged out and logged in again as an Author user. This next image shows what I saw. I no longer had access to files from any other users.

![Media Library in WordPress](https://docs-media.publishpress.com/publishpress-permissions/empty-media-library-22a5edf37a.webp)

I will note two key things:

- This example restriction applies to all users except Administrator. This image below shows the same site. I browsed to the Media Library as an Administrator. I have full access to all the images in the Media Library.
- There are potential complexities with editing media files. For example, you can deny a user access to a file that someone else has uploaded. But what happens if you also give that user the ability to edit a post where that image is used? The 5 options shown earlier in this guide will give you ways to deal with those scenarios.

![Media Library with sample images](https://docs-media.publishpress.com/publishpress-permissions/full-media-library-8beb439a05.webp)
