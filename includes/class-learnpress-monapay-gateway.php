<?php
/**
 * LearnPress MONA Pay payment method.
 *
 * @package LearnPress_MONAPay
 */

defined( 'ABSPATH' ) || exit;

final class LearnPress_MONAPay_Gateway extends LP_Gateway_Abstract {
	/** @var string */
	public $id = 'monapay';

	/** Constructor. */
	public function __construct() {
		parent::__construct();
		$this->method_title       = __( 'MONA Pay bank transfer (VietQR)', 'learnpress-monapay' );
		$this->method_description = __( 'Automatic bank transfer confirmation with dynamic VietQR, virtual accounts, and signed webhooks. Money goes directly to your bank account; MONA Pay does not hold funds.', 'learnpress-monapay' );
		$this->title              = $this->setting( 'title', __( 'MONA Pay bank transfer (VietQR)', 'learnpress-monapay' ) );
		$this->description        = $this->setting( 'description', __( 'Scan the dynamic VietQR. Your course order is confirmed automatically when the transfer arrives.', 'learnpress-monapay' ) );
		$this->enabled            = $this->setting( 'enable', 'no' );

		add_action( 'learn-press/order/received', array( $this, 'render_payment_box' ) );
	}

	/** Admin settings shown at LearnPress > Settings > Payments. */
	public function get_settings() {
		$webhook_url = rest_url( 'learnpress-monapay/v1/webhook' );
		return array(
			array( 'type' => 'title' ),
			array( 'title' => __( 'Enable/Disable', 'learnpress-monapay' ), 'id' => '[enable]', 'default' => 'no', 'type' => 'checkbox', 'desc' => __( 'Enable MONA Pay for LearnPress', 'learnpress-monapay' ) ),
			array( 'title' => __( 'Title', 'learnpress-monapay' ), 'id' => '[title]', 'default' => __( 'MONA Pay bank transfer (VietQR)', 'learnpress-monapay' ), 'type' => 'text' ),
			array( 'title' => __( 'Description', 'learnpress-monapay' ), 'id' => '[description]', 'default' => __( 'Scan the dynamic VietQR. Your course order is confirmed automatically when the transfer arrives.', 'learnpress-monapay' ), 'type' => 'textarea' ),
			array( 'title' => __( 'Client ID', 'learnpress-monapay' ), 'id' => '[client_id]', 'type' => 'text' ),
			array( 'title' => __( 'Client Secret', 'learnpress-monapay' ), 'id' => '[client_secret]', 'type' => 'password' ),
			array( 'title' => __( 'Account number', 'learnpress-monapay' ), 'id' => '[owner_number]', 'type' => 'text' ),
			array( 'title' => __( 'Account owner type', 'learnpress-monapay' ), 'id' => '[owner_type]', 'default' => 'ORG', 'type' => 'select', 'options' => array( 'ORG' => __( 'Organization (ORG)', 'learnpress-monapay' ), 'PER' => __( 'Individual (PER)', 'learnpress-monapay' ) ) ),
			array( 'title' => __( 'Merchant ID', 'learnpress-monapay' ), 'id' => '[merchant_id]', 'type' => 'text' ),
			array( 'title' => __( 'Terminal ID', 'learnpress-monapay' ), 'id' => '[terminal_id]', 'type' => 'text' ),
			array( 'title' => __( 'Virtual account prefix', 'learnpress-monapay' ), 'id' => '[virtual_account_prefix]', 'type' => 'text', 'desc' => __( 'The ACB/MONA Pay virtualAccountPrefix, up to 10 characters.', 'learnpress-monapay' ) ),
			array( 'title' => __( 'Beneficiary name', 'learnpress-monapay' ), 'id' => '[beneficiary_name]', 'type' => 'text' ),
			array( 'title' => __( 'Webhook Secret', 'learnpress-monapay' ), 'id' => '[webhook_secret]', 'type' => 'password', 'desc' => sprintf( __( 'Use HMAC_SHA256 and this callback URL: %s', 'learnpress-monapay' ), '<code>' . esc_html( $webhook_url ) . '</code>' ) ),
			array( 'type' => 'sectionend' ),
		);
	}

	/** Front-end payment method description. */
	public function get_payment_form() {
		return wp_kses_post( wpautop( $this->description ) );
	}

