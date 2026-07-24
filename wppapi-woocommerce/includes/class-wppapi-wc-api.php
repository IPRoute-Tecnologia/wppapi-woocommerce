<?php
/**
 * Cliente HTTP para a API WPPAPI (gateway estilo Z-API por instância).
 *
 * @package WPPAPI_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Comunicação com a WPPAPI.
 */
class WPPAPI_WC_API {

	const TIMEOUT = 15;

	/**
	 * Monta a URL de um endpoint da instância.
	 *
	 * O token NUNCA vai na URL: vai apenas no header Client-Token.
	 *
	 * @param array  $settings Configurações do plugin.
	 * @param string $endpoint Endpoint (ex.: "status", "send-text").
	 * @return string
	 */
	private static function endpoint_url( $settings, $endpoint ) {
		return trailingslashit( $settings['base_url'] )
			. 'instances/' . rawurlencode( $settings['instance_id'] )
			. '/token/_/' . ltrim( $endpoint, '/' );
	}

	/**
	 * Headers de autenticação e conteúdo.
	 *
	 * @param array $settings Configurações do plugin.
	 * @return array
	 */
	private static function headers( $settings ) {
		return array(
			'Client-Token' => $settings['token'],
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);
	}

	/**
	 * Consulta o status da conexão da instância.
	 *
	 * @param array $settings Configurações do plugin.
	 * @return array { ok, code, error, body }
	 */
	public static function get_status( $settings ) {
		$response = wp_remote_get(
			self::endpoint_url( $settings, 'status' ),
			array(
				'timeout' => self::TIMEOUT,
				'headers' => self::headers( $settings ),
			)
		);

		return self::parse_response( $response );
	}

	/**
	 * Envia uma mensagem de texto.
	 *
	 * @param array  $settings Configurações do plugin.
	 * @param string $phone    Telefone E.164 sem "+".
	 * @param string $message  Mensagem.
	 * @return array { ok, code, error, body }
	 */
	public static function send_text( $settings, $phone, $message ) {
		$response = wp_remote_post(
			self::endpoint_url( $settings, 'send-text' ),
			array(
				'timeout' => self::TIMEOUT,
				'headers' => self::headers( $settings ),
				'body'    => wp_json_encode(
					array(
						'phone'   => $phone,
						'message' => $message,
					)
				),
			)
		);

		return self::parse_response( $response );
	}

	/**
	 * Normaliza o resultado de wp_remote_*.
	 *
	 * @param array|WP_Error $response Resposta HTTP.
	 * @return array { ok, code, error, body }
	 */
	private static function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'    => false,
				'code'  => 0,
				'error' => $response->get_error_message(),
				'body'  => '',
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$ok   = $code >= 200 && $code < 300;

		$error = '';
		if ( ! $ok ) {
			$error = '' !== $body ? wppapi_wc_str_limit( $body ) : sprintf( 'HTTP %d', $code );
		}

		return array(
			'ok'    => $ok,
			'code'  => $code,
			'error' => $error,
			'body'  => $body,
		);
	}
}
