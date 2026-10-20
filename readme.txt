=== Admin Studio ===
Contributors: layothedeveloper
Tags: login page, email templates, white label, maintenance mode, two-factor
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Rebrand the login page, system emails, error pages, browser tab details and admin dashboard. Uses your theme's colours by default.

== Description ==

WordPress shows a lot of screens and emails in its own default style: the login page, password reset emails, the "critical error" page, the "briefly unavailable" screen, the admin footer credit. Admin Studio lets you restyle all of them to match your brand, from one place.

It starts from your theme. Colours and fonts are read from theme.json on block themes and from the Customizer on classic themes, the logo comes from your theme logo, and the favicon is your site icon. Change any of them once in the brand kit and every screen follows, or override a value for one area only.

= Login page =

* Four layouts: centered card, split screen with an image, full background, and a restyled WordPress default.
* Background colour, gradient, image or video, with an overlay.
* Logo, form, field style, button and link colours, message above the form, footer text.
* Hide "Lost your password?", "Go to site", the language switcher or the privacy link.
* Custom CSS.
* Live preview in the Customizer. Applies to log in, register, lost password, reset password and the "session expired" login.

= Emails =

* A branded HTML layout for 15 core WordPress emails, including new account, password reset, password changed, email change, comment notifications, automatic update reports and recovery mode.
* Edit the subject, heading, body and button of each email in a visual or HTML editor, with tags such as {user_name}, {reset_link} and {site_name}.
* A plain-text version is generated automatically.
* Preview any email and send yourself a test.

= Error and system pages =

* Critical error page ("There has been a critical error on this website").
* Database connection error page, saved as a static file so it works while the database is down.
* Update maintenance screen ("Briefly unavailable for scheduled maintenance").
* WordPress notice screens such as "Sorry, you are not allowed to access this page".
* An optional branded 404 page.

= Tab and page details =

* Browser tab title format for the admin and login screens.
* Site icon on login, admin and system pages.
* Mobile address bar colour.
* Remove the generator tag, core asset versions and legacy header links.

= Admin branding =

* Replace or remove the WordPress logo in the admin bar.
* Replace or remove the "Thank you for creating with WordPress" footer and the version number.
* Hide dashboard widgets and add your own welcome panel.
* A brand admin colour scheme built from your colours.

= Login protection =

* Custom login URL, with logged-out requests to wp-login.php and /wp-admin blocked.
* Custom dashboard address: move /wp-admin to an address such as /office. Set up automatically on Apache and LiteSpeed, with ready-to-paste rules for Nginx, and switched on only after a test proves it works.
* Limit failed login attempts per IP, with an allow list and a lockout log.
* Honeypot field against bots.
* Two-factor authentication with authenticator apps, email codes and backup codes. Require it for chosen roles.

= Coming soon and maintenance mode =

* A branded holding page with an optional countdown.
* Choose which roles see the real site, allow-list pages, and share a bypass link with clients.
* Maintenance mode sends a 503 status so search engines come back later.

= Tools =

* Export and import settings as JSON.
* Reset any section to its defaults.
* Status panel showing drop-in files, detected theme colours and overlapping plugins.

== Installation ==

1. Upload the `admin-studio` folder to `/wp-content/plugins/`, or install it from Plugins → Add New.
2. Activate the plugin.
3. Go to Appearance → Customize → Admin Studio to design the login page, emails and system pages.
4. Go to Settings → Admin Studio for email wording, admin branding, login protection, coming soon mode and tools.

== Frequently Asked Questions ==

= I moved the login page and forgot the address. How do I get back in? =

Add this line to wp-config.php, above "That's all, stop editing!":

`define( 'ADMINSTUDIO_DISABLE_LOGIN_URL', true );`

Log in at /wp-login.php, check the address under Settings → Admin Studio → Login protection, then remove the line.

= I moved the dashboard and cannot reach it. What do I do? =

Add this line to wp-config.php:

`define( 'ADMINSTUDIO_DISABLE_ADMIN_URL', true );`

The dashboard is back at /wp-admin straight away. Fix the setting under Settings → Admin Studio → Login protection, then remove the line. On Apache, deactivating Admin Studio also removes its .htaccess rules.

= Does moving the dashboard hide that the site runs WordPress? =

It hides the dashboard address, which stops most automated scans. WordPress's own scripts and styles still load from /wp-admin and /wp-includes, so it is one layer of protection, not a disguise.

= I lost my phone and my backup codes. How do I sign in? =

Ask another administrator to tick "Reset two-factor for this user" on your profile. If you are the only administrator, add this line to wp-config.php, sign in, and remove it again:

`define( 'ADMINSTUDIO_DISABLE_2FA', true );`

= Does it change my emails' delivery? =

No. It only changes how core WordPress emails look. Delivery still goes through wp_mail(), so SMTP plugins keep working.

= Why are some error pages saved as files? =

When the database is down or WordPress hits a fatal error, plugins cannot run. WordPress looks for special files in wp-content (php-error.php, db-error.php and maintenance.php) in those moments. Admin Studio writes a static copy of your design to those files each time you save. It never touches a file of that name written by another plugin or your host, and it removes its own files when you deactivate it.

= Does it contact outside services? =

Only when you choose a Google font in the brand kit. See External services below. No other external requests are made, and nothing is tracked.

== External services ==

This plugin can connect to Google Fonts, but only after a site administrator picks a Google font as the heading or body font in Appearance → Customize → Admin Studio → Brand kit and publishes the change.

* What is sent and when: when the change is published, your server requests the font's stylesheet from fonts.googleapis.com and the font files from fonts.gstatic.com, once per font. The request contains the font name and your server's IP address. No visitor or user data is sent.
* What happens next: the files are saved in your uploads folder (`wp-content/uploads/admin-studio/fonts/`) and served from your own site. Visitors' browsers never contact Google.
* Service provider: Google Fonts. Terms of service: https://developers.google.com/fonts/terms. Privacy policy: https://policies.google.com/privacy.

== Third-party libraries ==

* QR Code Generator for JavaScript by Kazuhiko Arase, version 1.4.4, MIT licence. Used to draw the QR code when a user sets up two-factor authentication. Source: https://github.com/kazuhikoarase/qrcode-generator

== Changelog ==

= 1.0.0 =
* First release.
