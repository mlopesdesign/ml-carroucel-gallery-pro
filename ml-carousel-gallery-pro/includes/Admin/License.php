<?php
namespace MLCarouselGalleryPro\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class License {
	private const OPTION_KEY = 'mlcgp_license_state';

	/** @var Settings */
	private $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function get_state(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		return wp_parse_args(
			$saved,
			[
				'status'       => 'inactive',
				'plan'         => 'free',
				'serial'       => '',
				'email'        => '',
				'expires_at'   => '',
				'last_check'   => '',
				'activated_at' => '',
				'instance_id'  => $this->get_instance_id(),
				'message'      => '',
			]
		);
	}

	public function get_runtime_config(): array {
		$settings = $this->settings->get();
		return [
			'product_id'  => (string) ( $settings['license_product_id'] ?? 'ml-carousel-gallery-pro' ),
			'endpoint'    => (string) ( $settings['license_endpoint'] ?? '' ),
			'item_name'   => (string) ( $settings['license_item_name'] ?? 'ML Carousel Gallery Pro' ),
			'seller_name' => (string) ( $settings['license_seller_name'] ?? 'ML Lopes Design' ),
			'cta_title'   => (string) ( $settings['license_cta_title'] ?? 'Licenciamento padrão ML ativo' ),
			'cta_text'    => (string) ( $settings['license_cta_text'] ?? '' ),
		];
	}

	public function save( array $input ): array {
		$state               = $this->get_state();
		$state['serial']     = sanitize_text_field( (string) ( $input['serial'] ?? '' ) );
		$state['email']      = sanitize_email( (string) ( $input['email'] ?? '' ) );
		$requested_status    = $this->sanitize_status( (string) ( $input['status'] ?? 'inactive' ) );
		$requested_plan      = $this->sanitize_plan( (string) ( $input['plan'] ?? 'free' ) );
		$state['expires_at'] = sanitize_text_field( (string) ( $input['expires_at'] ?? '' ) );
		$state['last_check'] = current_time( 'mysql' );
		$state['instance_id'] = $this->get_instance_id();

		$validation = $this->validate_remote( $state['serial'], $state['email'], $requested_plan, $requested_status, $state['instance_id'] );
		if ( 'remote' === $validation['mode'] ) {
			$state['status']     = $this->sanitize_status( (string) ( $validation['status'] ?? 'inactive' ) );
			$state['plan']       = $this->sanitize_plan( (string) ( $validation['plan'] ?? 'free' ) );
			$state['expires_at'] = sanitize_text_field( (string) ( $validation['expires_at'] ?? $state['expires_at'] ) );
			$state['message']    = sanitize_text_field( (string) ( $validation['message'] ?? 'Licença validada com sucesso.' ) );
		} else {
			$state['status']  = $requested_status;
			$state['plan']    = $requested_plan;
			$state['message'] = ! empty( $validation['message'] )
				? sanitize_text_field( (string) $validation['message'] )
				: ( 'active' === $state['status'] ? 'Licença ativa localmente. Configure o endpoint real para validação remota.' : 'Licença salva localmente.' );
		}

		if ( 'active' === $state['status'] && '' === $state['activated_at'] ) {
			$state['activated_at'] = current_time( 'mysql' );
		}

		update_option( self::OPTION_KEY, $state, false );
		return $state;
	}

	private function validate_remote( string $serial, string $email, string $requested_plan, string $requested_status, string $instance_id ): array {
		$config   = $this->get_runtime_config();
		$endpoint = trim( (string) ( $config['endpoint'] ?? '' ) );
		if ( '' === $endpoint ) {
			return [ 'mode' => 'local', 'plan' => $requested_plan, 'status' => $requested_status ];
		}

		$response = wp_remote_post(
			$endpoint,
			[
				'timeout' => 20,
				'headers' => [ 'Accept' => 'application/json' ],
				'body'    => [
					'product_id'       => (string) ( $config['product_id'] ?? '' ),
					'item_name'        => (string) ( $config['item_name'] ?? '' ),
					'seller_name'      => (string) ( $config['seller_name'] ?? '' ),
					'serial'           => $serial,
					'email'            => $email,
					'instance_id'      => $instance_id,
					'site_url'         => home_url( '/' ),
					'requested_plan'   => $requested_plan,
					'requested_status' => $requested_status,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return [ 'mode' => 'local', 'plan' => $requested_plan, 'status' => $requested_status, 'message' => 'Falha no endpoint: ' . $response->get_error_message() ];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return [ 'mode' => 'local', 'plan' => $requested_plan, 'status' => $requested_status, 'message' => 'Resposta inválida do endpoint de licença.' ];
		}

		return [
			'mode'       => 'remote',
			'plan'       => (string) ( $data['plan'] ?? $requested_plan ),
			'status'     => (string) ( $data['status'] ?? $requested_status ),
			'expires_at' => (string) ( $data['expires_at'] ?? '' ),
			'message'    => (string) ( $data['message'] ?? 'Licença validada remotamente.' ),
		];
	}

	public function is_feature_allowed( string $required_plan ): bool {
		$state    = $this->get_state();
		$levels   = [ 'free' => 1, 'trial' => 2, 'full' => 3, 'lifetime' => 4 ];
		$current  = $levels[ $state['plan'] ] ?? 1;
		$required = $levels[ $required_plan ] ?? 1;
		if ( 'active' !== $state['status'] && ! ( 'trial' === $state['plan'] && 'blocked' !== $state['status'] ) ) {
			return $required <= 1;
		}
		return $current >= $required;
	}

	public function get_plan_label( string $plan ): string {
		$labels = [ 'free' => 'Free', 'trial' => 'Trial', 'full' => 'Full', 'lifetime' => 'Lifetime' ];
		return $labels[ $plan ] ?? ucfirst( $plan );
	}

	public function get_status_label( string $status ): string {
		$labels = [ 'inactive' => 'Inativo', 'active' => 'Ativo', 'expired' => 'Expirado', 'blocked' => 'Bloqueado' ];
		return $labels[ $status ] ?? ucfirst( $status );
	}

	private function sanitize_plan( string $plan ): string {
		$allowed = [ 'free', 'trial', 'full', 'lifetime' ];
		return in_array( $plan, $allowed, true ) ? $plan : 'free';
	}

	private function sanitize_status( string $status ): string {
		$allowed = [ 'inactive', 'active', 'expired', 'blocked' ];
		return in_array( $status, $allowed, true ) ? $status : 'inactive';
	}

	private function get_instance_id(): string {
		return substr( hash( 'sha256', home_url( '/' ) . '|' . wp_salt( 'auth' ) ), 0, 20 );
	}
}
