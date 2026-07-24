<?php
/**
 * Fila de envio, retry e registro de mensagens.
 *
 * @package WPPAPI_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Envia mensagens de forma assíncrona com retry.
 */
class WPPAPI_WC_Messenger {

	const HOOK        = 'wppapi_wc_send_message';
	const GROUP       = 'wppapi-woocommerce';
	const MAX_RETRIES = 3;

	/**
	 * Backoff entre tentativas, em segundos: 5 min, 30 min, 2 h.
	 *
	 * @var int[]
	 */
	private static $retry_delays = array( 300, 1800, 7200 );

	/**
	 * Registra o callback do hook de envio (Action Scheduler e wp-cron
	 * disparam o mesmo hook, então um único handler atende aos dois).
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'handle' ), 10, 1 );
	}

	/**
	 * Enfileira uma mensagem para envio assíncrono.
	 *
	 * @param int    $order_id ID do pedido.
	 * @param string $event    Slug do evento (created|processing|shipped|completed).
	 * @param int    $attempt  Número da tentativa (1 = primeira).
	 * @param int    $delay    Atraso em segundos antes de executar.
	 */
	public static function queue( $order_id, $event, $attempt = 1, $delay = 0 ) {
		// Na primeira tentativa, respeita o enable do evento. Retentativas
		// sempre prosseguem (o evento já foi aceito quando enfileirado).
		if ( 1 === (int) $attempt ) {
			$settings = wppapi_wc_get_settings();
			if ( empty( $settings['events'][ $event ] ) || 'yes' !== $settings['events'][ $event ]['enabled'] ) {
				return;
			}
		}

		$payload = array(
			'order_id' => (int) $order_id,
			'event'    => (string) $event,
			'attempt'  => (int) $attempt,
		);
		$when    = time() + max( 0, (int) $delay );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $when, self::HOOK, array( $payload ), self::GROUP );
		} else {
			wp_schedule_single_event( $when, self::HOOK, array( $payload ) );
		}
	}

	/**
	 * Processa um envio agendado.
	 *
	 * @param array $payload { order_id, event, attempt }.
	 */
	public static function handle( $payload ) {
		$payload  = wp_parse_args(
			(array) $payload,
			array(
				'order_id' => 0,
				'event'    => '',
				'attempt'  => 1,
			)
		);
		$order_id = (int) $payload['order_id'];
		$event    = (string) $payload['event'];
		$attempt  = (int) $payload['attempt'];

		$labels      = wppapi_wc_event_labels();
		$event_label = isset( $labels[ $event ] ) ? $labels[ $event ] : $event;

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wppapi_wc_add_log(
				array(
					'order_id' => $order_id,
					'event'    => $event_label,
					'status'   => __( 'Falha', 'wppapi-woocommerce' ),
					'error'    => __( 'Pedido não encontrado.', 'wppapi-woocommerce' ),
				)
			);
			return;
		}

		// LGPD: nenhuma mensagem sai sem opt-in registrado no pedido.
		if ( 'yes' !== $order->get_meta( '_wppapi_whatsapp_optin' ) ) {
			wppapi_wc_add_log(
				array(
					'order_id' => $order_id,
					'event'    => $event_label,
					'status'   => __( 'Ignorado (sem opt-in)', 'wppapi-woocommerce' ),
				)
			);
			return;
		}

		$phone = wppapi_wc_normalize_phone( $order->get_billing_phone() );
		if ( '' === $phone ) {
			wppapi_wc_add_log(
				array(
					'order_id' => $order_id,
					'event'    => $event_label,
					'status'   => __( 'Falha', 'wppapi-woocommerce' ),
					'error'    => __( 'Telefone de cobrança ausente ou em formato não reconhecido.', 'wppapi-woocommerce' ),
				)
			);
			return;
		}

		$settings = wppapi_wc_get_settings();
		$template = isset( $settings['events'][ $event ]['template'] ) ? $settings['events'][ $event ]['template'] : '';
		if ( '' === trim( $template ) ) {
			wppapi_wc_add_log(
				array(
					'order_id' => $order_id,
					'phone'    => $phone,
					'event'    => $event_label,
					'status'   => __( 'Ignorado (template vazio)', 'wppapi-woocommerce' ),
				)
			);
			return;
		}

		$message = self::render_template( $template, $order );
		$result  = WPPAPI_WC_API::send_text( $settings, $phone, $message );

		if ( $result['ok'] ) {
			wppapi_wc_add_log(
				array(
					'order_id'      => $order_id,
					'phone'         => $phone,
					'event'         => $event_label,
					'status'        => __( 'Enviado', 'wppapi-woocommerce' ),
					'response_code' => $result['code'],
				)
			);
			return;
		}

		if ( $attempt <= self::MAX_RETRIES ) {
			$delay = self::$retry_delays[ $attempt - 1 ];
			wppapi_wc_add_log(
				array(
					'order_id'      => $order_id,
					'phone'         => $phone,
					'event'         => $event_label,
					'status'        => sprintf(
						/* translators: %d: minutos até a próxima tentativa. */
						__( 'Falha; nova tentativa em %d min', 'wppapi-woocommerce' ),
						(int) ( $delay / 60 )
					),
					'response_code' => $result['code'],
					'error'         => wppapi_wc_str_limit( $result['error'] ),
				)
			);
			self::queue( $order_id, $event, $attempt + 1, $delay );
		} else {
			wppapi_wc_add_log(
				array(
					'order_id'      => $order_id,
					'phone'         => $phone,
					'event'         => $event_label,
					'status'        => __( 'Falha definitiva', 'wppapi-woocommerce' ),
					'response_code' => $result['code'],
					'error'         => wppapi_wc_str_limit( $result['error'] ),
				)
			);
		}
	}

	/**
	 * Substitui os placeholders do template.
	 *
	 * @param string   $template Template da mensagem.
	 * @param WC_Order $order    Pedido.
	 * @return string
	 */
	private static function render_template( $template, $order ) {
		$total = html_entity_decode(
			wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			ENT_QUOTES,
			'UTF-8'
		);

		$map = array(
			'{nome}'     => $order->get_billing_first_name(),
			'{pedido}'   => $order->get_order_number(),
			'{total}'    => $total,
			'{rastreio}' => wppapi_wc_get_tracking_code( $order ),
		);

		return strtr( $template, $map );
	}
}
