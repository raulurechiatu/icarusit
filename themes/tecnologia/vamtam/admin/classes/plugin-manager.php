<?php

/**
 * Plugin Manager
 *
 * @package vamtam/tecnologia
 */
/**
 * class VamtamPluginManager
 */
class VamtamPluginManager {
	/**
	 * TGMPA instance storage
	 *
	 * @var object
	 */
	protected $tgmpa_instance;

	/**
	 * TGMPA Menu slug
	 *
	 * @var string
	 */
	protected $tgmpa_menu_slug 	= 'tgmpa-install-plugins';

	/**
	 * TGMPA Menu url
	 *
	 * @var string
	 */
	protected $tgmpa_url 		= 'themes.php?page=tgmpa-install-plugins';

	/**
	 * Holds the current instance of the plugin manager
	 * @var VamtamPluginManager
	 */
	private static $instance 	= null;

	/**
	 * @return VamtamPluginManager
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance 	= new self;
		}

		return self::$instance;
	}

	public function __construct() {
		$this->init_globals();
		$this->init_actions();
	}

	/**
	 * Setup the class globals.
	 */
	public function init_globals() {
		$this->parent_slug     	= 'vamtam_theme_setup';
		if ( class_exists( 'TGM_Plugin_Activation' ) && isset( $GLOBALS['tgmpa'] ) ) {
			$this->get_tgmpa_instanse();
			$this->set_tgmpa_url();
		}
		if( isset( $_POST['action'] ) && $_POST['action'] === "vamtam_setup_plugins" && wp_doing_ajax() ) {
			add_filter( 'wp_redirect', '__return_false', 999 );
		}
	}

	/**
	 * Setup the hooks, actions and filters.
	 */
	public function init_actions() {
		if ( current_user_can( 'manage_options' ) ) {

            add_action( 'admin_menu'				    , array( $this, 'admin_menus' ), 15 );
            add_action( 'admin_enqueue_scripts'		    , array( $this, 'enqueue_scripts' ) );
            add_filter( 'tgmpa_load'				    , array( $this, 'tgmpa_load' ), 10, 1 );
			add_action( 'wp_ajax_vamtam_setup_plugins'	, array( $this, 'ajax_plugins' ) );
			add_filter( 'tgmpa_admin_menu_args', function ( $menu ) {
				$menu['parent_slug'] = null; // Hide tgmpa menu without losing tgmpa-install-plugins page.
				return $menu;
			} );

			if( isset( $_POST['action'] ) && $_POST['action'] === "vamtam_setup_plugins" && wp_doing_ajax() ) {
				add_filter( 'wp_redirect', '__return_false', 999 );
			}
		}
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'vamtam-admin-plugin-manager'	, VAMTAM_ADMIN_ASSETS_URI . 'js/plugin-manager.js'	, array(
			'jquery'
		), '1.7.2', true );

