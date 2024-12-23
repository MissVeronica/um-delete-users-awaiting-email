# UM Users Awaiting email Activation - Version 2.0.0 
Extension to Ultimate Member to Remind or Remove Users who have not replied with an email Activation after Registration.

## UM Settings -> Extensions -> Remind late Users
### Login attempts by late Users
1. * Resend Activation email at Login attempts - Tick to resend the %s template when User is trying to Login without email Activation
2. * Show User's email address in Login error - Tick to show the User's email address where we send the email Activation request.
### Remind late Users
1. * Remind User before activation hash expires - Tick to enable resending of the %s if the secret hash will expire before next WP Cronjob search.
2. * UM Dashboard modal - Tick to activate the Remind late Users WP Cronjob status modal at the UM Dashboard.
3. * UM Resend email Activation - Tick to activate the UM Resend of email Activation in WP Users Page columns and frontpage cog wheel except backend bulk Resend.
4. * UM Action Scheduler - Tick to disable sending the Activation emails via the UM Action Scheduler.
5. * WP All Users filter - Tick to activate a WP All Users filter button for display of the late Users to remind and extra User list columns.
6. * How often to search for expired activations - Tick to have the WP Cronjob look for qualified Users daily. Default is hourly. Daily is at noon with start from tomorrow
7. * Max number of times to remind User - Enter the max number of times to remind a late Activation User. Default value is 3 times.
8. * Wait between sending emails if not SMTP - Tick to add a WP Cronjob wait of five seconds between sending the notification emails to allow WP Mail to process the email transportation.
8. * Email placeholder {reminder_text} text - Text to include in emails sent by the Remind WP Cronjob in other cases placeholder is empty. You can include other email placeholders in this text.
9. * Email placeholder {reminder_text} last text - Text to include in the the last email sent by the Remind WP Cronjob before User is Removed. You can include other email placeholders in this text.

## UM Settings -> Extensions -> Remove late Users
1. * Remove Users with late Email Activation - Tick to activate deletion of Users with unreplied email activations by the Plugin WP Cronjob. - If the WP Cronjob is deactivated you can still do a manual User deletion via the link to WP All Users at the UM Dashboard modal.
2. * Number of days to wait for Email Activation - Enter the number of days until removing a late email activation. - Only values larger than zero are accepted and default value is 3 days.
3. * UM Dashboard modal - Tick to activate the Remove late Users WP Cronjob status modal at the UM Dashboard.
4. * UM Resend email Activation - Tick to activate the UM Resend of email Activation in WP Users Page columns and frontpage cog wheel except backend bulk Resend.
5. * WP All Users filter - Tick to activate a WP All Users filter button for display of a late Activation Users list incl extra User list columns.
6. * How often to search for late Users - Tick to search for qualified Users weekly. Default is daily at midnight.
7. * Admin User info email - Tick to activate a summary email with a removed User list sent to Site Admin.
8. * Send User Notification email - Tick to send the "%s" to the User email if this email Notification is activated.
9. * Date format placeholder {registration_date} - Enter your custom date format. Default is WP site default format %s
10. * Wait between sending emails if not SMTP - Tick to add a WP Cronjob wait of five seconds between sending the notification emails to allow WP Mail to process the email transportation.

## UM Settings -> General -> Users
1. Email activation link expiration (days) - For user registrations requiring email confirmation via a link, how long should the activation link remain active before expiring? If this field is left blank, the activation link will not expire.
2. Recommended 1 or 2 days which will send an Activation Reminder email each or every second day after User Registration at the hour before current email Activation expiration. A Reminder will create a new expiration time after 1 or 2 days.

## UM Dashboard modals
1. Modal side box header - Number of Users with late Activations
2. WP Cronjob next execution time if activated 
3. Link to plugin settings.
4. Link to WP All Users with filter of current late email Activations
5. Link to email Templates

## WP All Users - extra columns
1. User registered - User Registration date and time
2. Reminder before - Current Activation email expires at this time and Remind WP Cronjob will send a new Activation email with a new expiration time during the hour before this time.
3. Remove after %s days - Date and time for User Removal with plugin settings for number of days after Registration

## Site Admin email
1. An email with User ID, Username, First name, Last name, User email, Registration date, Birthdate of the deleted Users by the Cronjob in HTML table format.

## Additional email placeholders
1. {reminder_text} - Text for Late User Reminder email. Blank text for first sent Activation email at Registrations. Email placeholders allowed within the text message.  - Example: This email is a reminder for email activation of the Username {username}. Registered {registration_date}.
2. {registration_date} - User Registration data and time
3. {expiration_time} - current date and time for this User Registration or a Remind Activation when email Activation expires
4. {expiration_days} - current number of days after this User Registration  or a Remind Activation when email Activation expires. Use {expiration_hours} for the last Reminder text.
5. {expiration_hours} - current number of hours after this User Registration or a Remind Activation when email Activation expires. Reduced number of hours for last Reminder when User will be Removed.
6. {max_reminders} - max number of reminders when "Remove late Activations" is inactive
7. {removal_time} - current date and time for this User Registration when User is Removed
8. {removal_days} - current number of days after this User Registration when User is Removed 
9. {activation_days} - current number of days after this User Registration when User is Removed 
10. {timezone} - WP Timezone text

### Default {reminder_text} and translatable
1. Email placeholder {reminder_text} text - This email is a reminder for Activation of your Account {username} Registered at {registration_date}.
2. Email placeholder {reminder_text} last text - NOTE! This is our last reminder about activation of your Account. Your Account will be removed at {removal_time} which is  {removal_days} days after your Registration.

## WP Cronjobs
1. Hook name Remind late Users: um_cron_remind_users_awaiting_email
2. Hook name Remove late Users: um_cron_delete_users_awaiting_email

## Translations or Text changes
1. Use the "Loco Translate" plugin with text domain awaiting-email-activation
2. https://wordpress.org/plugins/loco-translate/
3. Use the "Say What?" plugin with text domain awaiting-email-activation
4. https://wordpress.org/plugins/say-what/

## References
1. WP Cronjob https://developer.wordpress.org/plugins/cron/

## Plugins
### Notification emails
1. Email Parse Shortcode - https://github.com/MissVeronica/um-email-parse-shortcode
2. Additional email Recipients - https://github.com/MissVeronica/um-additional-email-recipients
3. Check & Log Email - https://wordpress.org/plugins/check-email/
4. Mail logging – WP Mail Catcher - https://wordpress.org/plugins/wp-mail-catcher/
### WP CronJob
1. Plugin "Advanced Cron Manager" https://wordpress.org/plugins/advanced-cron-manager/
2. Plugin "WP Crontrol" https://wordpress.org/plugins/wp-crontrol/

## Updates
Version 2.0.0 
1. Two different WP Cronjobs for late Users Remind and Remove late Users both have settings in UM Extensions settings and UM Dashboard status modals.
2. WP Cronjob for User Reminders execute hourly and for User Removal daily at midnight.
3. Additional WP All Users columns for User Registration, Email Activation expiry and also date and time for Removal of the late Activation User.
4. Many new email placeholders incl a placeholder for the Remind user email template (UM default email Activation) text about why a Remind is being sent to the User.
5. Login attempts by late Users will send a Reminder about Activation too.

## Installation & Updates
1. Install and update by downloading the plugin ZIP file via the green "Code" button
2. Install as a new Plugin, which you upload in WordPress -> Plugins -> Add New -> Upload Plugin.
3. Activate the Plugin: Ultimate Member - Users Awaiting Email Activation
