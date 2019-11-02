<?php

class WC_Rede_API {

	protected $gateway;

	private $environment;
	private $store;
	private $capture = true;
	private $soft_descriptor;
	private $partner_module;
	private $partner_gateway;

	public function __construct( $gateway = null ) {
		$pv = $gateway->pv;
		$token = $gateway->token;

		if ( $gateway->environment == 'test' ) {
			$environment = \Rede\Environment::sandbox();
		} else {
			$environment = \Rede\Environment::production();
		}

		$this->gateway = $gateway;
		$this->capture = (bool) $gateway->auto_capture;
		$this->soft_descriptor = $gateway->soft_descriptor;
		$this->partner_gateway = $gateway->partner_gateway;
		$this->partner_module = $gateway->partner_module;
		$this->store = new \Rede\Store( $pv, $token, $environment );
	}

	/**
	 * @param $id
	 * @param $amount
	 * @param int $installments
	 * @param array $credit_card_data
	 * @return \Rede\Transaction|StdClass
	 */
	public function do_transaction_request(
		$id,
		$amount,
		$installments = 1,
		$credit_card_data = array()
	) {
		$transaction = ( new \Rede\Transaction( $amount, $id ) )->creditCard(
			$credit_card_data['card_number'],
			$credit_card_data['card_cvv'],
			$credit_card_data['card_expiration_month'],
			$credit_card_data['card_expiration_year'],
			$credit_card_data['card_holder']
		)->capture( $this->capture );

		if ( $installments > 1 ) {
			$transaction->setInstallments( $installments );
		}

		if ( ! empty( $this->soft_descriptor ) ) {
			$transaction->setSoftDescriptor( $this->soft_descriptor );
		}

		if ( ! empty( $this->partner_module ) && ! empty( $this->partner_gateway ) ) {
			$transaction->additional( $this->partner_gateway, $this->partner_module );
		}

		$transaction = ( new \Rede\eRede( $this->store, $this->get_logger() ) )->create( $transaction );

		return $transaction;
	}

	protected function get_logger() {
		$logger = new \Monolog\Logger( 'rede' );
		$logger->pushHandler( new \Monolog\Handler\StreamHandler( WP_CONTENT_DIR . '/uploads/wc-logs/rede.log', \Monolog\Logger::DEBUG ) );
		$logger->info( 'Log Rede' );

		return $logger;
	}

	public function do_transaction_consultation( $tid ) {
		return ( new \Rede\eRede( $this->store, $this->get_logger() ) )->get( $tid );
	}

	public function do_transaction_cancellation( $tid, $amount = 0 ) {
		$transaction = ( new \Rede\eRede( $this->store, $this->get_logger() ) )->cancel( ( new \Rede\Transaction( $amount ) )->setTid( $tid ) );

		return $transaction;
	}

	public function do_transaction_capture( $tid, $amount ) {
		$transaction = ( new \Rede\eRede( $this->store, $this->get_logger() ) )->capture( ( new \Rede\Transaction( $amount ) )->setTid( $tid ) );

		return $transaction;
	}
}
