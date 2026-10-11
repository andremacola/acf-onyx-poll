<p align="center">
  <img width="914" height="296" src="https://raw.github.com/andremacola/acf-onyx-poll/master/assets/images/banner-1544x500.png">
</p>

> Install from the [WordPress Plugin Directory](https://wordpress.org/plugins/acf-onyx-poll/) or clone the master branch of this repository.

Onyx Poll is an independent WordPress polling plugin using the **WordPress REST API**, native WordPress administration, and its own database tables. ACF and ACF PRO are not required. Contributions through pull requests are welcome.

Onyx Poll aims to remain **free, lightweight, and easy to use**.

The Twitter style is based on [Twitter](https://twitter.com) poll cards.

## Screenshots

<p align="center">

![Poll administration list](https://raw.github.com/andremacola/acf-onyx-poll/master/assets/images/screenshot-1.png)

![Poll editing screen](https://raw.github.com/andremacola/acf-onyx-poll/master/assets/images/screenshot-2.png)

![Poll example](https://raw.github.com/andremacola/acf-onyx-poll/master/assets/images/screenshot-3.png)

</p>

## How to use

* Use `[onyx-poll]` to show the latest published poll.
* To show a specific poll, replace `XX` with its ID: `[onyx-poll id=XX]`.
* Set the alignment with the `class` attribute: `[onyx-poll class=left]`. Available values are `left`, `right`, and `full`.
* Choose the `twitter` or `standard` style: `[onyx-poll class="twitter"]`.
* Combine a style and alignment: `[onyx-poll class="standard left"]`.
* If your theme supports widgets, go to **Appearance > Widgets** and add the **Poll** widget.

## Features

- [x] Block editor integration with live preview
- [x] Shortcodes for specific polls or the latest published poll
- [x] Voting with a single click
- [x] Works with caching plugins
- [x] Multiple polls per page
- [x] Support for images and custom sizes
- [x] Native sidebar widget
- [x] Standard and Twitter styles
- [x] Automatic poll modal
- [x] Widget results after the poll expires
- [x] Highlighted answer choices in results
- [x] Voting limits by device or time interval
- [x] Scheduled poll publication and expiration
- [x] Results as percentages, vote counts, or both
- [x] Show or hide results
- [x] CSS customization through variables
- [x] Disable all plugin CSS and use your own
- [x] Custom columns in the WordPress administration list
- [x] Translation support

## To do

- [ ] Improve or cache REST API responses for sites with heavy traffic
- [ ] Load JavaScript only on pages containing a poll
- [ ] Documentation for users and developers
- [ ] Integration with Google reCAPTCHA
- [ ] Email notifications when polls expire (under consideration)
- [ ] Support for AMP pages (under consideration)
- [ ] Multiple answer selection (under consideration)
- [ ] JavaScript refactoring for readability and maintenance

## Upgrading from the ACF version

On activation or upgrade, existing polls are automatically copied from their legacy post metadata into the new tables. ACF does not need to be installed or active. Migration runs in batches of 50 polls per request and resumes automatically; an individually requested poll is also imported on demand. Each import is transactional, and retrying migration never overwrites already imported answers or new votes.

Poll IDs, answer order, image IDs, answer counters, end dates, voting limits, and general settings are preserved. The legacy manually entered total is replaced with the sum of the answer vote counts. Old post metadata is retained for recovery. Existing shortcodes, widgets, and `acf/acf-onyx-poll` blocks continue to work without rewriting post content. The legacy block name is retained for compatibility.

Version 2.0 requires WordPress 6.3 and PHP 7.4 or newer and permission to create database tables. Custom templates or integrations that read/write ACF fields, legacy poll post metadata or ACF forms/hooks must be adapted. Back up and test on staging before a production upgrade. Legacy fields are not synchronized after migration: downgrading to 1.2.0 does not copy new votes or edits back to them. The public `readme.txt` includes a breaking-changes disclaimer, migration FAQ and WordPress.org upgrade notice.

## Uninstalling

In **Polls > Settings**, administrators can choose **Keep data (default)** or **Permanently delete plugin data**. Permanent deletion displays an irreversible deletion warning and requires acknowledgement before saving the setting. Deactivating the plugin never deletes poll data.

When WordPress deletes the plugin, the saved choice is applied. If deletion was enabled, cleanup removes only these exact tables/options and poll posts:

- `{prefix}onyx_polls` and `{prefix}onyx_poll_answers`.
- `onyx_poll_settings`, `onyx_poll_db_version`, `onyx_poll_migration_complete`, `onyx_poll_cron_status` and `widget_onyx_poll`.
- The three legacy ACF option names `options_onyx_poll_modal_time`, `options_onyx_poll_css`, `options_onyx_poll_image_size` and their exact `_options_...` field-reference entries.
- Records in the posts table whose `post_type` is exactly `onyxpolls`, regardless of publication/trash status.

Other posts and pages are preserved, including their blocks and shortcodes referencing a poll. Attachments, media files, legacy post metadata, comments, revisions and other plugins' data are untouched. Cleanup never matches names by a generic `onyx` prefix or searches post content to select records for deletion.

## Development checks

Run `wp eval-file tests/integration.php` against a WordPress installation with this plugin loaded. The suite creates temporary tables with a random isolated prefix, checks migration, derived totals, concurrency, REST compatibility, legacy blocks and uninstall isolation, and removes its own fixtures. It never runs destructive uninstall against the site's real tables. The database user needs permission to create and drop temporary fixture tables.

Run `wp eval-file tests/security.php` for isolated REST, CSRF, password protection, browser-session limits, concurrent voting, cache controls and settings-permission checks. Run `npm run test:frontend` for client-side token refresh, plain-permalink URLs, malformed cookies, transport errors and duplicate-click handling.

Run `npm install` and `npm run build` after changing frontend JavaScript or shared CSS to regenerate the shipped bundles. Native admin and block scripts are shipped without a build dependency at runtime.

Run `npm run lint:js` to check JavaScript sources and Node scripts with the project's WordPress ESLint rules. `npm run lint:js:fix` applies automatic formatting corrections. Generated minified files, dependencies, `dist/` and the entire `_svn/` checkout are excluded. The parser supports the syntax already used by the plugin and Node scripts; WordPress and administration globals are declared explicitly.

## Translations

English (US) is the source language; no separate English catalog is needed. The plugin includes complete `.po` and compiled `.mo` catalogs for Brazilian Portuguese (`pt_BR`), Spanish from Spain (`es_ES`), Dutch (`nl_NL`), and German (`de_DE`). They use the default informal variants for Spanish, Dutch, and German. WordPress selects the appropriate catalog from its active locale.

After changing translatable source strings, regenerate the template with WP-CLI:

```sh
wp i18n make-pot . languages/acf-onyx-poll.pot --domain=acf-onyx-poll --exclude=_svn,dist,node_modules,tests,scripts,assets/images/backups,assets/js/onyx-poll.min.js
```

Update each `.po` against the template with `msgmerge`, review the translations, and run `npm run i18n:build`. `npm run i18n:check` checks catalog coverage, placeholders, plural forms, and whether compiled files match the catalogs. Both commands require GNU gettext (`msgcmp` and `msgfmt`). Release preparation runs the translation check automatically.

Use the [Brazilian Portuguese guidelines](https://br.wordpress.org/team/handbook/traducao/boas-praticas/), [English style guide](https://make.wordpress.org/docs/style-guide/formatting/text/), [Spanish guide](https://es.wordpress.org/team/handbook/traducciones/guia/), [Dutch style guide](https://nl.wordpress.org/vertaalproces/stijlgids/), and [German style guide](https://de.wordpress.org/team/handbook/polyglots-team/style-guide/) and their locale glossaries. Preserve placeholders, code examples, and product names. Translations still need review and approval by the relevant community editors before becoming official WordPress.org language packs.

New releases can add or change source strings. Do not replace a published SVN tag. Import copies in `dist/translations/` use the current source strings; submit them to Translate WordPress after that source version is available there.

## Preparing a WordPress.org release

The SVN working copy lives in `_svn/`, inside this project. Git ignores the entire folder, including `.svn` and historical tags. `dist/` stores preparation manifests and is also ignored. These folders and all development files are excluded from the distributable plugin.

Requirements: Node.js 18 or newer, the Subversion CLI, GNU gettext, and installed development dependencies (`npm install` or `yarn install`). The checkout must contain `trunk/` and `tags/`. Keep `readme.txt` in this project as the canonical WordPress.org readme; it includes the public FAQ and historical changelog.

For a new release:

```sh
npm run release:version -- 2.0.2
# Add the 2.0.2 changes to the changelog in readme.txt.
npm run release
svn status _svn
svn diff _svn
```

The version command updates `package.json`, the plugin header, the PHP version constant and the readme's stable tag together. `npm run release` checks JavaScript lint, builds the CSS/JS, checks that versions and requirements match, copies only runtime files into `_svn/trunk`, stages obsolete-file removals and new files, and creates `_svn/tags/<version>` locally. It also writes a SHA-256 manifest in `dist/svn-preparation.json`.

The same command validates the exact PNG dimensions and copies the two banners (1544×500 and 772×250) and two icons (256×256 and 128×128) from `assets/images/` to `_svn/assets/`. These directory images stay outside trunk, tags and installation packages. Original branding backups in `assets/images/backups/` are local only: Git ignores them and release preparation never copies them to SVN or packages.

No ZIP is needed for SVN publication, and the default command does not generate one. If you separately need a package for manual installation, `npm run release:zip` creates `dist/acf-onyx-poll-<version>.zip`; this optional command does not modify SVN.

Preparing again before a commit is supported when the SVN files still match the previous generated manifest. Manual edits or unexpected working-copy changes, including changes to directory images, cause preparation to stop before changing SVN. Committed tags are never overwritten. Historical tags, screenshots and other directory assets are preserved. The script never runs `svn update` or `svn commit` and never uses publication credentials; review and submit the local changes explicitly when ready.

`npm run test:release` exercises packaging, repeat preparation, version checks and protection of manual edits and published tags against a disposable local SVN repository. It never connects to WordPress.org.

## Cron status

Poll settings shows the last successful check, latest completed attempt and source, next scheduled event and site timezone. Executions from WP-Cron and the existing REST endpoint are identified separately. A check with no expired polls is still a successful run. Missing events, overdue runs and database failures are reported, and the same status appears in WordPress Site Health. Records start with version 2.0; earlier history is not available. Reading the status does not execute the job.

The hourly event is registered through the WordPress scheduler and is restored if missing, including on upgrades that do not trigger activation. Deactivation clears only the plugin event. Deadline checks during voting do not depend on cron.

## Notes

- Internet Explorer is not supported. The public interface uses lightweight JavaScript and CSS and does not require jQuery.

- Requirements: PHP 7.4 or newer and WordPress 6.3 or newer. Poll editing, settings and Gutenberg controls are implemented by this plugin. The folder, shortcode, widget ID and text domain remain compatible with earlier installations.

- WP-Cron normally depends on site visits. For predictable scheduling, configure your host to call `wp-cron.php` every five minutes: `*/5 * * * * curl --fail --silent --show-error 'https://example.com/wp-cron.php?doing_wp_cron' > /dev/null 2>&1`. The Onyx event itself remains hourly. Only after configuring and testing the external scheduler, add `define('DISABLE_WP_CRON', true);` to `wp-config.php`. See the [WordPress documentation](https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/).

- The compatible direct endpoint `https://example.com/wp-json/onyx/polls/cron` runs only the Onyx expiration check. It does not service the other WordPress scheduled tasks. Its executions are recorded, but an overdue WP-Cron event still needs investigation. Poll Settings includes instructions and generic command examples; replace `https://example.com` with your WordPress site URL.

## CSS customization

Override these CSS variables in your theme stylesheet to customize the poll appearance.

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

You may also need to set `font-family` and `font-size` on some elements to match your theme.

For more customization options, refer to `assets/css/onyx-poll.css`. Add overrides to your theme instead of editing the plugin files, which are replaced during updates.

## Database, user data, and logs

Onyx Poll stores poll rules in `{prefix}onyx_polls` and answers, image references and vote counters in `{prefix}onyx_poll_answers`. It uses the actual WordPress table prefix, not a hard-coded `wp_`.

`{prefix}onyx_poll_guards` stores keyed hashes, poll IDs, counters and expiry times for anonymous session limits and public cron throttling. It contains no IP addresses, usernames or browser fingerprints. Cron prunes expired rows in bounded batches. Explicit uninstall cleanup includes this table and preserves the same exclusions for unrelated WordPress content.

The total is always calculated from the answer counters. It is read-only in administration and is not stored as a separate counter. Manual edits to individual answer counts remain available. Concurrent votes use atomic increments; saving the administration form preserves votes received while the form was open and reports conflicting edits.

WordPress still stores poll titles and publication status as the `onyxpolls` post type. General settings live in the plugin's own `onyx_poll_settings` option. New poll data is never written to `wp_postmeta`.

Onyx Poll does not store visitor IP addresses or usernames.

Browser cookies remember answer choices, voting intervals, and modal dismissal: `onyx_poll_limit_XX`, `onyx_poll_modal`, and `onyx_poll_choice_XX`.

## Voting security in 2.0.2

Anonymous votes use a signed, HttpOnly browser cookie (`__Host-onyx_poll_voter` on HTTPS, `onyx_poll_voter` on HTTP) with SameSite=Lax. The cookie uses a random identity, not an IP address or fingerprint. HTTPS cookies also use Secure, the host-only prefix and path `/`. Each poll response supplies a short-lived token bound to the identity, poll ID and administrative version; changing the answers invalidates old tokens.

Voting accepts POST only. The request must carry `X-Onyx-Poll-Token` and the same session cookie; foreign or opaque browser origins are rejected. Tokens are fetched at runtime rather than embedded in cached HTML. Poll REST responses send `Cache-Control: private, no-store`, and the frontend also refreshes the token before voting. Custom clients must follow this flow and preserve cookies; built-in blocks, widgets and shortcodes already do so.

Each browser session can submit at most 10 valid voting requests per minute. Poll intervals are reserved under a database lock in the vote transaction, preventing duplicate limited votes from parallel requests or deletion of only the legacy limit cookie. A complete cookie reset, a different browser or automated creation of new sessions can still bypass anonymous identity limits. Unlimited voting keeps the session burst limit while removing the poll interval. These protections cannot establish one unique human and do not replace host-level protection against traffic floods.

Public reads and votes respect WordPress poll passwords. Global settings require `manage_options`; poll editing keeps its existing WordPress permissions. The compatible public cron endpoint is limited to one execution per minute. Expiration updates recheck deadlines so they cannot close a concurrently reopened poll.

The hardening follows the [WordPress guidance on guest nonces](https://developer.wordpress.org/apis/security/nonces/#customize-nonces-for-guests-non-logged-in-users), [OWASP CSRF guidance](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html) and [OWASP API resource limits](https://api-security.owasp.org/editions/2023/en/0xa4-unrestricted-resource-consumption/). The update creates the guards table through the existing schema upgrade without rewriting poll records or reimporting completed migrations.

## Browser support

![Chrome](https://raw.github.com/alrra/browser-logos/master/src/chrome/chrome_24x24.png) | ![Firefox](https://raw.github.com/alrra/browser-logos/master/src/firefox/firefox_24x24.png) | ![Edge](https://raw.githubusercontent.com/alrra/browser-logos/master/src/edge/edge_24x24.png) | ![Safari](https://raw.github.com/alrra/browser-logos/master/src/safari/safari_24x24.png) | ![IE](https://raw.githubusercontent.com/alrra/browser-logos/master/src/archive/internet-explorer_9-11/internet-explorer_9-11_24x24.png)
--- | --- | --- | --- | ---
✔ | ✔ | ✔ | ✔ | ✘
