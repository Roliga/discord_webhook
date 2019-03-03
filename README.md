discord_webhook
===============

This plugin adds a [filter action](https://git.tt-rss.org/fox/tt-rss/wiki/ContentFilters) that sends new articles to a [webhook](https://support.discordapp.com/hc/en-us/articles/228383668) for a Discord server channel.

Installation
------------

Clone this repository into your `plugins.local` directory in your TT-RSS installation. Ensure the plugin directory is named EXACTLY `discord_webhook`, otherwise it won't work.

Configuration
-------------

Once you have enabled the plugin a new section called *Discord Webhook* should be available in the Preferences tab in your TT-RSS settings where you can enter a webhook URL.

A new filter action should also be available. Select *Invoke plugin* as the filter action then *Discord_Webhook: Send to Discord*.
