<?php

/**
 * v3 - http://strava.github.io/api/v3/oauth/
 * 
 * Set up an "API Application" at Strava
 * Save the Client ID and Client Secret in WordPress - redirect to strava oauth/authorize URL for permission
 * Get redirected back to this settings page with ?code=
 * Use code to retrieve auth token
 */

class WPStrava_Settings {

	private $feedback;
	private $token;
	private $page_name = 'wp-strava-options';
	private $option_page = 'wp-strava-settings-group';
	
	//register admin menus
	public function hook() {
		add_action( 'admin_init', array( $this, 'register_strava_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_strava_menu' ) );
		add_action( 'current_screen', array( $this, 'current_screen' ) );
		add_action( 'option_home', array( $this, 'option_home' ) );
	}

	public function current_screen( $screen ) {
		if ( $screen->id = 'settings_page_' . $this->page_name ) {			
			if ( isset( $_GET['code'] ) ) {
				$token = $this->get_token( $_GET['code'] );
				if ( $token ) {
					add_settings_error( 'strava_token', 'strava_token', sprintf( __( 'New Strava Token Retrieved: %s', 'wp-strava' ), $this->feedback ) , 'updated' );
					update_option( 'strava_token', $token );
				} else {
					add_settings_error( 'strava_token', 'strava_token', $this->feedback );
				}
			}
		}
	}

	/**
	 * This runs after options are saved
	 */
	public function option_home() {
		if ( isset( $_POST['option_page'] ) && $_POST['option_page'] == $this->option_page ) {
			//redirect only if all the right options are in place
			$errors = get_settings_errors();
			if ( ! empty( $errors ) )
				return;
			
			$client_id = get_option( 'strava_client_id' );
			$client_secret = get_option( 'strava_client_secret' );

			if ( $client_id && $client_secret ) {
				$redirect = admin_url( "options-general.php?page={$this->page_name}" );
				$url = "https://www.strava.com/oauth/authorize?client_id={$client_id}&response_type=code&redirect_uri={$redirect}&approval_prompt=force";
				wp_redirect( $url );
				exit();
			}			
		}
	}
	
	public function add_strava_menu() {
		add_options_page( __( 'Strava Settings', 'wp-strava' ),
						  __( 'Strava', 'wp-strava' ),
						  'manage_options',
						  $this->page_name,
						  array( $this, 'print_strava_options' ) );
	}

	public function register_strava_settings() {
		$this->token = get_option( 'strava_token' );
	   
		add_settings_section( 'strava_api', __( 'Strava API', 'wp-strava' ), array( $this, 'print_api_instructions' ), 'wp-strava' ); //NULL / NULL no section label needed

		if ( ! $this->token ) {
			register_setting( $this->option_page, 'strava_client_id', array( $this, 'sanitize_client_id' ) );
			register_setting( $this->option_page, 'strava_client_secret', array( $this, 'sanitize_client_secret' ) );

			add_settings_field( 'strava_client_id', __( 'Strava Client ID', 'wp-strava' ), array( $this, 'print_client_input' ), 'wp-strava', 'strava_api' );
			add_settings_field( 'strava_client_secret', __( 'Strava Client Secret', 'wp-strava' ), array( $this, 'print_secret_input' ), 'wp-strava', 'strava_api' );
		} else {
			register_setting( $this->option_page, 'strava_token',    array( $this, 'sanitize_token' ) );
			add_settings_field( 'strava_token', __( 'Strava Token', 'wp-strava' ), array( $this, 'print_token_input' ), 'wp-strava', 'strava_api' );
		}
		
		register_setting( $this->option_page, 'strava_som',    array( $this, 'sanitize_som' ) );

		add_settings_section( 'strava_options', __( 'Options', 'wp-strava' ), NULL, 'wp-strava' );

		add_settings_field( 'strava_som', __( 'System of Measurement', 'wp-strava' ), array( $this, 'print_som_input' ), 'wp-strava', 'strava_options' );
	}

