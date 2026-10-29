<?php

/**
 * Purchase Helper
 *
 * @package vamtam/tecnologia
 */
/**
 * class VamtamPurchaseHelper
 */
class VamtamPurchaseHelper extends VamtamAjax {

	public static $storage_path;

	/**
	 * Hook ajax actions
	 */
	public function __construct() {
		parent::__construct();

		add_filter( 'admin_body_class', array( __CLASS__, 'vamtam_admin_body_class' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu_1'), 11 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu_2' ), 22 ); // after "Help"

		add_action( 'after_setup_theme', array( __CLASS__, 'after_setup_theme' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_early_init' ), 5 );
		add_action( 'admin_notices', array( __CLASS__, 'notice_early' ), 5 ); // after TGMPA registers its notices, but before printing
		add_action( 'admin_notices', array( __CLASS__, 'offers_notice' ), 6 );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_scripts' ) );

		add_filter( 'tgmpa_update_bulk_plugins_complete_actions', array( __CLASS__, 'tgmpa_plugins_complete_actions' ), 10, 2 );
	}

	public static function vamtam_admin_body_class( $classes )
	{
		// Adds a class to the body tag to hint for pending verification.
		if ( ! Version_Checker::is_valid_purchase_code() ) {
			$classes .= ' vamtam-not-verified';
		}
		return $classes;
	}

	public static function notice_early() {
		$screen = get_current_screen();
		if ( ! self::is_theme_setup_page() && $screen->id !== 'plugins' ) {
			remove_action( 'admin_notices', array( $GLOBALS['tgmpa'], 'notices' ), 10 );
		}

		$valid_key = Version_Checker::is_valid_purchase_code();

		$is_updates_page = $screen->id === 'update-core';

		if ( ! $valid_key && ! $is_updates_page ) {
			VamtamFramework::license_register();
		}
	}

	private static function server_tests() {
		$timeout = (int) ini_get( 'max_execution_time' );
		$memory  = ini_get( 'memory_limit' );
		$memoryB = str_replace( array( 'G', 'M', 'K' ), array( '000000000', '000000', '000' ), $memory );

		$tests = array(
			array(
				'name'  => esc_html__( 'PHP Version', 'tecnologia' ),
				'test'  => version_compare( phpversion(), '5.5', '<' ),
				'value' => phpversion(),
				'desc'  => esc_html__( 'While this theme works with all PHP versions supported by WordPress Core, PHP versions 5.5 and older are no longer maintained by their developers. Consider switching your server to PHP 5.6 or newer.', 'tecnologia' ),
			),
			array(
				'name'  => esc_html__( 'PHP Time Limit', 'tecnologia' ),
				'test'  => $timeout > 0 && $timeout < 30,
				'value' => $timeout,
				'desc'  => esc_html__( 'The PHP time limit should be at least 30 seconds. Note that in some configurations your server (Apache/nginx) may have a separate time limit. Please consult with your hosting provider if you get a time out while importing the demo content.', 'tecnologia' ),
			),
			array(
				'name'  => esc_html__( 'PHP Memory Limit', 'tecnologia' ),
				'test'  => (int) $memory > 0 && $memoryB < 96 * 1024 * 1024,
				'value' => $memory,
				'desc'  => esc_html__( 'You need a minimum of 96MB memory to use the theme and the bundled plugins. For non-US English websites you need a minimum of 128MB in order to accomodate the translation features which are otherwise disabled.', 'tecnologia' ),
			),
			array(
				'name'  => esc_html__( 'PHP ZipArchive Extension', 'tecnologia' ),
				'test'  => ! class_exists( 'ZipArchive' ),
				'value' => '',
				'desc'  => esc_html__( 'ZipArchive is a requirement for importing the demo sliders.', 'tecnologia' ),
			),
		);

		$fail = 0;

		foreach ( $tests as $test ) {
			$fail += (int) $test['test'];
		}

		return array(
			'fail'  => $fail,
			'tests' => $tests,
		);
	}

	private static function is_theme_setup_page() {
		return isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'vamtam_theme_setup' ) );
	}

