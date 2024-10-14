<?php
/**
 * Plugin Name:     Ultimate Member - Delete Users Awaiting Email Activation
 * Description:     Extension to Ultimate Member to delete Users who have not replied with an email activation after Registration either by a Plugin WP Cronjob or manual deletion by Site Admin from a dedicated WP All Users page.
 * Version:         1.0.0
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica?tab=repositories
 * Plugin URI:      https://github.com/MissVeronica/um-delete-users-awaiting-email
 * Update URI:      https://github.com/MissVeronica/um-delete-users-awaiting-email
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.8.8
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'UM' ) ) return;

class UM_Delete_Users_Awaiting_Email_Activation {

    public $message       = '';
    public $plugin_status = array();
    public $wp_cron_event = 'um_cron_delete_users_awaiting_email';

    public function __construct() {

        define( 'Plugin_Basename_DUAE', plugin_basename( __FILE__ ));

        add_filter( 'um_settings_structure',             array( $this, 'um_settings_structure_delete_users_awaiting_email' ), 10, 1 );
        add_action( 'load-toplevel_page_ultimatemember', array( $this, 'load_metabox_delete_users_awaiting_email' ) );
        add_filter( 'pre_user_query',                    array( $this, 'filter_users_delete_users_awaiting_email' ), 99 );

        add_filter( 'plugin_action_links_' . Plugin_Basename_DUAE, array( $this, 'plugin_settings_link' ), 10, 1 );

        if ( UM()->options()->get( 'delete_users_awaiting_email' ) == 1 ) {

            add_action( $this->wp_cron_event, array( $this, 'um_delete_users_awaiting_email_cronjob' ));

            if ( ! wp_next_scheduled( $this->wp_cron_event ) ) {

                $cron_time = new DateTime( 'tomorrow', new DateTimeZone( wp_timezone_string() ) );
                wp_schedule_event( $cron_time->getTimestamp(), 'daily', $this->wp_cron_event );
            }

        } else {

            if ( wp_next_scheduled( $this->wp_cron_event ) ) {
                wp_clear_scheduled_hook( $this->wp_cron_event );
            }
        }

        register_deactivation_hook( __FILE__, array( $this, 'delete_users_awaiting_email_deactivation' ));
        register_activation_hook(   __FILE__, array( $this, 'delete_code_snippet_cronjob' ));
    }

    public function plugin_settings_link( $links ) {

        $url = get_admin_url() . 'admin.php?page=um_options&section=users';
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings' ) . '</a>';

        return $links;
    }

    public function restart_cronjob() {

        $status = wp_clear_scheduled_hook( $this->wp_cron_event );

        return $status;
    }

    public function delete_users_awaiting_email_deactivation() {

        wp_clear_scheduled_hook( $this->wp_cron_event );
    }

    public function delete_code_snippet_cronjob() {

        if ( wp_next_scheduled( 'um_cron_delete_users_cron' ) ) {
            wp_clear_scheduled_hook( 'um_cron_delete_users_cron' );
        }
    }

    public function load_metabox_delete_users_awaiting_email() {

        $users_awaiting_email = $this->find_users_without_confirmation();

        $wp_all_users_url = admin_url( 'users.php' ) . '?delete_users_awaiting=email_activation';
        $link = '<a href="' . esc_url( $wp_all_users_url ) . '">' . esc_html__( 'WP All Users', 'ultimate-member' ) . '</a>';

        $counter = count( $users_awaiting_email );

        switch( $counter ) {

            case 0:  $header = esc_html__( 'No Users are late with their Email Activations', 'ultimate-member' ); 
                     $this->message = '';
                     break;

            case 1:  $header = esc_html__( 'One User is late with the Email Activation', 'ultimate-member' );

                     if ( UM()->options()->get( 'delete_users_awaiting_email' ) == 1 ) {
                        $this->message = esc_html__( 'This User will be deleted by the Plugin WP Cronjob at midnight.', 'ultimate-member' );

                     } else {
                        $this->message = sprintf( esc_html__( 'This User can be deleted by yourself via this %s link.', 'ultimate-member' ), $link );
                     }
                     break;

            default: $header = sprintf( esc_html__( '%s Users are late with their Email Activations', 'ultimate-member' ), $counter );

                     if ( UM()->options()->get( 'delete_users_awaiting_email' ) == 1 ) { 
                        $this->message = sprintf( esc_html__( 'These %s Users will be deleted by the Plugin WP Cronjob at midnight.', 'ultimate-member' ), $counter );

                     } else {
                        $this->message = sprintf( esc_html__( 'These %s Users can be deleted by yourself via this %s link.', 'ultimate-member' ), $counter, $link );
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

        $this->wp_cronjob_status();

        $settings_url = get_admin_url() . 'admin.php?page=um_options&section=users';
        $wp_all_users_url = admin_url( 'users.php' ) . '?delete_users_awaiting=email_activation'; ?>

        <div> <?php echo implode( '', $this->plugin_status ); ?>
        </div>
        <div> <?php echo $this->message; ?>
        </div>
        <hr>
        <div> <?php echo '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Plugin Settings', 'ultimate-member' ) . '</a>'; ?>
        </div>
        <div> <?php if ( UM()->options()->get( 'delete_users_awaiting_email' ) == 1 ) {
                        echo '<a href="' . esc_url( $wp_all_users_url ) . '" title="' . esc_html__( 'Next Users to be deleted by the WP Cronjob', 'ultimate-member' ) . '">' . esc_html__( 'WP All Users', 'ultimate-member' ) . '</a>'; 
                    } ?>
        </div> <?php

        if ( function_exists( 'um_delete_users_awaiting_email' )) { ?>

            <hr>
            <div> <?php echo esc_html__( 'Note', 'ultimate-member' ); ?>
            </div>
            <div> <?php echo esc_html__( 'Remove the old UM code snippet function \'um_delete_users_awaiting_email\' and the \'wp_schedule_event\' code lines from your active theme\'s functions.php file.', 'ultimate-member' ); ?>
            </div>
            <div> <?php echo esc_html__( 'After the old UM code snippet removal deactivate and activate this plugin and the old daily \'um_cron_delete_users_cron\' WP Cronjob will also be deleted by this plugin.', 'ultimate-member' ); ?>
            </div> <?php
        }
    }

    public function wp_cronjob_status() {

        $cronjob = wp_next_scheduled( $this->wp_cron_event );

        if ( ! empty( $cronjob )) {

            $minutes = intval(( $cronjob - time() ) / 60 );

            if ( $minutes > 60 ) {
                $this->plugin_status[] = sprintf( esc_html__( 'The Plugin WP Cronjob will execute next at %s', 'ultimate-member' ), esc_attr( $this->get_local_time( $cronjob ) ) );

            } else {

                if ( $minutes > 0 ) {
                    $this->plugin_status[] = sprintf( esc_html__( 'The Plugin WP Cronjob will execute next in about %d minutes', 'ultimate-member' ), absint( $minutes ));

                } else {

                    $seconds = absint( $cronjob - time() );
                    if ( $seconds < 3600 ) {
                        $this->plugin_status[] = sprintf( esc_html__( 'The Plugin WP Cronjob has been waiting %d minutes in the WP job queue', 'ultimate-member' ), intval( $seconds/60 ));

                    } else {

                        $this->restart_cronjob();
                        $this->plugin_status[] = sprintf( esc_html__( 'Restarted the Plugin WP Cronjob after waiting in the WP Cronjob queue for %d minutes', 'ultimate-member' ), intval( $seconds/60 ));
                    }
                }
            }

        } else {
            $this->plugin_status[] = esc_html__( 'No active Plugin WP Cronjob', 'ultimate-member' );
        }
    }

    public function get_local_time( $cronjob ) {

        $utc_timestamp_converted = date( 'Y/m/d H:i:s', $cronjob );
        $local_timestamp = get_date_from_gmt( $utc_timestamp_converted, 'Y/m/d H:i:s' );

        return $local_timestamp;
    }

    public function get_delete_users_awaiting_days() {

        $delete_users_awaiting_days = UM()->options()->get( 'delete_users_awaiting_days' );

        if ( empty( $delete_users_awaiting_days ) || ! is_numeric( $delete_users_awaiting_days )) {
            $delete_users_awaiting_days = 5;
        }

        return absint( $delete_users_awaiting_days );
    }

    public function filter_users_delete_users_awaiting_email( $filter_query ) {

        global $wpdb;
        global $pagenow;

        if ( is_admin() && $pagenow == 'users.php' && ! empty( $_REQUEST['delete_users_awaiting'] ) ) {

            if ( sanitize_key( $_REQUEST['delete_users_awaiting'] ) === 'email_activation' ) {

                $delete_users_awaiting_days = $this->get_delete_users_awaiting_days();
                $registration = date( 'Y-m-d 00:00:00', time() - $delete_users_awaiting_days * DAY_IN_SECONDS );

                $filter_query->query_where = str_replace( 'WHERE 1=1', "WHERE 1=1
                                                                        AND user_registered < '{$registration}'
                                                                        AND {$wpdb->users}.ID IN (
                                                                        SELECT {$wpdb->usermeta}.user_id FROM $wpdb->usermeta
                                                                        WHERE {$wpdb->usermeta}.meta_key = 'account_status'
                                                                        AND {$wpdb->usermeta}.meta_value = 'awaiting_email_confirmation')",
                                                            $filter_query->query_where );
            }
        }

        return $filter_query;
    }

    public function find_users_without_confirmation() {

        $delete_users_awaiting_days = $this->get_delete_users_awaiting_days();

        $args = array(
                        'fields'     => 'ID',
                        'number'     => -1,
                        'date_query' => array(
                                                array( 'before'    => $delete_users_awaiting_days . ' days ago midnight',
                                                       'inclusive' => true ),
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

        $users = get_users( $args );
        sort( $users );

        return $users;
    }

    public function um_delete_users_awaiting_email_cronjob() {

        $users = $this->find_users_without_confirmation();

        if ( ! empty( $users ) && is_array( $users )) {

            $user_summary = array();

            $delete_users_awaiting_admin = ( UM()->options()->get( 'delete_users_awaiting_admin' ) == 1 ) ? true : false;
            $status_ok = esc_html__( 'OK', 'ultimate-member' );

            foreach( $users as $user_id ) {

                UM()->user()->remove_cache( $user_id );
                um_fetch_user( $user_id );

                if ( um_user( 'account_status' ) === 'awaiting_email_confirmation' ) {

                    if ( $delete_users_awaiting_admin ) {

                        $user_summary[] = $this->create_admin_info_table_row( $user_id, $status_ok );
                    }

                    UM()->user()->delete();

                } else {

                    if ( $delete_users_awaiting_admin ) {

                        $status_invalid = sprintf( esc_html__( 'Invalid account status %s', 'ultimate-member' ), um_user( 'account_status' ));
                        $user_summary[] = $this->create_admin_info_table_row( $user_id, $status_invalid );
                    }

                    UM()->user()->remove_cache( $user_id );
                }
            }

            if ( $delete_users_awaiting_admin && ! empty( $user_summary )) {

                $this->send_users_info_admin( $users, $user_summary );
            }
        }
    }

    public function send_users_info_admin( $users, $user_summary ) {

        $table_hdr = $this->create_admin_info_table_header_row();

        $number = sprintf( esc_html__( '%s Users', 'ultimate-member' ), count( $users ));
        if ( count( $users ) == 1 ) {
            $number = esc_html__( 'One User', 'ultimate-member' );
        }

        $subject = wp_kses( sprintf( esc_html__( "%s deleted from %s by the WP Cronjob %s", 'ultimate-member' ),
                                                $number,
                                                get_bloginfo( 'name' ),
                                                date_i18n( 'Y/m/d', current_time( 'timestamp' ))),
                                                UM()->get_allowed_html( 'templates' ));

        $body =
            '<style>table,th,td { border:1px solid black; } th,td { padding:10px; }</style>
             <h3>' . $subject . '</h3>
             <table>' . "\r\n" . $table_hdr . "\r\n" . implode( "\r\n", $user_summary ) . "\r\n" . '</table>';

        $headers = array();

        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = sprintf( 'From: %s <%s>', esc_html( get_bloginfo( 'name' )), um_admin_email());

        wp_mail( um_admin_email(), $subject, $body, $headers );
    }

    public function create_admin_info_table_header_row() {

        $user_info = array();

        $user_info[] = esc_html__( 'UserID',            'ultimate-member' );
        $user_info[] = esc_html__( 'Username',          'ultimate-member' );
        $user_info[] = esc_html__( 'First name',        'ultimate-member' );
        $user_info[] = esc_html__( 'Last name',         'ultimate-member' );
        $user_info[] = esc_html__( 'User email',        'ultimate-member' );
        $user_info[] = esc_html__( 'Registration date', 'ultimate-member' );
        $user_info[] = esc_html__( 'Birthdate',         'ultimate-member' );
        $user_info[] = esc_html__( 'Plugin',            'ultimate-member' );

        $html = '<tr><th>' . implode( '</th><th>', $user_info ) . '</th></tr>';

        return $html;
    }

    public function create_admin_info_table_row( $user, $status ) {

        $user_info = array();

        $user_info[] = $user;
        $user_info[] = um_user( 'user_login' );
        $user_info[] = um_user( 'first_name' );
        $user_info[] = um_user( 'last_name' );
        $user_info[] = um_user( 'user_email' );
        $user_info[] = um_user( 'user_registered' );
        $user_info[] = um_user( 'birth_date' );
        $user_info[] = $status;

        $user_info = array_map( 'esc_attr', $user_info );
        $html = '<tr><td>' . implode( '</td><td>', $user_info ) . '</td></tr>';

        return $html;
    }

    public function um_settings_structure_delete_users_awaiting_email( $settings_structure ) {

        if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'um_options' ) {
            if ( ! isset( $_REQUEST['section'] ) || $_REQUEST['section'] == 'users' ) {

                $plugin_data = get_plugin_data( __FILE__ );
                $prefix = '&nbsp; * &nbsp;';

                $settings_structure['']['sections']['users']['form_sections']['delete_users_awaiting']['title']       = esc_html__( 'Delete Users Awaiting Email Activation', 'ultimate-member' );
                $settings_structure['']['sections']['users']['form_sections']['delete_users_awaiting']['description'] = sprintf( esc_html__( 'Plugin version %s - tested with UM 2.8.8', 'ultimate-member' ), $plugin_data['Version'] );

                $settings = array();
                $settings[] = array(
                                        'id'             => 'delete_users_awaiting_email',
                                        'type'           => 'checkbox',
                                        'label'          => $prefix . esc_html__( 'Delete Users with late Activation', 'ultimate-member' ),
                                        'checkbox_label' => esc_html__( 'Tick to activate deletion of Users with unreplied email activations by the Plugin WP Cronjob.', 'ultimate-member' ),
                                        'description'    => esc_html__( 'If the WP Cronjob is deactivated you can still do a manual User deletion via the link to WP All Users at the UM Dashboard modal.', 'ultimate-member' ),
                                    );

                $settings[] = array(
                                        'id'             => 'delete_users_awaiting_admin',
                                        'type'           => 'checkbox',
                                        'label'          => $prefix . esc_html__( 'Admin User info email', 'ultimate-member' ),
                                        'checkbox_label' => esc_html__( 'Tick to activate an email with a deleted User list to Site Admin.', 'ultimate-member' ),
                                        'conditional'    => array( 'delete_users_awaiting_email', '=', 1 ),
                                    );

                $settings[] = array(
                                        'id'          => 'delete_users_awaiting_days',
                                        'type'        => 'text',
                                        'size'        => 'small',
                                        'label'       => $prefix . esc_html__( 'Number of days to wait for Activation', 'ultimate-member' ),
                                        'description' => esc_html__( 'Enter the number of days for accepting an email activation.', 'ultimate-member' ) . '<br />' .
                                                         esc_html__( 'Only values larger than zero are accepted and default value is 5 days.', 'ultimate-member' ),
                                    );

                $settings_structure['']['sections']['users']['form_sections']['delete_users_awaiting']['fields'] = $settings;
            }
        }

        return $settings_structure;
    }


}

new UM_Delete_Users_Awaiting_Email_Activation();

