<?php

class WC_Rede_Credit extends WC_Rede_Abstract {
	public $auto_capture = true;
	public $min_parcels_value = 0;
	public $max_parcels_number = 12;

	public function __construct() {
		$this->id                 = 'rede_credit';
		$this->method_title       = 'Cartão de crédito';
		$this->method_description = 'Habilita e configura pagamentos com cartão de crédito com a Rede';
		$this->auto_capture       = $this->get_option( 'auto_capture' );
		$this->max_parcels_number = $this->get_option( 'max_parcels_number' );
		$this->min_parcels_value  = $this->get_option( 'min_parcels_value' );
		$this->partner_module     = $this->get_option( 'module' );
		$this->partner_gateway    = $this->get_option( 'gateway' );
		$this->supports           = [
			'products',
			'refunds'
		];

		parent::__construct();

		$this->init_form_fields();
		$this->init_settings();

		$this->api = new WC_Rede_API( $this );

		if ( ! $this->auto_capture ) {
			add_action( 'woocommerce_order_status_completed', [
				$this,
				'process_capture'
			] );
		}

		add_action( 'woocommerce_order_status_cancelled', [
			$this,
			'process_refund'
		] );

		add_action( 'woocommerce_order_status_refunded', [
			$this,
			'process_refund'
		] );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [
			$this,
			'process_admin_options'
		] );

		add_action( 'woocommerce_thankyou_' . $this->id, [
			$this,
			'thankyou_page'
		] );

		add_filter( 'woocommerce_get_order_item_totals', [
			$this,
			'order_items_payment_details'
		], 10, 2 );

