---
title: "Capabilities in PublishPress Permissions"
order: 8
doc_category: presspermit
section: Capabilities in Permissions
---

# Capabilities in PublishPress Permissions

Table of Contents

- [Main Plugin Capabilities](#0-toc-title)
- [User Management](#1-toc-title)
- [Groups](#2-toc-title)
- [Group Members](#3-toc-title)
- [Content Permissions](#4-toc-title)
- [Term Permissions](#5-toc-title)
- [Media Library](#6-toc-title)
- [Circles](#7-toc-title)
- [Statuses](#8-toc-title)

You can control who has access to features in the [PublishPress Permissions](https://publishpress.com/links/permissions) plugin. This is possible by installing the [PublishPress Capabilities](https://publishpress.com/links/capabilities).

Go to the “Capabilities” screen and click the “[PublishPress Permissions](https://publishpress.com/links/permissions) tab, as shown in the screenshot below:

![PublishPress Capabilities and the Permissions plugin](https://docs-media.publishpress.com/publishpress-permissions/permissions-capabilities-99013398c1.webp)

### Main Plugin Capabilities [#](#0-toc-title)

- **pp manage settings**: Controls access to the PublishPress Permissions screens inside the admin area.
- **pp administer content**: Can manage other user’s Permissions. Also grants capabilities for all post types and statuses.
- **pp unfiltered**: PublishPress Permissions will not apply any Extra Roles or Permissions to limit or expand viewing or editing access.
- **pp force quick edit**: Makes the Quick Edit and Bulk Edit option available to non-Administrators, even though that could allow them to bypass restrictions set by the Permissions plugin.

### User Management [#](#1-toc-title)

- **pp assign roles**: Can assign supplemental Roles or Exceptions to individual users. Other user management capabilities may also be required.
- **pp assign bulk roles**: Can use bulk tools to assign supplemental Roles or Exceptions to individual users. Other user management capabilities may also be required.

### Groups [#](#2-toc-title)

- **pp edit groups**: Can edit all the name and description of all Permission Groups.
- **pp create groups**: Can create new Permission Groups and set the name and description.
- **pp create network groups**: Can create groups that work across all the sites in the multisite network.
- **pp delete groups**: Can delete Permission Groups.

### Group Members [#](#3-toc-title)

- **pp manage members**: If group editing is allowed, can also modify group membership.
- **pp manage network members**: If group editing is allowed, can also modify multisite network group membership.

### Content Permissions [#](#4-toc-title)

- **pp associate any page**: Can bypass restrictive “Set as Parent” Permissions (for all hierarchical post types).
- **pp set edit exceptions**: Can set Editing Permissions.
- **pp set read exceptions**: Can set Viewing Permissions.
- **pp set revise exceptions**: Can set Revision Permissions.

### Term Permissions [#](#5-toc-title)

- **pp set term assign exceptions**: Can set Term Assignment Permissions.
- **pp set term associate exceptions**: Can set “Set as Parent” Permissions.
- **pp set term manage exceptions**: Can set Term Management Permissions.
- **pp set associate exceptions**: Can set “Set as Parent” Permissions.

### Media Library [#](#6-toc-title)

- **edit own attachments**: Edit own file uploads, even if they become attached to an uneditable post.
- **list others unattached files**: See other user’s unattached file uploads in Media Library.
- **pp list all files**: Do not alter the Media Library listing provided by WordPress.

### Circles [#](#7-toc-title)

- **pp exempt edit circle**: Users will be exempt from any Editorial Circle restrictions.
- **pp exempt read circle**: Users will be exempt from any Visibility Circle restrictions.

### Statuses [#](#8-toc-title)

- **pp define privacy**: Create or edit custom Privacy statuses.
- **set posts status**: Extra Roles created as type-specific copy of this role will enable assignment of specified custom statuses.
- **pp moderate any**: Editors can edit posts which are set to a moderation status (Pitch, Approved, etc.) without a status-specific editing capability.
