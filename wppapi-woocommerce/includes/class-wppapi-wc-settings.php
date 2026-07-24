<?php
/**
 * Tela de configurações, teste de conexão e log de mensagens.
 *
 * @package WPPAPI_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin do plugin.
 */
class WPPAPI_WC_Settings {

	const SLUG = 'wppapi-woocommerce';

	/**
	 * Registra menu e handlers de admin-post.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_wppapi_wc_save_settings', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_wppapi_wc_test_connection', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_wppapi_wc_clear_log', array( __CLASS__, 'handle_clear_log' ) );
	}

	/**
	 * Submenu do WooCommerce.
	 */
	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'WPPAPI', 'wppapi-woocommerce' ),
			__( 'WPPAPI', 'wppapi-woocommerce' ),
			'manage_woocommerce',
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Redireciona de volta para a tela de configurações.
	 */
	private static function redirect() {
		wp_safe_redirect( add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Guarda um aviso transitório para o usuário atual.
	 *
	 * @param string $type    success|error.
	 * @param string $message Mensagem.
	 */
	private static function set_notice( $type, $message ) {
		set_transient(
			'wppapi_wc_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Salva as configurações.
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permissão negada.', 'wppapi-woocommerce' ) );
		}
		check_admin_referer( 'wppapi_wc_save_settings' );

		$current = wppapi_wc_get_settings();
		$post    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verificado por check_admin_referer().

		$base_url = isset( $post['base_url'] ) ? esc_url_raw( $post['base_url'] ) : '';
		if ( '' === $base_url ) {
			$base_url = 'https://api.wpp-api.com';
		}

		$settings = array(
			'base_url'    => untrailingslashit( $base_url ),
			'instance_id' => isset( $post['instance_id'] ) ? sanitize_text_field( $post['instance_id'] ) : '',
			'token'       => $current['token'],
			'optin_label' => isset( $post['optin_label'] ) ? sanitize_text_field( $post['optin_label'] ) : $current['optin_label'],
			'events'      => array(),
		);

		// Token: campo em branco mantém o valor atual (nunca exibido em claro).
		$token = isset( $post['token'] ) ? sanitize_text_field( $post['token'] ) : '';
		if ( '' !== $token ) {
			$settings['token'] = $token;
		}

		foreach ( wppapi_wc_event_labels() as $key => $label ) {
			$enabled  = isset( $post['events'][ $key ]['enabled'] ) ? 'yes' : 'no';
			$template = isset( $post['events'][ $key ]['template'] ) ? sanitize_textarea_field( $post['events'][ $key ]['template'] ) : '';

			$settings['events'][ $key ] = array(
				'enabled'  => $enabled,
				'template' => $template,
			);
		}

		update_option( 'wppapi_wc_settings', $settings, false );

		self::set_notice( 'success', __( 'Configurações salvas.', 'wppapi-woocommerce' ) );
		self::redirect();
	}

	/**
	 * Testa a conexão com a WPPAPI usando as credenciais salvas.
	 */
	public static function handle_test() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permissão negada.', 'wppapi-woocommerce' ) );
		}
		check_admin_referer( 'wppapi_wc_test_connection' );

		$settings = wppapi_wc_get_settings();

		if ( '' === $settings['instance_id'] || '' === $settings['token'] ) {
			self::set_notice( 'error', __( 'Salve o Instance ID e o Token antes de testar a conexão.', 'wppapi-woocommerce' ) );
			self::redirect();
		}

		$result = WPPAPI_WC_API::get_status( $settings );

		if ( $result['ok'] ) {
			self::set_notice(
				'success',
				sprintf(
					/* translators: 1: código HTTP, 2: corpo da resposta. */
					__( 'Conexão bem-sucedida (HTTP %1$d). Resposta: %2$s', 'wppapi-woocommerce' ),
					$result['code'],
					wppapi_wc_str_limit( $result['body'] )
				)
			);
		} else {
			self::set_notice(
				'error',
				sprintf(
					/* translators: %s: mensagem de erro. */
					__( 'Falha na conexão: %s', 'wppapi-woocommerce' ),
					wppapi_wc_str_limit( $result['error'] )
				)
			);
		}

		self::redirect();
	}

	/**
	 * Limpa o log de mensagens.
	 */
	public static function handle_clear_log() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permissão negada.', 'wppapi-woocommerce' ) );
		}
		check_admin_referer( 'wppapi_wc_clear_log' );

		delete_option( 'wppapi_wc_log' );

		self::set_notice( 'success', __( 'Log limpo.', 'wppapi-woocommerce' ) );
		self::redirect();
	}

	/**
	 * Renderiza a tela de configurações.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permissão negada.', 'wppapi-woocommerce' ) );
		}

		$settings = wppapi_wc_get_settings();
		$labels   = wppapi_wc_event_labels();

		$notice = get_transient( 'wppapi_wc_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'wppapi_wc_notice_' . get_current_user_id() );
		}

		$token_hint = '';
		if ( '' !== $settings['token'] ) {
			$token_hint = sprintf(
				/* translators: %s: últimos 4 caracteres do token. */
				__( 'Token atual: ••••%s — deixe o campo em branco para mantê-lo.', 'wppapi-woocommerce' ),
				substr( $settings['token'], -4 )
			);
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WPPAPI para WooCommerce', 'wppapi-woocommerce' ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wppapi_wc_save_settings" />
				<?php wp_nonce_field( 'wppapi_wc_save_settings' ); ?>

				<h2><?php esc_html_e( 'Conexão com a WPPAPI', 'wppapi-woocommerce' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wppapi_base_url"><?php esc_html_e( 'Base URL', 'wppapi-woocommerce' ); ?></label></th>
						<td>
							<input type="url" id="wppapi_base_url" name="base_url" value="<?php echo esc_attr( $settings['base_url'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Padrão: https://api.wpp-api.com', 'wppapi-woocommerce' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppapi_instance_id"><?php esc_html_e( 'Instance ID', 'wppapi-woocommerce' ); ?></label></th>
						<td>
							<input type="text" id="wppapi_instance_id" name="instance_id" value="<?php echo esc_attr( $settings['instance_id'] ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppapi_token"><?php esc_html_e( 'Token', 'wppapi-woocommerce' ); ?></label></th>
						<td>
							<input type="password" id="wppapi_token" name="token" value="" class="regular-text" autocomplete="new-password" />
							<?php if ( '' !== $token_hint ) : ?>
								<p class="description"><?php echo esc_html( $token_hint ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Eventos e mensagens', 'wppapi-woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'Placeholders disponíveis: {nome}, {pedido}, {total}, {rastreio}.', 'wppapi-woocommerce' ); ?></p>
				<table class="form-table" role="presentation">
					<?php foreach ( $labels as $key => $label ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="events[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $settings['events'][ $key ]['enabled'], 'yes' ); ?> />
									<?php esc_html_e( 'Ativo', 'wppapi-woocommerce' ); ?>
								</label>
								<br /><br />
								<textarea name="events[<?php echo esc_attr( $key ); ?>][template]" rows="3" class="large-text"><?php echo esc_textarea( $settings['events'][ $key ]['template'] ); ?></textarea>
								<?php if ( 'shipped' === $key ) : ?>
									<p class="description"><?php esc_html_e( 'Disparado pelo botão "Salvar e notificar envio" na tela do pedido.', 'wppapi-woocommerce' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Opt-in (LGPD)', 'wppapi-woocommerce' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wppapi_optin_label"><?php esc_html_e( 'Texto do checkbox no checkout', 'wppapi-woocommerce' ); ?></label></th>
						<td>
							<input type="text" id="wppapi_optin_label" name="optin_label" value="<?php echo esc_attr( $settings['optin_label'] ); ?>" class="large-text" />
							<p class="description"><?php esc_html_e( 'Nenhuma mensagem é enviada sem o opt-in do cliente.', 'wppapi-woocommerce' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Salvar configurações', 'wppapi-woocommerce' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Testar conexão', 'wppapi-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'Consulta o status da instância usando as credenciais salvas. Salve as configurações antes de testar.', 'wppapi-woocommerce' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wppapi_wc_test_connection" />
				<?php wp_nonce_field( 'wppapi_wc_test_connection' ); ?>
				<?php submit_button( __( 'Testar conexão', 'wppapi-woocommerce' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Log de mensagens (últimas 50)', 'wppapi-woocommerce' ); ?></h2>
			<?php self::render_log_table(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 10px;">
				<input type="hidden" name="action" value="wppapi_wc_clear_log" />
				<?php wp_nonce_field( 'wppapi_wc_clear_log' ); ?>
				<?php submit_button( __( 'Limpar log', 'wppapi-woocommerce' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Tabela com as últimas mensagens registradas.
	 */
	private static function render_log_table() {
		$log = get_option( 'wppapi_wc_log', array() );
		if ( ! is_array( $log ) || empty( $log ) ) {
			echo '<p>' . esc_html__( 'Nenhuma mensagem registrada ainda.', 'wppapi-woocommerce' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Data', 'wppapi-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Pedido', 'wppapi-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Telefone', 'wppapi-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Evento', 'wppapi-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wppapi-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'HTTP', 'wppapi-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Erro', 'wppapi-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $log as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( $entry['time'] ); ?></td>
						<td>
							<?php
							$order = wc_get_order( (int) $entry['order_id'] );
							if ( $order ) {
								echo '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
							} else {
								echo '#' . esc_html( $entry['order_id'] );
							}
							?>
						</td>
						<td><?php echo esc_html( $entry['phone'] ); ?></td>
						<td><?php echo esc_html( $entry['event'] ); ?></td>
						<td><?php echo esc_html( $entry['status'] ); ?></td>
						<td><?php echo esc_html( $entry['response_code'] ? $entry['response_code'] : '—' ); ?></td>
						<td><?php echo esc_html( $entry['error'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
