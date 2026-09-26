<?php

use PHPUnit\Framework\TestCase;

final class LearnPress_MONAPay_Mock_Order {
	private $id;
	private $total;
	private $completed;

	public function __construct( $id, $total, $completed = false ) {
		$this->id = $id;
		$this->total = $total;
		$this->completed = $completed;
	}
	public function get_id() { return $this->id; }
	public function get_total() { return $this->total; }
	public function has_status( $status ) { return 'completed' === $status && $this->completed; }
}

final class PaymentMatchingTest extends TestCase {
	public function test_parses_only_standalone_lp_order_codes(): void {
		$this->assertSame( 123, learnpress_monapay_parse_order_id( 'LP123' ) );
		$this->assertSame( 10234, learnpress_monapay_parse_order_id( 'Hoc phi LP10234 tai ACB' ) );
		$this->assertSame( 42, learnpress_monapay_parse_order_id( 'thanh toan lp # 42' ) );
		$this->assertNull( learnpress_monapay_parse_order_id( 'HELP123' ) );
		$this->assertNull( learnpress_monapay_parse_order_id( 'LP0' ) );
	}

	public function test_matches_order_content_and_sufficient_amount(): void {
		$order = new LearnPress_MONAPay_Mock_Order( 123, 250000 );
		$payload = array( 'amount' => 250000, 'transaction_code' => 'FT001' );
		$this->assertSame( 'matched', learnpress_monapay_match_payment( $order, $payload, 123 ) );
		$payload['amount'] = 250001;
		$this->assertSame( 'matched', learnpress_monapay_match_payment( $order, $payload, 123 ) );
	}

	public function test_rejects_underpayment_and_wrong_content(): void {
		$order = new LearnPress_MONAPay_Mock_Order( 123, 250000 );
		$this->assertSame( 'underpaid', learnpress_monapay_match_payment( $order, array( 'amount' => 249999, 'transaction_code' => 'FT002' ), 123 ) );
		$this->assertSame( 'wrong_order', learnpress_monapay_match_payment( $order, array( 'amount' => 250000, 'transaction_code' => 'FT003' ), 124 ) );
	}

	public function test_completed_order_is_idempotent(): void {
		$order = new LearnPress_MONAPay_Mock_Order( 123, 250000, true );
		$this->assertSame( 'already_paid', learnpress_monapay_match_payment( $order, array( 'amount' => 250000, 'transaction_code' => 'FT004' ), 123 ) );
	}
}

