---
title: "How to Migrate from PressPermit Core to PublishPress Permissions"
order: 22
doc_category: presspermit
section: Migrate
---

# How to Migrate from PressPermit Core to PublishPress Permissions

Table of Contents

- [How to Update to Permissions Pro](#0-toc-title)
- [How to Rollback Your PressPermit Update](#1-toc-title)

In June 2019, we moved the PressPermit plugin over to PublishPress.com. This is part of [an acquisition involving several plugins](https://publishpress.com/blog/presspermit/).

During the move, we made two other changes:

1. We simplified how PressPermit works.
2. The changed the name to “[PublishPress Permissions](https://publishpress.com/links/permissions)“.

Before June 2019, there was a single “Press Permit Core” plugin and then over a dozen different extensions. Now all the extensions have been combined into one package called “**[PublishPress Permissions Pro](https://publishpress.com/links/permissions)**“.

## How to Update to Permissions Pro [#](#0-toc-title)

If you were a PressPermit user before June 2019, here’s how to update to the new Permissions Pro.

- Backup your site. The codebase is 99% the same as the older version, but it’s always a good habit to backup before updating.
- Download Permissions Pro from your account here at PublishPress.com. If you don’t have an account yet, [please contact us](https://publishpress.com/contact/).
- Install the new Permissions Pro plugin, but don’t activate it immediately.
- Deactivate the Press Permit Core plugin.
- Activate the Permissions Pro plugin.
- Deactivate all your PressPermit extensions.
- Check your site to make sure everything is working correctly.
- Delete the old Press Permit Core plugin, plus the extensions.

If you need help during any of these steps, [please don’t hesitate to contact us](help@publishpress.com).

## How to Rollback Your PressPermit Update [#](#1-toc-title)

If anything goes wrong with your update, here’s how you can temporarily roll back to PressPermit Core:

- Download the [Press Permit Core 2.6.3 zip file](https://downloads.wordpress.org/plugin/press-permit-core.2.6.3.zip) from [wordpress.org](http://wordpress.org/).
- Deactivate and delete PressPermit 2.7.x.
- Install your downloaded plugin zip via Plugins > Add New > Upload.
- Activate Press Permit Core.
- Finally, if you deactivated any of the PressPermit extensions, temporarily re-activate those.
