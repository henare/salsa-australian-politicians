Salsa Campaigns for Australian Politicians
==========================================

This plugin allows users of [Salsa]'s CRM system to run campaigns
targeting Australia Federal Politicians via their Wordpress site.

It uses code from [wp-jalapeno] and if you want to run any other types of
campaign you should use that plugin.

Set up
------

You need to set up targets in Salsa that match the name of every Federal
politician and you need to provide some settings on the plugin settings
page of your Wordpress site.

These settings include your Salsa access details and an [OpenAustralia]
API key, which is used to get the details of current Federal MPs.

Running a campaign
------------------

When you want to run a campaign, set it up like you would any other
Salsa Campaign except you don't need to select any targets. The plugin
finds targets based on their name and only works with current Federal
MPs.

The plugin uses the name of the action to get the sample subject and
content for the email so it must be unique. Once you've got the action
ready you can insert the campaign into your Wordpress site using the
shortcode `[salsa-campaign name="My campaign"]`.

If you've configured your action correctly it should now allow users of
your site to:

1. Enter their postcode to locate their Federal MPs
2. Select which MP they'd like to email
3. Enter their details and message to the MP
4. Have it sent via Salsa actions, with their details added to your
  supporter database

Constraints
-----------

There's a few constraints when using this plugin:

* No HTML email (Salsa Campaigns do not support this)
* No multi content targeted actions
* Campaigns must have unique names

Changelog
---------

0.0.1 - Initial release

  [salsa]: http://www.salsalabs.com/
  [openaustralia]: http://www.openaustralia.org/
  [wp-jalapeno]: http://www.wpjalapeno.com/
