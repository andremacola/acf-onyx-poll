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

IMPORTANT: 2.0 is a major update with breaking changes to requirements and internal storage. Back up the full database and plugin files, and test the update on a staging copy before updating a production site. See the breaking changes below and the migration FAQ.

Migration starts automatically on activation or on the next WordPress request after upgrading an already active plugin from 1.2.0 or an earlier ACF version. No manual export, import or reactivation is needed. The plugin creates {prefix}onyx_polls and {prefix}onyx_poll_answers and copies existing polls in resumable batches of 50 per request. A requested poll is imported on demand if its batch has not run yet, including the latest-poll shortcode and modal selection.

IDs, titles, publication status, answer order and text, image references, individual vote counters, dates, voting limits, result preferences and global settings are preserved. Existing blocks, shortcodes and widgets keep working without editing post content. Each poll is imported in a database transaction; retrying migration does not overwrite already imported polls or votes received in 2.0.

Legacy custom fields are kept for recovery and are not deleted by the update. New rules and votes are written only to the plugin tables. ACF is not required to run the plugin or migration; keep it installed if your theme or another plugin still needs it. The database user must be allowed to create the plugin tables.

= Breaking changes in 2.0 =

* Minimum requirements are now WordPress 6.3 and PHP 7.4. Check the hosting environment before updating.
* Poll data moves from WordPress custom fields to the plugin tables. Custom templates, snippets or integrations that read or write ACF fields or onyx_poll_* post metadata must be adapted; those legacy fields stop receiving updates. The plugin's existing shortcodes, block identifier, widget and public REST routes remain compatible.
* Total votes is now read-only and equals the sum of answer counters. A manually entered total that differed from that sum will change during migration; individual answer counters are preserved.
* Poll editing and settings use native plugin forms. Customizations that depend on ACF field registration, form markup or ACF-specific hooks must be reviewed.
* Downgrading to 1.2.0 does not copy new votes or edits back into legacy fields. Restoring an old backup also loses changes made after that backup. Plan rollback with a current database backup; do not use downgrade as a way to reverse migration.

== Installation ==

1. Install Onyx Poll through the WordPress Plugins screen, or upload the plugin folder to wp-content/plugins.
2. Activate the plugin and create a poll under Polls.
3. Add an Onyx Poll block, shortcode or widget to your site.

== Frequently Asked Questions ==

= Do I need ACF PRO installed? =

No. Version 2.0 uses its own editor, settings and database tables. Existing ACF poll data migrates automatically.

= Will my 1.2.0 polls and votes migrate automatically? =

Yes. Updating the active plugin starts migration on the next WordPress request; a fresh activation also starts it. Polls are copied in batches of 50 per request and migration resumes on subsequent requests. A poll needed by a shortcode, block, widget or modal is imported on demand, so a large poll collection does not need to finish migrating before those polls can be displayed. No ACF installation or manual import is required.

Poll IDs, answers, images, individual counters and settings are preserved. Migration does not delete the original custom fields and repeated runs do not overwrite new votes. The only intentional result change is that total votes becomes the sum of the answers.

= Can I update without changing existing posts or losing site content? =

The built-in blocks, shortcodes and widgets remain compatible, and the update does not rewrite posts or pages or remove media. However, no major update can guarantee compatibility with every hosting environment, theme or custom integration. Back up the database and files, test on staging, verify PHP 7.4 and WordPress 6.3 or newer, and confirm that the database user can create tables. Review the breaking changes if custom code accesses the old ACF fields directly.

= Can I remove ACF or downgrade after updating? =

Onyx Poll 2.0 and its migration work without ACF. Remove ACF only if your theme and other plugins do not need it. Old ACF fields remain as a pre-migration copy; they are not synchronized with new votes or edits. Downgrading to 1.2.0 therefore does not preserve changes made in 2.0. Keep a current database backup and test any rollback separately.

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
* Keep latest-poll shortcodes, listings and modal selection working while a large migration is still pending.
* Calculate total votes from answers and preserve concurrent votes during administration saves.
* Added settings descriptions, help, cron execution tracking and Site Health integration.
* Improved image controls, answer reordering and block previews.
* Added an explicit, scoped data cleanup option on uninstall; other content is preserved.
* Breaking: requires WordPress 6.3 and PHP 7.4; custom ACF-based integrations must adapt to dedicated storage and native forms. Total votes is derived from answers. See the upgrade notice and migration FAQ.

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
Major update: requires WordPress 6.3 and PHP 7.4. Polls migrate automatically to dedicated tables; total votes becomes the answer sum. Custom ACF integrations need adaptation. Back up and test on staging before upgrading. Downgrading does not restore new votes to old fields.
