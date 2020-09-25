<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Rede\Environment;
use Rede\eRede;
use Rede\Store;
use Rede\Transaction;

class WC_Rede_API {
	protected $gateway;

	private $environment;
	private $store;
	private $capture = true;
	private $soft_descriptor;
	private $partner_module;
	private $partner_gateway;

	public function __construct( $gateway = null ) {
		$pv    = $gateway->pv;
		$token = $gateway->token;

		if ( $gateway->environment == 'test' ) {
			$environment = Environment::sandbox();
		} else {
			$environment = Environment::production();
		}

		$this->gateway         = $gateway;
		$this->capture         = (bool) $gateway->auto_capture;
		$this->soft_descriptor = $gateway->soft_descriptor;
		$this->partner_gateway = $gateway->partner_gateway;
		$this->partner_module  = $gateway->partner_module;
		$this->store           = new Store( $pv, $token, $environment );
	}

	/**
	 * @param $id
	 * @param $amount
	 * @param int $installments
	 * @param array $credit_card_data
	 *
	 * @return Transaction|StdClass
	 */
	public function do_transaction_request(
		$id,
		$amount,
		$installments = 1,
		$credit_card_data = array()
	) {
		$transaction = ( new Transaction( $amount, $id ) )->creditCard(
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

		$transaction = ( new eRede( $this->store, $this->get_logger() ) )->create( $transaction );

		return $transaction;
	}

	protected function get_logger() {
		$logger = new Logger( 'rede' );
		$logger->pushHandler( new StreamHandler( WP_CONTENT_DIR . '/uploads/wc-logs/rede.log', Logger::DEBUG ) );
		$logger->info( 'Log Rede' );

		return $logger;
	}

	public function do_transaction_consultation( $tid ) {
		return ( new eRede( $this->store, $this->get_logger() ) )->get( $tid );
	}

	public function do_transaction_cancellation( $tid, $amount = 0 ) {
		$transaction = ( new eRede( $this->store, $this->get_logger() ) )->cancel( ( new Transaction( $amount ) )->setTid( $tid ) );

		return $transaction;
	}

	public function do_transaction_capture( $tid, $amount ) {
		$transaction = ( new eRede( $this->store, $this->get_logger() ) )->capture( ( new Transaction( $amount ) )->setTid( $tid ) );

		return $transaction;
	}
}
