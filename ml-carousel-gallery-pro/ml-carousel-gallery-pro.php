<?php
/**
 * Plugin Name: ML Carousel Gallery Pro
 * Plugin URI: https://mlopesdesign.com/
 * Description: Carousel horizontal de galerias do ML Gallery Pro com autoplay, swipe mobile e links nativos de album/galeria.
 * Version: 1.10.12
 * Author: Mlopesdesign
 * Author URI: https://mlopesdesign.com/
 * Text Domain: ml-carousel-gallery-pro
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/mlopesdesign/ml-carroucel-gallery-pro
 *
 * @package MLCarouselGalleryPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MLCGP_VERSION', '1.10.12' );
define( 'MLCGP_FILE', __FILE__ );
define( 'MLCGP_DIR', plugin_dir_path( __FILE__ ) );
define( 'MLCGP_URL', plugin_dir_url( __FILE__ ) );
define( 'MLCGP_OPTION_KEY', 'mlcgp_settings' );

require_once MLCGP_DIR . 'includes/Admin/Helpers.php';
require_once MLCGP_DIR . 'includes/Admin/License.php';
require_once MLCGP_DIR . 'includes/Admin/Settings.php';
require_once MLCGP_DIR . 'includes/Admin/AdminUI.php';
require_once MLCGP_DIR . 'includes/Core/Plugin.php';
require_once MLCGP_DIR . 'includes/Core/GitHubUpdater.php';
require_once MLCGP_DIR . 'includes/Frontend/Carousel.php';



register_activation_hook(
	__FILE__,
	static function () {
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'mlcgp_github_latest_release_' . md5( 'mlopesdesign|ml-carroucel-gallery-pro,ml-carousel-gallery-pro,ml-carrousel-gallery-pro' ) );
	}
);

add_action(
	'init',
	static function () {
		$mlcgp_github_updater = new \MLCarouselGalleryPro\Core\GitHubUpdater(
			MLCGP_FILE,
			MLCGP_VERSION,
			'mlopesdesign',
			[
				'ml-carroucel-gallery-pro',
				'ml-carousel-gallery-pro',
				'ml-carrousel-gallery-pro',
			]
		);
		$mlcgp_github_updater->boot();
	},
	1
);

add_action(
	'plugins_loaded',
	static function () {
		\MLCarouselGalleryPro\Core\Plugin::instance()->boot();
	}
);
