<p align="center">
  <img width="772" height="250" src="https://raw.github.com/andremacola/acf-onyx-poll/master/assets/images/banner-772x250.png">
</p>

# ACF ONYX POLL

> Install from [Wordpress Plugins](https://br.wordpress.org/plugins/acf-onyx-poll/ "Wordpress Plugin Repository") or clone the master branch from this repository

Plugin for polls based on regular Wordpress and acf (advanced custom fields) functionalities using **WP REST API** and ***Javascript*** methods. Fell free to submit a Pull Request.

The main goal of ACF Onyx Poll is to be **totally free, lightweight and simple**. No fancy, colorful and polluted options or donate screens.

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

## FEATURES

- [x] Include poll with a shortcode (ommit the ID to get the latest poll) `[onyx-poll id=XX class="left|right|full"]`
- [x] One click to vote
- [x] Works with cache plugins
- [x] Multiple polls per page
- [x] Support for images
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
- [ ] Native widget for sidebar
- [ ] Multiple style options
- [ ] Custom Gutemberg Block
- [ ] Inject javascript only if current page has a poll
- [ ] Documentation/Wiki for users and developers
- [ ] Integration with Google reCaptcha
- [ ] Email notification when poll is expired (considering)
- [ ] Support for AMP Pages (considering)
- [ ] Select multiple options to vote (considering)
- [ ] Javascript Refactoring for better code view and maintenance (after first release)

## OBSERVATIONS

- I don't support Internet Explorer Browser and all PR's related will be rejected. One of the goals of this plugin is to be js/css lightweight and jQuery free.

- ACF Onyx Poll [register fields via php](https://www.advancedcustomfields.com/resources/register-fields-via-php/) to be able to use Wordpress translation functions for field labels. So you won't be able to view/edit the fields inside ACF Custom Fields Settings.

- To enable a better/faster **CRON** you need to manually set your host cronjob to get *https://domain.tld/wp-json/onyx/polls/cron* endpoint or disable WP-Cron `define('DISABLE_WP_CRON', true);` inside your wp-config and manually create the cron in your host/server

	- **Option 1**: To run every hour set the cron: <br> `0 * * * * wget -q -O - https://domain.tld/wp-json/onyx/polls/cron > /dev/null 2>&1`

	- **Option 2**: if you disable the default WP-Cron: <br> `0 * * * * wget -q -O - https://domain.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1`

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
	--choiceBorderRadius: 100px;
	--closeBorderRadius: 100px;
	--loaderBorderColor: rgb(209, 226, 240);
	--loaderBG: #a3caec;
}
```

Maybe you will have to set the `font-family` and `font-size` for some elements to match your theme style.

If you need a more advanced attributes, the CSS source is located on `assets/css/onyx-poll.css`. You can use as a guide but **never** override the original files.

## DATABASE, USER DATA AND LOGS

At the database side, ACF Onyx Poll uses the default ACF/Wordpress structure to store data (the meta fields). Nothing to worry about here.

For privacy, ACF Onyx Poll **does not store** any user data like ip address, usernames etc...

The only things the plugin saves in the user's browser is some cookies to check the user choice for specific poll, if the modal was previously showed/closed and a timer. You can check the cookies by looking for `onyx_poll_limit_XX, onyx_poll_modal and onyx_poll_choice_XX`.

## BROWSER SUPPORT

![Chrome](https://raw.github.com/alrra/browser-logos/master/src/chrome/chrome_24x24.png) | ![Firefox](https://raw.github.com/alrra/browser-logos/master/src/firefox/firefox_24x24.png) | ![Edge](https://raw.githubusercontent.com/alrra/browser-logos/master/src/edge/edge_24x24.png) | ![Safari](https://raw.github.com/alrra/browser-logos/master/src/safari/safari_24x24.png) | ![IE](https://raw.githubusercontent.com/alrra/browser-logos/master/src/archive/internet-explorer_9-11/internet-explorer_9-11_24x24.png)
--- | --- | --- | --- | ---
✔ | ✔ | ✔ | ✔ | ✘
