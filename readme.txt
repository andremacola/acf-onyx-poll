=== Onyx Poll ===
Contributors: andremacola
Tags: poll, polls, onyx, voting, survey
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Independent polls with a block, shortcode, widget, modal, images and dedicated database tables. No ACF required.

== Description ==

Onyx Poll creates lightweight polls with the WordPress REST API and JavaScript. ACF and ACF PRO are not required.

= Features =

* Gutenberg block with live preview, standard and Twitter styles.
* Shortcode, sidebar widget and automatic modal.
* Multiple polls per page, answer images and registered thumbnail sizes.
* Editable answer counters; the total is always their sum.
* Percentage, number or combined results, with an option to hide results until expiration.
* Voting intervals remembered by browser cookies.
* Automatic expiration and an hourly WP-Cron task.
* Native administration with media selection and answer reordering.
* Dedicated tables for poll rules, answers and vote counters.
* Settings descriptions, help, cron execution status and WordPress Site Health integration.
* Optional permanent cleanup on uninstall; data is kept by default.

= Upgrading from the ACF version =

Existing polls migrate automatically, in resumable batches. IDs, titles, publication status, answers, images, individual vote counters and settings are preserved. Existing blocks, shortcodes and widgets keep working. The total is recalculated from the answers, correcting any manually entered total.

Legacy custom fields are kept for recovery. New rules and votes are written only to the plugin tables. Back up the database before upgrading. Once migration completes, ACF is not needed by this plugin; other plugins or your theme may still use it.

== Installation ==

1. Install Onyx Poll through the WordPress Plugins screen, or upload the plugin folder to wp-content/plugins.
2. Activate the plugin and create a poll under Polls.
3. Add an Onyx Poll block, shortcode or widget to your site.

== Frequently Asked Questions ==

= Do I need ACF PRO installed? =

No. Version 2.0 uses its own editor, settings and database tables. Existing ACF poll data migrates automatically.

= How do I add a poll to a post? =

Insert the Onyx Poll block and select the poll and style, or use a shortcode:

    [onyx-poll]
    [onyx-poll id=85]
    [onyx-poll id=85 class="twitter full"]

Replace 85 with your poll ID. Omit the ID to show the latest published poll. Alignment classes: left, right, full. Styles: twitter, standard. The poll list provides a shortcode to copy.

= How do I add a poll to a sidebar? =

If your theme provides widget areas, add the Poll widget under Appearance > Widgets and select its poll and style.

= Can I use my own CSS? =

Yes. Keep the default stylesheet enabled to override its CSS variables, or enable Use own CSS in Poll Settings to replace all plugin styles. Settings > Help contains the full variable example.

    .onyx-poll {
        --pollWidgetWidth: 400px;
        --borderColor: #dbe9f5;
        --boxShadow: 0 4px 12px 6px rgba(31,70,88,.04);
        --modalBorderRadius: 4px;
        --questionColor: #333;
        --choiceColor: #333;
        --buttonColor: #333;
        --choiceHoverBG: #f5f5f5;
        --choiceBarColor: #e0e0e0;
        --choiceBorderRadius: 100px;
        --closeBorderRadius: 100px;
        --loaderBorderColor: rgb(209,226,240);
        --loaderBG: #a3caec;
    }

= How can I check the expiration cron? =

Poll Settings shows the last successful check, its source, the next scheduled check, timezone and status. A new installation waits for its first recorded execution; no older execution history is invented. Checks with no expired polls also count as successful runs. The plugin adds its status to Tools > Site Health.

WP-Cron normally depends on site visits. For predictable execution, configure your host to request wp-cron.php every five minutes; the Onyx event itself remains hourly:

    */5 * * * * curl --fail --silent --show-error 'https://example.com/wp-cron.php?doing_wp_cron' > /dev/null 2>&1

Only after the external scheduler is configured and tested, disable the automatic page trigger in wp-config.php:

    define('DISABLE_WP_CRON', true);

The existing direct expiration endpoint is also supported:

    0 * * * * curl --fail --silent --show-error 'https://example.com/wp-json/onyx/polls/cron' > /dev/null 2>&1

This endpoint runs only the Onyx check. It does not run other WordPress scheduled tasks. Endpoint runs are identified separately; an overdue WordPress event still needs investigation. Voting checks deadlines immediately, even when cron is delayed.

See the [WordPress server scheduler documentation](https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/).

= Where is the data stored? =

