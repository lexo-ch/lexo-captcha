# LEXO Captcha
Automatically converts images to WebP format upon upload.

---
## Versioning
Release tags are created with Semantic versioning in mind. Commit messages were following convention of [Conventional Commits](https://www.conventionalcommits.org/).

---
## Compatibility
- WordPress version `>=6.4`. Tested and works fine up to `6.7.2`.
- PHP version `>=7.4.1`. Tested and works fine up to `8.3.0`.

---
## Installation
1. Go to the [latest release](https://github.com/lexo-ch/lexo-captcha/releases/latest/).
2. Under Assets, click on the link named `Version x.y.z`. It's a compiled build.
3. Extract zip file and copy the folder into your `wp-content/plugins` folder and activate LEXO Captcha in plugins admin page. Alternatively, you can use downloaded zip file to install it directly from your plugin admin page.

---
## Filters
#### - `lexocaptcha/admin_localized_script`
*Parameters*
`apply_filters('lexocaptcha/admin_localized_script', $args);`
- $args (array) The array which will be used for localizing `lexocaptchaAdminLocalized` variable in the admin.

#### - `lexocaptcha/enqueue/admin-lexocaptcha.js`
*Parameters*
`apply_filters('lexocaptcha/enqueue/admin-lexocaptcha.js', $args);`
- $args (bool) Printing of the file `admin-lexocaptcha.js` (script id is `lexocaptcha/admin-lexocaptcha.js-js`). It also affects printing of the localized `lexocaptchaAdminLocalized` variable.

#### - `lexocaptcha/enqueue/admin-lexocaptcha.css`
*Parameters*
`apply_filters('lexocaptcha/enqueue/admin-lexocaptcha.css', $args);`
- $args (bool) Printing of the file `admin-lexocaptcha.css` (stylesheet id is `lexocaptcha/admin-lexocaptcha.css-css`).

#### - `lexocaptcha/options-page/capability`
*Parameters*
`apply_filters('lexocaptcha/options-page/capability', $args);`
- $args (string) Change minimun user capability for settings page.

#### - `lexocaptcha/options-page/parent-slug`
*Parameters*
`apply_filters('lexocaptcha/options-page/parent-slug', $args);`
- $args (string) Change parent slug for options page.

#### - `lexocaptcha/temporary-disable-period`
*Parameters*
`apply_filters('lexocaptcha/temporary-disable-period', $args);`
- $args (int) Change temporary disable period in mins.

#### - `lexocaptcha/dashboard-widget/capability`
*Parameters*
`apply_filters('lexocaptcha/dashboard-widget/capability', $args);`
- $args (string) Specify the capability that can see the dashboard widget.

#### - `lexocaptcha/dashboard-widget/date-format`
*Parameters*
`apply_filters('lexocaptcha/dashboard-widget/date-format', $args);`
- $args (string) Specify the date format for displaying the disable period in message. The default format is `d.m.Y H:i:s`.

---
## Actions
#### - `lexocaptcha/init`
- Fires on LEXO Captcha init.

#### - `lexocaptcha/localize/admin-lexocaptcha.js`
- Fires right before LEXO Captcha admin script has been enqueued.

#### - `lexocaptcha/plugin-temporarily-disabled`
- Fires when the plugin is temporarily disabled via the dashboard widget.

#### - `lexocaptcha/temporary-disablement-has-ended`
- Fires when the plugin is re-enabled via the dashboard widget or after temporary disable period.

---
## Changelog
Changelog can be seen on [latest release](https://github.com/lexo-ch/lexo-captcha/releases/latest/).
