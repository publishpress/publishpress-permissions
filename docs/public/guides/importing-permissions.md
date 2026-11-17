---
title: "Importing to PublishPress Permissions"
order: 21
doc_category: presspermit
section: Migrate
---

# Importing to PublishPress Permissions

PublishPress does not currently have a CSV import feature. However, some import options are available if you use phpMyAdmin or a similar database tool and are able to construct and execute queries.

Please feel free to [contact us](https://publishpress.com/contact/) if you need help with your import.

First, use the normal [PublishPress Permissions](https://publishpress.com/links/permissions) configuration screens to create some sample groups, group members and roles / exceptions.

Once your sample data is stored, go into phpMyAdmin and browse each of the following tables in a separate tab. The actual table name will have your database prefix:

- pp_groups
- pp_group_members
- ppc_roles
- ppc_exceptions
- ppc_exception_items

Take a look at the columns and values in each of those to get a feel for how your data is stored.

When you are ready to insert new permission groups, open up the SQL tab on the pp_groups table. You will be constructing queries like this. Don’t forget to apply your database prefix:

```
INSERT INTO 'pp_groups' (group_name, group_description) VALUES ('New Group Name', 'New Description');
INSERT INTO 'pp_groups' (group_name, group_description) VALUES ('Another Group Name', 'Another Description');
INSERT INTO 'pp_groups' (group_name, group_description) VALUES ('Some Group Name', 'Some Description');
```

These can be pasted into the phpMyAdmin window and executed all at once. You can imagine how you could use a spreadsheet to expedite the construction of those strings.

After creating the new groups, you will need to reload the pp_groups browse view and note the group_id values that were auto-created. These will be used in your group member insertions:

```
INSERT INTO 'pp_group_members' (group_id, user_id) VALUES (yourNewGroupID, yourActualUserID);
INSERT INTO 'pp_group_members' (group_id, user_id) VALUES (yourNewGroupID, anotherActualUserID);
```

You can follow a similar approach with the ppc_roles, ppc_exceptions and ppc_exception_items tables. For role_name value, note the pattern shown in your existing data. For example, a Page Author role is ‘author:post:page’

```
INSERT INTO 'ppc_roles' (agent_id, agent_type, role_name) VALUES (yourGroupID, 'pp_group', 'author:post:page');
```

– or –

```
INSERT INTO 'ppc_roles' (agent_id, agent_type, role_name) VALUES (yourUserID, 'user', 'author:post:page');
```

If you are inserting exceptions, note that ppc_exception_items rows are each associated with a single ppc_exception_item row (corresponding exception_id value). There is:

- ppc_exceptions row per distinct combination of user / group, post type, post operation and modification type
- related ppc_exception_items row for each post or term
