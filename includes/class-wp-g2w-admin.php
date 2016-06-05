<?php
/**
 * Registers a new admin page for the WP GoToWebinar Plugin
 *
 * @since 0.9.0
 */
class WP_G2W_Admin  {

	/**
	 * Create an admin menu item and settings page.
	 *
	 * @since 0.9.0
	 */

	const CLIENT_ID	             = 'JBW1kpzc9bgmlMkZlB9I4gyFZdO0i4m1';
	const CLIENT_SECRET          = 'hg1TkLA4YCG330qz';

	//const REDIRECT_URI		     = 'http://tailoreddev.loc/wp-admin/admin.php?page=genesis-g2w';
	const AUTHORIZATION_ENDPOINT = 'https://api.citrixonline.com/oauth/authorize';
	const TOKEN_ENDPOINT		 = 'https://api.citrixonline.com/oauth/access_token';

	static $client;
	static $state;
	static $redirect_uri;

	function __construct() {

		self::$redirect_uri = admin_url( 'options-general.php?page=wp-gtw' );

		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'oauth_callack' ) );
		add_action( 'admin_enqueue_scripts' , array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'media_buttons' , array( $this, 'add_my_media_button' ) );
		add_action( 'admin_footer' , array( $this, 'footer_modal'));
		add_action( 'wp_ajax_gtw_get_webinars'   , array( $this, 'ajax_get_webinars' ) );

	}

	/**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'WP GoToWebinar',
            'WP GoToWebinar Settings',
            'manage_options',
            'wp-gtw',
            array( $this, 'form' )
        );
    }

	static public function admin_enqueue_scripts() {
        wp_enqueue_script( 'jquery-magnific-popup', WP_GTW_PLUGIN_URL .'/js/jquery.magnific-popup.min.js', array('jquery' ) );
        wp_enqueue_script( 'affiliate_links_admin', WP_GTW_PLUGIN_URL .'/js/admin.js', array('jquery', 'jquery-magnific-popup' ) );
        wp_enqueue_style( 'magnific-popup-css' , WP_GTW_PLUGIN_URL .'/css/magnific-popup.css' );
        wp_enqueue_style ( 'affiliate_links_admin_style', WP_GTW_PLUGIN_URL .'/css/admin.css' );
    }

    function add_my_media_button() {
	    echo '<button id="insert_gtw_form_link" class="button"><img src="'.WP_GTW_PLUGIN_URL.'/images/gtw_icon.png"> Add Webinar</button>';
	}

	static function footer_modal() { ?>
		<div id="insert_gtw_form" class="white-popup mfp-hide">
		  	<h1>Add Webinar Registration</h1>
		  	<form method="post" id="gtw_insert_form">
		  		<table class="form-table">
		  			<tr>
		  				<th>Select webinar</th>
		  				<td>
					  		<select id="webinar_key">
							  	<option>Loading webinars....</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>Button Text</th>
						<td>
							<input type="regular-text" id="button_text" placeholder="Register Now">
							<p class="description">Leave blank for default</p>
						</td>
					</tr>
					<tr>
						<th>Redirect to thank you page?</th>
						<td><input type="checkbox" id="gtw_redirect_check" value="1"></td>
					</tr>
					<tr id="gtw_redirect_row">
						<th>Thank you page</th>
						<td><?php wp_dropdown_pages( array( 'id' => 'thank_page' ) ); ?></td>
					</tr>
				</table>
				<button type="submit" class="button-primary">Insert Registration Form</button>
			</form>
		</div>
	<?php }

	public static function ajax_get_webinars() {
  		$output = '';
  		$webinars = WP_G2W()->get_webinars();
  		foreach( $webinars as $webinar ) {
  			$output .= sprintf(
  				'<option value="%d">%s</option>',
  				$webinar['key'],
  				$webinar['title'].' ('.$webinar['date'].')'
  			);
  		}
  		echo $output;
  		wp_die();
	}

	public static function oauth_callack() {
		if ( isset ( $_GET['code'] ) ) {
			self::setup_oauth();
			$credentials = self::get_credentials();
			update_option( 'wp_gtw_credentials', $credentials );
		}
	}

	private static function setup_oauth() {

		if ( ! class_exists( 'oAuth_Client' ) ) {
			require_once( 'oAuth2_Client.php' );
		}
		require_once( 'GrantType/IGrantType.php' );
		require_once( 'GrantType/AuthorizationCode.php' );

		self::$state   = 'gtw_oauth_token';
		self::$client  = new oAuth_Client\oAuth_Client( self::CLIENT_ID, self::CLIENT_SECRET );

		$credentials = get_option( 'wp_gtw_credentials' );

		if ( $token ) {

			self::$client->setAccessToken( $credentials['token'] );
			self::$client->setAccessTokenType( 1 );

		}

	}

	private static function get_credentials(){

		$params = array( 'code' => $_GET['code'], 'redirect_uri' => self::$redirect_uri );

		$response = self::$client->getAccessToken( self::TOKEN_ENDPOINT, 'authorization_code', $params );

		$token = array(
			'token' => $response['result']['access_token'],
			'organiser_key' => $response['result']['organizer_key']
		);

		return $token;

	}

	/**
	 * The Genesis GoToWebinar plugin settings form.
	 *
	 * @since 0.9.0
	 */
	function form() {
		self::setup_oauth();
		$url = self::$client->getAuthenticationUrl(self::AUTHORIZATION_ENDPOINT, self::$redirect_uri, array( 'state' => self::$state ) );
		$credentials = get_option( 'wp_gtw_credentials' );
		?>
		<div class="wrap">
			<h1>WP GoToWebinar Settings</h1>
			<table class="form-table">
			<tbody>

				<tr valign="top">
					<th>Authenticate</th>
					<td>
						<?php if( empty( $credentials ) ) : ?>
							<a class="button-primary"  href="<?php echo $url; ?>">Link GoToWebinar Account</a>
						<?php else: ?>
							<p class="description">You have successfully linked your GoToWebinar account.</p>
							<a class="button"  href="<?php echo $url; ?>">Update GoToWebinar Account</a>
						<?php endif; ?>
					</td>
				</tr>

			</tbody>
			</table>
		</div>

		<?php
	}

}
