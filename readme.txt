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

It also helps you run the dashboard for the people who use it: what each role can do and see, how the dashboard looks, which widgets appear, who is signed in, and how the login is protected.

It starts from your site. Brand colours come from your choice of source: your theme, your logo, the colours visitors see on your front end (page builders included), or custom colours. Fonts are read from your theme, the logo comes from your theme logo, and the favicon is your site icon. Change any of them once in the brand kit and every screen follows, or override a value for one area only.

= Login page =

* Four layouts: centered card, split screen with an image, full background, and a restyled WordPress default.
* Background colour, gradient, image or video, with an overlay.
* Logo, form, field style, button and link colours, message above the form, footer text.
* Arrange the links under the form: stacked, inline, split, inside the card or as full-width buttons. Put "Lost your password?" next to the Password label or above the button, or hide any link.
* Hide the language switcher or the privacy link, or rename the "Go to site" link.
* Replace any wording on the log in, lost password, reset password and registration screens, including labels, buttons, links and messages.
* One message of your own for every failed log in, so the screen does not reveal which usernames exist.
* Custom CSS.
* Live preview in the Customizer, with a screen picker for log in, register, lost password, reset password and the two-factor code. Applies to every login screen, including the "session expired" login.

= Emails =

* A branded HTML layout for 15 core WordPress emails, including new account, password reset, password changed, email change, comment notifications, automatic update reports and recovery mode.
* Edit the subject, heading, body and button of each email in a visual or HTML editor, with tags such as {user_name}, {reset_link} and {site_name}.
* A plain-text version is generated automatically.
* Preview any email and send yourself a test.

= Error and system pages =

* Critical error page ("There has been a critical error on this website").
* Database connection error page, saved as a static file so it works while the database is down.
* Update maintenance screen ("Briefly unavailable for scheduled maintenance").
* Your own "no permission" page in place of "Sorry, you are not allowed to access this page", and your own expired link page.
* Other WordPress notice screens in your design.
* An optional branded 404 page.

= Tab and page details =

* Browser tab title format for the admin, login, error and notice screens, plus the separator, 404 and search titles on your site.
* Upload your own favicon, show it on login, admin and system pages, and stop /favicon.ico showing the WordPress logo.
* Mobile address bar colour.
* Remove the generator tag, core asset versions, legacy, REST API and embed links, and the emoji script; turn off XML-RPC; stop visitors looking up usernames.

= Admin branding =

* Replace or remove the WordPress logo in the admin bar.
* Replace or remove the "Thank you for creating with WordPress" footer and the version number.
* A brand admin colour scheme built from your colours.

= Dashboard widgets =

* Show or hide any dashboard widget per role, including widgets from other plugins such as WooCommerce and Elementor.
* Add your own widgets: rich text, quick links with icons, a support card, a video, "What's new" from a category, recent sign-ups, content counts, recent comments and a Site Health summary. Each can be limited to chosen roles.
* Save your own dashboard arrangement as the default for everyone, and optionally stop people rearranging it.

= Dashboard look =

* Admin templates for the whole dashboard: Studio, Midnight, Minimal and Compact, each with a dark mode.
* Palettes from your brand kit or ready-made, or your own accent, sidebar, background and text colours, with contrast handled automatically.
* Sidebar sections with headings, outline icons, renamed and reordered menu items, and a footer with your account, View site, Sign out and a light/dark switch.
* A greeting on the dashboard home.
* Set a template for the whole site, per role or per user, let people choose their own, or lock it. "Try it on me" lets you look around before rolling it out.

= Roles and permissions =

* Create, clone, rename and delete roles, and edit the capabilities of any role in plain language.
* Give a user several roles, or grant and deny single capabilities.
* Choose which menu items each role sees, and keep chosen roles out of the dashboard.
* Send each role, or one person, to a chosen place after logging in: the dashboard, their profile, the home page, an admin screen, any page on the site or another address.
* Hide the toolbar, or single toolbar items, per role on the site and in the dashboard.
* Automatic role backups and an activity log with CSV download.

= Login protection =

* Custom login URL, with logged-out requests to wp-login.php and /wp-admin blocked.
* Custom dashboard address: move /wp-admin to an address such as /office. Set up automatically on Apache and LiteSpeed, with ready-to-paste rules for Nginx, and switched on only after a test proves it works.
* Limit failed login attempts per IP, with an allow list and a lockout log.
* Honeypot field against bots.
* Two-factor authentication with authenticator apps, email codes and backup codes. Require it for chosen roles.