	/** Only expose a fully configured VND payment method. */
	public function is_enabled() {
		if ( ! parent::is_enabled() || 'VND' !== strtoupper( (string) learn_press_get_currency() ) ) {
			return false;
		}
		foreach ( array( 'client_id', 'client_secret', 'owner_number', 'merchant_id', 'terminal_id', 'virtual_account_prefix', 'beneficiary_name', 'webhook_secret' ) as $key ) {
			if ( '' === trim( (string) $this->setting( $key, '' ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Generate the order-scoped VietQR and move the order to processing.
	 *
	 * @param int $order_id LearnPress order ID.
	 * @return array
	 * @throws Exception When the order or API response is invalid.
	 */
	public function process_payment( $order_id ) {
		$order = learn_press_get_order( absint( $order_id ) );
		if ( ! $order || 'monapay' !== (string) $order->get_payment_method() ) {
			throw new Exception( esc_html__( 'Invalid LearnPress order for MONA Pay.', 'learnpress-monapay' ) );
		}

		$amount = (int) round( (float) $order->get_total() );
		if ( $amount <= 0 || $amount > 1000000000 ) {
			throw new Exception( esc_html__( 'The course order amount is outside MONA Pay limits.', 'learnpress-monapay' ) );
		}

		$qr_url = (string) get_post_meta( $order->get_id(), '_monapay_qr_image_url', true );
		if ( '' === $qr_url ) {
			try {
				$client = LearnPress_MONAPay_Client::create( $this->api_settings() );
				$payload = array(
					'ownerNumber'          => $this->clean_setting( 'owner_number' ),
					'ownerType'            => $this->owner_type(),
					'merchantId'           => $this->clean_setting( 'merchant_id' ),
					'terminalId'           => $this->clean_setting( 'terminal_id' ),
					'orderId'              => 'LP' . $order->get_id(),
					'virtualAccountPrefix' => substr( $this->clean_setting( 'virtual_account_prefix' ), 0, 10 ),
					'beneficiaryName'      => substr( $this->clean_setting( 'beneficiary_name' ), 0, 100 ),
					'amount'               => $amount,
					'description'          => 'LP' . $order->get_id(),
				);
				if ( method_exists( $order, 'get_checkout_email' ) && is_email( $order->get_checkout_email() ) ) {
					$payload['payer_email'] = sanitize_email( $order->get_checkout_email() );
				}
				$data = $client->qr->generate( $payload );
			} catch ( Throwable $exception ) {
				throw new Exception( esc_html( $exception->getMessage() ) );
			}

			$qr_url = isset( $data['qr_image_url'] ) ? esc_url_raw( $data['qr_image_url'] ) : '';
			if ( ! wp_http_validate_url( $qr_url ) || 0 !== strpos( $qr_url, 'https://' ) ) {
				throw new Exception( esc_html__( 'MONA Pay returned an invalid QR image URL.', 'learnpress-monapay' ) );
			}

			update_post_meta( $order->get_id(), '_monapay_qr_image_url', $qr_url );
			update_post_meta( $order->get_id(), '_monapay_order_code', 'LP' . $order->get_id() );
			if ( ! empty( $data['id'] ) ) {
				update_post_meta( $order->get_id(), '_monapay_qr_id', sanitize_text_field( $data['id'] ) );
			}
			if ( ! empty( $data['virtual_account_number'] ) ) {
				update_post_meta( $order->get_id(), '_monapay_virtual_account_number', sanitize_text_field( $data['virtual_account_number'] ) );
			}
		}

		$order->update_status( LP_ORDER_PROCESSING );
		LearnPress::instance()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/** Render the dynamic QR on the LearnPress order-received screen. */
	public function render_payment_box( $order ) {
		if ( ! is_object( $order ) || 'monapay' !== (string) $order->get_payment_method() || $order->has_status( 'completed' ) ) {
			return;
		}
		$qr_url = (string) get_post_meta( $order->get_id(), '_monapay_qr_image_url', true );
		if ( '' === $qr_url ) {
			return;
		}
		$account = (string) get_post_meta( $order->get_id(), '_monapay_virtual_account_number', true );
		?>
		<section class="learnpress-monapay-payment" style="border:1px solid #dcdcde;border-radius:8px;padding:24px;margin:24px 0;text-align:center">
			<h2><?php esc_html_e( 'Scan VietQR to pay', 'learnpress-monapay' ); ?></h2>
			<p><?php esc_html_e( 'Please verify all payment information before making the transfer.', 'learnpress-monapay' ); ?></p>
			<p style="font-size:24px;font-weight:700"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>
			<p><img src="<?php echo esc_url( $qr_url ); ?>" width="320" height="320" alt="<?php esc_attr_e( 'VietQR for this course order', 'learnpress-monapay' ); ?>" style="display:block;max-width:100%;height:auto;margin:16px auto" /></p>
			<p>
				<?php if ( '' !== $account ) : ?><strong><?php esc_html_e( 'Virtual account:', 'learnpress-monapay' ); ?></strong> <?php echo esc_html( $account ); ?><br /><?php endif; ?>
				<strong><?php esc_html_e( 'Beneficiary:', 'learnpress-monapay' ); ?></strong> <?php echo esc_html( $this->clean_setting( 'beneficiary_name' ) ); ?><br />
				<strong><?php esc_html_e( 'Transfer content:', 'learnpress-monapay' ); ?></strong> <?php echo esc_html( 'LP' . $order->get_id() ); ?>
			</p>
			<p><?php esc_html_e( 'The order will complete automatically and course enrollment will be granted after the bank transfer is confirmed.', 'learnpress-monapay' ); ?></p>
		</section>
		<?php
	}

	/** Read a gateway setting. */
	private function setting( $key, $default = '' ) {
		return $this->settings->get( $key, $default );
	}

	/** Read and sanitize a text setting at its use boundary. */
	private function clean_setting( $key ) {
		return sanitize_text_field( (string) $this->setting( $key, '' ) );
	}

	/** Restrict ownerType to values in the MONA Pay schema. */
	private function owner_type() {
		$value = $this->clean_setting( 'owner_type' );
		return in_array( $value, array( 'ORG', 'PER' ), true ) ? $value : 'ORG';
	}

	/** Return the credential subset used by the official SDK. */
	private function api_settings() {
		return array(
			'client_id'    => $this->clean_setting( 'client_id' ),
			'client_secret' => trim( (string) $this->setting( 'client_secret', '' ) ),
		);
	}
}
