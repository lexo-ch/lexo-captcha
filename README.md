# LEXO Captcha
WordPress plugin for simple, self-hosted hidden captcha evaluation.

---
## Versioning
Release tags are created with Semantic versioning in mind. Commit messages were following convention of [Conventional Commits](https://www.conventionalcommits.org/).

---
## Compatibility
- WordPress version `>=6.4`. Tested and works fine up to `6.8.2`.
- PHP version `>=7.4.1`. Tested and works fine up to `8.4.4`.

---
## Installation
1. Go to the [latest release](https://github.com/lexo-ch/lexo-captcha/releases/latest/).
2. Under Assets, click on the link named `Version x.y.z`. It's a compiled build.
3. Extract zip file and copy the folder into your `wp-content/plugins` folder and activate LEXO Captcha in plugins admin page. Alternatively, you can use downloaded zip file to install it directly from your plugin admin page.

---
## Usage
LEXO Captcha attempts to prevent spam using tokens and the limitations surrounding those tokens. Limitations include time until the token is allowed for use and token expiry. LEXO Captcha also tests whether the client has ever physically interacted with the page. In the frontend, a token is requested and passed on with captcha form submissions. The backend can then evaluate the token's validity and react accordingly. On evaluation, the token is consumed (invalidated), and a new one must be requested.

Requesting a token in the frontend can be done using the `LEXO_Captcha.requestToken()` method. LEXO Captcha will do this automatically on page load. Using `LEXO_Captcha.compileData()` (should be awaited), you can generate a JSON string containing data necessary for evaluation. Pass this data on (POST) to the backend with your form submissions, preferrably as the property `"lexo_captcha_data"` in your request body. In the backend, use `lexo_captcha_evaluate_data()` to evaluate the captcha data. The method will consume the token and return `true` if the data is valid, and `false` otherwise. By default, `lexo_captcha_evaluate_data()` pulls the captcha data from `$_POST['lexo_captcha_data']`, but if you would like, you can pass the data on yourself like so: `lexo_captcha_evaluate_data($data)`. This function returns `bool` value. Once the backend responds, in the frontend, you should request a new token (since the old one has been consumed) for the next form submission.

---
## Filters
### Captcha

#### - `lexocaptcha/captcha/client-timestamp-tolerance`
*Parameters*
`apply_filters('lexocaptcha/captcha/client-timestamp-tolerance', $tolerance);`
- `$tolerance` (`int`) The amount of time in ms client-supplied timestamps may be off from server time.
- Default is `300000`.

#### - `submit-cooldown`
*Parameters*
`apply_filters('lexocaptcha/captcha/submit-cooldown', $cooldown);`
- `$cooldown` (`int`) The amount of time in ms that a client must wait after requesting a token before being allowed to use it.
- Default is `15000`.

#### - `lexocaptcha/captcha/max-interaction-age`
*Parameters*
`apply_filters('lexocaptcha/captcha/max-interaction-age', $max_age);`
- `$max_age` (`int`) The amount of time in ms before the client-supplied interaction data expires.
- Default is `3600000`.

#### - `lexocaptcha/captcha/max-token-age`
*Parameters*
`apply_filters('lexocaptcha/captcha/max-token-age', $max_age);`
- `$max_age` (`int`) The amount of time in ms before the requested token expires.
- Default is `3600000`.

---
### Loader

#### - `lexocaptcha/loader/front-localized-script`
*Parameters*
`apply_filters('lexocaptcha/loader/front-localized-script', $globals);`
- `$globals` (`array`) The variables to pass onto the frontend script. Will be accessible in `lexocaptchaFrontLocalized` JavaSript variable.

---
### Statistics Page

#### - `lexocaptcha/statistics-page/parent-slug`
*Parameters*
`apply_filters('lexocaptcha/statistics-page/parent-slug', $parent_slug);`
- `$parent_slug` (`string`) The slug of the parent menu page for the statistics page.
- Default is `options-general.php`.

#### - `lexocaptcha/statistics-page/capability`
*Parameters*
`apply_filters('lexocaptcha/statistics-page/capability', $capability);`
- `$capability` (`string`) The user capability required to view the statistics page.
- Default is `manage_options`.

#### - `lexocaptcha/statistics-page/date-format`
*Parameters*
`apply_filters('lexocaptcha/statistics-page/date-format', $date_format);`
- `$date_format` (`string`) The date format which will be used in statistics page.
- Default is `d.m.Y H:i:s`.
---
## Actions
#### - `lexocaptcha/init`
- Fires on LEXO Captcha init.

#### - `lexoscripts/localize/front.js`
- Fires right before LEXO Captcha frontend script has been enqueued.

## Custom JavaScript Events

#### - `lexocaptcha:response`
- Fires on form submit.

Event can be listened in your theme using `addEventListener`.

##### Example

```js
document.addEventListener('lexocaptcha:response', (event) => {
  const {
    form,
    success
  } = event.detail;

  console.log('The form element:', form);
  console.log({ success });

  // You can check which form it was and do something specific for this form
  if (form.dataset.action === 'send_contact_email') {
    console.log('This is the contact form. Do some specific action.');
  }
});
```
---
## Changelog
Changelog can be seen on [latest release](https://github.com/lexo-ch/lexo-captcha/releases/latest/).
