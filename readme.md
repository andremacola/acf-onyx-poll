<p align="center">
  <img width="914" height="296" src="https://raw.github.com/andremacola/acf-onyx-poll/master/assets/images/banner-1544x500.png">
</p>

> Install from [Wordpress Plugins](https://br.wordpress.org/plugins/acf-onyx-poll/ "Wordpress Plugin Repository") or clone the master branch from this repository

Onyx Poll is an independent WordPress polling plugin using the **WP REST API**, native WordPress administration and its own database tables. ACF and ACF PRO are not required. Feel free to submit a Pull Request.

The main goal of Onyx Poll is to be **totally free, lightweight and simple**. No fancy, colorful and polluted options or donate screens.

This plugin is based on [Twitter](https://twitter.com) poll cards style.

## SCREENSHOTS

<p align="center">

![Admin List Table](https://raw.github.com/andremacola/acf-onyx-poll/master/assets/images/screenshot-1.png "Admin List Table")

![Admin Edit Screen](https://raw.github.com/andremacola/acf-onyx-poll/master/assets/images/screenshot-2.png "Admin Edit Screen")

![Poll Example](https://raw.github.com/andremacola/acf-onyx-poll/master/assets/images/screenshot-3.png "Poll Example")

</p>

## HOW TO USE

* Use the shortcode `[onyx-poll]` to get the latest poll
* To add a specific poll, assign the option **ID** with the requested poll to the shortcode: `[onyx-poll id=XX]`
* You can align the poll container with the option **class** in the shortcode: `[onyx-poll class=left]`. Available options are `left, right, full`
* To select which **style** you want: `[onyx-poll class="twitter|standard"]`
* Is it possible to combine style and aligment. Ex: `[onyx-poll class="standard left"]`
* To include a **widget**, your theme need to support it. Go to Appereance > Widgets and select **Poll** widget from *Onyx Poll*

## FEATURES

- [x] **Gutemberg Block** with live preview
- [x] Include poll with a shortcode (ommit the ID to get the latest poll) `[onyx-poll id=XX class="left|right|full"]`
- [x] One click to vote
- [x] Works with cache plugins
- [x] Multiple polls per page
- [x] Support for images and custom sizes
- [x] Native widget for sidebar
- [x] Multiple style options `[onyx-poll class="twitter|standard"]`
- [x] Show poll in a modal
- [x] Show poll results on widget after expired
- [x] Highlight choosed choice in results area
- [x] Limit vote by device or time
- [x] Poll activation/expiration schedule
- [x] Results in percentage, numbers or both
- [x] Show/Hide results
- [x] Customize css with css variables
- [x] Disable all plugin CSS and use your own
- [x] Custom columns on Wordpress data table admin area
- [x] Translations support

## TO DO

- [ ] Improve/Cache REST API response for better performance on high traffic sites
- [ ] Inject javascript only if current page has a poll
- [ ] Documentation/Wiki for users and developers
- [ ] Integration with Google reCaptcha
- [ ] Email notification when poll is expired (considering)
- [ ] Support for AMP Pages (considering)
- [ ] Select multiple options to vote (considering)
- [ ] Javascript Refactoring for better code view and maintenance (after first release)

## UPGRADING FROM THE ACF VERSION

On activation or upgrade, existing polls are automatically copied from their legacy post metadata into the new tables. ACF does not need to be installed or active. Migration runs in batches of 50 polls per request and resumes automatically; an individually requested poll is also imported on demand. Each import is transactional, and retrying migration never overwrites already imported answers or new votes.

Poll IDs, answer order, image IDs, answer counters, end dates, voting limits and general settings are preserved. The legacy manually entered total is replaced with the sum of the answers. Old post metadata is retained for recovery. Existing shortcodes, widgets and `acf/acf-onyx-poll` Gutenberg blocks continue to work without rewriting post content. The legacy block name is a compatibility identifier, not an ACF dependency.

Version 2.0 requires WordPress 6.3 and PHP 7.4 or newer and permission to create database tables. Custom templates or integrations that read/write ACF fields, legacy poll post metadata or ACF forms/hooks must be adapted. Back up and test on staging before a production upgrade. Legacy fields are not synchronized after migration: downgrading to 1.2.0 does not copy new votes or edits back to them. The public `readme.txt` includes a breaking-changes disclaimer, migration FAQ and WordPress.org upgrade notice.

## UNINSTALLING

In **Polls > Settings**, administrators can choose **Keep data (default)** or **Permanently delete plugin data**. Permanent deletion displays an irreversible deletion warning and requires acknowledgement before saving the setting. Deactivating the plugin never deletes poll data.

When WordPress deletes the plugin, the saved choice is applied. If deletion was enabled, cleanup removes only these exact tables/options and poll posts:

- `{prefix}onyx_polls` and `{prefix}onyx_poll_answers`.
- `onyx_poll_settings`, `onyx_poll_db_version`, `onyx_poll_migration_complete`, `onyx_poll_cron_status` and `widget_onyx_poll`.
- The three legacy ACF option names `options_onyx_poll_modal_time`, `options_onyx_poll_css`, `options_onyx_poll_image_size` and their exact `_options_...` field-reference entries.
- Records in the posts table whose `post_type` is exactly `onyxpolls`, regardless of publication/trash status.

Other posts and pages are preserved, including their blocks and shortcodes referencing a poll. Attachments, media files, legacy post metadata, comments, revisions and other plugins' data are untouched. Cleanup never matches names by a generic `onyx` prefix or searches post content to select records for deletion.

## DEVELOPMENT CHECKS

Run `wp eval-file tests/integration.php` against a WordPress installation with this plugin loaded. The suite creates temporary tables with a random isolated prefix, checks migration, derived totals, concurrency, REST compatibility, legacy blocks and uninstall isolation, and removes its own fixtures. It never runs destructive uninstall against the site's real tables. The database user needs permission to create and drop temporary fixture tables.

Run `npm install` and `npm run build` after changing frontend JavaScript or shared CSS to regenerate the shipped bundles. Native admin and block scripts are shipped without a build dependency at runtime.

Run `npm run lint:js` to check JavaScript sources and Node scripts with the project's WordPress ESLint rules. `npm run lint:js:fix` applies automatic formatting corrections. Generated minified files, dependencies, `dist/` and the entire `_svn/` checkout are excluded. The parser supports the syntax already used by the plugin and Node scripts; WordPress and administration globals are declared explicitly.

## PREPARING A WORDPRESS.ORG RELEASE

The SVN working copy lives in `_svn/`, inside this project. Git ignores the entire folder, including `.svn` and historical tags. `dist/` stores preparation manifests and is also ignored. These folders and all development files are excluded from the distributable plugin.

Requirements: Node.js 18 or newer, the Subversion CLI, and installed development dependencies (`npm install` or `yarn install`). The checkout must contain `trunk/` and `tags/`. Keep `readme.txt` in this project as the canonical WordPress.org readme; it includes the public FAQ and historical changelog.

For a new release:

```sh
npm run release:version -- 2.0.1
# Add the 2.0.1 changes to the changelog in readme.txt.
npm run release
svn status _svn
svn diff _svn
```

The version command updates `package.json`, the plugin header, the PHP version constant and the readme's stable tag together. `npm run release` checks JavaScript lint, builds the CSS/JS, checks that versions and requirements match, copies only runtime files into `_svn/trunk`, stages obsolete-file removals and new files, and creates `_svn/tags/<version>` locally. It also writes a SHA-256 manifest in `dist/svn-preparation.json`.

The same command validates the exact PNG dimensions and copies the two banners (1544×500 and 772×250) and two icons (256×256 and 128×128) from `assets/images/` to `_svn/assets/`. These directory images stay outside trunk, tags and installation packages. Original branding backups in `assets/images/backups/` are local only: Git ignores them and release preparation never copies them to SVN or packages.

No ZIP is needed for SVN publication, and the default command does not generate one. If you separately need a package for manual installation, `npm run release:zip` creates `dist/acf-onyx-poll-<version>.zip`; this optional command does not modify SVN.

Preparing again before a commit is supported when the SVN files still match the previous generated manifest. Manual edits or unexpected working-copy changes, including changes to directory images, cause preparation to stop before changing SVN. Committed tags are never overwritten. Historical tags, screenshots and other directory assets are preserved. The script never runs `svn update` or `svn commit` and never uses publication credentials; review and submit the local changes explicitly when ready.

`npm run test:release` exercises packaging, repeat preparation, version checks and protection of manual edits and published tags against a disposable local SVN repository. It never connects to WordPress.org.

## CRON STATUS

Poll Settings shows the last successful check, latest completed attempt and source, next scheduled event and site timezone. Executions from WP-Cron and the existing REST endpoint are identified separately. A check with no expired polls is still a successful run. Missing events, overdue runs and database failures are reported, and the same status appears in WordPress Site Health. Records start with version 2.0; earlier history is not invented. Reading the status does not execute the job.

The hourly event is registered through the WordPress scheduler and is restored if missing, including on upgrades that do not trigger activation. Deactivation clears only the plugin event. Deadline checks during voting do not depend on cron.

## OBSERVATIONS

- I don't support Internet Explorer Browser and all PR's related will be rejected. One of the goals of this plugin is to be js/css lightweight and jQuery free.

- Requirements: PHP 7.4 or newer and WordPress 6.3 or newer. Poll editing, settings and Gutenberg controls are implemented by this plugin. The folder, shortcode, widget ID and text domain remain compatible with earlier installations.

- WP-Cron normally depends on site visits. For predictable scheduling, configure your host to call `wp-cron.php` every five minutes: `*/5 * * * * curl --fail --silent --show-error 'https://example.com/wp-cron.php?doing_wp_cron' > /dev/null 2>&1`. The Onyx event itself remains hourly. Only after configuring and testing the external scheduler, add `define('DISABLE_WP_CRON', true);` to `wp-config.php`. See the [WordPress documentation](https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/).

- The compatible direct endpoint `https://example.com/wp-json/onyx/polls/cron` runs only the Onyx expiration check. It does not service the other WordPress scheduled tasks. Its executions are recorded, but an overdue WP-Cron event still needs investigation. Poll Settings includes instructions and generic command examples; replace `https://example.com` with your WordPress site URL.

## CSS Customizations

You can do some customization by overriding some CSS variables inside your css file.

```css
.onyx-poll {
	--pollWidgetWidth: 400px;
	--borderColor: #dbe9f5;
	--boxShadow: 0 4px 12px 6px rgba(31,70,88,0.04);
	--modalBorderRadius: 4px;
	--questionColor: #333;
	--choiceColor: #333;
	--buttonColor: #333;
	--choiceHoverBG: #f5f5f5;
	--choiceBarColor: #e0e0e0;
	--choiceBorderRadius: 100px;
	--closeBorderRadius: 100px;
	--loaderBorderColor: rgb(209, 226, 240);
	--loaderBG: #a3caec;
}
```

Maybe you will have to set the `font-family` and `font-size` for some elements to match your theme style.

If you need a more advanced attributes, the CSS source is located on `assets/css/onyx-poll.css`. You can use as a guide but **never** override the original files.

## DATABASE, USER DATA AND LOGS

Onyx Poll stores poll rules in `{prefix}onyx_polls` and answers, image references and vote counters in `{prefix}onyx_poll_answers`. It uses the actual WordPress table prefix, not a hard-coded `wp_`.

The total is always calculated from the answer counters. It is read-only in administration and is not stored as a separate counter. Manual edits to individual answer counts remain available. Concurrent votes use atomic increments; saving the administration form preserves votes received while the form was open and reports conflicting edits.

WordPress still stores poll titles and publication status as the `onyxpolls` post type. General settings live in the plugin's own `onyx_poll_settings` option. New poll data is never written to `wp_postmeta`.

For privacy, ACF Onyx Poll **does not store** any user data like ip address, usernames etc...

The only things the plugin saves in the user's browser is some cookies to check the user choice for specific poll, if the modal was previously showed/closed and a timer. You can check the cookies by looking for `onyx_poll_limit_XX, onyx_poll_modal and onyx_poll_choice_XX`.

## BROWSER SUPPORT

![Chrome](https://raw.github.com/alrra/browser-logos/master/src/chrome/chrome_24x24.png) | ![Firefox](https://raw.github.com/alrra/browser-logos/master/src/firefox/firefox_24x24.png) | ![Edge](https://raw.githubusercontent.com/alrra/browser-logos/master/src/edge/edge_24x24.png) | ![Safari](https://raw.github.com/alrra/browser-logos/master/src/safari/safari_24x24.png) | ![IE](https://raw.githubusercontent.com/alrra/browser-logos/master/src/archive/internet-explorer_9-11/internet-explorer_9-11_24x24.png)
--- | --- | --- | --- | ---
✔ | ✔ | ✔ | ✔ | ✘
