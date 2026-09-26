<?php
/**
 * Signed MONA Pay webhook receiver for LearnPress.
 *
 * @package LearnPress_MONAPay
 */

defined( 'ABSPATH' ) || exit;

final class LearnPress_MONAPay_Webhook {
	/** Register hooks. */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/** Register POST /wp-json/learnpress-monapay/v1/webhook. */
	public function register_route() {
		register_rest_route(
			'learnpress-monapay/v1',
			'/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Verify, match, and complete one flat incoming transaction.
	 *
	 * A WordPress nonce is intentionally not used for this server-to-server route;
	 * the raw-body HMAC and five-minute timestamp are its authentication mechanism.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle( $request ) {
		$raw_body  = (string) $request->get_body();
		$timestamp = (string) $request->get_header( 'x-mona-timestamp' );
		$signature = (string) $request->get_header( 'x-mona-signature' );
		$secret    = $this->webhook_secret();

		if ( '' === $secret ) {
			return $this->response( 503, false, 'Webhook is not configured.' );
		}
		if ( ! learnpress_monapay_verify_signature( $raw_body, $timestamp, $signature, $secret ) ) {
			return $this->response( 401, false, 'Invalid signature.' );
		}

		$payload = json_decode( $raw_body, true );
		if ( ! is_array( $payload ) || isset( $payload['data'] ) ) {
			return $this->response( 400, false, 'Expected a flat JSON payload.' );
		}

		$transaction_code = isset( $payload['transaction_code'] ) ? sanitize_text_field( (string) $payload['transaction_code'] ) : '';
		if ( 'DUMMY123' === $transaction_code ) {
			return $this->response( 200, true, 'Valid test webhook.' );
		}

		if ( ! isset( $payload['amount'], $payload['description'], $payload['account_number'] ) || '' === $transaction_code || ! is_numeric( $payload['amount'] ) ) {
			return $this->response( 400, false, 'Invalid transaction payload.' );
		}
		if ( isset( $payload['type'] ) && 'income' !== (string) $payload['type'] ) {
			return $this->response( 400, false, 'Only income transactions are accepted.' );
		}

		$order_id = learnpress_monapay_parse_order_id( (string) $payload['description'] );
		$order    = $order_id ? learn_press_get_order( $order_id ) : false;
		if ( ! $order || 'monapay' !== (string) $order->get_payment_method() ) {
			return $this->response( 200, true, 'Transaction received; no matching order.' );
		}

		$result = learnpress_monapay_match_payment( $order, $payload, $order_id );
		if ( 'underpaid' === $result ) {
			return $this->response( 200, true, 'Transaction received; amount is insufficient.' );
		}
		if ( 'already_paid' === $result ) {
			return $this->response( 200, true, 'Transaction was already processed.' );
		}
		if ( 'matched' !== $result ) {
			return $this->response( 400, false, 'Transaction does not match the order.' );
		}

		$codes = get_post_meta( $order->get_id(), '_monapay_transaction_codes', true );
		$codes = is_array( $codes ) ? array_map( 'strval', $codes ) : array();
		if ( in_array( $transaction_code, $codes, true ) ) {
			return $this->response( 200, true, 'Transaction was already processed.' );
		}

		$codes[] = $transaction_code;
		update_post_meta( $order->get_id(), '_monapay_transaction_codes', array_values( array_unique( $codes ) ) );
		$order->payment_complete( $transaction_code );

		return $this->response( 200, true, 'Payment confirmed and course enrollment activated.' );
	}

	/** Read the secret from LearnPress' gateway settings group. */
	private function webhook_secret() {
		$secret = LP_Settings::instance()->get( 'monapay.webhook_secret', '' );
		if ( '' !== (string) $secret ) {
			return trim( (string) $secret );
		}
		$legacy = get_option( 'learn_press_monapay', array() );
		return is_array( $legacy ) && isset( $legacy['webhook_secret'] ) ? trim( (string) $legacy['webhook_secret'] ) : '';
	}

	/** Build a consistent JSON response. */
	private function response( $status, $success, $message ) {
		return new WP_REST_Response(
			array(
				'success' => (bool) $success,
				'message' => sanitize_text_field( $message ),
				'data'    => null,
			),
			(int) $status
		);
	}
}