		add_action( 'woocommerce_admin_order_data_after_billing_address', [
			$this,
			'display_meta'
		], 10, 1 );
	}

	public function init_form_fields() {
		$this->form_fields = [
			'enabled' => [
				'title'   => 'Habilita/Desabilita',
				'type'    => 'checkbox',
				'label'   => 'Habilita pagamento com cartão de crédito',
				'default' => 'yes'
			],
			'title'   => [
				'title'   => 'Título',
				'type'    => 'text',
				'default' => 'Cartão de crédito'
			],

			'rede'        => [
				'title' => 'Configuração geral',
				'type'  => 'title'
			],
			'environment' => [
				'title'       => 'Ambiente',
				'type'        => 'select',
				'description' => 'Escolha o ambiente',
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => 'test',
				'options'     => [
					'test'       => 'Testes',
					'production' => 'Produção'
				]
			],
			'pv'          => [
				'title'   => 'PV',
				'type'    => 'text',
				'default' => ''
			],
			'token'       => [
				'title'   => 'Token',
				'type'    => 'text',
				'default' => ''
			],

			'soft_descriptor' => [
				'title'   => 'Soft Descriptor',
				'type'    => 'text',
				'default' => ''
			],

			'credit_options' => [
				'title' => 'Configuracões de cartão de crédito',
				'type'  => 'title'
			],

			'auto_capture'       => [
				'title'   => 'Autorização e captura',
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => '2',
				'options' => [
					'1' => 'Autorize e capture automaticamente',
					'0' => 'Apenas autorize'
				]
			],
			'min_parcels_value'  => [
				'title'   => 'Valor da menor parcela',
				'type'    => 'text',
				'default' => '0'
			],
			'max_parcels_number' => [
				'title'   => 'Máximo de parcelas',
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => '12',
				'options' => [
					'1'  => '1x',
					'2'  => '2x',
					'3'  => '3x',
					'4'  => '4x',
					'5'  => '5x',
					'6'  => '6x',
					'7'  => '7x',
					'8'  => '8x',
					'9'  => '9x',
					'10' => '10x',
					'11' => '11x',
					'12' => '12x'
				]
			],

			'partners' => [
				'title' => 'Configuracões para parceiros',
				'type'  => 'title'
			],
			'module'   => [
				'title'   => 'ID Módulo',
				'type'    => 'text',
				'default' => ''
			],
			'gateway'  => [
				'title'   => 'ID Gateway',
				'type'    => 'text',
				'default' => ''
			],

			'developers' => [
				'title' => 'Configuracões para desenvolvedores',
				'type'  => 'title'
			],

			'debug' => [
				'title'   => 'Depuração',
				'type'    => 'checkbox',
				'label'   => 'Ativa logs de depuração',
				'default' => 'no'
			]
		];
	}

	public function get_installment_text( $quantity, $order_total ) {
		$installments = $this->get_installments( $order_total );

		if ( isset( $installments[ $quantity - 1 ] ) ) {
			return $installments[ $quantity - 1 ]['label'];
		}

		if ( isset( $installments[ $quantity ] ) ) {
			return $installments[ $quantity ]['label'];
		}

		return $quantity;
	}

	public function get_installments( $order_total = 0 ) {
		$installments = [];
		$min_value    = $this->min_parcels_value;
		$max_parcels  = $this->max_parcels_number;

		for ( $i = 1; $i <= $max_parcels; ++ $i ) {
			if ($order_total / $i < $min_value) {
				break;
			}

			$label = sprintf( '%dx de R$ %.02f', $i, $order_total / $i );

			if ( $i == 1 ) {
				$label = sprintf( 'R$ %.02f à vista', $order_total );
			}

			$installments[] = [
				'num'   => $i,
				'label' => $label
			];
		}

		if ( count( $installments ) == 0 ) {
			$installments[] = [
				'num'   => 1,
				'label' => sprintf( 'R$ %.02f à vista', $order_total )
			];
		}

		return $installments;
	}

	public function checkout_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( ! $this->is_available() ) {
			return;
		}

		wp_enqueue_style( 'wc-rede-checkout-webservice' );
	}

	public function process_payment( $order_id ) {
		$order       = new WC_Order( $order_id );
		$card_number = isset( $_POST['rede_credit_number'] ) ? sanitize_text_field( $_POST['rede_credit_number'] ) : '';
		$valid       = true;

		if ($this->debug) {
			$this->get_logger()->info( "Iniciando pagamento por crédito" );
		}

		if ( $valid ) {
			$valid = $this->validate_card_fields( $_POST );
		}

		if ( $valid ) {
			$valid = $this->validate_card_number( $card_number );
		}

		if ( $valid ) {
			$valid = $this->validate_installments( $_POST, $order->get_total() );
		}

		if ( $valid ) {
			$installments = isset( $_POST['rede_credit_installments'] ) ? absint( $_POST['rede_credit_installments'] ) : 1;
			$expiration   = explode( '/', $_POST['rede_credit_expiry'] );
			$card_data    = [
				'card_number'           => preg_replace( '/[^\d]/', '', $_POST['rede_credit_number'] ),
				'card_expiration_month' => $expiration[0],
				'card_expiration_year'  => $expiration[1],
				'card_cvv'              => $_POST['rede_credit_cvc'],
				'card_holder'           => $_POST['rede_credit_holder_name']
			];

			try {
				$order_id    = $order->get_id();
				$amount      = $order->get_total();
				$transaction = $this->api->debug( $this->debug )->do_credit_request( $order_id + time(), $amount, $installments, $card_data );

				update_post_meta( $order_id, '_transaction_id', $transaction->getTid() );
				update_post_meta( $order_id, '_wc_rede_transaction_return_code', $transaction->getReturnCode() );
				update_post_meta( $order_id, '_wc_rede_transaction_return_message', $transaction->getReturnMessage() );
				update_post_meta( $order_id, '_wc_rede_transaction_installments', $installments );
				update_post_meta( $order_id, '_wc_rede_transaction_id', $transaction->getTid() );
				update_post_meta( $order_id, '_wc_rede_transaction_refund_id', $transaction->getRefundId() );
				update_post_meta( $order_id, '_wc_rede_transaction_cancel_id', $transaction->getCancelId() );
				update_post_meta( $order_id, '_wc_rede_transaction_bin', $transaction->getCardBin() );
				update_post_meta( $order_id, '_wc_rede_transaction_last4', $transaction->getLast4() );
				update_post_meta( $order_id, '_wc_rede_transaction_nsu', $transaction->getNsu() );
				update_post_meta( $order_id, '_wc_rede_transaction_authorization_code', $transaction->getAuthorizationCode() );

				$authorization = $transaction->getAuthorization();
				$brand         = $transaction->getBrand();

				if ( ! is_null( $authorization ) ) {
					update_post_meta( $order_id, '_wc_rede_transaction_authorization_status', $authorization->getStatus() );
				}

				update_post_meta( $order_id, '_wc_rede_brand_tid', $transaction->getBrandTid() );

				if ( ! is_null( $brand ) ) {
					update_post_meta( $order_id, '_wc_rede_brand_name', $brand->getName() );
					update_post_meta( $order_id, '_wc_rede_brand_return_code', $brand->getReturnCode() );
					update_post_meta( $order_id, '_wc_rede_brand_return_message', $brand->getReturnMessage() );
				}

				update_post_meta( $order_id, '_wc_rede_transaction_holder', $transaction->getCardHolderName() );
				update_post_meta( $order_id, '_wc_rede_transaction_expiration', sprintf( '%02d/%04d', $expiration[0], $expiration[1] ) );
				update_post_meta( $order_id, '_wc_rede_transaction_environment', $this->environment );

				$this->process_order_status( $order, $transaction, '' );
			} catch ( Exception $e ) {
				if ($this->debug) {
					$this->get_logger()->error( sprintf( 'Erro no pagamento[%s]: %s', $e->getCode(), $e->getMessage() ) );
				}

				$this->add_error( 'erro no pagamento.' );
				$valid = false;
			}
		}

		if ( $valid ) {
			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			];
		} else {
			return [
				'result'   => 'fail',
				'redirect' => ''
			];
		}
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = new WC_Order( $order_id );

		if ( ! $order || ! $order->get_transaction_id() ) {
			return false;
		}

		if ( empty( $order->get_meta( '_wc_rede_transaction_canceled' ) ) ) {
			$tid    = $order->get_transaction_id();
			$amount = wc_format_decimal( $amount );

			try {
				$transaction = $this->api->debug( $this->debug )->do_transaction_cancellation( $tid, $amount );

				update_post_meta( $order_id, '_wc_rede_transaction_refund_id', $transaction->getRefundId() );
				update_post_meta( $order_id, '_wc_rede_transaction_cancel_id', $transaction->getCancelId() );
				update_post_meta( $order_id, '_wc_rede_transaction_canceled', true );

				$order->add_order_note( 'Reembolsado: ' . wc_price( $amount ) );
			} catch ( Exception $e ) {
				return new WP_Error( 'rede_refund_error', sanitize_text_field( $e->getMessage() ) );
			}

			return true;
		}

		return false;
	}

	public function process_capture( $order_id ) {
		$order = new WC_Order( $order_id );

		if ( ! $order || ! $order->get_transaction_id() ) {
			return false;
		}

		if ( empty( $order->get_meta( '_wc_rede_captured' ) ) ) {
			$tid    = $order->get_transaction_id();
			$amount = $order->get_total();

			try {
				$transaction = $this->api->debug( $this->debug )->do_transaction_capture( $tid, $amount );

				update_post_meta( $order_id, '_wc_rede_transaction_nsu', $transaction->getNsu() );
				update_post_meta( $order_id, '_wc_rede_captured', true );

				$order->add_order_note( 'Capturado' );
			} catch ( Exception $e ) {
				return new WP_Error( 'rede_capture_error', sanitize_text_field( $e->getMessage() ) );
			}

			return true;
		}

		return false;
	}

	protected function get_checkout_form( $order_total = 0 ) {
		$wc_get_template = 'woocommerce_get_template';

		if ( function_exists( 'wc_get_template' ) ) {
			$wc_get_template = 'wc_get_template';
		}

		$wc_get_template( 'credit-card/rede-payment-form.php', [
			'installments' => $this->get_installments( $order_total )
		], 'woocommerce/rede/', WC_Rede::get_templates_path() );
	}
}