	public function print_api_instructions() {
		?><p>Steps:</p>
			<ol>
				<li>Create your app here: http://www.strava.com/developers</li>
				<p>Use the following information:</p>
				<ul>
					<li>Application Name: [SiteName] Strava</li>
					<li>Website: [site_url]
					<li>Application Description: WP-Strava for [SiteName]
					<li>Authorization Callback Domain: [site_url] + oauth path
				</ul>
				<li>Once you've created your application, enter the Client ID and Client Secret below, which can be found at https://www.strava.com/settings/api</li>
				<li>You'll be redirected to strava to authorize your app after saving your Client ID and Secret. If successful, your Strava Token will display</li>
				<li>Erase your Strava Token if you need to re-authorize</li>
			</ol>

		<?php
		//'
	}
	
	public function print_strava_options() {
		?>
		<div class="wrap">
   			<div id="icon-options-general" class="icon32"><br/></div>
			<h2><?php _e( 'Strava Settings', 'wp-strava' ); ?></h2>
					
			<form method="post" action="<?php echo admin_url( 'options.php' ); ?>">
				<?php settings_fields( $this->option_page ); ?>
				<?php do_settings_sections( 'wp-strava' ); ?>

				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	public function print_client_input() {
		?><input type="text" id="strava_client_id" name="strava_client_id" value="<?php echo get_option('strava_client_id'); ?>" /><?php
	}

	public function print_secret_input() {
		?><input type="text" id="strava_client_secret" name="strava_client_secret" value="<?php echo get_option('strava_client_secret'); ?>" /><?php
	}

	public function print_token_input() {
		?><input type="text" id="strava_token" name="strava_token" value="<?php echo get_option('strava_token'); ?>" /><?php
	}

	public function sanitize_client_id( $client_id ) {
		if ( ! is_numeric( $client_id ) ) {
			add_settings_error( 'strava_client_id', 'strava_client_id', __( 'Client ID must be a number.', 'wp-strava' ) );
		}
		return $client_id;
	}

	public function sanitize_client_secret( $client_secret ) {
		if ( trim( $client_secret ) == '' ) {
			add_settings_error( 'strava_client_secret', 'strava_client_secret', __( 'Client Secret is required.', 'wp-strava' ) );
		}
		echo "WHEREAMI";
		return $client_secret;
	}

	public function sanitize_token( $token ) {
		/*
		if ( isset( $_GET['code'] ) ) {
			$token = $this->get_token( $_GET['code'] );
			if ( $token ) {
				add_settings_error( 'strava_token', 'strava_token', sprintf( __( 'New Strava Token Retrieved: %s', 'wp-strava' ), $this->feedback ) , 'updated' );
				return $token;
			} else {
				add_settings_error( 'strava_token', 'strava_token', $this->feedback );
				return NULL;
			}
		}
		*/
		return $token;
	}

	private function get_token( $code ) {
		$client_id = get_option( 'strava_client_id' );
		$client_secret = get_option( 'strava_client_secret' );

		if ( $client_id && $client_secret ) {
			$data = array( 'client_id' => $client_id, 'client_secret' => $client_secret, 'code' => $code );
			$strava_info = WPStrava::get_instance()->api->post( 'oauth/token', $data );

			if( $strava_info ) {
				if( isset( $strava_info->access_token ) ) {
					$this->feedback .= __( 'Successfully authenticated.', 'wp-strava' );
					return $strava_info->access_token;
				} else {
					$this->feedback .= __( 'Authentication failed, please check your credentials.', 'wp-strava' );
					return false;
				}
			} else {
				$this->feedback .= __( 'There was an error pulling data of strava.com.', 'wp-strava' );
				return false;
			}
		} else {
			$this->feedback .= __( 'Missing Client ID or Client Secret.', 'wp-strava' );
			return false;
		}		
	}
	
	public function print_options_label() {
		?><p>Options</p><?php
	}

	public function print_som_input() {
		$strava_som = get_option( 'strava_som' );
		?>
		<select id="strava_som" name="strava_som">
			<option value="metric" <?php selected( $strava_som, 'metric' ); ?>><?php _e( 'Metric', 'wp-strava' )?></option>
			<option value="english" <?php selected( $strava_som, 'english' ); ?>><?php _e( 'English', 'wp-strava' )?></option>
		</select>
		<?php
	}

	public function sanitize_som( $som ) {
		return $som;
	}

	public function __get( $name ) {
		return get_option( "strava_{$name}" );
	}

}