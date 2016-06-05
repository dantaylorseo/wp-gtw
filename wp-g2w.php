<?php
/*
Plugin Name: GoToWebinar for WordPress
Plugin URI: https://github.com/copyblogger/wp-gtw
Description: This plugin creates a shortcode you can insert into a post or page that will allow users to register for a webinar.
Author: Rainmaker Digital
Author URI: http://rainmakerdigital.com/

Version: 0.9.0

Text Domain: wp-gtw
Domain Path: /languages

License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/


class WP_G2W {

	/**
	 * Plugin version.
	 */
	public $plugin_version = '0.9.0';

	/**
	 * Plugin directory. Assigned in __construct().
	 */
	public $plugin_dir;

	/**
	 * Genesis Go2Webinar Admin object.
	 *
	 * @since 0.9.0
	 */
	public $admin;

	/**
	 * Debugging flag
	 *
	 * @since 0.9.0
	 */
	public $debug = false;

	/**
	 * Constructor. Runs when object is instantiated.
	 *
	 * @since 0.9.0
	 */
	public function __construct() {

		$this->plugin_dir = plugin_dir_path( __FILE__ );

		define( "WP_GTW_PLUGIN_DIR", $this->plugin_dir );
		define(	"WP_GTW_PLUGIN_URL", plugins_url( '', __FILE__ ) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->debug = true;
		}

		//register_activation_hook( __FILE__, array( $this, 'activation' ) );
		add_action( 'admin_post_gtw_sc_register', array( $this, 'register_for_webinar' ) );

		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		add_action( 'init', array( $this, 'includes' ) );
		add_action( 'init', array( $this, 'instantiate' ) );
		add_action( 'init', array( $this, 'add_shortcodes' ) );

	}


	/**
	 * Load the plugin textdomain, for translation.
	 *
	 * @since 0.9.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'wp-gtw', false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Include all the main class files.
	 *
	 * @since 0.9.0
	 */
	public function includes() {}

	/**
	 * Create the objects, assign to variables as part of the main class object.
	 *
	 * @since 0.9.0
	 */
	public function instantiate() {

		require_once( $this->plugin_dir . 'includes/class-wp-g2w-admin.php' );
		$this->admin = new WP_G2W_Admin;

	}

	public function admin_settings_page() {



	}

	/**
	 * Register the shortcode(s).
	 *
	 * @since 0.9.0
	 */
	public function add_shortcodes() {

		add_shortcode( 'webinar', array( $this, 'webinar_shortcode' ) );

	}

	/**
	 * Main [webinar] shortcode.
	 *
	 * @since 0.9.0
	 */
	public function webinar_shortcode( $atts ) {

		if ( ! is_user_logged_in() ) {
			return __( 'You must be logged in to register for the webinar.', 'wp-gtw' );
		}

		$atts = shortcode_atts( array(
			'key'    => '',
			'page'   => '',
			'button' => __( 'Register now', 'wp-gtw' ),
		), $atts );

		$user = wp_get_current_user();

		if ( $this->debug ) {
			echo "<p>First Name: " . $user->first_name . "<br />\n";
			echo "Last Name: " . $user->last_name . "<br />\n";
			echo "Email: " . $user->user_email . "</p>\n";
		}



		if ( empty ( $_GET['status'] ) && $this->user_registered( $atts['key'], $user ) ) {
			return __( 'You have already registered for this webinar', 'wp-gtw' );
		} elseif ( ! empty ( $_GET['status'] ) && $_GET['status'] == 'success' ) {
			return __( 'You have successfully registered for this webinar.', 'wp-gtw' );
		} elseif ( ! empty ( $_GET['status'] ) && $_GET['status'] == 'error' ) {
			return __( 'There was an error registering for this webinar.', 'wp-gtw' );
		}

		return $this->registration_form( $atts['button'], $user, $atts['key'], $atts['page'] );

	}

	public function register_for_webinar() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'gtw_sc_register' ) ) {
			wp_die('Sorry an error occurred, please go back and try again.');
			exit;
		} else {
			if ( isset ( $_POST['first_name'] ) && isset ( $_POST['last_name'] ) ) {
				wp_update_user(
					array(
						'ID'         => $user->ID,
						'first_name' => $_POST['first_name'],
						'last_name'  => $_POST['last_name']
					)
				);

			}
			$user = wp_get_current_user();
			if ( $this->process_form( $_POST['key'], $user ) ) {
				if ( empty ( $_POST['page'] ) ) {
					$url = add_query_arg( 'status', 'success', $_POST['_wp_http_referer'] );
				} else {
					$url = add_query_arg( 'status', 'success', get_the_permalink( $_POST['page'] ) );
				}

			} else {
				$url = add_query_arg( 'status', 'error', $_POST['_wp_http_referer'] );
			}
			wp_safe_redirect( $url );
		}
	}

	/**
	 * Show the registration form/button.
	 *
	 * @since 0.9.0
	 */
	public function registration_form( $button_text, $user, $key, $page ) {

		$form  = '<form method="post" action="'.admin_url( 'admin-post.php' ).'">';
		$form .= '<input type="hidden" name="action" value="gtw_sc_register">';
		$form .= '<input type="hidden" name="key" value="'.$key.'">';
		$form .= '<input type="hidden" name="page" value="'.$page.'">';
		$form .= wp_nonce_field( 'gtw_sc_register', '_wpnonce', true, false );
		if( ! $user->first_name || ! $user->first_name ) {
			$form .= '<p>Please complete both your first name and last name in your profile before continuing.</p>';
		}

		if( empty ( $user->first_name ) ) {
			$form .= '<input required type="text" name="first_name" placeholder="First name">';
		}

		if( empty ( $user->last_name ) ) {
			$form .= '<input required type="text" name="last_name" placeholder="Last name">';
		}

		$form .= sprintf( '<input type="submit" name="submit" value="%s" />', esc_attr( $button_text ) );
		$form .= '</form>';
		//$this->get_webinars();
		return $form;

	}

	/**
	 * Process the registration form.
	 *
	 * @since 0.9.0
	 */
	public function process_form( $webinar_key, $user ) {

		if ( ! $user->first_name || ! $user->last_name || ! $user->user_email ) {

			if ( $this->debug ) {
				echo '<p><pre>';
				var_dump( $user );
				echo '</pre></p>';
			}

			return false;

		}

		$data = array(
			'firstName' => $user->first_name,
			'lastName'  => $user->last_name,
			'email'     => $user->user_email,
		);

		$request = $this->g2w_api_request( array(
			'endpoint' => sprintf( '/webinars/%s/registrants', $webinar_key ),
			'data'     => $data,
		) );

		if ( $this->debug ) {
			echo '<p><pre>';
			var_dump( $request );
			echo '</pre></p>';
		}

		return $request;

	}

	/**
	 * Check to see if user is already registered for the webinar.
	 *
	 * @since 0.9.0
	 */
	public function user_registered( $webinar_key, $user ) {

		$registrants = $this->g2w_api_request( array(
			'endpoint'    => sprintf( '/webinars/%s/registrants', $webinar_key ),
			'method'      => 'get',
		) );

		if ( $this->debug ) {
			echo '<p><pre>';
			var_dump( $registrants );
			echo '</pre></p>';
		}

		$registrants = json_decode( $registrants['body'] );

		if ( ! $registrants ) {
			return false;
		}

		foreach ( $registrants as $registrant ) {

			if ( $user->user_email == $registrant->email ) {
				return true;
			}

		}

		return false;

	}

	public function get_webinars() {

		$return = array();

		$request = $this->g2w_api_request( array(
			'endpoint' => '/upcomingWebinars',
			'method'   => 'get'
		) );

		if ( $this->debug ) {
			echo '<p><pre>';
			var_dump( json_decode( $request['body'] ) );
			echo '</pre></p>';
		}

		$webinars = json_decode( $request['body'] );

		foreach ( $webinars as $webinar ) {
			$return[] = array(
				'key'   => $webinar->webinarKey,
				'title' => $webinar->subject,
				'date'  => date_i18n( get_option( 'date_format' ), strtotime( $webinar->times[0]->startTime ) )
			);
		}

		if ( $this->debug ) {
			echo '<p><pre>';
			var_dump( $return );
			echo '</pre></p>';
		}

		return $return;

	}

	/**
	 * Request to GoToWebinar API.
	 *
	 * 0.9.0
	 */
	public function g2w_api_request( $args = array() ) {

		$args = wp_parse_args( $args, array(
			'endpoint'    => '/',
			'method'      => 'post',
			'data'        => array(),
		) );

		$credentials = get_option( 'wp_gtw_credentials' );

		$rest_url = sprintf( 'https://api.citrixonline.com/G2W/rest/organizers/%s', $credentials['organiser_key'] );

		$url = $rest_url . $args['endpoint'];

		$request_args = array(
			'body'    => json_encode( $args['data'] ),
			'headers' => array(
				'Accept'        => 'application/json',
				'Content-type'  => 'application/json',
				'Authorization' => 'OAuth oauth_token=' . $credentials['token'],
			),
		);

		if ( $this->debug ) {
			echo "<p>REST URL: {$url}</p>\n";
			echo '<p>Request Args:<br /><pre>';
			var_dump( $request_args );
			echo '</pre></p>';
		}

		//* Execute the POST/GET
		$response = 'get' == $args['method'] ? wp_remote_get( $url, $request_args ) : wp_remote_post( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return $response;

	}

}

function WP_G2W() {

	static $_wp_g2w = null;

	if ( null == $_wp_g2w ) {
		$_wp_g2w = new WP_G2W;
	}

	return $_wp_g2w;

}

WP_G2W();
