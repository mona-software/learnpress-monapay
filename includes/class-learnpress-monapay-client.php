<?php
/**
 * WordPress transport adapter for the official monapay/php-sdk.
 *
 * @package LearnPress_MONAPay
 */

defined( 'ABSPATH' ) || exit;

use MonaPay\Client;

final class LearnPress_MONAPay_Client {
	/**
	 * Create the official SDK client with WordPress networking.
	 *
	 * @param array $settings Sanitized gateway settings.
	 * @return Client
	 */
	public static function create( $settings ) {
		$client_id = isset( $settings['client_id'] ) ? sanitize_text_field( $settings['client_id'] ) : '';
		$client_secret = isset( $settings['client_secret'] ) ? trim( (string) $settings['client_secret'] ) : '';

		return new Client(
			'',
			'',
			$client_secret,
			'https://api.monapay.vn',
			array( __CLASS__, 'transport' ),
			20,
			$client_id
		);
	}

	/**
	 * Adapt an SDK request to wp_remote_request().
	 *
	 * @param array $request SDK transport request.
	 * @return array{status:int,body:string}
	 * @throws RuntimeException On a WordPress transport error.
	 */
	public static function transport( $request ) {
		$url = isset( $request['url'] ) ? esc_url_raw( (string) $request['url'] ) : '';
		if ( ! wp_http_validate_url( $url ) || 0 !== strpos( $url, 'https://' ) ) {
			throw new RuntimeException( esc_html__( 'MONA Pay API URL must be a valid HTTPS URL.', 'learnpress-monapay' ) );
		}

		$args = array(
			'method'      => strtoupper( sanitize_key( $request['method'] ) ),
			'timeout'     => min( 30, max( 1, (int) $request['timeout'] ) ),
			'redirection' => 2,
			'sslverify'   => true,
			'headers'     => is_array( $request['headers'] ) ? $request['headers'] : array(),
		);
		if ( null !== $request['body'] ) {
			$args['body'] = (string) $request['body'];
		}

		$response = wp_safe_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( esc_html( $response->get_error_message() ) );
		}

		return array(
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'body'   => (string) wp_remote_retrieve_body( $response ),
		);
	}
}
