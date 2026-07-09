<?php
/**
 * Plugin core bootstrap.
 *
 * @package MLCarouselGalleryPro
 */

namespace MLCarouselGalleryPro\Core;

use MLCarouselGalleryPro\Admin\AdminUI;
use MLCarouselGalleryPro\Admin\License;
use MLCarouselGalleryPro\Admin\Settings;
use MLCarouselGalleryPro\Frontend\Carousel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	/** @var self|null */
	private static $instance = null;

	private Settings $settings_manager;
	private License $license_manager;
	private AdminUI $admin_ui;
	private Carousel $carousel;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings_manager = new Settings();
		$this->license_manager  = new License( $this->settings_manager );
		$this->admin_ui         = new AdminUI();
		$this->carousel         = new Carousel( $this->settings_manager );
	}

	public function boot(): void {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_init', [ $this->settings_manager, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_mlcgp_save_settings', [ $this, 'handle_save_settings' ] );
		add_action( 'admin_post_mlcgp_save_license', [ $this, 'handle_save_license' ] );
		add_action( 'wp_ajax_mlcgp_refresh_covers', [ $this, 'handle_refresh_covers' ] );

		$this->carousel->hooks();
	}

	public function register_admin_menu(): void {
		add_menu_page(
			__( 'ML Carousel Gallery Pro', 'ml-carousel-gallery-pro' ),
			__( 'ML Carousel', 'ml-carousel-gallery-pro' ),
			'manage_options',
			'ml-carousel-gallery-pro',
			[ $this, 'render_admin_page' ],
			'dashicons-images-alt2',
			58
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_ml-carousel-gallery-pro' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'mlcgp-admin', MLCGP_URL . 'assets/css/admin.css', [], MLCGP_VERSION );
		wp_enqueue_script( 'mlcgp-admin', MLCGP_URL . 'assets/js/admin.js', [], MLCGP_VERSION, true );
		wp_localize_script(
			'mlcgp-admin',
			'mlcgpAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mlcgp_refresh_covers' ),
			]
		);
	}

	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab    = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		$toast_message = isset( $_GET['mlcgp_toast'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['mlcgp_toast'] ) ) : '';
		$toast_type    = isset( $_GET['mlcgp_toast_type'] ) ? sanitize_key( (string) wp_unslash( $_GET['mlcgp_toast_type'] ) ) : 'success';

		$this->admin_ui->render( $this->settings_manager, $this->license_manager, $this->carousel, $active_tab, $toast_message, $toast_type );
	}

	public function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'ml-carousel-gallery-pro' ) );
		}

		check_admin_referer( 'mlcgp_save_settings' );
		$raw       = isset( $_POST['mlcgp_settings'] ) ? wp_unslash( $_POST['mlcgp_settings'] ) : [];
		$sanitized = $this->settings_manager->sanitize( is_array( $raw ) ? $raw : [] );
		update_option( MLCGP_OPTION_KEY, $sanitized, false );
		$this->redirect_with_toast( 'settings', 'Configurações salvas com sucesso.', 'success' );
	}

	public function handle_save_license(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'ml-carousel-gallery-pro' ) );
		}

		check_admin_referer( 'mlcgp_save_license' );
		$raw   = isset( $_POST['mlcgp_license'] ) ? wp_unslash( $_POST['mlcgp_license'] ) : [];
		$state = $this->license_manager->save( is_array( $raw ) ? $raw : [] );
		$message = ! empty( $state['message'] ) ? (string) $state['message'] : 'Licença salva com sucesso.';
		$this->redirect_with_toast( 'license', $message, 'success' );
	}

	public function handle_refresh_covers(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
		}

		check_ajax_referer( 'mlcgp_refresh_covers' );
		$this->carousel->invalidate_cache();
		wp_send_json_success( [ 'message' => 'Capas atualizadas.' ] );
	}

	private function redirect_with_toast( string $tab, string $message, string $type ): void {
		$url = add_query_arg(
			[
				'page'             => 'ml-carousel-gallery-pro',
				'tab'              => $tab,
				'mlcgp_toast'       => rawurlencode( $message ),
				'mlcgp_toast_type'  => $type,
			],
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
