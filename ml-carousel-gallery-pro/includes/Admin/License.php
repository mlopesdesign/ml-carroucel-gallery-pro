<?php
/**
 * Licença do ML Carousel Gallery Pro.
 *
 * 1.11.0: a licença passa a ser emitida e conferida pelo site tools.mlopesdesign.com.br
 * (cliente ML_License_Client_V2). Antes o "status" e o "plano" eram escolhidos à mão na
 * própria tela e salvos localmente (qualquer um se dava licença "Ativo/Lifetime"); o
 * endpoint configurável nunca foi usado. Regras novas: 1 licença = 1 site; teste grátis
 * de 30 dias por site; sem teste/licença válidos, salvar configurações fica travado —
 * os carrosséis publicados continuam aparecendo no site e nenhum dado é apagado.
 *
 * A option antiga (mlcgp_license_state) e os campos antigos em mlcgp_settings são
 * preservados (não são mais usados).
 *
 * @package MLCarouselGalleryPro
 */

namespace MLCarouselGalleryPro\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class License {
	private const API_BASE   = 'https://tools.mlopesdesign.com.br/wp-json/mllh/v1';
	private const PUBLIC_KEY = 'VGZQp2X3RJMuDmQBlIEXS4fqsZD1CnFlIJgfVtY/pHo=';
	private const PRODUCT_ID = 'ml-carousel-gallery-pro';

	public const BUY_URL     = 'https://tools.mlopesdesign.com.br/produtos/';
	public const ACCOUNT_URL = 'https://tools.mlopesdesign.com.br/minha-conta/';

	/** @var Settings */
	private $settings;

	/** @var \ML_License_Client_V2|null */
	private $client = null;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function client(): \ML_License_Client_V2 {
		if ( null === $this->client ) {
			$this->client = new \ML_License_Client_V2(
				[
					'api_base'   => self::API_BASE,
					'product'    => self::PRODUCT_ID,
					'public_key' => self::PUBLIC_KEY,
					'version'    => MLCGP_VERSION,
				]
			);
		}
		return $this->client;
	}

	/** Teste ou licença válidos neste site. */
	public function is_active(): bool {
		return $this->client()->is_premium();
	}

	/** Painel travado (sem teste nem licença). */
	public function is_locked(): bool {
		return ! $this->is_active();
	}

	/** @return array|\WP_Error */
	public function activate( string $key ) {
		return $this->client()->activate( $key );
	}

	/** @return array|\WP_Error */
	public function start_trial() {
		return $this->client()->start_trial();
	}

	/** @return array|\WP_Error */
	public function check() {
		return $this->client()->refresh();
	}

	public function deactivate(): void {
		$this->client()->deactivate();
	}

	/**
	 * Estado pronto para a tela.
	 *
	 * @return array<string, mixed>
	 */
	public function get_state(): array {
		$state  = $this->client()->state();
		$active = ! empty( $state['active'] );
		$mode   = (string) ( $state['mode'] ?? 'none' );
		$plan   = strtolower( (string) ( $state['plan'] ?? '' ) );
		$days   = $state['days_left'] ?? null;

		$plans      = [
			'trial'    => 'Teste grátis',
			'monthly'  => 'Mensal',
			'annual'   => 'Anual',
			'yearly'   => 'Anual',
			'lifetime' => 'Vitalícia',
			'manual'   => 'Cortesia',
			'courtesy' => 'Cortesia',
		];
		$plan_label = $plans[ $plan ] ?? ( '' !== $plan ? ucfirst( $plan ) : '—' );

		if ( $active && 'trial' === $mode ) {
			$label   = 'Teste';
			$tone    = 'trial';
			$message = null === $days ? 'Teste grátis ativo neste site.' : sprintf( 1 === (int) $days ? 'Teste grátis ativo: falta %d dia.' : 'Teste grátis ativo: faltam %d dias.', (int) $days );
		} elseif ( $active ) {
			$label   = 'Ativa';
			$tone    = 'full';
			$message = 'Licença ativa neste site.';
		} else {
			$label = 'Sem licença';
			$tone  = 'free';
			if ( 'trial_expired' === ( $state['status'] ?? '' ) ) {
				$message = 'O teste grátis terminou. Ative uma licença para voltar a editar os carrosséis. Os carrosséis publicados continuam no ar.';
			} elseif ( '' !== (string) ( $state['message'] ?? '' ) ) {
				$message = (string) $state['message'];
			} else {
				$message = 'Comece o teste grátis de 30 dias ou ative a sua licença.';
			}
		}

		$expires_label = '';
		if ( ! empty( $state['expires_at'] ) ) {
			$ts = strtotime( (string) $state['expires_at'] );
			if ( $ts ) {
				$expires_label = wp_date( get_option( 'date_format', 'd/m/Y' ), $ts );
			}
		}

		return [
			'active'          => $active,
			'locked'          => ! $active,
			'mode'            => $mode,
			'is_trial'        => $active && 'trial' === $mode,
			'label'           => $label,
			'tone'            => $tone,
			'message'         => $message,
			'plan_label'      => $plan_label,
			'expires_label'   => $expires_label,
			'key_masked'      => (string) ( $state['key_masked'] ?? '' ),
			'can_start_trial' => ! $active && empty( $state['trial_used'] ) && 'license' !== $mode,
		];
	}
}
