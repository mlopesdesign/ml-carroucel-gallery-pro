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
		// Instancia o cliente de licença cedo para agendar a renovação diária.
		$this->license_manager->client();
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
		// 1.11.0: sem teste/licença válidos, a edição fica travada (o carrossel publicado segue no ar).
		if ( $this->license_manager->is_locked() ) {
			$this->redirect_with_toast( 'license', 'Ative a licença (ou comece o teste grátis) para salvar as configurações.', 'error' );
		}
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
		$op = isset( $_POST['mlcgp_license_op'] ) ? sanitize_key( wp_unslash( (string) $_POST['mlcgp_license_op'] ) ) : '';

		switch ( $op ) {
			case 'activate':
				$key    = isset( $_POST['mlcgp_license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['mlcgp_license_key'] ) ) : '';
				$result = $this->license_manager->activate( $key );
				$ok_msg = 'Licença ativada neste site.';
				break;
			case 'trial':
				$result = $this->license_manager->start_trial();
				$ok_msg = 'Teste grátis de 30 dias iniciado.';
				break;
			case 'check':
				$result = $this->license_manager->check();
				$ok_msg = 'Licença conferida.';
				break;
			case 'deactivate':
				$this->license_manager->deactivate();
				$result = true;
				$ok_msg = 'Este site foi liberado. A chave já pode ser ativada em outro site.';
				break;
			default:
				$result = new \WP_Error( 'bad_request', 'Ação de licença inválida.' );
				$ok_msg = '';
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_toast( 'license', $result->get_error_message(), 'error' );
		}

		$this->redirect_with_toast( 'license', $ok_msg, 'success' );
	}

	public function handle_refresh_covers(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
		}

		check_ajax_referer( 'mlcgp_refresh_covers' );
		if ( $this->license_manager->is_locked() ) {
			wp_send_json_error( [ 'message' => 'Ative a licença (ou comece o teste grátis) para usar esta ação.' ], 403 );
		}
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
