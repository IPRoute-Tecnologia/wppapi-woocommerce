<?php
/**
 * Plugin Name:       WPPAPI for WooCommerce
 * Plugin URI:        https://github.com/IPRoute-Tecnologia/wppapi-woocommerce
 * Description:       Envia atualizações transacionais de pedidos do WooCommerce por WhatsApp via WPPAPI, com opt-in LGPD, envio assíncrono com retry e log de mensagens.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            IPRoute Tecnologia
 * Author URI:        https://github.com/IPRoute-Tecnologia
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wppapi-for-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:      9.4
 */

defined( 'ABSPATH' ) || exit;

define( 'WPPAPI_WC_VERSION', '1.0.0' );
define( 'WPPAPI_WC_FILE', __FILE__ );
define( 'WPPAPI_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPPAPI_WC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declara compatibilidade com HPOS (Custom Order Tables).
 */
add_action( 'before_woocommerce_init', 'wppapi_wc_declare_hpos_compatibility' );
function wppapi_wc_declare_hpos_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

/**
 * Bootstrap do plugin.
 */
add_action( 'plugins_loaded', 'wppapi_wc_init' );
function wppapi_wc_init() {
	load_plugin_textdomain( 'wppapi-for-woocommerce', false, dirname( plugin_basename( WPPAPI_WC_FILE ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wppapi_wc_missing_woocommerce_notice' );
		return;
	}

	require_once WPPAPI_WC_PATH . 'includes/class-wppapi-wc-api.php';
	require_once WPPAPI_WC_PATH . 'includes/class-wppapi-wc-messenger.php';
	require_once WPPAPI_WC_PATH . 'includes/class-wppapi-wc-order.php';
	require_once WPPAPI_WC_PATH . 'includes/class-wppapi-wc-settings.php';

	WPPAPI_WC_Settings::init();
	WPPAPI_WC_Messenger::init();
	WPPAPI_WC_Order::init();
}

/**
 * Aviso quando o WooCommerce não está ativo.
 */
function wppapi_wc_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'WPPAPI para WooCommerce requer o plugin WooCommerce ativo para funcionar.', 'wppapi-for-woocommerce' );
	echo '</p></div>';
}

/**
 * Link "Configurações" na lista de plugins.
 */
add_filter( 'plugin_action_links_' . plugin_basename( WPPAPI_WC_FILE ), 'wppapi_wc_action_links' );
function wppapi_wc_action_links( $links ) {
	$url   = admin_url( 'admin.php?page=wppapi-for-woocommerce' );
	$label = esc_html__( 'Configurações', 'wppapi-for-woocommerce' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . $label . '</a>' );
	return $links;
}

/**
 * Configurações padrão do plugin.
 *
 * @return array
 */
function wppapi_wc_default_settings() {
	return array(
		'base_url'    => 'https://api.wpp-api.com',
		'instance_id' => '',
		'token'       => '',
		'optin_label' => __( 'Aceito receber atualizações do pedido por WhatsApp', 'wppapi-for-woocommerce' ),
		'events'      => array(
			'created'    => array(
				'enabled'  => 'yes',
				'template' => __( 'Olá {nome}! Recebemos seu pedido #{pedido} no valor de {total}. Assim que o pagamento for confirmado, avisamos por aqui.', 'wppapi-for-woocommerce' ),
			),
			'processing' => array(
				'enabled'  => 'yes',
				'template' => __( 'Olá {nome}! O pagamento do seu pedido #{pedido} ({total}) foi aprovado. Já estamos preparando tudo!', 'wppapi-for-woocommerce' ),
			),
			'shipped'    => array(
				'enabled'  => 'yes',
				'template' => __( 'Olá {nome}! Seu pedido #{pedido} foi enviado. Código de rastreio: {rastreio}.', 'wppapi-for-woocommerce' ),
			),
			'completed'  => array(
				'enabled'  => 'yes',
				'template' => __( 'Olá {nome}! Seu pedido #{pedido} foi concluído. Obrigado pela compra!', 'wppapi-for-woocommerce' ),
			),
		),
	);
}

/**
 * Retorna as configurações mescladas com os padrões.
 *
 * @return array
 */
function wppapi_wc_get_settings() {
	$defaults = wppapi_wc_default_settings();
	$saved    = get_option( 'wppapi_wc_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$settings = wp_parse_args( $saved, $defaults );

	foreach ( $defaults['events'] as $key => $event_defaults ) {
		$saved_event                = isset( $saved['events'][ $key ] ) && is_array( $saved['events'][ $key ] ) ? $saved['events'][ $key ] : array();
		$settings['events'][ $key ] = wp_parse_args( $saved_event, $event_defaults );
	}

	return $settings;
}

/**
 * Rótulos dos eventos de pedido.
 *
 * @return array
 */
function wppapi_wc_event_labels() {
	return array(
		'created'    => __( 'Pedido criado', 'wppapi-for-woocommerce' ),
		'processing' => __( 'Pagamento aprovado', 'wppapi-for-woocommerce' ),
		'shipped'    => __( 'Pedido enviado', 'wppapi-for-woocommerce' ),
		'completed'  => __( 'Pedido concluído', 'wppapi-for-woocommerce' ),
	);
}

/**
 * Normaliza um telefone brasileiro para E.164 sem o "+" (55 + DDD + número).
 *
 * Celulares são normalizados para 9 dígitos (com o nono dígito).
 * Retorna string vazia quando o formato não é reconhecido.
 *
 * @param string $phone Telefone de entrada.
 * @return string
 */
function wppapi_wc_normalize_phone( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	if ( '' === $digits ) {
		return '';
	}

	// Remove prefixo internacional discado (00...).
	if ( strpos( $digits, '00' ) === 0 ) {
		$digits = substr( $digits, 2 );
	}

	$national = '';
	$length   = strlen( $digits );

	if ( strpos( $digits, '55' ) === 0 && $length >= 12 && $length <= 13 ) {
		// Já está com código do país: 55 + DDD (2) + número (8 ou 9).
		$national = substr( $digits, 2 );
	} elseif ( $length >= 10 && $length <= 11 ) {
		// Número nacional: DDD (2) + número (8 ou 9).
		$national = $digits;
	} else {
		return '';
	}

	$ddd    = substr( $national, 0, 2 );
	$number = substr( $national, 2 );

	// Celular sem o nono dígito: 8 dígitos começando com 6-9 recebem o prefixo 9.
	if ( strlen( $number ) === 8 ) {
		$first = substr( $number, 0, 1 );
		if ( in_array( $first, array( '6', '7', '8', '9' ), true ) ) {
			$number = '9' . $number;
		}
	}

	return '55' . $ddd . $number;
}

/**
 * Obtém o código de rastreio do pedido.
 *
 * Prioridade: meta própria do plugin, meta genérica `_tracking_code`,
 * itens do plugin WooCommerce Shipment Tracking.
 *
 * @param WC_Order $order Pedido.
 * @return string
 */
function wppapi_wc_get_tracking_code( $order ) {
	$tracking = $order->get_meta( '_wppapi_tracking_code' );

	if ( '' === $tracking || null === $tracking ) {
		$tracking = $order->get_meta( '_tracking_code' );
	}

	if ( '' === $tracking || null === $tracking ) {
		$items = $order->get_meta( '_wc_shipment_tracking_items' );
		if ( is_array( $items ) && ! empty( $items ) ) {
			$first = reset( $items );
			if ( is_array( $first ) && ! empty( $first['tracking_number'] ) ) {
				$tracking = $first['tracking_number'];
			}
		}
	}

	return is_string( $tracking ) ? $tracking : '';
}

/**
 * Adiciona uma entrada no log de mensagens (máx. 50, mais recentes primeiro).
 *
 * @param array $entry Dados da entrada.
 */
function wppapi_wc_add_log( $entry ) {
	$log = get_option( 'wppapi_wc_log', array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}

	$entry = wp_parse_args(
		$entry,
		array(
			'time'          => current_time( 'mysql' ),
			'order_id'      => 0,
			'phone'         => '',
			'event'         => '',
			'status'        => '',
			'response_code' => 0,
			'error'         => '',
		)
	);

	array_unshift( $log, $entry );
	$log = array_slice( $log, 0, 50 );

	update_option( 'wppapi_wc_log', $log, false );
}

/**
 * Limita o tamanho de uma string de forma segura para UTF-8.
 *
 * @param string $text  Texto.
 * @param int    $limit Limite de caracteres.
 * @return string
 */
function wppapi_wc_str_limit( $text, $limit = 200 ) {
	$text = (string) $text;
	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $text, 0, $limit );
	}
	return substr( $text, 0, $limit );
}