		wp_localize_script(
			'vamtam-admin-plugin-manager' ,
			'vamtam_setup_params',
			array(
				'tgm_plugin_nonce' => array(
					'update'  => wp_create_nonce( 'tgmpa-update' ),
					'install' => wp_create_nonce( 'tgmpa-install' ),
				),
				'tgm_bulk_url'     => admin_url( $this->tgmpa_url ),
				'ajaxurl'          => admin_url( 'admin-ajax.php' ),
				'wpnonce'          => wp_create_nonce( 'vamtam_setup_nonce' ),
			) );
	}

    /**
     * Check for TGMPA load
     */
	public function tgmpa_load( $status ) {
		return is_admin() || current_user_can( 'install_themes' );
	}

	/**
	 * Get configured TGMPA instance
	 */
	public function get_tgmpa_instanse() {
		$this->tgmpa_instance 	= call_user_func( array( get_class( $GLOBALS['tgmpa'] ), 'get_instance' ) );
	}

	/**
	 * Update $tgmpa_menu_slug and $tgmpa_parent_slug from TGMPA instance
	 */
	public function set_tgmpa_url() {
		$this->tgmpa_menu_slug 	= ( property_exists( $this->tgmpa_instance, 'menu' ) ) ? $this->tgmpa_instance->menu : $this->tgmpa_menu_slug;
		$tgmpa_parent_slug 		= ( property_exists( $this->tgmpa_instance, 'parent_slug' ) && $this->tgmpa_instance->parent_slug !== 'themes.php' ) ? 'admin.php' : 'themes.php';
		$this->tgmpa_url 		= $tgmpa_parent_slug . '?page=' . $this->tgmpa_menu_slug;
	}

	/**
	 * Add admin menus/screens.
	 */
	public function admin_menus() {

	}

	/**
	 * Output the tgmpa plugins list
	 */
	private function get_plugins( $custom_list = array() ) {

		if ( empty( $this->tgmpa_instance )) {
			$this->get_tgmpa_instanse();
		}

		$r = new ReflectionMethod( 'TGMPA_List_Table', 'categorize_plugins_to_views' );
		$r->setAccessible( true );

		$plugins = $r->invoke( new TGMPA_List_Table() );

		return $plugins;
	}

	/**
	 * Checks if there are open actions for the required plugins.
	 * Returns a status:
	 * 		success -> no pending actions for required plugins.
	 * 		warning -> there are pending updates for required plugins.
	 * 		error   -> there are pending installations/activations for required plugins.
	 *		fail    -> the procedure encountered a problem.
	 */
	public static function get_required_plugins_status( $custom_list = array() ) {

		if ( class_exists( 'TGM_Plugin_Activation' ) ) {
			$tgmpa = TGM_Plugin_Activation::get_instance();
		} else {
			return 'fail';
		}

		$r = new ReflectionMethod( 'TGMPA_List_Table', 'categorize_plugins_to_views' );
		$r->setAccessible( true );

		$plugins = $r->invoke( new TGMPA_List_Table() );

		foreach ( $plugins as $status => $list ) {
			foreach ( $list as $slug => $plugin ) {
				if ( $plugin['required'] !== true ) {
					unset( $plugins[ $status ][ $slug ]  );
				}
			}
		}

		if ( count( $plugins['all'] ) > 0 ) {
			if ( count( $plugins['install'] ) > 0 || count( $plugins['activate'] ) > 0 ) {
				return 'error';
			}
			if ( count( $plugins['update'] ) > 0 ) {
				return 'warning';
			}
		}
		return 'success';
	}

	/**
	 * Returns the plugin data from WP.org API
	 */
	public static function get_plugin_data_by_slug( $slug = '' ) {

		if ( empty( $slug ) ) {
			return false;
		}

	    $key = sanitize_key( 'vamtam_plugin_data_'.$slug );

	    if ( true|| false === ( $plugins = get_transient( $key ) ) ) {
			$args = array(
				'slug' => $slug,
				'fields' => array(
			 		'short_description' => true
				)
			);

			$url = 'https://api.wordpress.org/plugins/info/1.2/';

			$url = add_query_arg(
				array(
					'action'  => 'plugin_information',
					'request' => (object) $args,
				),
				$url
			);

			$response = wp_remote_get(
				$url,
				array(
					'timeout'    => 15,
					'user-agent' => 'WordPress/' . wp_get_wp_version() . '; ' . home_url( '/' ),
				)
			);

			$data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( is_object( $data ) && isset( $data->short_description ) && isset( $data->version ) ) {
				$plugins = [ 'Description' => $data->short_description , 'Version' => $data->version ];
			} else {
				$plugins = false;
			}

			// Set transient for next time... keep it for 24 hours
			set_transient( $key, $plugins, 24 * HOUR_IN_SECONDS );

	    }

	    return $plugins;
	}

	/**
	 * Plugins AJAX Process
	 */
	public function ajax_plugins() {
		if ( ! check_ajax_referer( 'vamtam_setup_nonce', 'wpnonce' ) || empty( $_POST['slug'] ) ) {
			wp_send_json_error( array( 'error' => 1, 'message' => esc_html__( 'No Slug Found', 'tecnologia' ) ) );
		}
		$json = array();
		// send back some json we use to hit up TGM
		$plugins = $this->get_plugins();
		// what are we doing with this plugin?
		foreach ( $plugins['activate'] as $slug => $plugin ) {
			if ( $_POST['slug'] == $slug ) {
				$json = array(
					'url'           => admin_url( $this->tgmpa_url ),
					'plugin'        => array( $slug ),
					'tgmpa-page'    => $this->tgmpa_menu_slug,
					'plugin_status' => 'all',
					'_wpnonce'      => wp_create_nonce( 'bulk-plugins' ),
					'action'        => 'tgmpa-bulk-activate',
					'action2'       => - 1,
					'message'       => esc_html__( 'Activating...', 'tecnologia' ),
				);
				break;
			}
		}
		foreach ( $plugins['install'] as $slug => $plugin ) {
			if ( $_POST['slug'] == $slug ) {
				$json = array(
					'url'           => admin_url( $this->tgmpa_url ),
					'plugin'        => array( $slug ),
					'tgmpa-page'    => $this->tgmpa_menu_slug,
					'plugin_status' => 'all',
					'_wpnonce'      => wp_create_nonce( 'bulk-plugins' ),
					'action'        => 'tgmpa-bulk-install',
					'action2'       => - 1,
					'message'       => esc_html__( 'Installing...', 'tecnologia' ),
				);
				break;
			}
		}

		if ( $json ) {
			$json['hash'] = md5( serialize( $json ) ); // used for checking if duplicates happen, move to next plugin
			wp_send_json( $json );
		} else {
			wp_send_json( array( 'done' => 1, 'message' => esc_html__( 'Activated', 'tecnologia' ) ) );
		}
		exit;

	}

	public static function filter_tabs() {
		?>
		<div id="vamtam-plugins-filters">
			<ul>
				<li>
					<a data-filter="required" class="vamtam-filter-btn">
						<?php esc_html_e( 'Required', 'tecnologia' ); ?>
					</a>
				</li>
				<li>
					<a data-filter="recommended" class="vamtam-filter-btn">
						<?php esc_html_e( 'Recommended', 'tecnologia' ); ?>
					</a>
				</li>
			</ul>
			<hr/>
			<p id="vamtam-required-plugins-notice">
				<strong><?php esc_html_e( 'Why required plugins?', 'tecnologia' ); ?></strong>
				<br />
				<?php esc_html_e( 'A plugin offers additional functionality and features beyond a typical WordPress installation. You do not need additional licensing in order to use the plugins below with your theme.', 'tecnologia' ); ?>
			</p>
			<p id="vamtam-recommended-plugins-notice">
				<svg xmlns="http://www.w3.org/2000/svg" width="14" height="50" viewBox="0 0 14 50"><path fill-rule="evenodd" d="M7 33.3a5.4 5.4 0 0 1-5.4-5L0 5.8A5.1 5.1 0 0 1 1.1 2c.2-.2.5-.3.8-.1.2.2.3.5.1.8a4.1 4.1 0 0 0-.9 2.8l1.6 22.7a4.3 4.3 0 0 0 5 3.9c.3 0 .6.1.7.4 0 .3-.2.6-.5.7H7zm4.7-3.6h-.1a.6.6 0 0 1-.4-.7v-.7L13 5.6v-.3c0-2.3-2-4.2-4.3-4.2h-2A.6.6 0 0 1 6 .6c0-.4.3-.6.6-.6h2A5.4 5.4 0 0 1 14 5.7l-1.6 22.7-.1.9c-.1.2-.3.4-.6.4zM7 50a6.2 6.2 0 0 1-6.2-6.1A6.2 6.2 0 1 1 13 42.2c0 .3-.1.6-.4.7-.3 0-.6-.1-.7-.4a5.1 5.1 0 0 0-10 1.4 5 5 0 0 0 5.1 5 5 5 0 0 0 5-5c0-.3.3-.6.7-.6.3 0 .5.3.5.6 0 3.4-2.8 6.1-6.2 6.1z"/></svg>
				<?php esc_html_e( 'Please note that the theme doesn\'t depend on the plugins in this list to function properly. Nor does the demo content importer. Install them only if you are going to use them, otherwise, they may impact the performance of the site or put an unnecessary burden on your hosting.', 'tecnologia' ); ?>
			</p>
		</div>
		<?php
	}
}