	public static function admin_scripts( $hook = '' ) {
		$theme_version = VamtamFramework::get_version();

		wp_register_script( 'vamtam-check-license', VAMTAM_ADMIN_ASSETS_URI . 'js/check-license.js', array( 'jquery' ), $theme_version, true );
		wp_register_script( 'vamtam-import-buttons', VAMTAM_ADMIN_ASSETS_URI . 'js/import-buttons.js', array( 'jquery' ), $theme_version, true );

		// DM Sans powers the offer banners: the dashboard sidebar card and the dismissible promo notice.
		$on_setup_page = false !== strpos( (string) $hook, 'vamtam_theme' ) || false !== strpos( (string) $hook, 'tgmpa' );
		if ( $on_setup_page || self::should_show_offers_notice() ) {
			wp_enqueue_style( 'vamtam-admin-dm-sans', 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap', array(), null );
		}
	}

	public static function tgmpa_plugins_complete_actions( $update_actions, $plugin_info ) {
		if ( isset( $update_actions['dashboard'] ) ) {
			$update_actions['dashboard'] = sprintf(
				esc_html__( 'All plugins installed and activated successfully. %1$s', 'tecnologia' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=vamtam_theme_setup_import_content' ) ) . '" class="button button-primary">' . esc_html__( 'Continue with theme setup.', 'tecnologia' ) . '</a>'
			);

			$update_actions['dashboard'] .= '
                <script>
                    window.scroll( 0, 10000000 );
                </script>
            ';
		}

		return $update_actions;
	}

	public static function admin_menu() {
		add_menu_page( esc_html__( 'VamTam', 'tecnologia' ), esc_html__( 'VamTam', 'tecnologia' ), 'edit_theme_options', 'vamtam_theme_setup', array( __CLASS__, 'page' ), '', 2 );
		add_submenu_page( 'vamtam_theme_setup', esc_html__( 'Dashboard', 'tecnologia' ), esc_html__( 'Dashboard', 'tecnologia' ), 'edit_theme_options', 'vamtam_theme_setup', array( __CLASS__, 'page' ) );
		remove_submenu_page('vamtam_theme_setup','vamtam_theme_setup');
		add_submenu_page( 'vamtam_theme_setup', esc_html__( 'Dashboard', 'tecnologia' ), esc_html__( 'Dashboard', 'tecnologia' ), 'edit_theme_options', 'vamtam_theme_setup', array( __CLASS__, 'page' ) );
	}

	public static function admin_menu_1() {
		//Called with a lower priority so 'Installed Plugins' menu item has been registered (tgmpa).
		add_submenu_page( 'vamtam_theme_setup', esc_html__( 'Import Demo Content', 'tecnologia' ), esc_html__( 'Import Demo Content', 'tecnologia' ), 'edit_theme_options', 'vamtam_theme_setup_import_content', array( __CLASS__, 'vamtam_theme_setup_import_content' ) );
	}

	public static function admin_menu_2() {
		add_submenu_page(
			'vamtam_theme_setup',
			esc_html__( 'Services', 'tecnologia' ),
			esc_html__( 'Deals Up To', 'tecnologia' ) .
			'<span id="vamtam-premium-services">' . esc_html__( '70% Off', 'tecnologia' ) . '</span>',
			'edit_theme_options',
			'vamtam_theme_services',
			array( __CLASS__, 'services_menu_item' )
		);
	}

	public static function services_menu_item() {
		wp_redirect( 'https://vamtam.com/services/' );
		exit;
	}

	private static function promo_url( $campaign ) {
		// Envato Elements registrations get the Elements landing page; everything else
		// (purchase code, or not yet registered) gets the Envato Market one.
		$is_elements = (bool) get_option( VamtamFramework::get_token_option_key() );
		$base        = $is_elements ? 'https://vamtam.com/promo-envato-elements/' : 'https://vamtam.com/promo-envato-market/';

		return add_query_arg(
			array(
				'utm_source'   => 'wp',
				'utm_medium'   => 'banner',
				'utm_campaign' => $campaign,
			),
			$base
		);
	}

	private static function should_show_offers_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( get_transient( 'vamtam_dismissed_offers_notice' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		// Skip the VamTam dashboard pages (they carry their own sidebar banner) and the block editor.
		if ( false !== strpos( (string) $screen->id, 'tecnologia' ) || $screen->is_block_editor() ) {
			return false;
		}

		return true;
	}

	public static function offers_notice() {
		if ( ! self::should_show_offers_notice() ) {
			return;
		}
		?>
		<div class="vamtam-notice vamtam-offers-notice notice is-dismissible">
			<a class="vamtam-offers-cta" href="<?php echo esc_url( self::promo_url( 'admin_banner' ) ); ?>" target="_blank" rel="noopener noreferrer">
					<img class="vamtam-offers-logo" src="<?php echo esc_url( VAMTAM_ADMIN_ASSETS_URI . 'images/banner-logo.svg' ); ?>" alt="VamTam" width="44" height="131" />
				<span class="vamtam-offers-content">
					<span class="vamtam-offers-text">
					<span class="vamtam-offers-title"><?php esc_html_e( 'Exclusive Theme & Service Offers', 'tecnologia' ); ?></span>
					<span class="vamtam-offers-desc"><?php esc_html_e( 'Access exclusive offers on theme licenses, updates, support, and professional services.', 'tecnologia' ); ?></span>
				</span>
					<span class="vamtam-offers-actions">
					<span class="vamtam-offers-deal-wrap">
				<svg class="vamtam-offers-deal" viewBox="0 0 220 58" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="<?php esc_attr_e( 'Up to 70% off', 'tecnologia' ); ?>"><path d="M23.8493 30.4993C25.2765 30.5847 26.7036 30.6702 51.3447 29.6296C75.9858 28.589 123.798 26.4197 147.22 25.1821C170.643 23.9445 168.228 23.7042 164.397 23.9189C160.566 24.1336 155.393 24.8105 145.151 26.4956C134.908 28.1807 119.753 30.8533 111.009 32.2005C102.264 33.5477 100.39 33.4884 100.146 33.62C99.9021 33.7517 101.346 34.0761 117.251 33.9097C133.155 33.7433 163.477 33.0763 179.207 32.7218C194.936 32.3673 195.155 32.3457 195.448 32.2864" stroke="#C4FF44" stroke-width="47.6987" stroke-linecap="round"/><path d="M148.033 40.2402C146.087 40.2402 144.37 39.8014 142.882 38.9237C141.394 38.0461 140.22 36.8345 139.362 35.2891C138.522 33.7246 138.102 31.9215 138.102 29.88C138.102 27.8385 138.522 26.0451 139.362 24.4996C140.22 22.9351 141.394 21.714 142.882 20.8364C144.37 19.9587 146.087 19.5199 148.033 19.5199C149.998 19.5199 151.725 19.9587 153.213 20.8364C154.721 21.714 155.884 22.9351 156.705 24.4996C157.544 26.0451 157.964 27.8385 157.964 29.88C157.964 31.9215 157.544 33.7246 156.705 35.2891C155.884 36.8345 154.721 38.0461 153.213 38.9237C151.725 39.8014 149.998 40.2402 148.033 40.2402ZM148.033 36.6342C149.235 36.6342 150.275 36.3575 151.153 35.8042C152.03 35.2509 152.708 34.4782 153.185 33.4861C153.662 32.4748 153.9 31.2728 153.9 29.88C153.9 28.4872 153.662 27.2948 153.185 26.3026C152.708 25.2914 152.03 24.5092 151.153 23.9559C150.275 23.4025 149.235 23.1259 148.033 23.1259C146.85 23.1259 145.82 23.4025 144.942 23.9559C144.084 24.5092 143.406 25.2914 142.91 26.3026C142.433 27.2948 142.195 28.4872 142.195 29.88C142.195 31.2728 142.433 32.4748 142.91 33.4861C143.406 34.4782 144.084 35.2509 144.942 35.8042C145.82 36.3575 146.85 36.6342 148.033 36.6342ZM161.231 39.8968V19.8633H174.367V23.0973H165.238V28.2774H172.507V31.4255H165.238V39.8968H161.231ZM177.19 39.8968V19.8633H190.326V23.0973H181.196V28.2774H188.466V31.4255H181.196V39.8968H177.19Z" fill="black"/><circle cx="147.944" cy="30.9763" r="7.94979" fill="black"/><path d="M72.3558 39.1018L79.6251 22.5026H70.0663V19.0683H83.7463V21.873L76.477 39.1018H72.3558ZM94.5709 39.4452C92.7392 39.4452 91.1652 39.0064 89.8487 38.1287C88.5322 37.2511 87.521 36.0395 86.815 34.4941C86.1091 32.9296 85.7561 31.1266 85.7561 29.0851C85.7561 27.0435 86.1091 25.2501 86.815 23.7046C87.521 22.1401 88.5322 20.919 89.8487 20.0414C91.1652 19.1637 92.7392 18.7249 94.5709 18.7249C96.4025 18.7249 97.967 19.1637 99.2644 20.0414C100.581 20.919 101.592 22.1401 102.298 23.7046C103.004 25.2501 103.357 27.0435 103.357 29.0851C103.357 31.1266 103.004 32.9296 102.298 34.4941C101.592 36.0395 100.581 37.2511 99.2644 38.1287C97.967 39.0064 96.4025 39.4452 94.5709 39.4452ZM94.5709 35.8392C95.5057 35.8392 96.3262 35.5625 97.0321 35.0092C97.738 34.4559 98.2914 33.6832 98.692 32.6911C99.0927 31.6799 99.293 30.4779 99.293 29.0851C99.293 27.6922 99.0927 26.4998 98.692 25.5076C98.2914 24.4964 97.738 23.7142 97.0321 23.1609C96.3262 22.6076 95.5057 22.3309 94.5709 22.3309C93.636 22.3309 92.8155 22.6076 92.1096 23.1609C91.4037 23.7142 90.8503 24.4964 90.4497 25.5076C90.049 26.4998 89.8487 27.6922 89.8487 29.0851C89.8487 30.4779 90.049 31.6799 90.4497 32.6911C90.8503 33.6832 91.4037 34.4559 92.1096 35.0092C92.8155 35.5625 93.636 35.8392 94.5709 35.8392ZM110.141 39.1018L121.36 19.0683H125.252L114.034 39.1018H110.141ZM124.623 39.4452C123.688 39.4452 122.839 39.2353 122.076 38.8156C121.312 38.3958 120.702 37.8044 120.244 37.0412C119.805 36.2589 119.586 35.3431 119.586 34.2938C119.586 33.2062 119.805 32.2809 120.244 31.5177C120.702 30.7545 121.312 30.1726 122.076 29.7719C122.839 29.3522 123.688 29.1423 124.623 29.1423C125.558 29.1423 126.397 29.3522 127.141 29.7719C127.904 30.1726 128.505 30.7545 128.944 31.5177C129.383 32.2809 129.602 33.2062 129.602 34.2938C129.602 35.3431 129.383 36.2589 128.944 37.0412C128.505 37.8044 127.904 38.3958 127.141 38.8156C126.397 39.2353 125.558 39.4452 124.623 39.4452ZM124.594 36.7264C124.957 36.7264 125.281 36.631 125.567 36.4402C125.853 36.2494 126.073 35.9728 126.225 35.6102C126.397 35.2477 126.483 34.8089 126.483 34.2938C126.483 33.7405 126.397 33.2921 126.225 32.9487C126.073 32.5861 125.853 32.3095 125.567 32.1187C125.281 31.9279 124.957 31.8325 124.594 31.8325C124.232 31.8325 123.907 31.9279 123.621 32.1187C123.335 32.3095 123.106 32.5861 122.934 32.9487C122.781 33.2921 122.705 33.7405 122.705 34.2938C122.705 34.8089 122.781 35.2477 122.934 35.6102C123.106 35.9728 123.335 36.2494 123.621 36.4402C123.907 36.631 124.232 36.7264 124.594 36.7264ZM110.914 29.0564C109.979 29.0564 109.13 28.8466 108.367 28.4268C107.604 28.0071 106.993 27.4156 106.535 26.6524C106.096 25.8702 105.877 24.9543 105.877 23.905C105.877 22.8174 106.096 21.8921 106.535 21.1289C106.993 20.3657 107.604 19.7743 108.367 19.3545C109.13 18.9348 109.979 18.7249 110.914 18.7249C111.849 18.7249 112.688 18.9348 113.433 19.3545C114.196 19.7743 114.797 20.3657 115.236 21.1289C115.674 21.8921 115.894 22.8174 115.894 23.905C115.894 24.9543 115.674 25.8702 115.236 26.6524C114.797 27.4156 114.196 28.0071 113.433 28.4268C112.688 28.8466 111.849 29.0564 110.914 29.0564ZM110.885 26.3376C111.248 26.3376 111.572 26.2422 111.858 26.0514C112.145 25.8606 112.364 25.584 112.517 25.2215C112.688 24.8589 112.774 24.4201 112.774 23.905C112.774 23.3517 112.688 22.8938 112.517 22.5312C112.364 22.1687 112.145 21.8921 111.858 21.7013C111.572 21.5105 111.257 21.4151 110.914 21.4151C110.551 21.4151 110.218 21.5105 109.912 21.7013C109.626 21.8921 109.397 22.1687 109.225 22.5312C109.073 22.8938 108.997 23.3517 108.997 23.905C108.997 24.4201 109.073 24.8589 109.225 25.2215C109.397 25.584 109.626 25.8606 109.912 26.0514C110.199 26.2422 110.523 26.3376 110.885 26.3376Z" fill="black"/><path d="M26.6174 35.6045C25.8988 35.6045 25.2454 35.4598 24.6574 35.1705C24.0694 34.8812 23.6028 34.4472 23.2574 33.8685C22.9121 33.2898 22.7394 32.5525 22.7394 31.6565V25.6365H24.6294V31.6705C24.6294 32.1558 24.7088 32.5665 24.8674 32.9025C25.0261 33.2292 25.2548 33.4718 25.5534 33.6305C25.8614 33.7892 26.2254 33.8685 26.6454 33.8685C27.0748 33.8685 27.4388 33.7892 27.7374 33.6305C28.0454 33.4718 28.2788 33.2292 28.4374 32.9025C28.5961 32.5665 28.6754 32.1558 28.6754 31.6705V25.6365H30.5654V31.6565C30.5654 32.5525 30.3881 33.2898 30.0334 33.8685C29.6788 34.4472 29.1981 34.8812 28.5914 35.1705C27.9941 35.4598 27.3361 35.6045 26.6174 35.6045ZM32.4074 35.4365V25.6365H36.0754C36.8687 25.6365 37.5267 25.7718 38.0494 26.0425C38.5721 26.3132 38.9594 26.6772 39.2114 27.1345C39.4634 27.5918 39.5894 28.1145 39.5894 28.7025C39.5894 29.2438 39.4681 29.7432 39.2254 30.2005C38.9827 30.6578 38.6001 31.0312 38.0774 31.3205C37.5547 31.6005 36.8874 31.7405 36.0754 31.7405H34.2974V35.4365H32.4074ZM34.2974 30.2145H35.9634C36.5701 30.2145 37.0041 30.0792 37.2654 29.8085C37.5361 29.5285 37.6714 29.1598 37.6714 28.7025C37.6714 28.2172 37.5361 27.8438 37.2654 27.5825C37.0041 27.3118 36.5701 27.1765 35.9634 27.1765H34.2974V30.2145ZM46.5839 35.4365V27.1625H43.7139V25.6365H51.3159V27.1625H48.4599V35.4365H46.5839ZM56.8493 35.6045C55.8973 35.6045 55.0573 35.3898 54.3293 34.9605C53.6013 34.5312 53.032 33.9385 52.6213 33.1825C52.2107 32.4172 52.0053 31.5352 52.0053 30.5365C52.0053 29.5378 52.2107 28.6605 52.6213 27.9045C53.032 27.1392 53.6013 26.5418 54.3293 26.1125C55.0573 25.6832 55.8973 25.4685 56.8493 25.4685C57.8107 25.4685 58.6553 25.6832 59.3833 26.1125C60.1113 26.5418 60.676 27.1392 61.0773 27.9045C61.488 28.6605 61.6933 29.5378 61.6933 30.5365C61.6933 31.5352 61.488 32.4172 61.0773 33.1825C60.676 33.9385 60.1113 34.5312 59.3833 34.9605C58.6553 35.3898 57.8107 35.6045 56.8493 35.6045ZM56.8493 33.9105C57.4467 33.9105 57.96 33.7752 58.3893 33.5045C58.828 33.2245 59.1687 32.8325 59.4113 32.3285C59.654 31.8245 59.7753 31.2272 59.7753 30.5365C59.7753 29.8365 59.654 29.2392 59.4113 28.7445C59.1687 28.2405 58.828 27.8532 58.3893 27.5825C57.96 27.3118 57.4467 27.1765 56.8493 27.1765C56.2613 27.1765 55.748 27.3118 55.3093 27.5825C54.8707 27.8532 54.53 28.2405 54.2873 28.7445C54.0447 29.2392 53.9233 29.8365 53.9233 30.5365C53.9233 31.2272 54.0447 31.8245 54.2873 32.3285C54.53 32.8325 54.8707 33.2245 55.3093 33.5045C55.748 33.7752 56.2613 33.9105 56.8493 33.9105Z" fill="black"/></svg>
					</span>
					<span class="vamtam-offers-btn"><?php esc_html_e( 'Explore Offers', 'tecnologia' ); ?></span>
					<svg class="vamtam-offers-arrow" width="30" height="29" viewBox="0 0 30 29" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M24.4492 1.74238C29.4389 5.0618 30.192 14.6086 28.895 19.9056C28.1186 23.072 24.2914 24.8928 21.1199 23.8836C19.4581 23.355 18.376 22.096 17.7135 20.5312C14.2872 25.118 9.06088 30.8856 2.65006 28.3406C2.15692 28.1476 1.72534 27.8343 1.37616 27.421C-1.83001 23.5969 4.76177 16.2393 7.09387 13.3767C5.83269 13.2874 4.50943 13.1757 3.27332 12.9225C1.80522 12.6168 1.01192 11.669 0.589546 10.6609C0.0233958 9.30224 -0.298787 6.5601 0.390439 5.21992C2.31407 1.48537 21.1942 -2.30817 24.4516 1.73952L24.4492 1.74238ZM6.589 27.7867C11.1453 27.506 14.816 22.6222 17.2773 19.2528C17.1243 18.6891 17.0345 18.1587 16.9467 17.5868C14.3649 20.5492 9.77201 25.8704 6.589 27.7867ZM1.41319 9.72155C2.16752 12.4806 5.65356 12.1966 7.95791 12.3271L9.24156 10.8068C7.15985 10.8502 3.09546 10.7369 1.41263 9.71893L1.41319 9.72155ZM25.7364 22.622C30.1714 20.683 28.0542 8.23442 25.9957 4.95904C27.1278 8.6174 28.3375 19.6251 25.7364 22.622ZM2.51788 23.3147C2.19496 23.7965 1.8974 24.3201 1.70316 24.8655C1.62509 25.0858 1.91351 25.3257 2.07403 25.1069C2.41758 24.6426 2.69766 24.1067 2.91758 23.573C3.01362 23.3375 2.67981 23.0793 2.52063 23.3146L2.51788 23.3147ZM6.66499 18.2467C5.18334 20.1316 4.04438 22.2492 3.03569 24.4167C2.88406 24.7445 3.36896 24.9712 3.53827 24.6583C4.66673 22.58 5.95117 20.5986 7.15452 18.5635C7.32583 18.2752 6.86873 17.9859 6.66499 18.2467ZM11.2568 14.5966C9.44942 16.5245 8.07728 18.9794 6.79654 21.276C6.64593 21.5489 7.07025 21.7393 7.22765 21.4823C8.61372 19.232 10.2896 17.2051 11.7287 14.9999C11.9435 14.6724 11.5372 14.2994 11.2571 14.5993L11.2568 14.5966ZM14.0096 13.1596C12.9862 14.3377 12.0805 15.6132 11.1421 16.8585C10.9094 17.1683 11.3581 17.557 11.6019 17.249C12.5723 16.0259 13.5748 14.8275 14.4577 13.5401C14.6688 13.2348 14.2629 12.8673 14.0096 13.1596Z" fill="black"/></svg>
				</span>
				</span>
			</a>
		</div>
		<?php
	}

	public static function registration_warning() {
		?>
		<div class="vamtam-notice-wrap">
			<div class="vamtam-notice">
				<p>
					<?php echo esc_html__( 'Please activate your license to get theme updates, premium support, and access to demo content.', 'tecnologia' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=vamtam_theme_setup' ) ); ?>">
						<?php echo esc_html__( 'Register Now', 'tecnologia' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	public static function vamtam_theme_setup_import_content() {
		wp_enqueue_script( 'vamtam-check-license' );
		$valid_key = Version_Checker::is_valid_purchase_code();
		?>
		<div id="vamtam-ts-import-content" class="vamtam-ts">
			<div id="vamtam-ts-side">
				<?php self::dashboard_navigation(); ?>
			</div>
			<div id="vamtam-ts-main">
				<?php if ( $valid_key ) : ?>
					<?php self::import_buttons() ?>
				<?php else : ?>
					<?php self::registration_warning(); ?>
				<?php endif ?>
			</div>
		</div>
		<?php
	}

	public static function after_setup_theme() {
		if ( self::is_theme_setup_page() ) {
			add_filter( 'heartbeat_settings', [ __CLASS__, 'heartbeat_settings' ] );
		}

		add_filter( 'fs_redirect_on_activation_ajax-search-for-woocommerce', '__return_false', 100 );
	}

	public static function admin_early_init() {
		add_filter( 'woocommerce_enable_setup_wizard', '__return_false' );
		add_filter( 'woocommerce_prevent_automatic_wizard_redirect', '__return_true' );

		if ( class_exists( 'Elementor\Plugin' ) ) {
			remove_action( 'admin_init', [ Elementor\Plugin::instance()->admin, 'maybe_redirect_to_getting_started' ] );
		}

		if ( get_transient( '_fp_activation_redirect' ) ) {
			delete_transient( '_fp_activation_redirect' );
		}

		if ( get_transient( '_booked_welcome_screen_activation_redirect' ) ) {
			delete_transient( '_booked_welcome_screen_activation_redirect' );
		}

		if ( get_option( 'sbi_plugin_do_activation_redirect', false ) ) {
			remove_action( 'admin_init', 'sbi_activation_plugin_redirect' );
			delete_option( 'sbi_plugin_do_activation_redirect' );
		}

		add_filter( 'fs_redirect_on_activation_ajax-search-for-woocommerce', '__return_false', 100 );
	}

	public static function admin_init() {
		$purchase_code_option_id = VamtamFramework::get_purchase_code_option_key();
		$registration_label = esc_html__( 'Enter your purchase code from vamtam.com to receive theme updates and support.', 'tecnologia' );

		add_settings_section(
			'vamtam_purchase_settings_section',
			'',
			array( __CLASS__, 'settings_section' ),
			'vamtam_theme_setup'
		);
		add_settings_field(
			$purchase_code_option_id,
			$registration_label,
			array( __CLASS__, 'purchase_key' ),
			'vamtam_theme_setup',
			'vamtam_purchase_settings_section',
			array(
				$purchase_code_option_id,
			)
		);

		register_setting(
			'vamtam_theme_setup',
			$purchase_code_option_id,
			array( __CLASS__, 'sanitize_license_key' )
		);
	}

	public static function sanitize_license_key( $value ) {
		return preg_replace( '/[^-\w\d]/', '', $value );
	}

	public static function settings_section() {
	}

	public static function heartbeat_settings( $settings ) {
		$settings['interval'] = 15;
		return $settings;
	}

	public static function page() {
		wp_enqueue_script( 'vamtam-check-license' );

		$status        = self::server_tests();
		$theme_name    = ucfirst( wp_get_theme()->get_template() );
		$theme_version = VamtamFramework::get_version();
		$valid_key     = Version_Checker::is_valid_purchase_code();
		$is_token      = get_option( VamtamFramework::get_token_option_key() );

		// Licensing-terms link (URL + store label) per license type. JS keeps it in sync
		// with the selected tab (see vamtam-admin.js); this is the initial/registered value.
		$terms          = array(
			'vamtam'   => array( 'url' => 'https://vamtam.com/terms-conditions/Licensing', 'label' => 'vamtam.com' ),
			'market'   => array( 'url' => 'http://themeforest.net/licenses',               'label' => 'Envato Market' ),
			'elements' => array( 'url' => 'https://elements.envato.com/license-terms',      'label' => 'Envato Elements' ),
		);
		$license_source = get_option( '_vamtam_license_source' );
		if ( ! isset( $terms[ $license_source ] ) ) {
			$license_source = 'vamtam';
		}

		?>
		<h2></h2>

		<div id="vamtam-ts-homepage" class="vamtam-ts">
			<div id="vamtam-ts-side">
				<?php self::dashboard_navigation(); ?>
			</div>
			<div id="vamtam-ts-main">
				<?php do_action( 'vamtam_theme_setup_notices' ); ?>
				<div id="vamtam-ts-dash-register">
					<div id="vamtam-ts-register-product">
						<?php
							if ( defined( 'ENVATO_HOSTED_SITE' ) ) :
								esc_html_e( 'All done.', 'tecnologia' );
							else :
						?>
							<form id="vamtam-register-form" method="post" action="options.php" autocomplete="off">
								<?php if ( $valid_key ) : ?>
									<div id="vamtam-verified-code">
										<p>
											<?php
												$license_type = $is_token ? 'license code' : 'purchase code';
												esc_html_e( 'Thanks for verifying your ' . $license_type . '!', 'skole' )
											?>
											<br />
											<?php echo esc_html( sprintf( __( 'You can now enjoy %s and build great websites.', 'tecnologia' ) , $theme_name ) ); ?>
										</p>
									</div>
								<?php else : ?>
									<div id="vamtam-envato-market-radios">
										<div>
											<label>
												<input type="radio" id="vamtam-own-store-radio" name="vamtam_envato_elements" checked="">
												<span><?php echo esc_html__( 'vamtam.com', 'tecnologia' ); ?></span>
											</label>
											<label>
												<input type="radio" id="vamtam-envato-market-radio" name="vamtam_envato_elements">
												<span><?php echo esc_html__( 'Envato Market', 'tecnologia' ); ?></span>
											</label>
											<label>
												<input type="radio" id="vamtam-envato-elements-radio" name="vamtam_envato_elements">
												<span><?php echo esc_html__( 'Envato Elements', 'tecnologia' ); ?></span>
											</label>
										</div>
									</div>
									<div id="vamtam-envato-logo-wrap" >
										<svg id="vamtam-envato-market-logo" class="hidden" width="190" height="26" xmlns="http://www.w3.org/2000/svg"><g fill-rule="nonzero" fill="none"><path d="M29.477 5.025c3.849 0 7.613 2.269 7.613 7.325 0 .402-.022 1.024-.066 1.462a.197.197 0 0 1-.196.177H26.03c.316 1.81 1.58 2.987 3.562 2.987 1.315 0 2.153-.726 2.609-1.595a.273.273 0 0 1 .303-.14l4.037.88c.123.027.188.16.138.275-.944 2.138-3.092 4.256-7.117 4.256-5.285 0-8.1-3.447-8.1-7.813 0-4.367 2.93-7.814 8.014-7.814Zm3.101 6.204c-.2-1.724-1.35-2.643-3.016-2.643-2.184 0-3.103 1.12-3.447 2.643h6.463ZM38.468 19.996V5.683c0-.109.088-.198.198-.198h4.2c.109 0 .198.088.198.198v1.699c1.006-1.58 2.5-2.356 4.424-2.356 2.816 0 5.228 1.925 5.228 6.234v8.737a.198.198 0 0 1-.198.198h-4.2a.198.198 0 0 1-.198-.198V11.92c0-1.925-1.006-2.987-2.472-2.987-1.58 0-2.585 1.034-2.585 3.39v7.673a.198.198 0 0 1-.198.198h-4.2a.198.198 0 0 1-.199-.198l.002-.001ZM53 5.484h4.455c.088 0 .164.057.19.14l3.347 11.093 3.347-11.093a.197.197 0 0 1 .19-.14h4.455c.137 0 .233.135.185.264L64.044 20.01a.276.276 0 0 1-.26.183h-5.586a.279.279 0 0 1-.26-.183L52.811 5.75a.198.198 0 0 1 .186-.265h.001ZM78.917 19.996v-2.244c-.718 1.493-2.326 2.902-4.826 2.902-2.902 0-5.056-1.838-5.056-4.424 0-2.729 1.81-4.768 5.774-4.768h2.298c1.264 0 1.61-.919 1.494-1.523-.173-1.035-1.092-1.58-2.385-1.58-1.633 0-2.62.903-2.744 2.145a.198.198 0 0 1-.23.176l-3.897-.65a.198.198 0 0 1-.163-.229c.634-3.39 3.85-4.773 7.149-4.773 3.298 0 6.951.804 6.951 6.894v8.076a.198.198 0 0 1-.198.198h-3.971a.198.198 0 0 1-.198-.198l.002-.002Zm-3.476-2.677c1.838 0 3.103-1.379 3.246-3.103h-2.786c-1.694 0-2.298.69-2.269 1.638.03 1.005.833 1.465 1.81 1.465h-.001ZM83.974 8.963V5.682c0-.11.088-.198.198-.198h1.642a1.38 1.38 0 0 0 1.379-1.38v-2.56c0-.109.088-.198.198-.198h3.742c.108 0 .198.088.198.198v3.94h3.019c.109 0 .198.087.198.198v3.281a.198.198 0 0 1-.198.198h-3.02v5.315c0 1.732 1.473 2.437 3.009 1.89.101-.035.209.041.209.15v3.463c0 .127-.087.24-.21.269a7.09 7.09 0 0 1-1.6.176c-3.562 0-6.004-1.207-6.004-6.378V9.162h-2.56a.198.198 0 0 1-.198-.198l-.002-.001ZM111.323 12.839c0 4.309-3.044 7.813-8.044 7.813s-8.044-3.504-8.044-7.813c0-4.31 3.045-7.814 8.044-7.814 5 0 8.044 3.504 8.044 7.814Zm-4.596 0c0-2.126-1.179-3.908-3.448-3.908s-3.447 1.78-3.447 3.908c0 2.126 1.178 3.907 3.447 3.907 2.27 0 3.448-1.78 3.448-3.907Z" fill="#000"/><path d="M10.258 25.685a1.15 1.15 0 1 0 0-2.298 1.15 1.15 0 0 0 0 2.298ZM16.856 16.714l-6.472.693c-.119.013-.18-.138-.085-.212l6.334-4.931c.411-.336.673-.86.56-1.421-.111-.86-.822-1.421-1.719-1.308l-6.882 1.008c-.122.018-.187-.137-.09-.212l6.823-5.209c1.345-1.047 1.458-3.103.224-4.3-1.121-1.12-2.916-1.084-4.038.039L.518 12.039a1.948 1.948 0 0 0-.486 1.682c.187 1.01 1.196 1.682 2.206 1.495l5.926-1.209c.128-.026.198.145.087.216l-6.574 4.208c-.822.523-1.196 1.459-.934 2.393.262 1.234 1.495 1.944 2.692 1.645l9.827-2.42c.11-.027.193.101.12.19l-1.535 1.893c-.412.523.262 1.234.822.823l5.047-4.15c.897-.748.299-2.207-.86-2.094v.003Z" fill="#87E64B"/><path d="M115.921 19.996V6.145c0-.11.088-.198.198-.198h1.444c.108 0 .198.087.198.198v1.87c.89-1.551 2.068-2.299 3.676-2.299 2.068 0 3.303.949 4.107 2.873.919-1.924 2.182-2.873 4.165-2.873 2.442 0 4.595 1.321 4.595 5.717v8.563a.198.198 0 0 1-.198.198h-1.443a.198.198 0 0 1-.198-.198v-8.88c0-2.613-1.236-3.676-3.16-3.676-1.925 0-3.275 1.407-3.275 3.936v8.62a.198.198 0 0 1-.198.198h-1.443a.198.198 0 0 1-.198-.198v-8.88c0-2.613-1.236-3.676-3.16-3.676-1.925 0-3.275 1.407-3.275 3.705v8.85a.198.198 0 0 1-.198.199h-1.443a.198.198 0 0 1-.198-.198h.004ZM145.679 19.996v-2.79c-.92 1.982-2.73 3.218-5.055 3.218-2.643 0-4.596-1.667-4.596-4.194 0-3.016 2.212-4.48 5.113-4.48h3.074c1.092 0 1.407-.604 1.292-1.551-.143-1.236-1.092-2.873-3.59-2.873-2.497 0-3.768 1.49-3.97 2.898a.196.196 0 0 1-.231.164l-1.38-.266a.198.198 0 0 1-.157-.23c.562-2.853 3.012-4.173 5.71-4.173 2.697 0 5.63 1.15 5.63 6.348v7.931a.198.198 0 0 1-.199.198h-1.443a.198.198 0 0 1-.198-.198v-.002Zm-4.422-6.697c-2.413 0-3.39 1.263-3.39 2.786 0 1.407.92 2.73 2.787 2.73 2.844 0 4.912-2.098 5.026-5.515h-4.423V13.3ZM156.163 7.545a.197.197 0 0 1-.227.195c-2.604-.442-4.368 1.58-4.368 4.008v8.248a.198.198 0 0 1-.198.198h-1.443a.198.198 0 0 1-.198-.198V6.145c0-.11.088-.198.198-.198h1.443c.109 0 .198.087.198.198v2.501c.661-1.867 2.183-2.93 4.08-2.93.11 0 .234.013.345.028a.2.2 0 0 1 .172.197v1.605l-.002-.001ZM169.31 20.192h-1.905a.196.196 0 0 1-.156-.077l-5.743-7.363-1.895 1.694v5.548a.198.198 0 0 1-.198.198h-1.444a.198.198 0 0 1-.198-.198V2.008c0-.109.088-.198.198-.198h1.444c.109 0 .198.088.198.198v10.23l7.009-6.241a.197.197 0 0 1 .131-.05h1.987c.183 0 .268.225.131.345l-6.013 5.314 6.609 8.267c.103.13.011.32-.155.32Z" fill="#000"/><path d="M175.639 5.716c3.158 0 6.462 2.04 6.462 6.866 0 .291-.018.548-.039.77a.197.197 0 0 1-.197.178h-11.282c.258 3.131 2.27 5.17 5.142 5.17 2.328 0 3.72-1.39 4.305-2.83a.198.198 0 0 1 .226-.121l1.349.293a.197.197 0 0 1 .145.256c-.624 1.91-2.564 4.126-6.025 4.126-4.51 0-6.95-3.418-6.95-7.354 0-4.366 2.815-7.354 6.865-7.354h-.001Zm4.623 6.205c-.201-2.93-2.068-4.595-4.568-4.595-2.786 0-4.51 1.523-5.055 4.595h9.623ZM182.188 7.356V6.143c0-.109.087-.198.198-.198h1.295c.762 0 1.378-.618 1.378-1.378v-2.56c0-.109.088-.198.198-.198h1.328c.108 0 .198.088.198.198v3.938h3.019c.109 0 .198.088.198.198v1.213a.198.198 0 0 1-.198.199h-3.02v8.33c0 2.801 1.694 2.95 2.97 2.625a.197.197 0 0 1 .247.191v1.286a.2.2 0 0 1-.15.193 4.629 4.629 0 0 1-1.143.128c-2.73 0-3.763-1.494-3.763-4.625V7.555h-2.559a.198.198 0 0 1-.198-.199h.002Z" fill="#000"/></g></svg>
										<svg class="hidden" id="vamtam-envato-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280.28 64"><defs><style>.cls-1,.cls-2{fill:#191919;stroke-width:0}.cls-2{fill:#87e64b}</style></defs><path class="cls-1" d="M76.34 12.52c9.59 0 18.97 5.65 18.97 18.25 0 1-.05 2.55-.16 3.64-.03.25-.24.44-.49.44h-26.9c.79 4.51 3.94 7.44 8.88 7.44 3.28 0 5.37-1.81 6.5-3.97.14-.27.45-.41.75-.35l10.06 2.19c.31.07.47.4.34.69-2.35 5.33-7.7 10.61-17.73 10.61-13.17 0-20.19-8.59-20.19-19.47s7.3-19.47 19.97-19.47Zm7.73 15.46c-.5-4.29-3.36-6.59-7.52-6.59-5.44 0-7.73 2.79-8.59 6.59h16.11ZM98.75 49.82V14.16c0-.27.22-.49.49-.49h10.47c.27 0 .49.22.49.49v4.23c2.51-3.94 6.23-5.87 11.02-5.87 7.01 0 13.03 4.8 13.03 15.53v21.77c0 .27-.22.49-.49.49h-10.47a.49.49 0 0 1-.49-.49V29.7c0-4.8-2.51-7.44-6.16-7.44-3.94 0-6.44 2.58-6.44 8.45v19.12c0 .27-.22.49-.49.49H99.24a.49.49 0 0 1-.49-.49ZM134.95 13.66h11.1c.22 0 .41.14.47.35l8.34 27.64 8.34-27.64c.06-.21.25-.35.47-.35h11.1c.34 0 .58.34.46.66l-12.77 35.53c-.1.27-.36.46-.65.46h-13.92c-.29 0-.55-.18-.65-.46l-12.77-35.53a.49.49 0 0 1 .46-.66ZM199.53 49.82v-5.59c-1.79 3.72-5.8 7.23-12.03 7.23-7.23 0-12.6-4.58-12.6-11.02 0-6.8 4.51-11.88 14.39-11.88h5.73c3.15 0 4.01-2.29 3.72-3.79-.43-2.58-2.72-3.94-5.94-3.94-4.07 0-6.53 2.25-6.84 5.34-.03.28-.29.48-.57.44l-9.71-1.62a.49.49 0 0 1-.41-.57c1.58-8.45 9.59-11.89 17.81-11.89s17.32 2 17.32 17.18v20.12c0 .27-.22.49-.49.49h-9.9a.49.49 0 0 1-.49-.49Zm-8.66-6.66c4.58 0 7.73-3.44 8.09-7.73h-6.94c-4.22 0-5.73 1.72-5.65 4.08.07 2.51 2.08 3.65 4.51 3.65ZM212.13 22.33v-8.18c0-.27.22-.49.49-.49h4.09c1.9 0 3.44-1.54 3.44-3.44V3.85c0-.27.22-.49.49-.49h9.32c.27 0 .49.22.49.49v9.81h7.52c.27 0 .49.22.49.49v8.18c0 .27-.22.49-.49.49h-7.52v13.24c0 4.31 3.67 6.07 7.5 4.71.25-.09.52.1.52.37v8.63c0 .32-.21.6-.52.67-.99.23-2.36.44-3.99.44-8.88 0-14.96-3.01-14.96-15.89V22.82h-6.38a.49.49 0 0 1-.49-.49ZM280.28 31.99c0 10.74-7.59 19.47-20.04 19.47s-20.04-8.73-20.04-19.47 7.59-19.47 20.04-19.47 20.04 8.73 20.04 19.47Zm-11.46 0c0-5.3-2.93-9.73-8.59-9.73s-8.59 4.44-8.59 9.73 2.93 9.73 8.59 9.73 8.59-4.44 8.59-9.73Z"/><circle class="cls-2" cx="25.56" cy="61.14" r="2.86"/><path class="cls-2" d="m42 41.64-16.13 1.73c-.3.03-.45-.34-.21-.53l15.78-12.29c1.02-.84 1.68-2.14 1.4-3.54-.28-2.14-2.05-3.54-4.29-3.26L21.4 26.26c-.3.04-.46-.34-.22-.53l17-12.98c3.35-2.61 3.63-7.73.56-10.71-2.79-2.79-7.27-2.7-10.06.09L1.29 30a4.863 4.863 0 0 0-1.21 4.19c.47 2.52 2.98 4.19 5.5 3.73l14.77-3.01c.32-.07.49.36.22.54L4.19 45.94c-2.05 1.3-2.98 3.63-2.33 5.96.65 3.07 3.73 4.84 6.71 4.1l24.49-6.03c.28-.07.48.25.3.47l-3.82 4.72c-1.02 1.3.65 3.07 2.05 2.05l12.58-10.34c2.24-1.86.75-5.5-2.14-5.22Z"/></svg>
										<svg id="vamtam-own-store-logo" xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 113 24" width="113"><path d="m20.6022014 1.23182069c.2366536-.5166469.8971536-.5236253 1.1178916-.03807516.1921395.42275441 2.1699481 4.77396368 4.1604822 9.15314317l.3315696.7294534c1.986476 4.3702488 3.8871806 8.5517743 3.9291702 8.6440917.2255127.496079.8934808.6897604 1.2103249-.0072232.312804-.6881688 8.1932607-17.94527694 8.397226-18.4752685.1857236-.48261186.8699746-.5583949 1.1106684-.03721817.176041.38129949 2.1863264 4.7836849 4.3113246 9.44321277l.5182374 1.1364638c2.3051847 5.0556561 4.5814918 10.0551637 4.6562954 10.2427848.5160347 1.2944331-1.2930864 1.8868631-1.7721478.8254108-.3403504-.7539979-1.8244899-4.0201108-3.3988543-7.4830305l-.3157637-.694522-.3162967-.6956541c-1.8429766-4.05328051-3.6175566-7.95425139-3.6507187-8.02628884-.2558749-.55533419-.9256794-.51028063-1.1707806-.00440741-.2065269.42627607-1.7911401 3.90759436-3.4546655 7.57152045l-.5001258 1.1017836c-1.7774038 3.9165048-3.496775 7.7139576-3.5813974 7.9062866-.4551879 1.0347629-2.19881 1.5308419-2.9404799-.1008809-1.0618195-2.3359295-7.3793582-16.23458797-7.516845-16.53710799-.1971094-.43364059-.8831969-.50366951-1.1213196.02277164-.2699542.59659249-1.9783169 4.38243895-1.9783169 4.38243895-.6083456 1.1448258-2.3295633.2825642-1.7302774-.90351996 1.0268051-2.03243011 3.6166507-7.96358543 3.704799-8.15616495zm-20.06830388.15450435c-.52582901-1.19073642 1.23701427-1.9893354 1.76418998-.7987214.1234076.27852409 8.4908836 18.64911646 8.7375764 19.19184056.2509777.5520286.9037647.5492128 1.1449482.0312192.138956-.2983575 2.6264517-5.8241531 2.9352155-6.4700842.1088387-.2275939.2758307-.4347424.6301378-.4347424h6.4127878c1.2490122 0 1.4335115 1.9450164-.0206904 1.9451388h-4.5712231c-.9447783 0-1.3378951.6972285-1.5178645 1.0931612-.1799695.3959327-2.0474888 4.6722264-2.9047309 6.4729-.6275668 1.3180617-2.3502537 1.5078254-3.0611941-.0559497-.0985975-.2168907-2.21653227-4.8585283-4.43656683-9.7291661l-.34209073-.7505697c-2.22546429-4.88309075-4.45470848-9.78001511-4.77049512-10.49502626zm57.40610248 6.9132535h1.56l2.148 6.77999996h.024l2.196-6.77999996h1.524l-2.928 8.56799996h-1.668zm10.936 0h1.596l3.3 8.56799996h-1.608l-.804-2.268h-3.42l-.804 2.268h-1.548zm-.528 5.15999996h2.616l-1.284-3.68399996h-.036zm7.216-5.15999996h2.112l2.364 6.70799996h.024l2.304-6.70799996h2.088v8.56799996h-1.428v-6.612h-.024l-2.376 6.612h-1.236l-2.376-6.612h-.024v6.612h-1.428zm17.812 0v1.296h-2.724v7.27199996h-1.5v-7.27199996h-2.712v-1.296zm4.78 0 3.3 8.56799996h-1.608l-.804-2.268h-3.42l-.804 2.268h-1.548l3.288-8.56799996zm-.792 1.476h-.036l-1.296 3.68399996h2.616zm5.884-1.476h2.112l2.364 6.70799996h.024l2.304-6.70799996h2.088v8.56799996h-1.428v-6.612h-.024l-2.376 6.612h-1.236l-2.376-6.612h-.024v6.612h-1.428z"></path></svg>
									</div>
								<?php endif ?>
							<?php
								settings_fields( 'vamtam_theme_setup' );
								do_settings_sections( 'vamtam_theme_setup' );
							?>
							</form>
						<?php endif; ?>
					</div>
				</div>
				<div id="vamtam-check-license-disclaimer">
					<h5><?php esc_html_e( 'Licensing Terms', 'tecnologia' ); ?></h5>
					<p>
						<?php
						// Render all three type-specific links; JS shows the one matching the
						// selected tab (see vamtam-admin.js) so URLs/labels aren't duplicated there.
						$terms_links = '';
						foreach ( $terms as $type => $t ) {
							$terms_links .= '<a class="vamtam-licensing-terms-link" data-license-type="' . esc_attr( $type ) . '"'
								. ' href="' . esc_url( $t['url'] ) . '" target="_blank" rel="noopener noreferrer"'
								. ( $type === $license_source ? '' : ' style="display:none;"' ) . '>'
								/* translators: %s: store name, e.g. "vamtam.com", "Envato Market", "Envato Elements". */
								. sprintf( esc_html__( '%s terms here', 'tecnologia' ), esc_html( $t['label'] ) )
								. '</a>';
						}
						printf(
							/* translators: %s: link to the licensing terms for the selected license type, e.g. "Envato Market terms here". */
							esc_html__( 'You need to register a separate license for each domain on which you will use the theme. A single license is limited to a single domain/application. For more information, please refer to the %s.', 'tecnologia' ),
							$terms_links // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- links built from esc_url/esc_html above
						);
						?>
					</p>
				</div>
				<?php if ( current_user_can( 'switch_themes' ) ) : ?>
					<?php if ( ! defined( 'ENVATO_HOSTED_SITE' ) ) : ?>
						<div id="vamtam-server-tests">
							<h3>
								<?php if ( $status['fail'] > 0 ) : ?>
									<?php esc_html_e( 'System Status', 'tecnologia' ) ?>
									<?php $fail = $status['fail']; ?>
									<small><?php printf( esc_html( _n( '(%d potential issue)', '(%d potential issues)', $fail, 'tecnologia' ) ), $fail ) ?></small>
								<?php endif ?>
							</h3>
						</div>
					<?php endif ?>
				<?php endif ?>
			</div>
		</div>
		<?php
	}

	public static function dashboard_navigation() {
		$theme_name       = str_replace( 'VAMTAM-', '', strtoupper( wp_get_theme()->get_template() ) );
		$theme_version    = VamtamFramework::get_version();
		$valid_key        = Version_Checker::is_valid_purchase_code();
		$plugin_status    = VamtamPluginManager::get_required_plugins_status();
		$content_imported = ! ! get_option( 'vamtam_last_import_map', false );

		$routes = [
			'vamtam_theme_setup',
			'tgmpa-install-plugins',
			'vamtam_theme_setup_import_content',
			'vamtam_theme_help',
		];

		$cur_route = get_current_screen()->id;
		?>
		<nav id="vamtam-ts-nav-menu">
			<div id="vamtam-theme-title">
				<span id="vamtam-ts-greeter"><?php esc_html_e( 'WELCOME TO', 'tecnologia' ); ?></span>
				<span id="vamtam-ts-greeter-title"><?php echo esc_html( $theme_name ); ?></span>
				<span id="vamtam-ts-greeter-ver"><?php echo sprintf( esc_html__( 'VER. %s', 'tecnologia' ), $theme_version ); ?></span>
			</div>
			<ul>
				<li class="<?php echo esc_attr( $cur_route === 'toplevel_page_' . $routes[0] ? 'is-active' : '' ); ?>" >
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $routes[0] ) ); ?>">
						<svg class="ts-icon" xmlns="http://www.w3.org/2000/svg" width="21" height="30" viewBox="0 0 21 30"><path fill-rule="evenodd" d="M2.4 11.3l-.1.1V6.5C2.5 2.7 5.5 0 9.6 0h.2c2.3 0 5 0 7.1 2.1 2.3 2.2 2 5.8 1.9 8.8v.6c-.8-.8-1.6-1.4-2.5-2V6.8l-.1-.1a3.2 3.2 0 0 0 0-.3L16 6v-.2l-.1-.3v-.2h-.1V5a4.3 4.3 0 0 0-.3-.5 1.7 1.7 0 0 0-.2-.3.7.7 0 0 0-.1-.1l-.2-.2c-1.4-1.4-2.7-1.4-5.3-1.4h-.2C6.9 2.5 5 4 4.9 6.5v3.1l-.6.3-.1.1-1 .6-.1.2H3l-.6.5zM10.5 30A10.5 10.5 0 0 1 0 19.9a9 9 0 0 1 2.5-6.4 11.4 11.4 0 0 1 8.3-3.7c1.3 0 2.6.3 3.9.8A10.5 10.5 0 0 1 21 20c.1 5.3-4.7 10-10.5 10.1zm0-12.3c-.9 0-1.6.7-1.6 1.6 0 .5.3 1 .8 1.3v1.9h1.6v-1.9c.5-.2.9-.8.9-1.3 0-1-.8-1.6-1.7-1.6z"/></svg>
						<span><?php echo esc_html__( 'Register' , 'tecnologia' ); ?></span>
						<span class="vamtam-step-status <?php echo esc_attr( $valid_key ? 'success' : 'error' ); ?>"></span>
					</a>
				</li>
				<?php $tgmpa_instance 	= call_user_func( array( get_class( $GLOBALS['tgmpa'] ), 'get_instance' ) ); ?>
				<?php if ( isset( $tgmpa_instance ) && isset( $tgmpa_instance->page_hook ) ) : ?>
					<li class="<?php echo esc_attr( $cur_route === 'vamtam_page_' . $routes[1] ? 'is-active' : '' ); ?>" >
						<a <?php echo esc_attr( ! $valid_key ? 'class=disabled' : '' ); ?> href="<?php echo esc_url( admin_url( 'admin.php?page=' . $routes[1] ) ); ?>">
							<span class="ts-icon dashicons dashicons-admin-plugins"></span>
							<span><?php echo esc_html__( 'Install Plugins' , 'tecnologia' ); ?></span>
							<span class="vamtam-step-status <?php echo esc_attr( $valid_key ? $plugin_status : 'error' ); ?>"></span>
						</a>
					</li>
				<?php endif ?>
				<li class="<?php echo esc_attr( $cur_route === 'vamtam_page_' . $routes[2] ? 'is-active' : '' ); ?>" >
					<a <?php echo esc_attr( ! $valid_key || $plugin_status !== 'success' ? 'class=disabled' : '' ); ?> href="<?php echo esc_url( admin_url( 'admin.php?page=' . $routes[2] ) ); ?>">
						<svg class="ts-icon" xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><path fill-rule="evenodd" d="M25.6 25.6A15 15 0 0 0 4.4 4.4l2 2a12.2 12.2 0 1 1 0 17.2l-2 2a15 15 0 0 0 21.2 0zM0 13.7v2.8h16.7l-4.2 4.2 2 2 7.6-7.6-7.6-7.5-2 2 4.2 4.1H0z"/></svg>
						<span><?php echo esc_html__( 'Import Demo' , 'tecnologia' ); ?></span>
						<span class="vamtam-step-status <?php echo esc_attr( $valid_key && $content_imported ? 'success' : 'error' ); ?>"></span>
					</a>
				</li>
				<li>
					<a id="vamtam-hs-btn" class="<?php echo esc_attr( $cur_route === 'vamtam_page_' . $routes[3] ? 'is-active' : ''); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $routes[3] ) ); ?>">
						<svg class="ts-icon" width="30" height="30" viewBox="0 0 640 512" xmlns="http://www.w3.org/2000/svg"><path d="M208 352c114.9 0 208-78.8 208-176S322.9 0 208 0S0 78.8 0 176c0 38.6 14.7 74.3 39.6 103.4c-3.5 9.4-8.7 17.7-14.2 24.7c-4.8 6.2-9.7 11-13.3 14.3c-1.8 1.6-3.3 2.9-4.3 3.7c-.5 .4-.9 .7-1.1 .8l-.2 .2s0 0 0 0s0 0 0 0C1 327.2-1.4 334.4 .8 340.9S9.1 352 16 352c21.8 0 43.8-5.6 62.1-12.5c9.2-3.5 17.8-7.4 25.2-11.4C134.1 343.3 169.8 352 208 352zM448 176c0 112.3-99.1 196.9-216.5 207C255.8 457.4 336.4 512 432 512c38.2 0 73.9-8.7 104.7-23.9c7.5 4 16 7.9 25.2 11.4c18.3 6.9 40.3 12.5 62.1 12.5c6.9 0 13.1-4.5 15.2-11.1c2.1-6.6-.2-13.8-5.8-17.9c0 0 0 0 0 0s0 0 0 0l-.2-.2c-.2-.2-.6-.4-1.1-.8c-1-.8-2.5-2-4.3-3.7c-3.6-3.3-8.5-8.1-13.3-14.3c-5.5-7-10.7-15.4-14.2-24.7c24.9-29 39.6-64.7 39.6-103.4c0-92.8-84.9-168.9-192.6-175.5c.4 5.1 .6 10.3 .6 15.5z"/></svg>
						<span><?php echo esc_html__( 'Help & Support' , 'tecnologia' ); ?></span>
					</a>
				</li>
			</ul>
			<a id="vamtam-ts-offer-banner" href="<?php echo esc_url( self::promo_url( 'dashboard_banner' ) ); ?>" target="_blank" rel="noopener noreferrer">
				<h3 class="vamtam-offer-title"><?php esc_html_e( 'Exclusive Theme & Service Offers', 'tecnologia' ); ?></h3>
				<span class="vamtam-offer-uptolabel"><?php esc_html_e( 'Up to', 'tecnologia' ); ?></span>
				<span class="vamtam-offer-deal" role="img" aria-label="<?php esc_attr_e( '70% off', 'tecnologia' ); ?>"><svg viewBox="0 0 188 58" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M23.8371 31.3953C24.9977 31.4647 26.1582 31.5342 46.2179 30.3318C66.2776 29.1294 105.201 26.653 124.271 25.2728C143.341 23.8926 141.378 23.6836 138.259 23.9211C135.14 24.1585 130.925 24.8487 122.576 26.5444C114.227 28.2401 101.873 30.9204 94.7458 32.2808C87.6189 33.6412 86.0939 33.6005 85.8944 33.7282C85.6949 33.856 86.867 34.1535 99.8104 33.8598C112.754 33.5662 137.433 32.6722 150.235 32.2005C163.037 31.7287 163.215 31.7062 163.455 31.6471" stroke="#C4FF44" stroke-width="47.6746" stroke-linecap="round"/><path d="M116.166 39.9066C114.221 39.9066 112.505 39.468 111.017 38.5908C109.53 37.7136 108.357 36.5026 107.499 34.958C106.66 33.3943 106.24 31.5922 106.24 29.5517C106.24 27.5112 106.66 25.7186 107.499 24.174C108.357 22.6103 109.53 21.3898 111.017 20.5126C112.505 19.6354 114.221 19.1967 116.166 19.1967C118.13 19.1967 119.856 19.6354 121.344 20.5126C122.85 21.3898 124.013 22.6103 124.833 24.174C125.673 25.7186 126.092 27.5112 126.092 29.5517C126.092 31.5922 125.673 33.3943 124.833 34.958C124.013 36.5026 122.85 37.7136 121.344 38.5908C119.856 39.468 118.13 39.9066 116.166 39.9066ZM116.166 36.3024C117.368 36.3024 118.407 36.0259 119.284 35.4729C120.161 34.9198 120.838 34.1475 121.315 33.1559C121.792 32.1452 122.03 30.9438 122.03 29.5517C122.03 28.1596 121.792 26.9677 121.315 25.9761C120.838 24.9654 120.161 24.1835 119.284 23.6305C118.407 23.0775 117.368 22.8009 116.166 22.8009C114.984 22.8009 113.954 23.0775 113.077 23.6305C112.219 24.1835 111.542 24.9654 111.046 25.9761C110.569 26.9677 110.331 28.1596 110.331 29.5517C110.331 30.9438 110.569 32.1452 111.046 33.1559C111.542 34.1475 112.219 34.9198 113.077 35.4729C113.954 36.0259 114.984 36.3024 116.166 36.3024ZM129.357 39.5634V19.54H142.487V22.7723H133.362V27.9498H140.628V31.0963H133.362V39.5634H129.357ZM145.308 39.5634V19.54H158.438V22.7723H149.313V27.9498H156.578V31.0963H149.313V39.5634H145.308Z" fill="black"/><circle cx="116.077" cy="30.6332" r="7.94577" fill="black"/><path d="M40.53 38.7687L47.7956 22.178H38.2416V18.7454H51.9147V21.5487L44.6491 38.7687H40.53ZM62.7338 39.112C60.9031 39.112 59.3299 38.6734 58.014 37.7962C56.6982 36.919 55.6875 35.708 54.9819 34.1634C54.2763 32.5996 53.9236 30.7975 53.9236 28.7571C53.9236 26.7166 54.2763 24.924 54.9819 23.3794C55.6875 21.8156 56.6982 20.5952 58.014 19.718C59.3299 18.8407 60.9031 18.4021 62.7338 18.4021C64.5645 18.4021 66.1283 18.8407 67.425 19.718C68.7408 20.5952 69.7515 21.8156 70.4571 23.3794C71.1627 24.924 71.5155 26.7166 71.5155 28.7571C71.5155 30.7975 71.1627 32.5996 70.4571 34.1634C69.7515 35.708 68.7408 36.919 67.425 37.7962C66.1283 38.6734 64.5645 39.112 62.7338 39.112ZM62.7338 35.5078C63.6682 35.5078 64.4883 35.2313 65.1938 34.6783C65.8994 34.1252 66.4524 33.3529 66.8529 32.3613C67.2534 31.3506 67.4536 30.1492 67.4536 28.7571C67.4536 27.365 67.2534 26.1731 66.8529 25.1815C66.4524 24.1708 65.8994 23.3889 65.1938 22.8359C64.4883 22.2828 63.6682 22.0063 62.7338 22.0063C61.7994 22.0063 60.9794 22.2828 60.2738 22.8359C59.5682 23.3889 59.0152 24.1708 58.6147 25.1815C58.2143 26.1731 58.014 27.365 58.014 28.7571C58.014 30.1492 58.2143 31.3506 58.6147 32.3613C59.0152 33.3529 59.5682 34.1252 60.2738 34.6783C60.9794 35.2313 61.7994 35.5078 62.7338 35.5078ZM78.2964 38.7687L89.5095 18.7454H93.3997L82.1866 38.7687H78.2964ZM92.7704 39.112C91.836 39.112 90.9874 38.9022 90.2246 38.4827C89.4618 38.0632 88.8516 37.472 88.3939 36.7092C87.9553 35.9273 87.736 35.012 87.736 33.9631C87.736 32.8762 87.9553 31.9513 88.3939 31.1885C88.8516 30.4257 89.4618 29.844 90.2246 29.4436C90.9874 29.024 91.836 28.8143 92.7704 28.8143C93.7048 28.8143 94.5439 29.024 95.2876 29.4436C96.0504 29.844 96.6511 30.4257 97.0897 31.1885C97.5283 31.9513 97.7476 32.8762 97.7476 33.9631C97.7476 35.012 97.5283 35.9273 97.0897 36.7092C96.6511 37.472 96.0504 38.0632 95.2876 38.4827C94.5439 38.9022 93.7048 39.112 92.7704 39.112ZM92.7418 36.3945C93.1041 36.3945 93.4283 36.2992 93.7144 36.1085C94.0004 35.9178 94.2197 35.6413 94.3723 35.279C94.5439 34.9166 94.6297 34.478 94.6297 33.9631C94.6297 33.4101 94.5439 32.962 94.3723 32.6187C94.2197 32.2564 94.0004 31.9799 93.7144 31.7892C93.4283 31.5985 93.1041 31.5031 92.7418 31.5031C92.3795 31.5031 92.0553 31.5985 91.7692 31.7892C91.4832 31.9799 91.2544 32.2564 91.0827 32.6187C90.9302 32.962 90.8539 33.4101 90.8539 33.9631C90.8539 34.478 90.9302 34.9166 91.0827 35.279C91.2544 35.6413 91.4832 35.9178 91.7692 36.1085C92.0553 36.2992 92.3795 36.3945 92.7418 36.3945ZM79.0687 28.7285C78.1343 28.7285 77.2857 28.5187 76.5229 28.0992C75.7601 27.6796 75.1499 27.0884 74.6922 26.3257C74.2536 25.5438 74.0343 24.6284 74.0343 23.5796C74.0343 22.4926 74.2536 21.5677 74.6922 20.8049C75.1499 20.0421 75.7601 19.451 76.5229 19.0314C77.2857 18.6119 78.1343 18.4021 79.0687 18.4021C80.0031 18.4021 80.8422 18.6119 81.5859 19.0314C82.3487 19.451 82.9494 20.0421 83.388 20.8049C83.8267 21.5677 84.046 22.4926 84.046 23.5796C84.046 24.6284 83.8267 25.5438 83.388 26.3257C82.9494 27.0884 82.3487 27.6796 81.5859 28.0992C80.8422 28.5187 80.0031 28.7285 79.0687 28.7285ZM79.0401 26.011C79.4024 26.011 79.7266 25.9157 80.0127 25.725C80.2987 25.5343 80.518 25.2577 80.6706 24.8954C80.8422 24.5331 80.928 24.0945 80.928 23.5796C80.928 23.0266 80.8422 22.5689 80.6706 22.2066C80.518 21.8442 80.2987 21.5677 80.0127 21.377C79.7266 21.1863 79.412 21.091 79.0687 21.091C78.7064 21.091 78.3727 21.1863 78.0676 21.377C77.7815 21.5677 77.5527 21.8442 77.381 22.2066C77.2285 22.5689 77.1522 23.0266 77.1522 23.5796C77.1522 24.0945 77.2285 24.5331 77.381 24.8954C77.5527 25.2577 77.7815 25.5343 78.0676 25.725C78.3536 25.9157 78.6778 26.011 79.0401 26.011Z" fill="black"/></svg></span>
				<p class="vamtam-offer-desc"><?php esc_html_e( 'Access exclusive offers on theme licenses, updates, support, and professional services.', 'tecnologia' ); ?></p>
				<span class="vamtam-offer-btn"><?php esc_html_e( 'Explore Offers', 'tecnologia' ); ?></span>
				<span class="vamtam-offer-footer">
					<svg class="vamtam-offer-arrow" width="30" height="29" viewBox="0 0 30 29" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M24.4492 1.74238C29.4389 5.0618 30.192 14.6086 28.895 19.9056C28.1186 23.072 24.2914 24.8928 21.1199 23.8836C19.4581 23.355 18.376 22.096 17.7135 20.5312C14.2872 25.118 9.06088 30.8856 2.65006 28.3406C2.15692 28.1476 1.72534 27.8343 1.37616 27.421C-1.83001 23.5969 4.76177 16.2393 7.09387 13.3767C5.83269 13.2874 4.50943 13.1757 3.27332 12.9225C1.80522 12.6168 1.01192 11.669 0.589546 10.6609C0.0233958 9.30224 -0.298787 6.5601 0.390439 5.21992C2.31407 1.48537 21.1942 -2.30817 24.4516 1.73952L24.4492 1.74238ZM6.589 27.7867C11.1453 27.506 14.816 22.6222 17.2773 19.2528C17.1243 18.6891 17.0345 18.1587 16.9467 17.5868C14.3649 20.5492 9.77201 25.8704 6.589 27.7867ZM1.41319 9.72155C2.16752 12.4806 5.65356 12.1966 7.95791 12.3271L9.24156 10.8068C7.15985 10.8502 3.09546 10.7369 1.41263 9.71893L1.41319 9.72155ZM25.7364 22.622C30.1714 20.683 28.0542 8.23442 25.9957 4.95904C27.1278 8.6174 28.3375 19.6251 25.7364 22.622ZM2.51788 23.3147C2.19496 23.7965 1.8974 24.3201 1.70316 24.8655C1.62509 25.0858 1.91351 25.3257 2.07403 25.1069C2.41758 24.6426 2.69766 24.1067 2.91758 23.573C3.01362 23.3375 2.67981 23.0793 2.52063 23.3146L2.51788 23.3147ZM6.66499 18.2467C5.18334 20.1316 4.04438 22.2492 3.03569 24.4167C2.88406 24.7445 3.36896 24.9712 3.53827 24.6583C4.66673 22.58 5.95117 20.5986 7.15452 18.5635C7.32583 18.2752 6.86873 17.9859 6.66499 18.2467ZM11.2568 14.5966C9.44942 16.5245 8.07728 18.9794 6.79654 21.276C6.64593 21.5489 7.07025 21.7393 7.22765 21.4823C8.61372 19.232 10.2896 17.2051 11.7287 14.9999C11.9435 14.6724 11.5372 14.2994 11.2571 14.5993L11.2568 14.5966ZM14.0096 13.1596C12.9862 14.3377 12.0805 15.6132 11.1421 16.8585C10.9094 17.1683 11.3581 17.557 11.6019 17.249C12.5723 16.0259 13.5748 14.8275 14.4577 13.5401C14.6688 13.2348 14.2629 12.8673 14.0096 13.1596Z" fill="black"/></svg>
					vamtam.com
				</span>
			</a>
			<div id="vamtam-menu-logo">
			<a href="https://vamtam.com" target="_blank" rel="noopener noreferrer">
				<svg viewBox="0 0 113 24" xmlns="http://www.w3.org/2000/svg"><path d="m20.602 1.2318c0.23665-0.51665 0.89715-0.52363 1.1179-0.038075 0.19214 0.42275 2.1699 4.774 4.1605 9.1531l0.33157 0.72945c1.9865 4.3702 3.8872 8.5518 3.9292 8.6441 0.22551 0.49608 0.89348 0.68976 1.2103-0.0072232 0.3128-0.68817 8.1933-17.945 8.3972-18.475 0.18572-0.48261 0.86997-0.55839 1.1107-0.037218 0.17604 0.3813 2.1863 4.7837 4.3113 9.4432l0.51824 1.1365c2.3052 5.0557 4.5815 10.055 4.6563 10.243 0.51603 1.2944-1.2931 1.8869-1.7721 0.82541-0.34035-0.754-1.8245-4.0201-3.3989-7.483l-0.31576-0.69452-0.3163-0.69565c-1.843-4.0533-3.6176-7.9543-3.6507-8.0263-0.25587-0.55533-0.92568-0.51028-1.1708-0.0044074-0.20653 0.42628-1.7911 3.9076-3.4547 7.5715l-0.50013 1.1018c-1.7774 3.9165-3.4968 7.714-3.5814 7.9063-0.45519 1.0348-2.1988 1.5308-2.9405-0.10088-1.0618-2.3359-7.3794-16.235-7.5168-16.537-0.19711-0.43364-0.8832-0.50367-1.1213 0.022772-0.26995 0.59659-1.9783 4.3824-1.9783 4.3824-0.60835 1.1448-2.3296 0.28256-1.7303-0.90352 1.0268-2.0324 3.6167-7.9636 3.7048-8.1562zm-20.068 0.1545c-0.52583-1.1907 1.237-1.9893 1.7642-0.79872 0.12341 0.27852 8.4909 18.649 8.7376 19.192 0.25098 0.55203 0.90376 0.54921 1.1449 0.031219 0.13896-0.29836 2.6265-5.8242 2.9352-6.4701 0.10884-0.22759 0.27583-0.43474 0.63014-0.43474h6.4128c1.249 0 1.4335 1.945-0.02069 1.9451h-4.5712c-0.94478 0-1.3379 0.69723-1.5179 1.0932-0.17997 0.39593-2.0475 4.6722-2.9047 6.4729-0.62757 1.3181-2.3503 1.5078-3.0612-0.05595-0.098598-0.21689-2.2165-4.8585-4.4366-9.7292l-0.34209-0.75057c-2.2255-4.8831-4.4547-9.78-4.7705-10.495zm57.406 6.9133h1.56l2.148 6.78h0.024l2.196-6.78h1.524l-2.928 8.568h-1.668zm10.936 0h1.596l3.3 8.568h-1.608l-0.804-2.268h-3.42l-0.804 2.268h-1.548zm-0.528 5.16h2.616l-1.284-3.684h-0.036zm7.216-5.16h2.112l2.364 6.708h0.024l2.304-6.708h2.088v8.568h-1.428v-6.612h-0.024l-2.376 6.612h-1.236l-2.376-6.612h-0.024v6.612h-1.428zm17.812 0v1.296h-2.724v7.272h-1.5v-7.272h-2.712v-1.296zm4.78 0 3.3 8.568h-1.608l-0.804-2.268h-3.42l-0.804 2.268h-1.548l3.288-8.568zm-0.792 1.476h-0.036l-1.296 3.684h2.616zm5.884-1.476h2.112l2.364 6.708h0.024l2.304-6.708h2.088v8.568h-1.428v-6.612h-0.024l-2.376 6.612h-1.236l-2.376-6.612h-0.024v6.612h-1.428z"/></svg>
			</a>
			</div>
		</nav>
		<?php
	}

	public static function import_buttons() {
		wp_enqueue_script( 'vamtam-import-buttons' );

		wp_localize_script( 'vamtam-import-buttons', 'vamtamImportButtonsVars', array(
			'ajaxurl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'vamtam_attachment_progress' ),
			'resumeurl'    => esc_url_raw( add_query_arg( 'vamtam_continue_import', 1 ) ),
			'resumingmsg'  => esc_html__( 'The image import seems to have stalled. Trying to resume it...', 'tecnologia' ),
			'stalledmsg'   => esc_html__( 'The image import is not making progress. Use the button below to continue where it left off - it is safe to click more than once.', 'tecnologia' ),
			// Only shown once a manual resume has already been tried and the import stalled anyway.
			'stalledagain' => esc_html__( 'The image import stalled again after being resumed. This usually means WP-Cron is disabled or failing on this site, which prevents the importer from restarting itself. Keep using the button below to push the import along, and consider asking your host about WP-Cron.', 'tecnologia' ),
			'resumed'      => isset( $_GET['vamtam_continue_import'] ),
			'resumelabel'  => esc_html__( 'Resume import', 'tecnologia' ),
		));

		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		$content_allowed = defined( 'ELEMENTOR_PRO__FILE__' );

		$content_imported = ! ! get_option( 'vamtam_last_import_map', false );

		$is_token   = get_option( VamtamFramework::get_token_option_key() );
		$el_pro_nci = $is_token ? '5756' : '5757';

		$messages = array(
			'success-msg' => esc_html__( 'Imported.', 'tecnologia' ),
			'error-msg  ' => esc_html__( 'Failed to import. Please <a href="{fullimport}" target="_blank">click here</a> in order to see the full error message.', 'tecnologia' ),
		);

		$import_tests = array(
			array(
				'test'   => defined( 'ELEMENTOR_PRO__FILE__' ),
				'title'  => esc_html__( 'Posts, Pages and Site Layout', 'tecnologia' ),
				'failed' => wp_kses( __( "This theme requires Elementor Pro. If you don't have Elementor Pro, please <a href='https://be.elementor.com/visit/?bta=13981&nci={$el_pro_nci}' target='_blank'>download it here</a>. Install and activate it, and then proceed with importing the demo content.<br>If you have any issues with the importer please <a href='https://elementor.support.vamtam.com/support/solutions/articles/245218-vamtam-elementor-themes-how-to-install-the-theme-via-the-admin-panel-' target='_blank'>read this article</a>.<br>If you have any trouble importing the demo content or setting up the theme, we'd be happy to help. Please open a support ticket at <a href='https://support.vamtam.com/' target='_blank'>support.vamtam.com</a>.", 'tecnologia' ), 'vamtam-a-span' ),
			),
		);

		$will_import = array();

		foreach ( $import_tests as $test ) {
			if ( ! $test['test'] ) {
				$will_import[] = '<li><div class="vamtam-message">' . $test['failed'] . '</div></li>';
			}
		}

		$attachments_todo   = get_option( 'vamtam_import_attachments_todo', [ 'attachments' => '' ] )['attachments'];
		$total_attachements = is_countable( $attachments_todo ) ? count( $attachments_todo ) : 0;

		$img_progress = $total_attachements > 0 && class_exists( 'Vamtam_Importers_E' ) && is_callable( [ 'Vamtam_Importers_E', 'get_attachment_progress' ] ) ?
			Vamtam_Importers_E::get_attachment_progress( $total_attachements )['text'] :
			esc_html__( 'checking...', 'tecnologia' );

		$import_disabled_msg = empty( $will_import ) ? '' : '<div id="vamtam-recommended-plugins-notice" class="visible wide"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="50"><path fill-rule="evenodd" d="M7 33.3a5.4 5.4 0 01-5.4-5L0 5.8A5.1 5.1 0 011.1 2c.2-.2.5-.3.8-.1.2.2.3.5.1.8a4.1 4.1 0 00-.9 2.8l1.6 22.7a4.3 4.3 0 005 3.9c.3 0 .6.1.7.4 0 .3-.2.6-.5.7H7zm4.7-3.6h-.1a.6.6 0 01-.4-.7v-.7L13 5.6v-.3c0-2.3-2-4.2-4.3-4.2h-2A.6.6 0 016 .6c0-.4.3-.6.6-.6h2A5.4 5.4 0 0114 5.7l-1.6 22.7-.1.9c-.1.2-.3.4-.6.4zM7 50a6.2 6.2 0 01-6.2-6.1A6.2 6.2 0 1113 42.2c0 .3-.1.6-.4.7-.3 0-.6-.1-.7-.4a5.1 5.1 0 00-10 1.4 5 5 0 005.1 5 5 5 0 005-5c0-.3.3-.6.7-.6.3 0 .5.3.5.6 0 3.4-2.8 6.1-6.2 6.1z"/></svg><ul>' . implode( '<br>', $will_import ) . '</ul></div>';

		$buttons = array(
			array(
				'label'          => esc_html__( 'Dummy Content Import', 'tecnologia' ),
				'id'             => 'content-import-button',
				'description'    => esc_html__( 'You are advised to use this importer only on new WordPress sites.', 'tecnologia' ),
				'button_title'   => $content_imported ? esc_html__( 'Imported', 'tecnologia' ) : esc_html__( 'Import', 'tecnologia' ),
				'href'           => $content_allowed && !$content_imported ? wp_nonce_url( admin_url( 'admin.php?import=wpv&step=2' ), 'vamtam-import' ) : 'javascript:void( 0 )',
				'type'           => 'button',
				'class'          => $content_allowed && !$content_imported ? 'button-primary vamtam-import-button' : ($content_imported ? 'done disabled' : 'disabled'),
				'data'           => array_merge( $messages, [
					'content-imported' => $content_imported,
					'success-msg'      => sprintf( esc_html__( 'Main content imported. Image import progress: <span class="vamtam-image-import-progress">%s</span>.', 'tecnologia' ), $img_progress ),
					'fail-msg'         => esc_html__( 'Failed to import. We recommend that you contact your hosting provider for advice, as solving this issue is often specific to each server.', 'tecnologia' ),
					'timeout-msg'      => esc_html__( 'Failed to import. This is most likely caused by a timeout. Please contact your hosting provider for advice as to how you can increase the time limit on your server.', 'tecnologia' ),
				] ),
				'additional_msg' => $import_disabled_msg . wp_kses( sprintf( __( '<p class="vamtam-description">Please make sure to <a href="%s" target="_blank">backup</a> any existing content that you need as it will be removed by the import procedure (affects Posts, Pages and Menus).</p><p class="vamtam-description">We recommend that you use the <a href="%s" target="_blank">Post Name permalink structure</a></p><p class="vamtam-description">Images will be downloaded in the background after the main import is complete. Depending on your server, this may take several minutes.<br> In the meantime you may notice that some images are not visible.', 'tecnologia' ), esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=updraftplus&TB_iframe=true&width=772&height=921' ) ), esc_url( admin_url( 'options-permalink.php' ) ) ), 'vamtam-admin' ),
				'disabled_msg_plain' => '',
			),
		);

		echo '<div class="main-content">';

		VamtamDiagnostics::print();

		foreach ( $buttons as $button ) {
			self::render_button( $button );
		}

		echo '</div>';
	}

	public static function render_button( $button ) {
		echo '<div class="vamtam-box-wrap">';
		echo '<header><h3>' . esc_html( $button['label'] ) . '</h3></header>';

		$data = array();

		if ( isset( $button['data'] ) ) {
			foreach ( $button['data'] as $attr_name => $attr_value ) {
				$data[] = 'data-' . sanitize_title_with_dashes( $attr_name ) . '="' . esc_attr( $attr_value ) . '"';
			}
		}

		$data = implode( ' ', $data );

		echo '<div class="content">';

		if ( strpos( $button['class'], 'disabled' ) !== false ) {
			if ( isset( $button['disabled_msg'] ) ) {
				$href = isset( $button['disabled_msg_href'] ) ? $button['disabled_msg_href'] : admin_url( 'admin.php?page=tgmpa-install-plugins&plugin_status=required' );
				echo '<p class="vamtam-description">';
				if ( $href !== 'nolink' ) {
					echo '<a href="' . esc_html( $href ) . '">' . wp_kses_data( $button['disabled_msg'] ) . '</a>';
				} else {
					echo wp_kses_data( $button['disabled_msg'] );
				}
				echo '</p>';
			}

			if ( isset( $button['disabled_msg_plain'] ) ) {
				echo '<p class="vamtam-description">' . wp_kses_data( $button['disabled_msg_plain'] ) . '</p>';
			}
		} else {
			if ( isset( $button['description'] ) ) {
				echo '<p class="vamtam-description">' . wp_kses_data( $button['description'] ) . '</p>';
			}
			if ( isset( $button['warning'] ) ) {
				echo '<p class="vamtam-description warning">' . $button['warning'] . '</p>'; // xss ok
			}
		}

		if ( isset( $button['additional_msg'] ) ) {
			echo '<p class="vamtam-description">' . $button['additional_msg'] . '</p>'; // xss ok
		}

		echo '<div class="import-btn-wrap">';
		echo '<a href="' . ( isset( $button['href'] ) ? esc_attr( $button['href'] ) : '#' ) . '" id="' . esc_attr( $button['id'] ) . '" title="' . esc_attr( $button['button_title'] ) . '" class="button-primary vamtam-ts-button ' . esc_attr( $button['class'] ) . '" ' . $data . '>' . esc_html( $button['button_title'] ) . '</a>'; // xss ok - $data escaped above
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	public static function purchase_key( $args ) {
		$valid_key = Version_Checker::is_valid_purchase_code();
		$option_value = get_option( $args[0] );
		$placeholder = __( 'XXXXXX-XXX-XXXX-XXXX-XXXXXXXX', 'tecnologia' );
		$plugin_status = VamtamPluginManager::get_required_plugins_status();
		$content_imported = ! ! get_option( 'vamtam_last_import_map', false );


		$button_data = '';

		$data = array(
			'nonce'     => wp_create_nonce( 'vamtam-check-license' ),
		);

		if ( ! defined( 'ENVATO_HOSTED_SITE' ) ) {
			echo '<div id="vamtam-check-license-result"></div>';
		}
		echo '<div class="vamtam-licence-wrap">';
		// In the register state the control renders as a single input-like field with the
		// badge as a left cap and the reservers as blank field on the right (see CSS).
		echo '<span class="vamtam-licence-control' . ( $valid_key ? '' : ' vamtam-licence-control--boxed' ) . '">';
		if ( $valid_key ) {
			echo '<span id="vamtam-license-result"';
			echo 'class="valid">';
			echo '<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 30 30"><path fill-rule="evenodd" d="M30 15a15 15 0 1 1-30 0 15 15 0 0 1 30 0zm-2.7-4.4L15.7 22.3a1 1 0 0 1-1.4 0L7 13.7a1 1 0 0 1 1.4-1.3l6.6 7.7L26.5 8.7a13 13 0 1 0 .8 1.9z"/></svg>';
			esc_html_e( 'Valid', 'tecnologia' );
			echo '</span>';
		} else {
			$invalid_badge = '<span class="invalid"><span class="dashicons dashicons-no-alt"></span>' . esc_html__( 'Invalid', 'tecnologia' ) . '</span>';
			// Invisible width-reserver on the input's left; the real badge is overlaid on
			// top of it (absolutely, see CSS) so showing/hiding it never shifts the input.
			echo '<span class="vamtam-badge-sizer" aria-hidden="true">' . $invalid_badge . '</span>'; // xss ok
			echo '<span id="vamtam-license-result-wrap">';
			echo '<span class="valid">';
			echo '<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 30 30"><path fill-rule="evenodd" d="M30 15a15 15 0 1 1-30 0 15 15 0 0 1 30 0zm-2.7-4.4L15.7 22.3a1 1 0 0 1-1.4 0L7 13.7a1 1 0 0 1 1.4-1.3l6.6 7.7L26.5 8.7a13 13 0 1 0 .8 1.9z"/></svg>';
			esc_html_e( 'Valid', 'tecnologia' );
			echo '</span>';
			echo $invalid_badge; // xss ok - built from esc_html__ above
			echo '</span>';
		}
		echo '<input type="text" id="vamtam-envato-license-key" name="' . esc_attr( $args[0] ) . '" value="' . ( $valid_key && vamtam_sanitize_bool( $option_value ) ? esc_attr( $option_value ) : '' ) . '" size="64" ' . ( defined( 'SUBSCRIPTION_CODE' ) ? 'disabled' : '' ) . 'placeholder="' . $placeholder . '"' . '/>';
		if ( ! $valid_key ) {
			// Matching invisible reserver on the right, so the input stays centred and the
			// badge + input group is centred too — no dependency on the JS running.
			echo '<span class="vamtam-badge-sizer" aria-hidden="true">' . $invalid_badge . '</span>'; // xss ok
		}
		if ( $valid_key ) {
			echo '<button id="vamtam-check-license" class="button button-primary unregister" data-nonce="'. esc_attr( $data['nonce'] ) .'">';
			echo '<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 20 20"><path fill="white" d="M15.6 3.1h-4.1V1.5c0-.4-.2-.7-.4-1-.3-.2-.6-.3-1-.3H6.9c-.4 0-.7.1-1 .4-.2.2-.4.5-.4 1V3H1.4l-.5.2-.1.4.1.4c.2.2.3.2.5.2h.8L3.5 18c0 .3.2.5.5.8.2.2.5.3.8.3h7.4a1.2 1.2 0 0 0 1.2-1.2l1.4-13.7h.8c.2 0 .3 0 .5-.2l.1-.4-.1-.4a.6.6 0 0 0-.5-.2zM6.7 1.5v-.1h3.6V3H6.8V1.5zm7 2.8L12.2 18v.1H4.7L3.3 4.2h10.2z"/></svg>';
			echo '</button>';
		}
		echo '</span>';
		echo '</div>';

		if ( ! defined( 'ENVATO_HOSTED_SITE' ) ) {
			echo '<span style="display: block">';

			if ( ! $valid_key ) {
				echo '<p id="vamtam-code-help">';
				echo '<span id="vamtam-help-envato" class="hidden">';
				echo wp_kses( sprintf( __( ' <a href="%s" target="_blank">Where to Find Your Purchase Code</a>', 'tecnologia' ), 'https://elementor.support.vamtam.com/support/solutions/articles/252289-cannot-validate-purchase-key-or-token' ), 'vamtam-a-span' );
				echo '</span>';
				echo '<span id="vamtam-help-own-store">';
				echo wp_kses( sprintf( __( 'Your vamtam.com purchase code is available in your <a href="%s" target="_blank">Order Details</a>', 'tecnologia' ), 'https://vamtam.com/my-account/orders/' ), 'vamtam-a-span' );
				echo wp_kses( sprintf( __( ' <a href="%s" target="_blank">Where to Find Your Purchase Code</a>', 'tecnologia' ), 'https://elementor.support.vamtam.com/support/solutions/articles/252289-cannot-validate-purchase-key-or-token' ), 'vamtam-a-span' );
				echo '</span>';
				echo '</p>';

				echo '<p id="vamtam-code-help-elements" class="hidden">';
				echo wp_kses( sprintf( __( ' <a href="%s" target="_blank">Where to get the License Code from</a>', 'tecnologia' ), 'https://elementor.support.vamtam.com/support/solutions/articles/252289-cannot-validate-purchase-key-or-token' ), 'vamtam-a-span' );
				echo '</p>';

				echo '<button id="vamtam-check-license" class="button button-primary" ';

				foreach ( $data as $key => $value ) {
					echo ' data-' . $key . '="' . esc_attr( $value ) . '"';
				}

				echo '>' . esc_html__( 'Register', 'tecnologia' );
				echo '</button>';
			} else if ( $plugin_status !== 'success' ) {
				echo '<a id="vamtam-plugin-step" class="button-primary vamtam-ts-button" href="' . esc_url( admin_url( 'admin.php?page=tgmpa-install-plugins' ) ) . '">';
				echo esc_html__( 'Continue to required plguins', 'tecnologia' );
				echo '</a>';
			} elseif ( ! $content_imported ) {
				echo '<a id="vamtam-import-step" class="button-primary vamtam-ts-button" href="' . esc_url( admin_url( 'admin.php?page=vamtam_theme_setup_import_content' ) ) . '">';
				echo esc_html__( 'Continue to demo import', 'tecnologia' );
				echo '</a>';
			}

			echo '</span>';
		}
	}
}
