<?php
/**
 * Plugin Name:     Ultimate Member - Users Awaiting Email Activation
 * Description:     Extension to Ultimate Member to Remind or Remove Users who have not replied with an email Activation after Registration.
 * Version:         2.0.0
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica?tab=repositories
 * Plugin URI:      https://github.com/MissVeronica/um-delete-users-awaiting-email
 * Update URI:      https://github.com/MissVeronica/um-delete-users-awaiting-email
 * Text Domain:     awaiting-email-activation
 * Domain Path:     /languages
 * UM version:      2.9.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'UM' ) ) return;

class Users_Awaiting_Email_Activation {


    public $date_time_format       = '';
    public $custom_date_format     = '';

    public $reminder_status        = '';
    public $remove_timestamp_UTC   = '';
    public $remove_date_local      = '';

    public $slug                   = 'delete_awaiting_activation';
    public $wp_cronjob_sleep       = 5;
    public $default_awaiting_days  = 3;
    public $remind_users           = 0;
    public $users_column_hooks     = false;

    public $message                = array();
    public $plugin_status          = array();
    public $user_info              = array();
    public $late_users             = array();
    public $late_users_cache       = false;

    public $wp_cron_event          = 'um_cron_delete_users_awaiting_email';
    public $wp_cron_remind         = 'um_cron_remind_users_awaiting_email';
    public $current_cron_job       = '';


    public function __construct() {

        define( 'Plugin_Textdomain_DUAE', 'awaiting-email-activation' );

        if ( is_admin() && ! wp_doing_cron()) {

            define( 'Plugin_Basename_DUAE', plugin_basename( __FILE__ ));
            define( 'Plugin_Path_DUAE',     plugin_dir_path( __FILE__ ));

            add_filter( 'um_settings_structure',      array( $this, 'settings_structure_remind_users_awaiting_email' ), 10, 1 );
            add_filter( 'um_settings_structure',      array( $this, 'settings_structure_remove_users_awaiting_email' ), 10, 1 );

            if ( UM()->options()->get( 'delete_users_awaiting_filter' ) == 1 ) {
                add_filter( 'pre_user_query',          array( $this, 'filter_users_remove_users_awaiting_email' ), 99 );
            }

            if ( UM()->options()->get( 'remind_users_awaiting_filter' ) == 1 ) {
                add_filter( 'pre_user_query',          array( $this, 'filter_users_reminder_users_awaiting_email' ), 99 );
            }

            add_filter( 'um_email_notifications',      array( $this, 'email_notifications_delete_user' ), 100, 1 );
            add_action( 'manage_users_extra_tablenav', array( $this, 'late_users_awaiting_email_filter_status' ), 10, 1 );

            if ( UM()->options()->get( 'delete_users_awaiting_dashboard' ) == 1 ) {
                add_action( 'load-toplevel_page_ultimatemember', array( $this, 'load_metabox_delete_users_awaiting_email' ) );
            }

            if ( UM()->options()->get( 'remind_users_awaiting_dashboard' ) == 1 ) {
                add_action( 'load-toplevel_page_ultimatemember', array( $this, 'load_metabox_remind_users_awaiting_email' ) );
            }

            add_filter( 'plugin_action_links_' . Plugin_Basename_DUAE, array( $this, 'plugin_settings_link' ), 10, 1 );

            register_deactivation_hook( __FILE__, array( $this, 'deactivate_current_cronjobs' ));
            register_activation_hook(   __FILE__, array( $this, 'activation_plugin_users_awaiting_email' ));
        }

        if ( ! wp_doing_cron() && UM()->options()->get( 'logincheck_users_awaiting_email' ) == 1 ) {

            add_action( 'um_submit_form_errors_hook_logincheck',  array( $this, 'submit_form_errors_hook_logincheck' ), 10, 2 );
            add_filter( 'um_custom_authenticate_error_codes',     array( $this, 'custom_authenticate_error_codes' ), 10, 1 );
            add_filter( 'um_custom_error_message_handler',        array( $this, 'logincheck_error_message_handler' ), 10, 3 );
        }

        add_filter( 'um_template_tags_patterns_hook',             array( $this, 'add_placeholder_remind_remove' ), 9, 1 );
        add_filter( 'um_template_tags_replaces_hook',             array( $this, 'add_replace_placeholder_remind_remove' ), 9, 1 );
        add_action( 'um_after_user_hash_is_changed',              array( $this, 'after_user_hash_is_changed_remind_expiration' ), 10, 3 );

        add_filter( 'um_users_column_account_status_row_actions', array( $this, 'um_users_column_account_status_remove_action' ), 10, 2 );
        add_filter( 'um_admin_user_actions_hook',                 array( $this, 'um_admin_user_actions_hook_remove_action' ), 10, 2 );
        add_filter( 'um_admin_bulk_user_actions_hook',            array( $this, 'um_admin_bulk_user_actions_hook_remove_action' ), 10, 1 );

        add_filter( 'pre_as_enqueue_async_action',                array( $this, 'pre_as_enqueue_async_action_activation_email' ), 10, 5 );

        add_action( 'plugins_loaded',                             array( $this, 'users_awaiting_email_activation_plugin_loaded' ), 0 );
        add_action( 'init',                                       array( $this, 'do_plugin_page_init_setup' ));
    }

    public function users_awaiting_email_activation_plugin_loaded() {

        $locale = ( get_locale() != '' ) ? get_locale() : 'en_US';
        load_textdomain( Plugin_Textdomain_DUAE, WP_LANG_DIR . '/plugins/' . Plugin_Textdomain_DUAE . '-' . $locale . '.mo' );
        load_plugin_textdomain( Plugin_Textdomain_DUAE, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    public function plugin_settings_link( $links ) {

        $url = get_admin_url() . 'admin.php?page=um_options&tab=extensions&section=remind-late-users';
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings' ) . '</a>';

        return $links;
    }

    public function deactivate_current_cronjobs() {

        $this->remove_current_cronjob( $this->wp_cron_remind );
        $this->remove_current_cronjob( $this->wp_cron_event );
    }

    public function activation_plugin_users_awaiting_email() {

        $this->remove_current_cronjob( 'um_cron_delete_users_cron' );       // Old UM plugin free extension
        $this->remove_current_cronjob( 'um_cron_resend_activation_link' );  // Old UM plugin free extension

        $located = UM()->mail()->locate_template( $this->slug );

        if ( ! is_file( $located ) || filesize( $located ) == 0 ) {
            $located = wp_normalize_path( STYLESHEETPATH . '/ultimate-member/email/' . $this->slug . '.php' );
        }

        clearstatcache();
        if ( ! file_exists( $located ) || filesize( $located ) == 0 ) {

            wp_mkdir_p( dirname( $located ) );

            $email_source = file_get_contents( Plugin_Path_DUAE . $this->slug . '.php' );
            file_put_contents( $located, $email_source );

            if ( ! file_exists( $located ) ) {
                file_put_contents( um_path . 'templates/email/' . $this->slug . '.php', $email_source );
            }
        }
    }

    public function do_plugin_page_init_setup() {

        $this->date_time_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

        if ( is_admin() || wp_doing_cron()) {

            if ( UM()->options()->get( 'delete_users_awaiting_email' ) == 1 ) {

                add_action( $this->wp_cron_event, array( $this, 'delete_users_awaiting_email_cronjob' ));

                if ( UM()->options()->get( $this->slug . '_on' ) == 1 && UM()->options()->get( 'delete_users_awaiting_notification' ) == 1 ) {

                    add_action( 'um_delete_user',  array( $this, 'um_delete_users_notification_email' ), 10, 1 );

                    $this->custom_date_format = ( empty( UM()->options()->get( 'delete_users_awaiting_placeholder' ) )) ? $this->date_time_format :
                                                                                                                          sanitize_text_field( UM()->options()->get( 'delete_users_awaiting_placeholder' ));
                }

                $this->schedule_next_cronjob( $this->wp_cron_event );

            } else {

                $this->remove_current_cronjob( $this->wp_cron_event );
            }

            if ( UM()->options()->get( 'remind_users_awaiting_email' ) == 1 ) {

                add_action( $this->wp_cron_remind, array( $this, 'resend_activation_notify' ) );

                $this->schedule_next_cronjob( $this->wp_cron_remind );

            } else {

                $this->remove_current_cronjob( $this->wp_cron_remind );
            }
        }
    }

    public function get_next_cronjob_timestamp( $cronjob, $bool = true ) {

        $timestamp_UTC = false;

        if ( $this->current_cron_job == $cronjob ) {

            $timestamp_UTC = wp_next_scheduled( $cronjob );

        } else {

            $next_cron_event = wp_get_scheduled_event( $cronjob );

            if ( ! empty( $next_cron_event->timestamp )) {

                $timestamp_UTC = intval( $next_cron_event->timestamp );
                if ( $bool ) {
                    $timestamp_UTC += intval( $next_cron_event->interval );
                }
            }
        }

        return $timestamp_UTC;
    }

    public function find_users_for_resend_activation() {

        $remind_timestamp_UTC = $this->get_next_cronjob_timestamp( $this->wp_cron_remind );
        $users = array();

        if ( ! empty( $remind_timestamp_UTC )) {

            $args = array(
                            'fields'     => 'ID',
                            'number'     => -1,
                            'meta_query' => array(
                                                    'relation' => 'AND',

                                                    array(
                                                            'key'     => 'account_secret_hash_expiry',
                                                            'value'   => $remind_timestamp_UTC,
                                                            'compare' => '<',
                                                            'type'    => 'numeric'
                                                        ),

                                                    array(
                                                            'key'     => 'account_status',
                                                            'value'   => 'awaiting_email_confirmation',
                                                            'compare' => '='
                                                        )
                                                ),
                        );

            $users = get_users( $args );

            foreach( $users as $key => $user ) {

                um_fetch_user( $user );
                $this->get_user_removal_local_date_time();

                if ( $this->remove_timestamp_UTC == um_user( 'account_secret_hash_expiry' ) ) {
                    unset( $users[$key] );
                }
            }
        }

        return $users;
    }

    public function find_users_without_confirmation() {

        if ( UM()->options()->get( 'delete_users_awaiting_email' ) == 1 ) {
            if ( ! $this->late_users_cache ) {

                $args = array(
                                'fields'     => 'ID',
                                'number'     => -1,
                                'date_query' => array(
                                                        array( 'before'    => $this->get_delete_users_awaiting_days_ago_UTC(),
                                                               'inclusive' => true
                                                            ),
                                                    ),
                                'meta_query' => array(
                                                        'relation' => 'AND',
                                                        array(
                                                                'key'     => 'account_status',
                                                                'value'   => 'awaiting_email_confirmation',
                                                                'compare' => '='
                                                            )
                                                    )
                            );

                $this->late_users = get_users( $args );
                sort( $this->late_users );
                $this->late_users_cache = true;
            }
        }
    }

    public function get_delete_users_awaiting_days_ago_UTC() {

        $delete_users_awaiting_days        = $this->get_delete_users_awaiting_days();
        $next_remove_cronjob_timestamp_UTC = $this->get_next_cronjob_timestamp( $this->wp_cron_event );

        $next_remove_cronjob_timestamp_UTC = $next_remove_cronjob_timestamp_UTC - ( intval( $delete_users_awaiting_days ) * DAY_IN_SECONDS ) - DAY_IN_SECONDS;

        return $this->get_timestamp_formatted_local( $next_remove_cronjob_timestamp_UTC, false, 'Y/m/d H:i:s' );
    }

    public function filter_users_remove_users_awaiting_email( $filter_query ) {

        global $wpdb;
        global $pagenow;

        if ( is_admin() && $pagenow == 'users.php' ) {

            if ( isset( $_REQUEST['um_user_status'] ) && sanitize_key( $_REQUEST['um_user_status'] ) == 'awaiting_email_confirmation' ) {

                $this->activate_users_column_hooks();
            }

            if ( ! empty( $_REQUEST['delete_users_awaiting'] ) && isset( $_REQUEST['users_awaiting_filter'] ) ) {

                if ( sanitize_key( $_REQUEST['delete_users_awaiting'] ) === 'email_activation' ) {

                    $this->activate_users_column_hooks();

                    $registration_date_UTC = $this->get_delete_users_awaiting_days_ago_UTC();

                    $filter_query->query_where = str_replace( 'WHERE 1=1',
                                                                $wpdb->prepare(
                                                                        "WHERE 1=1 AND 
                                                                            user_registered < %s
                                                                            AND {$wpdb->users}.ID IN (
                                                                                SELECT user_id FROM {$wpdb->usermeta}
                                                                                    WHERE meta_key = 'account_status'
                                                                                    AND meta_value = 'awaiting_email_confirmation')",
                                                                        $registration_date_UTC ),
                                                                $filter_query->query_where );
                }
            }
        }
    }

    public function filter_users_reminder_users_awaiting_email( $filter_query ) {

        global $wpdb;
        global $pagenow;

        if ( is_admin() && $pagenow == 'users.php' ) {

            if ( isset( $_REQUEST['um_user_status'] ) && sanitize_key( $_REQUEST['um_user_status'] ) == 'awaiting_email_confirmation' ) {

                $this->activate_users_column_hooks();
            }

            if ( ! empty( $_REQUEST['delete_users_awaiting'] ) && isset( $_REQUEST['users_awaiting_filter'] ) ) {

                if ( sanitize_key( $_REQUEST['delete_users_awaiting'] ) === 'remind_activation' ) {

                    $this->activate_users_column_hooks();

                    $timestamp_UTC = $this->get_next_cronjob_timestamp( $this->wp_cron_remind );

                    if ( ! empty( $timestamp_UTC )) {

                        $filter_query->query_where = str_replace( 'WHERE 1=1',
                                                                    $wpdb->prepare(
                                                                        "WHERE 1=1 AND (
                                                                                ID IN ( SELECT user_id FROM {$wpdb->usermeta}
                                                                                    WHERE meta_key = 'account_secret_hash_expiry' AND meta_value < %d )
                                                                                AND ID IN (
                                                                                    SELECT user_id FROM {$wpdb->usermeta} WHERE
                                                                                    meta_key = 'account_status' AND meta_value = 'awaiting_email_confirmation' ))",
                                                                            $timestamp_UTC ),
                                                                    $filter_query->query_where );
                    }
                }
            }
        }
    }

    public function resend_activation_notify() {

        if ( wp_doing_cron()) {

            $this->current_cron_job = $this->wp_cron_remind;

            $users = $this->find_users_for_resend_activation();

            if ( ! empty( $users ) && is_array( $users )) {

                $this->find_users_without_confirmation();

                foreach ( $users as $user_id ) {

                    um_fetch_user( $user_id );

                    if ( 'awaiting_email_confirmation' === um_user( 'account_status' ) ) {

                        if ( UM()->options()->get( 'delete_users_awaiting_email' ) != 1 ) {

                            $timestamp_reg_UTC = strtotime( um_user( 'registration_date' ));
                            $wait_period       = absint( UM()->options()->get( 'activation_link_expiry_time' )) * DAY_IN_SECONDS;
                            $max_number        = absint( UM()->options()->get( 'remind_users_awaiting_max' ));

                            if ( time() > $timestamp_reg_UTC + --$max_number * $wait_period ) {

                                UM()->user()->remove_cache( $user_id );
                                continue;
                            }
                        }

                        UM()->common()->users()->send_activation( $user_id, true );

                        if ( UM()->options()->get( 'enable_action_scheduler') != 1 ) {
                            if ( UM()->options()->get( 'remind_users_awaiting_sleep' ) == 1 ) {

                                sleep( $this->wp_cronjob_sleep );
                            }
                        }
                    }

                    UM()->user()->remove_cache( $user_id );
                }
            }
        }
	}

    public function add_placeholder_remind_remove( $placeholders ) {

        $placeholders   = array_merge( array( '{reminder_text}' ), $placeholders );

        $placeholders[] = '{registration_date}';
        $placeholders[] = '{expiration_time}';
        $placeholders[] = '{expiration_days}';
        $placeholders[] = '{expiration_hours}';
        $placeholders[] = '{max_reminders}';
        $placeholders[] = '{removal_time}';
        $placeholders[] = '{removal_days}';
        $placeholders[] = '{activation_days}';
        $placeholders[] = '{timezone}';

        return $placeholders;
    }

    public function add_replace_placeholder_remind_remove( $replace_placeholders ) {

        $this->find_users_without_confirmation();

        if ( in_array( um_user( 'ID' ), $this->late_users )) { 
            $remind_user_text = ( empty( UM()->options()->get( 'remind_users_awaiting_last_text' ))) ? esc_html__( 'NOTE! This is our last reminder about activation of your Account. Your Account will be removed at {removal_time} which is {removal_days} days after your Registration.', 'awaiting-email-activation' ) :
                                                                                                       UM()->options()->get( 'remind_users_awaiting_last_text' );
        } else {
            $remind_user_text = ( empty( UM()->options()->get( 'remind_users_awaiting_text' ))) ? esc_html__( 'This email is a reminder for Activation of your Account {username} Registered at {registration_date}.', 'awaiting-email-activation' ) :
                                                                                                  UM()->options()->get( 'remind_users_awaiting_text' );
        }

        $get_reminder_user_text = ( $this->current_cron_job == $this->wp_cron_remind ) ? sanitize_text_field( $remind_user_text ) : 'xxx';
        $replace_placeholders   = array_merge( array( $get_reminder_user_text ), $replace_placeholders );

        $replace_placeholders[] = $this->get_timestamp_formatted_local( strtotime( um_user( 'user_registered' )), true );
        $replace_placeholders[] = $this->get_timestamp_formatted_local( um_user( 'account_secret_hash_expiry' ), true );
        $replace_placeholders[] = absint( UM()->options()->get( 'activation_link_expiry_time' ));
        $replace_placeholders[] = $this->get_hours_until_hash_expiry();
        $replace_placeholders[] = ( UM()->options()->get( 'delete_users_awaiting_email' ) != 1 ) ? absint( UM()->options()->get( 'remind_users_awaiting_max' )) : '';
        $replace_placeholders[] = $this->remove_date_local;
        $replace_placeholders[] = $this->get_delete_users_awaiting_days();
        $replace_placeholders[] = $this->get_delete_users_awaiting_days();
        $replace_placeholders[] = get_option( 'timezone_string' );

        return $replace_placeholders;
    }

    public function get_user_removal_local_date_time() {

        $timestamp_UTC              = strtotime( um_user( 'user_registered' )) + DAY_IN_SECONDS + $this->get_delete_users_awaiting_days() * DAY_IN_SECONDS;
        $remove_date_local          = $this->get_timestamp_formatted_local( $timestamp_UTC, true, 'Y/m/d' ) . ' 00:00:00';
        $this->remove_timestamp_UTC = strtotime( get_gmt_from_date( $remove_date_local ));

        return $remove_date_local;
    }

    public function get_hours_until_hash_expiry() {

        $hours = absint( UM()->options()->get( 'activation_link_expiry_time' )) * 24;

        $this->remove_date_local = $this->get_user_removal_local_date_time();
        $current_timestamp_UTC   = time() + ( $hours * HOUR_IN_SECONDS );
        $hours = ( $current_timestamp_UTC > $this->remove_timestamp_UTC ) ? $hours - intval(( $current_timestamp_UTC - $this->remove_timestamp_UTC + HOUR_IN_SECONDS ) / HOUR_IN_SECONDS ) : $hours;

        return esc_attr( $hours );
    }

    public function get_timestamp_formatted_local( $timestamp, $utc = false, $format = '' ) {

        if ( empty( $format )) {
            $format = $this->date_time_format;
        }

        $local_formatted = ( $utc ) ? get_date_from_gmt( date( 'Y-m-d H:i:s', intval( $timestamp )), $format ) : date( $format, intval( $timestamp ));

        return esc_attr( $local_formatted );
    }

    public function after_user_hash_is_changed_remind_expiration( $user_id, $hash, $expiration_UTC ) {

        if ( UM()->options()->get( 'delete_users_awaiting_email' ) == 1 ) {

            $this->find_users_without_confirmation();
            if ( in_array( $user_id, $this->late_users )) {

                $remove_timestamp_UTC = intval( wp_next_scheduled( $this->wp_cron_event ));

                if ( $expiration_UTC > $remove_timestamp_UTC ) {
                    update_user_meta( $user_id, 'account_secret_hash_expiry', $remove_timestamp_UTC );
                }
            }
        }
    }

    public function cronjob_settings( $cronjob ) {

        $cron_time_UTC = new DateTime( 'tomorrow', new DateTimeZone( wp_timezone_string() ) );

        if ( $cronjob == $this->wp_cron_event ) {

            $recurrence    = ( UM()->options()->get( 'delete_users_awaiting_weekly' ) == 1 ) ? 'weekly': 'daily';
            $timestamp_UTC = ( $recurrence == 'daily' ) ? $cron_time_UTC->getTimestamp() : $cron_time_UTC->getTimestamp() + WEEK_IN_SECONDS;
        }

        if ( $cronjob == $this->wp_cron_remind ) {

            $recurrence    = ( UM()->options()->get( 'remind_users_awaiting_hourly' ) == 1 ) ? 'daily' : 'hourly';
            $timestamp_UTC = ( $recurrence == 'hourly' ) ? time() + HOUR_IN_SECONDS : $cron_time_UTC->getTimestamp() + ( 12 * HOUR_IN_SECONDS );
        }

        return array( 'recurrence' => $recurrence, 'timestamp_UTC' => $timestamp_UTC );
    }

    public function schedule_next_cronjob( $cron_job ) {

        $settings = $this->cronjob_settings( $cron_job );
        $next_cron_event = wp_get_scheduled_event( $cron_job );

        if ( ! empty( $next_cron_event ) && $next_cron_event->schedule != $settings['recurrence'] ) {

            wp_clear_scheduled_hook( $cron_job );
            $next_cron_event = false;
        }

        if ( empty( $next_cron_event )) {

            wp_schedule_event( $settings['timestamp_UTC'], $settings['recurrence'], $cron_job );
        }
    }

    public function remove_current_cronjob( $cron_job ) {

        if ( wp_next_scheduled( $cron_job ) ) {
            wp_clear_scheduled_hook( $cron_job );
        }
    }

    public function load_metabox_remind_users_awaiting_email() {

        $header = '';

        $this->remind_users = $this->find_users_for_resend_activation();
        $counter = count( $this->remind_users );
        $this->message['remind'] = '';

        switch( $counter ) {

            case 0:     $header = esc_html__( 'No Users are close to email Activation expiry', 'awaiting-email-activation' );
                        break;

            case 1:     $header = esc_html__( 'One User is close to email Activation expiry', 'awaiting-email-activation' );

                        if ( UM()->options()->get( 'remind_users_awaiting_email' ) == 1 ) {
                            $this->message['remind'] = esc_html__( 'This User will be notified by the next Remind WP Cronjob.', 'awaiting-email-activation' );

                        } else {
                            $this->message['remind'] = esc_html__( 'This User can be notified by yourself via the button link.', 'awaiting-email-activation' );
                        }
                        break;

            default:    $header = sprintf( esc_html__( '%d Users are close to email Activation expiry', 'awaiting-email-activation' ), $counter );

                        if ( UM()->options()->get( 'remind_users_awaiting_email' ) == 1 ) {
                            $this->message['remind'] = sprintf( esc_html__( 'These %s Users will be notified by the next Remind WP Cronjob.', 'awaiting-email-activation' ), $counter );

                        } else {
                            $this->message['remind'] = sprintf( esc_html__( 'These %s Users can be notified by yourself via the button link.', 'awaiting-email-activation' ), $counter );
                        }
                        break;
        }

        add_meta_box(   'um-metaboxes-sidebox-remind-users-awaiting',
                        $header,
                        array( $this, 'toplevel_page_remind_users_awaiting' ),
                        'toplevel_page_ultimatemember', 'side', 'core'
                    );
    }

    public function toplevel_page_remind_users_awaiting() {

        $cronjob_UTC = wp_next_scheduled( $this->wp_cron_remind );
        $this->wp_next_scheduled_text( $cronjob_UTC, 'Remind' );

        $url_template = get_site_url() . '/wp-admin/admin.php?page=um_options&tab=email&email=checkmail_email';
        $template = sprintf( '<a href="%s" class="button" title="%s">%s</a>',
                                    esc_url( $url_template ),
                                    esc_html__( 'UM email template: Account Activation Email', 'awaiting-email-activation' ),
                                    esc_html__( 'UM email template', 'awaiting-email-activation' )); ?>

        <div> <?php echo implode( '</div><div>', $this->plugin_status ); ?></div>
        <div> <?php echo $this->message['remind']; ?></div>
        <hr>
        <table>
            <tr><td><?php $this->display_plugin_settings_link( 'remind-late-users' ); ?></td><?php

            if ( UM()->options()->get( 'remind_users_awaiting_filter' ) == 1 && count( $this->remind_users ) > 0 ) {

                $wp_all_users_url = admin_url( 'users.php' ) . '?delete_users_awaiting=remind_activation&users_awaiting_filter=true'; ?>
                    <td style="padding-left:5px;">
                    <?php
                        echo '<a href="' . esc_url( $wp_all_users_url ) . '" class="button"
                                title="' . esc_html__( 'Users to be reminded by the next WP Cronjob', 'awaiting-email-activation' ) . '">' .
                                esc_html__( 'All Late Users to Remind', 'awaiting-email-activation' ) . '</a>'; ?></td>
<?php       } ?>
                <td style="padding-left:5px;"> <?php echo $template; ?> </td>
            </tr>
        </table>
<?php

    }

    public function load_metabox_delete_users_awaiting_email() {

        $wp_all_users_url = admin_url( 'users.php' ) . '?delete_users_awaiting=email_activation&users_awaiting_filter=true';

        $this->find_users_without_confirmation();
        $counter = count( $this->late_users );
        $this->message['remove'] = '';

        switch( $counter ) {

            case 0:  $header = esc_html__( 'No Users are late with their email Activations', 'awaiting-email-activation' );
                     break;

            case 1:  $header = esc_html__( 'One User is late with the email Activation', 'awaiting-email-activation' );

                     if ( UM()->options()->get( 'delete_users_awaiting_email' ) == 1 ) {
                        $this->message['remove'] = esc_html__( 'This User will be removed by the next Remove WP Cronjob.', 'awaiting-email-activation' );

                     } else {
                        $this->message['remove'] = esc_html__( 'This User can be removed by yourself via the button link.', 'awaiting-email-activation' );
                     }
                     break;

            default: $header = sprintf( esc_html__( '%s Users are late with their email Activations', 'awaiting-email-activation' ), $counter );

                     if ( UM()->options()->get( 'delete_users_awaiting_email' ) == 1 ) {
                        $this->message['remove'] = sprintf( esc_html__( 'These %s Users will be removed by the next Remove WP Cronjob.', 'awaiting-email-activation' ), $counter );

                     } else {
                        $this->message['remove'] = sprintf( esc_html__( 'These %s Users can be removed by yourself via the button link.', 'awaiting-email-activation' ), $counter );
                     }
                     break;
        }

        add_meta_box(   'um-metaboxes-sidebox-delete-users-awaiting',
                        $header,
                        array( $this, 'toplevel_page_delete_users_awaiting' ),
                        'toplevel_page_ultimatemember', 'side', 'core'
                    );
    }

    public function toplevel_page_delete_users_awaiting() {

        $cronjob_UTC = wp_next_scheduled( $this->wp_cron_event );
        $this->wp_next_scheduled_text( $cronjob_UTC, 'Remove' );

        if ( UM()->options()->get( 'delete_users_awaiting_notification' ) == 1 ) {

            $url_template = get_site_url() . '/wp-admin/admin.php?page=um_options&tab=email&email=' . $this->slug;
            $template = sprintf( '<a href="%s" class="button" title="%s">%s</a>',
                                        esc_url( $url_template ),
                                        esc_html__( 'Plugin active email template: Delete Users Awaiting Email Activation', 'awaiting-email-activation' ),
                                        esc_html__( 'Email template', 'awaiting-email-activation' ));
        } ?>

        <div> <?php echo implode( '</div><div>', $this->plugin_status ); ?></div>
        <div> <?php echo $this->message['remove']; ?></div>
        <hr>
        <table>
            <tr><td> <?php $this->display_plugin_settings_link( 'remove-late-users' ); ?></td>

            <?php
                    if ( UM()->options()->get( 'delete_users_awaiting_filter' ) == 1 && ! empty( $this->message['remove'] )) {

                        $wp_all_users_url = admin_url( 'users.php' ) . '?delete_users_awaiting=email_activation&users_awaiting_filter=true'; ?>
                        <td style="padding-left:5px;">
                        <?php
                            echo '<a href="' . esc_url( $wp_all_users_url ) . '" class="button"
                                    title="' . esc_html__( 'Users to be removed by the next WP Cronjob', 'awaiting-email-activation' ) . '">' .
                                    esc_html__( 'All Late Users to Remove', 'awaiting-email-activation' ) . '</a>'; ?></td>
            <?php   }

                    if ( UM()->options()->get( 'delete_users_awaiting_notification' ) == 1 ) { ?>
                        <td style="padding-left:5px;"> <?php echo $template; ?> </td>
<?php               } ?>
            </tr>
        </table>
<?php
        if ( function_exists( 'um_delete_users_awaiting_email' )) { ?>

            <hr>
            <div> <?php echo esc_html__( 'Note', 'awaiting-email-activation' ); ?></div>
            <div> <?php echo esc_html__( 'Remove the old UM code snippet function \'um_delete_users_awaiting_email\' and the \'wp_schedule_event\' code lines from your active theme\'s functions.php file.', 'awaiting-email-activation' ); ?></div>
            <div> <?php echo esc_html__( 'The code snippet WP Cronjob will be removed by this plugin.', 'awaiting-email-activation' ); ?></div>
<?php
        }
    }

    public function display_plugin_settings_link( $type ) {

        $settings_url = get_admin_url() . 'admin.php?page=um_options&tab=extensions&section=' . $type;
        $title = esc_html__( 'Plugin settings at UM Extensions', 'awaiting-email-activation' );

        echo '<a href="' . esc_url( $settings_url ) . '" class="button" title="' . $title . '">' . esc_html__( 'Settings', 'awaiting-email-activation' ) . '</a>';
    }

    public function wp_next_scheduled_text( $cronjob_UTC, $type ) {

        $this->plugin_status = array();

        if ( ! empty( $cronjob_UTC )) {

            $minutes = intval(( $cronjob_UTC - time() ) / 60 );

            if ( $minutes > 60 ) {
                $this->plugin_status[] = sprintf( esc_html__( 'The %s WP Cronjob will execute next time at %s', 'awaiting-email-activation' ), $type, $this->get_display_time_formatted( $cronjob_UTC ) );

            } else {

                if ( $minutes > 0 ) {
                    $this->plugin_status[] = sprintf( esc_html__( 'The %s WP Cronjob will execute next in about %d minutes', 'awaiting-email-activation' ), $type, absint( $minutes ));

                } else {

                    $seconds = absint( $cronjob_UTC - time() );
                    if ( $seconds < 3600 ) {
                        $this->plugin_status[] = sprintf( esc_html__( 'The %s WP Cronjob has been waiting %d minutes in the job queue', 'awaiting-email-activation' ), $type, intval( $seconds/60 ));

                    } else {

                        $this->plugin_status[] = sprintf( esc_html__( 'The %s WP Cronjob has been waiting %d hours in the job queue', 'awaiting-email-activation' ), $type, intval( $seconds/3600 ));
                    }
                }
            }

        } else {

            $this->plugin_status[] = sprintf( esc_html__( 'No active %s Users WP Cronjob', 'awaiting-email-activation' ), $type );
        }
    }

     public function get_display_time_formatted( $cronjob_UTC ) {

        ob_start(); ?>
        <span title="<?php echo esc_attr( 'UTC: ' . $this->get_timestamp_formatted_local( $cronjob_UTC ) ); ?>">
			<?php echo esc_attr( $this->get_timestamp_formatted_local( $cronjob_UTC, true )); ?>
		</span>
        <?php

        return ob_get_clean();
    }

    public function get_delete_users_awaiting_days() {

        $delete_users_awaiting_days = UM()->options()->get( 'delete_users_awaiting_days' );

        if ( empty( $delete_users_awaiting_days ) || ! is_numeric( $delete_users_awaiting_days )) {
            $delete_users_awaiting_days = $this->default_awaiting_days;
        }

        return absint( $delete_users_awaiting_days );
    }

    public function late_users_awaiting_email_filter_status( $which ) {

        if ( 'top' == $which || 'bottom' == $which ) {

            ob_start();

            if ( isset( $_GET['delete_users_awaiting'] )) {

                if ( UM()->options()->get( 'delete_users_awaiting_filter' ) == 1 ) {
                    if ( sanitize_key( $_GET['delete_users_awaiting'] ) === 'email_activation' ) {

                        $time_limit = $this->get_timestamp_formatted_local( strtotime( $this->get_delete_users_awaiting_days_ago_UTC()), true ); ?>
                        <span class="button"> <?php echo sprintf( esc_html__( 'Filter: Late Users to Remove registered before %s', 'awaiting-email-activation' ), $time_limit ); ?></span>
<?php               }
                }

                if ( UM()->options()->get( 'remind_users_awaiting_filter' ) == 1 ) {
                    if ( sanitize_key( $_GET['delete_users_awaiting'] ) === 'remind_activation' ) {

                        $cronjob_UTC    = $this->get_next_cronjob_timestamp( $this->wp_cron_remind, false );
                        $reminder_local = esc_attr( $this->get_timestamp_formatted_local( $cronjob_UTC, true )); ?>

                        <span class="button"> <?php echo sprintf( esc_html__( 'Filter: Late Users to Remind at %s', 'awaiting-email-activation' ), $reminder_local ); ?></span>
 <?php              }
                }
            }

            $option = ob_get_clean();

            if ( ! empty( $option )) { ?>

                <div class="alignleft actions um-filter-by-status">
                    <?php echo $option; ?>
                </div>
<?php       }
        }
    }

    public function delete_users_awaiting_email_cronjob() {

        if ( wp_doing_cron()) {

            $this->current_cron_job = $this->wp_cron_event;

            $this->find_users_without_confirmation();
            if ( ! empty( $this->late_users ) && is_array( $this->late_users )) {

                $user_summary = array();
                $status_ok = esc_html__( 'OK', 'awaiting-email-activation' );

                $delete_users_awaiting_admin = ( UM()->options()->get( 'delete_users_awaiting_admin' ) == 1 ) ? true : false;

                foreach( $this->late_users as $user_id ) {

                    UM()->user()->remove_cache( $user_id );
                    um_fetch_user( $user_id );

                    if ( $delete_users_awaiting_admin ) {

                        $this->create_admin_info_table_row( $user_id );
                    }

                    if ( um_user( 'account_status' ) === 'awaiting_email_confirmation' ) {

                        UM()->user()->delete();

                        if ( $delete_users_awaiting_admin ) {

                            $status = ( get_userdata( $user_id ) ) ? esc_html__( 'User exists', 'awaiting-email-activation' ) : $status_ok;
                            $user_summary[] = $this->create_admin_info_table_row( $user_id, $status );
                        }

                    } else {

                        if ( $delete_users_awaiting_admin ) {

                            $status_invalid = sprintf( esc_html__( 'Invalid account status %s', 'awaiting-email-activation' ), um_user( 'account_status' ));
                            $user_summary[] = $this->create_admin_info_table_row( $user_id, $status_invalid );
                        }

                        UM()->user()->remove_cache( $user_id );
                    }
                }

                if ( $delete_users_awaiting_admin && ! empty( $user_summary )) {

                    $this->send_users_info_admin( $user_summary );
                }
            }

            $this->schedule_next_cronjob( $this->wp_cron_event );
        }
    }

    public function um_delete_users_notification_email( $user_id ) {

        $this->find_users_without_confirmation();
        if ( in_array( $user_id, $this->late_users )) {

            UM()->mail()->send( um_user( 'user_email' ), $this->slug, array() );

            if ( UM()->options()->get( 'delete_users_awaiting_sleep' ) == 1 ) {
                sleep( $this->wp_cronjob_sleep );
            }
        }
    }

    public function send_users_info_admin( $user_summary ) {

        $table_hdr = $this->create_admin_info_table_header_row();

        $number = ( count( $user_summary ) == 1 ) ? esc_html__( 'One User', 'awaiting-email-activation' ) : sprintf( esc_html__( '%s Users', 'awaiting-email-activation' ), count( $user_summary ));

        $subject = wp_kses( sprintf( esc_html__( "%s removed from %s by the WP Cronjob %s", 'awaiting-email-activation' ),
                                                $number,
                                                get_bloginfo( 'name' ),
                                                date_i18n( 'Y/m/d', current_time( 'timestamp' ))),
                                                UM()->get_allowed_html( 'templates' ));

        $plugin_notes = array();

        $plugin_notes[] = esc_html__( 'Plugin notes', 'awaiting-email-activation' );
        $plugin_notes[] = esc_html__( 'OK: User delete verified', 'awaiting-email-activation' );
        $plugin_notes[] = esc_html__( 'User exists: UM is unable to delete this User', 'awaiting-email-activation' );
        $plugin_notes[] = esc_html__( 'Invalid account status: User with a not expected account status and plugin rejected the User delete', 'awaiting-email-activation' );

        $headlines = array();

        $headlines[] = sprintf( esc_html__( 'List of Users removed due to not responding to their Account Activation email within %d days.', 'awaiting-email-activation' ),
                                                                                                                    $this->get_delete_users_awaiting_days());

        $headlines[] = sprintf( esc_html__( 'The Plugin WP Cronjob will execute next time at %s', 'awaiting-email-activation' ),
                                             $this->get_timestamp_formatted_local( wp_next_scheduled( $this->wp_cron_event ), true ));

        if ( UM()->options()->get( 'delete_users_awaiting_notification' ) == 1 ) {
            $headlines[] = esc_html__( 'Email notifications "Delete Users Awaiting Email Activation" sent to the Users', 'awaiting-email-activation' );
        }

        $body =
            '<style>table,th,td { border:1px solid black; } th,td { padding:5px; }</style>
             <h3>' . $subject . '</h3>
             <div>' . implode( '</div><div>', $headlines ) . '</div>
             <table>' . "\r\n" . $table_hdr . "\r\n" . implode( "\r\n", $user_summary ) . "\r\n" . '</table>
             <div>' . implode( '</div><div>', $plugin_notes ) . '</div>';

        $headers = array();

        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = sprintf( 'From: %s <%s>', esc_html( get_bloginfo( 'name' )), um_admin_email());

        wp_mail( um_admin_email(), $subject, $body, $headers );
    }

    public function create_admin_info_table_header_row() {

        $hdr_info = array();

        $hdr_info[] = esc_html__( 'UserID',            'awaiting-email-activation' );
        $hdr_info[] = esc_html__( 'Username',          'awaiting-email-activation' );
        $hdr_info[] = esc_html__( 'First name',        'awaiting-email-activation' );
        $hdr_info[] = esc_html__( 'Last name',         'awaiting-email-activation' );
        $hdr_info[] = esc_html__( 'User email',        'awaiting-email-activation' );
        $hdr_info[] = esc_html__( 'Registration date', 'awaiting-email-activation' );
        $hdr_info[] = esc_html__( 'Birthdate',         'awaiting-email-activation' );
        $hdr_info[] = esc_html__( 'Notes',             'awaiting-email-activation' );

        $html = '<tr><th>' . implode( '</th><th>', $hdr_info ) . '</th></tr>';

        return $html;
    }

    public function create_admin_info_table_row( $user_id, $status = false ) {

        if ( ! $status ) {

            $this->user_info = array();

            $this->user_info[] = $user_id;
            $this->user_info[] = um_user( 'user_login' );
            $this->user_info[] = um_user( 'first_name' );
            $this->user_info[] = um_user( 'last_name' );
            $this->user_info[] = um_user( 'user_email' );
            $this->user_info[] = $this->get_timestamp_formatted_local( strtotime( um_user( 'user_registered' )), true );
            $this->user_info[] = um_user( 'birth_date' );

            return;

        } else {

            $this->user_info[] = $status;

            $this->user_info = array_map( 'esc_attr', $this->user_info );
            $html = '<tr><td>' . implode( '</td><td>', $this->user_info ) . '</td></tr>';

            return $html;
        }
    }

    public function settings_structure_remind_users_awaiting_email( $settings ) {

        if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'um_options' ) {
            if ( isset( $_REQUEST['tab'] ) && $_REQUEST['tab'] == 'extensions' ) {

                $settings['extensions']['sections']['remind-late-users']['title'] = esc_html__( 'Remind late Users', 'awaiting-email-activation' );

                if ( ! isset( $_REQUEST['section'] ) || $_REQUEST['section'] == 'remind-late-users' ) {

                    if ( ! isset( $settings['extensions']['sections']['remind-late-users']['fields'] ) ) {

                        $settings['extensions']['sections']['remind-late-users']['description'] = $this->get_possible_plugin_update( 'um-email-activation' );
                        $settings['extensions']['sections']['remind-late-users']['fields']      = $this->create_remind_settings_fields();
                    }
                }
            }
        }

        return $settings;
    }

    public function settings_structure_remove_users_awaiting_email( $settings ) {

        if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'um_options' ) {
            if ( isset( $_REQUEST['tab'] ) && $_REQUEST['tab'] == 'extensions' ) {

                $settings['extensions']['sections']['remove-late-users']['title'] = esc_html__( 'Remove late Users', 'awaiting-email-activation' );

                if ( ! isset( $_REQUEST['section'] ) || $_REQUEST['section'] == 'remove-late-users' ) {

                    if ( ! isset( $settings['extensions']['sections']['remove-late-users']['fields'] ) ) {

                        $settings['extensions']['sections']['remove-late-users']['description'] = $this->get_possible_plugin_update( 'um-email-activation' );
                        $settings['extensions']['sections']['remove-late-users']['fields']      = $this->create_remove_settings_fields();
                    }
                }
            }
        }

        return $settings;
    }

    public function get_possible_plugin_update( $plugin ) {

        $plugin_data = get_plugin_data( __FILE__ );

        $documention = sprintf( ' <a href="%s" target="_blank" title="%s">%s</a>',
                                        esc_url( $plugin_data['PluginURI'] ),
                                        esc_html__( 'GitHub plugin documentation and download', 'awaiting-email-activation' ),
                                        esc_html__( 'Documentation', 'awaiting-email-activation' ));

        $description = sprintf( esc_html__( 'Plugin "Users Awaiting Email Activation" version %s - tested with UM 2.9.2 - %s', 'awaiting-email-activation' ),
                                                                            $plugin_data['Version'], $documention );

        return $description;
    }

    public function get_current_hash_expiration() {

        $days = ( ! empty( UM()->options()->get( 'activation_link_expiry_time' )) ) ? absint( UM()->options()->get( 'activation_link_expiry_time' )) : 999;
        $url  = get_admin_url() . 'admin.php?page=um_options&section=users';
        $link = sprintf( '<a href="%s">%s</a>',
                                esc_url( $url ),
                                esc_html__( 'UM Serttings', 'awaiting-email-activation' ));

        $description = sprintf( esc_html__( 'Your current %s for email activation hash expiration is %d days after Registration or Activation Reminder date and time.' ), $link, $days );

        return $description;
    }

    public function create_remind_settings_fields() {

        $url_template = get_site_url() . '/wp-admin/admin.php?page=um_options&tab=email&email=checkmail_email';
        $template = sprintf( '<a href="%s" title="%s">%s</a>',
                                    esc_url( $url_template ),
                                    esc_html__( 'UM email template: Account Activation Email', 'awaiting-email-activation' ),
                                    esc_html__( 'Account Activation Email', 'awaiting-email-activation' ));

        $prefix = '&nbsp; * &nbsp;';

        $settings = array();


        $settings[] = array(
                                    'id'             => 'email_activation_header',
                                    'type'           => 'header',
                                    'label'          => esc_html__( 'Login attempts by late Users', 'awaiting-email-activation' ),
                                );

            $settings[] = array(
                                    'id'             => 'logincheck_users_awaiting_email',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'Resend Activation email at Login attempts', 'awaiting-email-activation' ),
                                    'checkbox_label' => sprintf( esc_html__( 'Tick to resend the %s template when User is trying to Login without email Activation', 'awaiting-email-activation' ), $template ),
                                );

            $settings[] = array(
                                    'id'             => 'logincheck_users_awaiting_error',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'Show User\'s email address in Login error', 'awaiting-email-activation' ),
                                    'checkbox_label' => sprintf( esc_html__( 'Tick to show the User\'s email address where we send the email Activation request.', 'awaiting-email-activation' ), $template ),
                                );

        $settings[] = array(
                                    'id'             => 'email_activation_header',
                                    'type'           => 'header',
                                    'label'          => esc_html__( 'Remind late Users', 'awaiting-email-activation' ),
                                );

            $settings[] = array(
                                    'id'             => 'remind_users_awaiting_email',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'Remind User before activation hash expires', 'awaiting-email-activation' ),
                                    'checkbox_label' => sprintf( esc_html__( 'Tick to enable resending of the %s if the secret hash will expire before next WP Cronjob search.', 'awaiting-email-activation' ), $template ),
                                    'description'    => $this->get_current_hash_expiration(),
                                );

            $settings[] = array(
                                    'id'             => 'remind_users_awaiting_dashboard',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'UM Dashboard modal', 'awaiting-email-activation' ),
                                    'checkbox_label' => esc_html__( 'Tick to activate the Remind late Users WP Cronjob status modal at the UM Dashboard.', 'awaiting-email-activation' ),
                                );

            $settings[] = array(
                                    'id'             => 'remind_users_awaiting_email_action',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'UM Resend email Activation', 'awaiting-email-activation' ),
                                    'checkbox_label' => esc_html__( 'Tick to activate the UM Resend of email Activation in WP Users Page columns and frontpage cog wheel except backend bulk Resend.', 'awaiting-email-activation' ),
                                );

            if ( UM()->options()->get( 'enable_action_scheduler') == 1 ) {

                $url_settings = get_site_url() . '/wp-admin/admin.php?page=um_options&tab=advanced&section=features';
                $as_settings = sprintf( '<a href="%s" title="%s">%s</a>',
                                    esc_url( $url_settings ),
                                    esc_html__( 'UM Action Scheduler settings', 'awaiting-email-activation' ),
                                    esc_html__( 'Settings', 'awaiting-email-activation' ));

                $url_status = get_site_url() . '/wp-admin/tools.php?page=action-scheduler&s=um_dispatch_email&action=-1&paged=1&action2=-1';
                $as_status = sprintf( '<a href="%s" title="%s">%s</a>',
                                    esc_url( $url_status ),
                                    esc_html__( 'UM Action Scheduler email status', 'awaiting-email-activation' ),
                                    esc_html__( 'Status', 'awaiting-email-activation' ));

                $settings[] = array(
                                    'id'             => 'remind_users_awaiting_scheduler',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'UM Action Scheduler', 'awaiting-email-activation' ),
                                    'checkbox_label' => esc_html__( 'Tick to disable sending the Activation emails via the UM Action Scheduler.', 'awaiting-email-activation' ),
                                    'description'    => sprintf( esc_html__( 'UM Action Scheduler: %s, %s', 'awaiting-email-activation' ), $as_settings, $as_status ),
                                );
            }

            $settings[] = array(
                                    'id'             => 'remind_users_awaiting_filter',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'WP All Users filter', 'awaiting-email-activation' ),
                                    'checkbox_label' => esc_html__( 'Tick to activate a WP All Users filter button for display of the late Users to remind and extra User list columns.', 'awaiting-email-activation' ),
                                    'conditional'    => array( 'remind_users_awaiting_email', '=', 1 ),
                                );

            $settings[] = array(
                                    'id'             => 'remind_users_awaiting_hourly',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'How often to search for expired activations', 'awaiting-email-activation' ),
                                    'checkbox_label' => esc_html__( 'Tick to have the WP Cronjob look for qualified Users daily. Default is hourly. Daily is at noon with start from tomorrow', 'awaiting-email-activation' ),
                                    'conditional'    => array( 'remind_users_awaiting_email', '=', 1 ),
                                );

            if ( UM()->options()->get( 'delete_users_awaiting_email' ) != 1 ) {

                $settings[] = array(
                                    'id'             => 'remind_users_awaiting_max',
                                    'type'           => 'number',
                                    'size'           => 'small',
                                    'default'        => 3,
                                    'label'          => $prefix . esc_html__( 'Max number of times to remind User', 'awaiting-email-activation' ),
                                    'description'    => esc_html__( 'Enter the max number of times to remind a late Activation User. Default value is 3 times.', 'awaiting-email-activation' ),
                                );
            }

            if ( UM()->options()->get( 'enable_action_scheduler') != 1 ) {

                $settings[] = array(
                                    'id'             => 'remind_users_awaiting_sleep',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'Wait between sending emails if not SMTP', 'awaiting-email-activation' ),
                                    'checkbox_label' => esc_html__( 'Tick to add a WP Cronjob wait of five seconds between sending the notification emails to allow WP Mail to process the email transportation.', 'awaiting-email-activation' ),
                                    'conditional'    => array( 'remind_users_awaiting_email', '=', 1 ),
                                );
            }

            $settings[] = array(
                                    'id'             => 'remind_users_awaiting_text',
                                    'type'           => 'text',
                                    'label'          => $prefix . esc_html__( 'Email placeholder {reminder_text} text', 'awaiting-email-activation' ),
                                    'description'    => esc_html__( 'Text to include in emails sent by the Remind WP Cronjob in other cases placeholder is empty. You can include other email placeholders in this text.', 'awaiting-email-activation' ) . '<br />' .
                                                        esc_html__( 'Default:', 'awaiting-email-activation' ) . ' ' . esc_html__( 'This email is a reminder for Activation of your Account {username} Registered at {registration_date}.', 'awaiting-email-activation' ),
                                    'conditional'    => array( 'remind_users_awaiting_email', '=', 1 ),
                                );

            $settings[] = array(
                                    'id'             => 'remind_users_awaiting_last_text',
                                    'type'           => 'text',
                                    'label'          => $prefix . esc_html__( 'Email placeholder {reminder_text} last text', 'awaiting-email-activation' ),
                                    'description'    => esc_html__( 'Text to include in the the last email sent by the Remind WP Cronjob before User is Removed. You can include other email placeholders in this text.', 'awaiting-email-activation' ) . '<br />' .
                                                        esc_html__( 'Default:', 'awaiting-email-activation' ) . ' ' . esc_html__( 'NOTE! This is our last reminder about activation of your Account. Your Account will be removed at {removal_time} which is {removal_days} days after your Registration.', 'awaiting-email-activation' ),
                                    'conditional'    => array( 'remind_users_awaiting_email', '=', 1 ),
                                );
        return $settings;
    }

    public function create_remove_settings_fields() {

        $url_template  = get_site_url() . '/wp-admin/admin.php?page=um_options&tab=email&email=' . $this->slug;

        $template = sprintf( ' <a href="%s" title="%s">%s</a>',
                                        esc_url( $url_template ),
                                        esc_html__( 'Plugin email template', 'awaiting-email-activation' ),
                                        esc_html__( 'Delete Users Awaiting Email Activation', 'awaiting-email-activation' ));

        $prefix = '&nbsp; * &nbsp;';

        $settings = array();

        $settings[] = array(
                                    'id'             => 'email_activation_header',
                                    'type'           => 'header',
                                    'label'          => esc_html__( 'Remove late Users', 'awaiting-email-activation' ),
                                );

            $settings[] = array(
                                    'id'             => 'delete_users_awaiting_email',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'Remove Users with late Email Activation', 'awaiting-email-activation' ),
                                    'checkbox_label' => esc_html__( 'Tick to activate deletion of Users with unreplied email activations by the Plugin WP Cronjob.', 'awaiting-email-activation' ),
                                    'description'    => esc_html__( 'If the WP Cronjob is deactivated you can still do a manual User deletion via the link to WP All Users at the UM Dashboard modal.', 'awaiting-email-activation' ),
                                );

            $settings[] = array(
                                    'id'             => 'delete_users_awaiting_days',
                                    'type'           => 'number',
                                    'size'           => 'small',
                                    'default'        => $this->default_awaiting_days,
                                    'label'          => $prefix . esc_html__( 'Number of days to wait for Email Activation', 'awaiting-email-activation' ),
                                    'description'    => esc_html__( 'Enter the number of days until removing a late email activation.', 'awaiting-email-activation' ) . '<br />' .
                                                        sprintf( esc_html__( 'Only values larger than zero are accepted and default value is %d days.', 'awaiting-email-activation' ), $this->default_awaiting_days ) . '<br />' .
                                                        $this->get_current_hash_expiration(),
                                );

            $settings[] = array(
                                    'id'             => 'delete_users_awaiting_dashboard',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'UM Dashboard modal', 'awaiting-email-activation' ),
                                    'checkbox_label' => esc_html__( 'Tick to activate the Remove late Users WP Cronjob status modal at the UM Dashboard.', 'awaiting-email-activation' ),
                                );

            $settings[] = array(
                                    'id'             => 'remind_users_awaiting_email_action',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'UM Resend email Activation', 'awaiting-email-activation' ),
                                    'checkbox_label' => esc_html__( 'Tick to activate the UM Resend of email Activation in WP Users Page columns and frontpage cog wheel except backend bulk Resend.', 'awaiting-email-activation' ),
                                );

            $settings[] = array(
                                    'id'             => 'delete_users_awaiting_filter',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'WP All Users filter', 'awaiting-email-activation' ),
                                    'checkbox_label' => esc_html__( 'Tick to activate a WP All Users filter button for display of a late Activation Users list incl extra User list columns.', 'awaiting-email-activation' ),
                                    'conditional'    => array( 'delete_users_awaiting_email', '=', 1 ),
                                );

            $settings[] = array(
                                    'id'             => 'delete_users_awaiting_weekly',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'How often to search for late Users', 'awaiting-email-activation' ),
                                    'checkbox_label' => esc_html__( 'Tick to search for qualified Users weekly. Default is daily at midnight.', 'awaiting-email-activation' ),
                                    'conditional'    => array( 'delete_users_awaiting_email', '=', 1 ),
                                );

            $settings[] = array(
                                    'id'             => 'delete_users_awaiting_admin',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'Admin User info email', 'awaiting-email-activation' ),
                                    'checkbox_label' => esc_html__( 'Tick to activate a summary email with a removed User list sent to Site Admin.', 'awaiting-email-activation' ),
                                    'conditional'    => array( 'delete_users_awaiting_email', '=', 1 ),
                                );

            $settings[] = array(
                                    'id'             => 'delete_users_awaiting_notification',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'Send User Notification email', 'awaiting-email-activation' ),
                                    'checkbox_label' => sprintf( esc_html__( 'Tick to send the "%s" to the User email if this email Notification is activated.', 'awaiting-email-activation' ), $template ),
                                    'conditional'    => array( 'delete_users_awaiting_email', '=', 1 ),
                                );

            $settings[] = array(
                                    'id'             => 'delete_users_awaiting_placeholder',
                                    'type'           => 'text',
                                    'size'           => 'small',
                                    'label'          => $prefix . esc_html__( 'Date format placeholder {registration_date}', 'awaiting-email-activation' ),
                                    'description'    => sprintf( esc_html__( 'Enter your custom date format. Default is WP site default format %s', 'awaiting-email-activation' ), $this->date_time_format ),
                                    'conditional'    => array( 'delete_users_awaiting_notification', '=', 1 ),
                                );

            $settings[] = array(
                                    'id'             => 'delete_users_awaiting_sleep',
                                    'type'           => 'checkbox',
                                    'label'          => $prefix . esc_html__( 'Wait between sending emails if not SMTP', 'awaiting-email-activation' ),
                                    'checkbox_label' => esc_html__( 'Tick to add a WP Cronjob wait of five seconds between sending the notification emails to allow WP Mail to process the email transportation.', 'awaiting-email-activation' ),
                                    'conditional'    => array( 'delete_users_awaiting_notification', '=', 1 ),
                                );

        return $settings;
    }

    public function email_notifications_delete_user( $um_emails ) {

        $this->activation_plugin_users_awaiting_email();

        $custom_email = array( $this->slug => array(

                                        'key'			 => $this->slug,
                                        'title'			 => esc_html__( 'Remove Users Awaiting Email Activation', 'awaiting-email-activation' ),
                                        'subject'		 => 'Your account has been removed',
                                        'body'			 => '',
                                        'description'    => esc_html__( 'To send a custom notification email to the User when removed because of no email activation.', 'awaiting-email-activation' ),
                                        'recipient'		 => 'user',
                                        'default_active' => false
                                    )
                            );

        if ( UM()->options()->get( $this->slug . '_on' ) === '' ) {

            $email_on = empty( $custom_email[$this->slug]['default_active'] ) ? 0 : 1;
            UM()->options()->update( $this->slug . '_on', $email_on );
        }

        if ( UM()->options()->get( $this->slug . '_sub' ) === '' ) {

            UM()->options()->update( $this->slug . '_sub', $custom_email[$this->slug]['subject'] );
        }

        return array_merge( $um_emails, $custom_email );
    }

    public function submit_form_errors_hook_logincheck( $args, $form_data ) {

        $user_id = ( isset( UM()->login()->auth_id )) ? UM()->login()->auth_id : '';

        if ( ! empty( $user_id )) {

            um_fetch_user( $user_id );
            if ( um_user( 'account_status' ) == 'awaiting_email_confirmation' ) {

                $redirect = um_get_core_page( 'login' );
                $redirect = add_query_arg( 'err',    'awaiting_new_email_confirmation', $redirect . '?' );
                $redirect = add_query_arg( 'secret',  esc_attr( strtotime( um_user( 'user_registered' ) )), $redirect );
                $redirect = add_query_arg( 'user_id', esc_attr( $user_id ), $redirect );

                UM()->common()->users()->send_activation( $user_id, true );
                UM()->logout();

                wp_safe_redirect( $redirect );
                exit;
            }
        }
    }

    public function logincheck_error_message_handler( $err, $error, $args ) {

        if ( $error == 'awaiting_new_email_confirmation' ) {

            if ( isset( $_REQUEST['user_id'] ) && ! empty( $_REQUEST['user_id'] )) {
                $user_id = absint( $_REQUEST['user_id'] );

                if ( $user_id > 0 && UM()->common()->users()->get_status( $user_id ) == 'awaiting_email_confirmation' ) {

                    if ( isset( $_REQUEST['secret'] ) && ! empty( $_REQUEST['secret'] )) {
                        $user = get_userdata( $user_id );

                        if ( strtotime( $user->user_registered ) == sanitize_text_field( $_REQUEST['secret'] ) ) {

                            $add_email = ( UM()->options()->get( 'logincheck_users_awaiting_error' ) == 1 ) ? sprintf( esc_html__( '( to address %s )', 'awaiting-email-activation' ), $user->user_email ) : '';
                            $err = sprintf( esc_html__( 'Your User Account is still awaiting email verification and we have now sent you a new email %s for your Account Activation.', 'awaiting-email-activation' ), $add_email );
                        }
                    }

                    UM()->user()->remove_cache( $user_id );
                }
            }
        }

        return $err;
    }

    public function custom_authenticate_error_codes( $codes ) {

        $codes[] = 'awaiting_new_email_confirmation';
        return $codes;
    }

    public function activate_users_column_hooks() {

        if ( ! $this->users_column_hooks ) {

            add_filter( 'manage_users_sortable_columns', array( $this, 'register_sortable_columns_custom' ), 1, 1 );
            add_filter( 'manage_users_columns',          array( $this, 'manage_users_columns_custom' ), 10, 1 );
            add_filter( 'manage_users_custom_column',    array( $this, 'manage_users_custom_column' ), 10, 3 );

            $this->users_column_hooks = true;
        }
    }

    public function register_sortable_columns_custom( $columns ) {

        if ( UM()->options()->get( 'remind_users_awaiting_email' ) == 1 || UM()->options()->get( 'delete_users_awaiting_email' ) == 1 ) {
            $columns['user_registered'] = 'user_registered';
        }

        if ( UM()->options()->get( 'remind_users_awaiting_email' ) == 1 ) {
            $columns['account_secret_hash_expiry'] = 'account_secret_hash_expiry';
        }

        if ( UM()->options()->get( 'delete_users_awaiting_email' ) == 1 ) {
            $columns['user_account_removal'] = 'user_account_removal';
        }

        return $columns;
    }

    public function manage_users_columns_custom( $columns ) {

        if ( UM()->options()->get( 'remind_users_awaiting_email' ) == 1 || UM()->options()->get( 'delete_users_awaiting_email' ) == 1 ) {
            $columns['user_registered'] = esc_html__( 'User registered', 'awaiting-email-activation' );
        }

        if ( UM()->options()->get( 'remind_users_awaiting_email' ) == 1 ) {
            $columns['account_secret_hash_expiry'] = esc_html__( 'Remind before', 'awaiting-email-activation' );
        }

        if ( UM()->options()->get( 'delete_users_awaiting_email' ) == 1 ) {
            $columns['user_account_removal'] = sprintf( esc_html__( 'Remove after %s days', 'awaiting-email-activation' ), $this->get_delete_users_awaiting_days() );
        }

        return $columns;
    }

    public function manage_users_custom_column( $value, $column_name, $user_id ) {

        if ( $column_name == 'user_registered' ) {

            um_fetch_user( $user_id );
            $value = $this->get_timestamp_formatted_local( strtotime( um_user( 'user_registered' )), true );
            um_reset_user();
        }

        if ( $column_name == 'account_secret_hash_expiry' ) {

            um_fetch_user( $user_id );
            $value = $this->get_timestamp_formatted_local( um_user( 'account_secret_hash_expiry' ), true ); 
            um_reset_user();
        }

        if ( $column_name == 'user_account_removal' ) {

            um_fetch_user( $user_id );
            $value = $this->get_user_removal_local_date_time();
            um_reset_user();
        }

        return $value;
    }

    public function um_users_column_account_status_remove_action( $row_actions, $user_id ) {

        if ( UM()->options()->get( 'remind_users_awaiting_email_action' ) != 1 ) {
            um_fetch_user( $user_id );

            if ( um_user( 'account_status' ) != 'awaiting_email_confirmation' ) {

                foreach( $row_actions as $key => $row_action ) {

                    if ( strpos( $row_action, 'um-resend-activation-email' )) {
                        unset( $row_actions[$key] );
                        break;
                    }
                }
            }
        }

        return $row_actions;
    }

    public function um_admin_user_actions_hook_remove_action( $actions, $user_id ) {

        if ( UM()->options()->get( 'remind_users_awaiting_email_action' ) != 1 ) {
            um_fetch_user( $user_id );

            if ( um_user( 'account_status' ) != 'awaiting_email_confirmation' && isset( $actions['resend_user_activation'] )) {
                unset( $actions['resend_user_activation'] );
            }
        }

        return $actions;
    }

    public function um_admin_bulk_user_actions_hook_remove_action( $um_actions ) {

        if ( isset( $um_actions['um_resend_activation'] )) {
            unset( $um_actions['um_resend_activation'] );
        }

        return $um_actions;
    }

    public function pre_as_enqueue_async_action_activation_email( $bool, $hook, $args, $group, $priority ) {

        if ( UM()->options()->get( 'enable_action_scheduler') == 1 ) {
            if ( UM()->options()->get( 'remind_users_awaiting_scheduler' ) == 1 ) {

                if ( $hook == 'um_dispatch_email' && isset( $args[1] ) && $args[1] == 'checkmail_email' ) {

                    if ( isset( $args[0] )) {

                        UM()->mail()->send( $args[0], $args[1], array() );
                        $bool = 0;
                    }
                }
            }
        }

        return $bool;
    }


}

new Users_Awaiting_Email_Activation();



