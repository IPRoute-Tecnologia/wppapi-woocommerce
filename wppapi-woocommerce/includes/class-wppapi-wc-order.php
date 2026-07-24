<?php
/**
 * Hooks de pedido, opt-in no checkout e meta box de envio.
 *
 * @package WPPAPI_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Integração com o ciclo de vida do pedido.
 */
class WPPAPI_WC_Order {

	/**
	 * Registra os hooks.
	 */
	public static function init() {
		// Eventos de pedido.
		add_action( 'woocommerce_new_order', array( __CLASS__, 'on_new_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_processing' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_completed' ), 10, 1 );

		// Opt-in LGPD no checkout.
		add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'render_optin_field' ), 10, 1 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_optin' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_admin_optin' ), 10, 1 );

		// Meta box "Pedido enviado" (HPOS-safe). O campo de rastreio vai junto
		// do formulário principal do pedido; o botão é um link com nonce.
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_tracking_meta' ), 10, 1 );
		add_action( 'woocommerce_update_order', array( __CLASS__, 'save_tracking_meta' ), 10, 1 );
		add_action( 'admin_post_wppapi_wc_mark_shipped', array( __CLASS__, 'handle_mark_shipped' ) );
		// O handler acima usa link GET (admin-post.php aceita GET e POST).
		add_action( 'admin_post_nopriv_wppapi_wc_mark_shipped', array( __CLASS__, 'deny_anonymous' ) );
	}

	/**
	 * Pedido criado (qualquer origem: checkout, admin, REST).
	 *
	 * @param int $order_id ID do pedido.
	 */
	public static function on_new_order( $order_id ) {
		WPPAPI_WC_Messenger::queue( $order_id, 'created' );
	}

	/**
	 * Pagamento aprovado (status processing).
	 *
	 * @param int $order_id ID do pedido.
	 */
	public static function on_processing( $order_id ) {
		WPPAPI_WC_Messenger::queue( $order_id, 'processing' );
	}

	/**
	 * Pedido concluído (status completed).
	 *
	 * @param int $order_id ID do pedido.
	 */
	public static function on_completed( $order_id ) {
		WPPAPI_WC_Messenger::queue( $order_id, 'completed' );
	}

	/**
	 * Checkbox de opt-in no checkout.
	 *
	 * @param WC_Checkout $checkout Objeto do checkout.
	 */
	public static function render_optin_field( $checkout ) {
		$settings = wppapi_wc_get_settings();
		?>
		<p class="form-row wppapi-wc-optin" id="wppapi_whatsapp_optin_field">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
				<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="wppapi_whatsapp_optin" id="wppapi_whatsapp_optin" value="1" />
				<span><?php echo esc_html( $settings['optin_label'] ); ?></span>
			</label>
		</p>
		<?php
	}

	/**
	 * Salva o opt-in no meta do pedido (HPOS-safe via WC_Order).
	 *
	 * @param WC_Order $order Pedido em criação.
	 * @param array    $data  Dados do checkout.
	 */
	public static function save_optin( $order, $data ) {
		// O formulário do checkout já é protegido pelo nonce do WooCommerce;
		// verificamos explicitamente antes de ler $_POST.
		if (
			! isset( $_POST['woocommerce-process-checkout-nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ) ), 'woocommerce-process_checkout' )
		) {
			return;
		}

		$optin = isset( $_POST['wppapi_whatsapp_optin'] ) ? 'yes' : 'no';
		$order->update_meta_data( '_wppapi_whatsapp_optin', $optin );
	}

	/**
	 * Exibe o opt-in na tela do pedido no admin.
	 *
	 * @param WC_Order $order Pedido.
	 */
	public static function render_admin_optin( $order ) {
		$optin = $order->get_meta( '_wppapi_whatsapp_optin' );

		if ( 'yes' === $optin ) {
			$label = __( 'Sim', 'wppapi-woocommerce' );
		} elseif ( 'no' === $optin ) {
			$label = __( 'Não', 'wppapi-woocommerce' );
		} else {
			$label = __( 'Não informado', 'wppapi-woocommerce' );
		}

		echo '<p><strong>' . esc_html__( 'Opt-in WhatsApp:', 'wppapi-woocommerce' ) . '</strong> ' . esc_html( $label ) . '</p>';
	}

	/**
	 * Registra a meta box na tela do pedido (clássica e HPOS).
	 */
	public static function register_meta_box() {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}

		foreach ( array_unique( $screens ) as $screen ) {
			add_meta_box(
				'wppapi-wc-shipping',
				__( 'WhatsApp (WPPAPI) — Envio', 'wppapi-woocommerce' ),
				array( __CLASS__, 'render_meta_box' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Renderiza a meta box de envio/rastreio.
	 *
	 * A tela de edição do pedido já é um <form>; por isso NÃO usamos um form
	 * aninhado. O campo vai junto do formulário do pedido (salvo em
	 * woocommerce_process_shop_order_meta / woocommerce_update_order) e o
	 * botão é um link com nonce que carrega o valor digitado via JS.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post ou pedido (depende de HPOS).
	 */
	public static function render_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}

		$tracking = $order->get_meta( '_wppapi_tracking_code' );
		$external = wppapi_wc_get_tracking_code( $order );
		$notify   = wp_nonce_url(
			admin_url( 'admin-post.php?action=wppapi_wc_mark_shipped&order_id=' . $order->get_id() ),
			'wppapi_wc_mark_shipped_' . $order->get_id()
		);
		?>
		<p>
			<label for="wppapi_tracking_code"><strong><?php esc_html_e( 'Código de rastreio', 'wppapi-woocommerce' ); ?></strong></label>
			<input type="text" id="wppapi_tracking_code" name="wppapi_tracking_code" value="<?php echo esc_attr( $tracking ); ?>" class="widefat" />
		</p>
		<?php if ( '' === $tracking && '' !== $external ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: código de rastreio detectado de outro plugin. */
					esc_html__( 'Rastreio detectado de outro plugin: %s', 'wppapi-woocommerce' ),
					esc_html( $external )
				);
				?>
			</p>
		<?php endif; ?>
		<p>
			<a href="<?php echo esc_url( $notify ); ?>" class="button button-primary" id="wppapi-wc-notify-shipped"><?php esc_html_e( 'Salvar e notificar envio', 'wppapi-woocommerce' ); ?></a>
		</p>
		<p class="description">
			<?php esc_html_e( 'O código é salvo ao atualizar o pedido. O botão salva o valor digitado e enfileira a mensagem do evento "Pedido enviado" (somente se houver opt-in).', 'wppapi-woocommerce' ); ?>
		</p>
		<script>
		( function() {
			var btn = document.getElementById( 'wppapi-wc-notify-shipped' );
			var input = document.getElementById( 'wppapi_tracking_code' );
			if ( ! btn || ! input ) { return; }
			btn.addEventListener( 'click', function() {
				btn.href = btn.href.replace( /&tracking_code=[^&]*/, '' ) + '&tracking_code=' + encodeURIComponent( input.value );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Salva o código de rastreio enviado pelo formulário do pedido.
	 *
	 * O nonce é verificado upstream pelo próprio formulário de edição do
	 * pedido (tela clássica ou HPOS). A comparação com o valor atual evita
	 * loop infinito com o hook woocommerce_update_order.
	 *
	 * @param int $order_id ID do pedido.
	 */
	public static function save_tracking_meta( $order_id ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! isset( $_POST['wppapi_tracking_code'] ) ) {
			return;
		}

		$new   = sanitize_text_field( wp_unslash( $_POST['wppapi_tracking_code'] ) );
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_wppapi_tracking_code' ) === $new ) {
			return;
		}

		$order->update_meta_data( '_wppapi_tracking_code', $new );
		$order->save();
	}

	/**
	 * Bloqueia acesso anônimo ao endpoint de notificação.
	 */
	public static function deny_anonymous() {
		wp_die( esc_html__( 'Permissão negada.', 'wppapi-woocommerce' ) );
	}

	/**
	 * Processa o "Salvar e notificar envio" (link GET com nonce).
	 */
	public static function handle_mark_shipped() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permissão negada.', 'wppapi-woocommerce' ) );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		if ( ! $order_id ) {
			wp_die( esc_html__( 'Pedido não encontrado.', 'wppapi-woocommerce' ) );
		}
		check_admin_referer( 'wppapi_wc_mark_shipped_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Pedido não encontrado.', 'wppapi-woocommerce' ) );
		}

		if ( isset( $_GET['tracking_code'] ) ) {
			$tracking = sanitize_text_field( wp_unslash( $_GET['tracking_code'] ) );
			if ( '' !== $tracking ) {
				$order->update_meta_data( '_wppapi_tracking_code', $tracking );
			}
		}

		$order->add_order_note( __( 'Pedido marcado como enviado; notificação WhatsApp enfileirada (WPPAPI).', 'wppapi-woocommerce' ) );
		$order->save();

		WPPAPI_WC_Messenger::queue( $order_id, 'shipped' );

		wp_safe_redirect( $order->get_edit_order_url() );
		exit;
	}
}
