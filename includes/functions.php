<?php
/**
 * Pure matching and signature helpers.
 *
 * @package LearnPress_MONAPay
 */

defined( 'ABSPATH' ) || defined( 'LEARNPRESS_MONAPAY_TESTING' ) || exit;

if ( ! function_exists( 'learnpress_monapay_verify_signature' ) ) {
	/**
	 * Verify HMAC-SHA256 over "timestamp.raw_body" with a five-minute window.
	 *
	 * @param string   $raw_body  Unmodified HTTP request body.
	 * @param string   $timestamp X-Mona-Timestamp value.
	 * @param string   $signature X-Mona-Signature value.
	 * @param string   $secret    Shared webhook secret.
	 * @param int|null $now       Injectable current time for tests.
	 * @param int      $tolerance Allowed drift in seconds.
	 * @return bool
	 */
	function learnpress_monapay_verify_signature( $raw_body, $timestamp, $signature, $secret, $now = null, $tolerance = 300 ) {
		if ( '' === $secret || ! is_string( $timestamp ) || ! preg_match( '/^[0-9]{1,12}$/', $timestamp ) ) {
			return false;
		}

		if ( ! is_string( $signature ) || ! preg_match( '/^sha256=[a-f0-9]{64}$/', $signature ) ) {
			return false;
		}

		$current_time = null === $now ? time() : (int) $now;
		if ( abs( $current_time - (int) $timestamp ) > (int) $tolerance ) {
			return false;
		}

		$expected = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $secret );
		return hash_equals( $expected, $signature );
	}
}

if ( ! function_exists( 'learnpress_monapay_parse_order_id' ) ) {
	/**
	 * Extract an LP order ID without accepting LP embedded in another code.
	 *
	 * @param string $description Bank transfer description.
	 * @return int|null
	 */
	function learnpress_monapay_parse_order_id( $description ) {
		if ( ! is_string( $description ) || ! preg_match( '/(?:^|[^A-Z0-9])LP\s*#?\s*([0-9]+)(?:$|[^0-9])/i', $description, $matches ) ) {
			return null;
		}

		$order_id = (int) $matches[1];
		return $order_id > 0 ? $order_id : null;
	}
}

if ( ! function_exists( 'learnpress_monapay_match_payment' ) ) {
	/**
	 * Validate the immutable fields used to match a transfer to an order.
	 *
	 * Overpayments are accepted, matching the production WooCommerce integration;
	 * underpayments never complete an order.
	 *
	 * @param object $order       LearnPress order or compatible test double.
	 * @param array  $payload     Flat MONA Pay transaction payload.
	 * @param int    $description_order_id Order ID parsed from description.
	 * @return string matched, underpaid, wrong_order, invalid, or already_paid.
	 */
	function learnpress_monapay_match_payment( $order, $payload, $description_order_id ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) || ! method_exists( $order, 'get_total' ) ) {
			return 'invalid';
		}
		if ( ! is_array( $payload ) || ! isset( $payload['amount'], $payload['transaction_code'] ) || ! is_numeric( $payload['amount'] ) ) {
			return 'invalid';
		}
		if ( (int) $order->get_id() !== (int) $description_order_id || (int) $description_order_id <= 0 ) {
			return 'wrong_order';
		}
		if ( (int) round( (float) $payload['amount'] ) < (int) round( (float) $order->get_total() ) ) {
			return 'underpaid';
		}
		if ( method_exists( $order, 'has_status' ) && $order->has_status( 'completed' ) ) {
			return 'already_paid';
		}

		return 'matched';
	}
}

