<?php

use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase {
	private $timestamp = '1756355400';
	private $secret = '0123456789abcdef0123456789abcdef';
	private $body = '{"amount":2500000,"description":"LP123","transaction_code":"FT26240001234","account_number":"MONA00000123","type":"income"}';

	public function test_accepts_valid_raw_body_signature(): void {
		$signature = 'sha256=' . hash_hmac( 'sha256', $this->timestamp . '.' . $this->body, $this->secret );
		$this->assertTrue( learnpress_monapay_verify_signature( $this->body, $this->timestamp, $signature, $this->secret, 1756355400 ) );
	}

	public function test_rejects_tampered_body_and_signature(): void {
		$signature = 'sha256=' . hash_hmac( 'sha256', $this->timestamp . '.' . $this->body, $this->secret );
		$this->assertFalse( learnpress_monapay_verify_signature( $this->body . ' ', $this->timestamp, $signature, $this->secret, 1756355400 ) );
		$this->assertFalse( learnpress_monapay_verify_signature( $this->body, $this->timestamp, 'sha256=' . str_repeat( '0', 64 ), $this->secret, 1756355400 ) );
	}

	public function test_rejects_replay_outside_five_minutes(): void {
		$signature = 'sha256=' . hash_hmac( 'sha256', $this->timestamp . '.' . $this->body, $this->secret );
		$this->assertTrue( learnpress_monapay_verify_signature( $this->body, $this->timestamp, $signature, $this->secret, 1756355700 ) );
		$this->assertFalse( learnpress_monapay_verify_signature( $this->body, $this->timestamp, $signature, $this->secret, 1756355701 ) );
		$this->assertFalse( learnpress_monapay_verify_signature( $this->body, $this->timestamp, $signature, $this->secret, 1756355099 ) );
	}

	public function test_rejects_noncanonical_headers(): void {
		$signature = 'sha256=' . hash_hmac( 'sha256', $this->timestamp . '.' . $this->body, $this->secret );
		$this->assertFalse( learnpress_monapay_verify_signature( $this->body, '1756355400.0', $signature, $this->secret, 1756355400 ) );
		$this->assertFalse( learnpress_monapay_verify_signature( $this->body, $this->timestamp, strtoupper( $signature ), $this->secret, 1756355400 ) );
		$this->assertFalse( learnpress_monapay_verify_signature( $this->body, $this->timestamp, $signature, '', 1756355400 ) );
	}
}

