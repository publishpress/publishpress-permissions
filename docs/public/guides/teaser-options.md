---
title: "Options for Teasers for Restricted Content"
order: 53
doc_category: presspermit
---

# Options for Teasers for Restricted Content

Table of Contents

- [The Teaser Options](#h-the-teaser-type-tab)
- [The Options tab](#h-the-options-tab)

In this documentation, we’ll show you all the configuration options for your Teasers in the [PublishPress Permissions](https://publishpress.com/links/permissions) plugin. This feature controls what users will see when trying to view content they are blocked from accessing.

You will find these settings by going to Permissions > Teaser in your WordPress admin menu.

## The Teaser Options [#](#h-the-teaser-type-tab)

There are 6 choices for teasers. These will show when users try to access restricted content that they are not allowed to access.

- **No Teaser. Show “Page not found message”**. This is the default WordPress behavior. Your site will pretend that this content does not exist. All visitors will see is a “Nothing Found” message. They won’t even see the URL of the post in their browser bar.
- **Teaser text**: This will show text that you choose. The text can be placed before or after the post title and content.
- **Use content before Read More link as teaser text (available in Permissions Pro)**: This shows the content before the “Read More” link in your post, but hides the content after that link.
- **Use Excerpt as teaser text (available in Permissions Pro)**: This shows the content in the Excerpt for the post, but will hide the post content.
- **Use first X characters of content as teaser text (available in Permissions Pro):** This shows the first characters of the content, but hides the content after that link.
- **Redirect to another page (available in Permissions Pro):** This will automatically redirect visitors to a URL of your choice.

![Teaser options](https://docs-media.publishpress.com/publishpress-permissions/teaser-options-2-ecc22fdb8c.webp)

*Teaser Choices*

There are several setting available for each Teaser type:

- **Teaser Application**: Should the Teaser apply only when accessing the post directly, or also in the blog / list view?
- **User Application**: Does this apply to Not Logged In Users, Logged In Users, or both?
- **Navigation Menus**: Should the posts with a Teaser be linked to from site menus?
- **Private Posts**: Should the Teaser apply to posts that are restricted using the “Private” status?
- **Hide Featured Image**: Should the Featured Image be hidden if users don’t have access to the post?

![Teaser settings](https://docs-media.publishpress.com/publishpress-permissions/teaser-settings-posts-1-2de447f4ff.webp)

In the Pro version of [PublishPress Permissions](https://publishpress.com/links/permissions), it’s also possible to choose a custom style for your Teaser message, as shown in this screenshot below:

![Permissions plugin Teaser style](https://docs-media.publishpress.com/publishpress-permissions/custom-teaser-message-ddc56b10fe.webp)

## The Options tab [#](#h-the-options-tab)

WordPress automatically produces RSS feeds for all your content. It’s not always true that RSS feeds have the same permissions as your content, so some of it may accidentally become visible. With this feature, you can control what displays in your RSS feed for private content.

![RSS teaser options](https://docs-media.publishpress.com/publishpress-permissions/rss-options-teaser-cbb7f3fca5.webp)

*Rss Block*