= Sessions =

* See who is signed in, on which devices (browser, system, phone or computer), from which IP address, and when they were last active.
* Sign out one device, sign someone out everywhere, or block an account.
* Limit how many sessions one person can have: sign out the oldest or refuse the new sign-in, for everyone or chosen roles.
* A sign-in history with failed attempts and administrator actions, filters and CSV download.

= Coming soon and maintenance mode =

* A branded holding page with an optional countdown, or any page you built yourself, including Elementor pages and templates.
* Links on the holding page that lead into the site, including the logo and links to a custom login address, are switched off, so visitors cannot reach pages that are closed. Links to other websites keep working, and the toolbar follows your Toolbar settings.
* Choose which roles see the real site, allow-list pages, and share a bypass link with clients.
* Maintenance mode sends a 503 status so search engines come back later.

= Tools =

* Export and import settings as JSON, choosing the parts: settings and designs, dashboard look, dashboard widgets, menu and toolbar rules, roles, and personal settings. Pages and categories are matched by slug on the new site. A review screen lets you pick the roles to bring across, each with all its capabilities, and shows what would change; administrators keep every capability.
* Reset any section to its defaults.
* Status panel showing drop-in files, detected theme colours and overlapping plugins.

== Installation ==

1. Upload the `admin-studio` folder to `/wp-content/plugins/`, or install it from Plugins → Add New.
2. Activate the plugin.
3. Go to Appearance → Customize → Admin Studio to set the brand kit and design the login page, emails, system pages and coming soon page, with a live preview.
4. Go to Settings → Admin Studio for everything else: email wording, tab and page details, admin branding, dashboard look, dashboard widgets, roles and permissions, sessions, login protection, coming soon mode and tools. The Overview tab switches each feature on or off.

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

Only when you choose a Google font in the brand kit, or add a video widget to the dashboard. See External services below. No other external requests are made, and nothing is tracked.

= Does Admin Studio store personal data? =

The Sessions feature keeps a sign-in history with IP addresses and browser details, and the login protection keeps a lockout log. Both stay in your own database. Recording sign-ins can be switched off under Settings → Admin Studio → Sessions, and suggested wording is added to WordPress's privacy policy guide (Settings → Privacy).

== External services ==

This plugin can connect to Google Fonts, but only after a site administrator picks a Google font as the heading or body font in Appearance → Customize → Admin Studio → Brand kit and publishes the change.

* What is sent and when: when the change is published, your server requests the font's stylesheet from fonts.googleapis.com and the font files from fonts.gstatic.com, once per font. The request contains the font name and your server's IP address. No visitor or user data is sent.
* What happens next: the files are saved in your uploads folder (`wp-content/uploads/admin-studio/fonts/`) and served from your own site. Visitors' browsers never contact Google.
* Service provider: Google Fonts. Terms of service: https://developers.google.com/fonts/terms. Privacy policy: https://policies.google.com/privacy.

The Video dashboard widget embeds a video from the site an administrator chooses, such as YouTube or Vimeo. Nothing is sent until an administrator adds a video widget with a video address.

* What is sent and when: when the widget is first shown, your server asks the video site for the embed code through WordPress's built-in oEmbed support, sending the video address. The answer is kept for a day. Each time someone who can see the widget opens the dashboard, their browser loads the player from the video site, which receives their IP address and browser details as with any embedded video.
* Service providers: whichever site hosts the video. For YouTube: terms https://www.youtube.com/t/terms, privacy policy https://policies.google.com/privacy. For Vimeo: terms https://vimeo.com/terms, privacy policy https://vimeo.com/privacy.

== Third-party libraries ==

* Lucide icons, ISC licence. The dashboard look's outline icons are drawn in the style of, and partly based on, the Lucide icon set. Source: https://lucide.dev
* QR Code Generator for JavaScript by Kazuhiko Arase, version 1.4.4, MIT licence. Used to draw the QR code when a user sets up two-factor authentication. Source: https://github.com/kazuhikoarase/qrcode-generator

== Changelog ==

= 1.0.0 =
* First release.