Poll rules use {prefix}onyx_polls; answers, image references and votes use {prefix}onyx_poll_answers. WordPress stores titles and publication status in the onyxpolls post type. Settings and cron diagnostics use the plugin's own options. The actual WordPress table prefix is used.

No visitor IP addresses or usernames are stored. Cookies remember votes, modal dismissal and voting intervals: onyx_poll_limit_XX, onyx_poll_modal and onyx_poll_choice_XX.

= What happens on uninstall? =

Data is kept by default. In Poll Settings, administrators can choose permanent deletion and must acknowledge that recovery will not be possible without a backup.

Permanent cleanup removes only the two plugin tables, the exact list of plugin-owned options, and wp_posts rows with post_type onyxpolls. Other posts and pages are preserved, including those containing poll blocks or shortcodes. Media files, attachment relationships, comments, revisions and legacy custom fields are preserved. Deactivation never deletes poll data.

= Which browsers are supported? =

Current Chrome, Firefox, Safari and Edge. Internet Explorer is not supported. The public polling interface does not require jQuery.

== Screenshots ==

1. Poll administration list.
2. Poll editing screen.
3. Poll displayed on the site.

== Changelog ==

= 2.0.0 =
*Release Date - 11 Oct 2026*

* Removed the ACF PRO dependency with native administration and a native Gutenberg block.
* Added dedicated tables and automatic, resumable migration of existing polls.
* Calculate total votes from answers and preserve concurrent votes during administration saves.
* Added settings descriptions, help, cron execution tracking and Site Health integration.
* Improved image controls, answer reordering and block previews.
* Added an explicit, scoped data cleanup option on uninstall; other content is preserved.

= 1.2.0 =
*Release Date - 11 Jun 2025*

* Fixed: Sanitize shortcode attributes to prevent Stored XSS

= 1.1.9 =
*Release Date - 20 Abr 2025*

* Fixed: Wordpress textdomain translation notices

= 1.1.8 =
*Release Date - 28 Jul 2024*

* Fixed: PHP 8.2 Deprecated dynamic property warnings

= 1.1.7 =
*Release Date - 26 Jul 2024*

* TESTED: WordPress up to 6.6.1

= 1.1.6 =
*Release Date - 29 Set 2023*

* TESTED: WordPress up to 6.3.1

= 1.1.5 =
*Release Date - 20 Jul 2021*

* TESTED: WordPress up to 5.8

* ADDED: Now is possible to change the poll image size in options menu

* FIXED: Label for singular votes, thanks to @Dathix. See more in: https://github.com/andremacola/acf-onyx-poll/pull/13

= 1.1.4 =
*Release Date - 13 Jan 2021*

* FIXED: Removed an ACF hook that was causing issues with ACF JSON save location. See more in: https://github.com/andremacola/acf-onyx-poll/issues/12

* FIXED: Fatal error on list polls when 'end date' field was for some reason saved with an empty value. See more in: https://github.com/andremacola/acf-onyx-poll/issues/11

Thanks to:
- @rampantprint (on github)
- @bwiebeck (on github)

= 1.1.3 =
*Release Date - 21 Set 2020*

* IMPROVEMENT: Using ACF PRO as MU Plugin is now possible.

= 1.1.2 =
*Release Date - 11 Aug 2020*

* TESTED: WordPress 5.5
* FIXED: Rest API method permission_callback typo

= 1.1.1 =
*Release Date - 30 May 2020*

* HOTFIX: Some filters were causing fatal errors in some themes and conflicting with certain plugins

= 1.1.0 =
*Release Date - 30 May 2020*

* NEW Gutenberg Onyx Poll Block with live preview (no more shortcodes :))
* Shortcode is now available to copy on the pool table list
* Fix some php notices (again :))
* CSS adjustments
* JS adjustments
* Languages updates

= 1.0.1 =
*Release Date - 29 May 2020*

* Added native sidebar widget
* Added new bar style
* Update en language source pot file.
* Fix results view bug when only numbers or percentage option is selected
* Fix some php notices
* Fix css flex alignment
* Fix click event bug

= 1.0 =
*Release Date - 27 May 2020*

* Initial release

== Upgrade Notice ==

= 2.0.0 =
Back up your database before upgrading. Existing polls migrate automatically to dedicated tables and retain their IDs. ACF PRO is no longer required. Total votes are recalculated from individual answer counters. Data is kept on uninstall unless you explicitly enable permanent deletion.
