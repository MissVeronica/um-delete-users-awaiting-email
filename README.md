# UM Delete Users Awaiting email
## Release date Monday 2024-10-14
Extension to Ultimate Member to delete Users who have not replied with an email activation after Registration either by a Plugin WP Cronjob or manual deletion by Site Admin from a dedicated WP All Users page.

## UM Settings -> General -> Users
1. Delete Users with late Activation - Tick to activate deletion of Users with unreplied email activations by the plugin WP Cronjob.
2. Admin User info email - Tick to activate an email with a deleted User list to Site Admin.
3. Number of days to wait for Activation - Enter the number of days for accepting an email activation. Only values larger than zero are accepted and default value is 5 days.

## UM Dashboard modal
1. Modal side box header - Number of Users with late Activations
2. WP Cronjob if activated next execution daily time - default midnight
3. WP Cronjob inactive thera is an Admin possibility to delete late Activation Users in dedicated WP All Users page displaying only the late Users.
4. Link to plugin settings.
5. Note if old code snippet detected installed and instructions for total deletion.

## WP All Users
1. The link to .../wp-admin/users.php?delete_users_awaiting=email_activation will list late User Activations with plugin settings.

## Site Admin email
1. An email with User ID, Username, First name, Last name, User email, Registration date, Birthdate of the deleted Users by the Cronjob in HTML table format.

## WP Cronjob
1. Hook name: um_cron_delete_users_awaiting_email

## Translations or Text changes
1. Use the "Say What?" plugin with text domain ultimate-member
2. https://wordpress.org/plugins/say-what/

## References
1. WP Cronjob https://developer.wordpress.org/plugins/cron/
2. Plugin "Advanced Cron Manager" https://wordpress.org/plugins/advanced-cron-manager/
3. Plugin "WP Crontrol" https://wordpress.org/plugins/wp-crontrol/

## Updates
None

## Installation & Updates
1. Install and update by downloading the plugin ZIP file via the green "Code" button
2. Install as a new Plugin, which you upload in WordPress -> Plugins -> Add New -> Upload Plugin.
3. Activate the Plugin: Ultimate Member - Delete Users Awaiting Email Activation
