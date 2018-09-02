# Automatic Redirect
This is a simple Joomla! plugin that will create redirect for articles when the alias is changed. If the alias of the article is changed, the plugin will perform the following actions:

1. Create a redirect from the old article url to the new article url
2. Remove redirects where the `old_url` is the same as the old article url
3. Update redirects where the `new_url` is pointing to the old article url
4. Remove all redirects where the `old_url` is the same as its `new_url'