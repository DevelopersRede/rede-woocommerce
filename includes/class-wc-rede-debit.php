<?php

class WC_Rede_Debit extends WC_Rede_Abstract {
	public function __construct() {
		$this->id                 = 'rede_debit';
		$this->has_fields         = true;
		$this->method_title       = 'Cartão de débito';
		$this->method_description = 'Habilita e configura pagamentos com cartão de débito com a Rede';
		$this->supports           = [
			'products',
			'refunds'
		];

		parent::__construct();

		$this->init_form_fields();
		$this->init_settings();

		$this->api = new WC_Rede_API( $this );

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
				'label'   => 'Habilita pagamento com cartão de débito',
				'default' => 'yes'
			],
			'title'   => [
				'title'   => 'Título',
				'type'    => 'text',
				'default' => 'Cartão de débito'
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
		$card_number = isset( $_POST['rede_debit_number'] ) ? sanitize_text_field( $_POST['rede_debit_number'] ) : '';
		$valid       = true;

		if ( $this->debug ) {
			$this->get_logger()->info( "Iniciando pagamento por débito" );
		}

		if ( $valid ) {
			$valid = $this->validate_card_number( $card_number );
		}

		if ( $valid ) {
			$valid = $this->validate_card_fields( $_POST );
		}

		if ( $valid ) {
			$expiration = explode( "/", $_POST['rede_debit_expiry'] );

			$card_data = [
				'card_number'           => preg_replace( '/[^\d]/', '', $_POST['rede_debit_number'] ),
				'card_expiration_month' => $expiration[0],
				'card_expiration_year'  => $expiration[1],
				'card_cvv'              => $_POST['rede_debit_cvc'],
				'card_holder'           => $_POST['rede_debit_holder_name']
			];

			try {
				$order_id      = $order->get_id();
				$amount        = $order->get_total();
				$transaction   = $this->api->debug( $this->debug )->do_debit_request( $order_id + time(), $amount, $card_data, $this->get_return_url( $order ) );
				$authorization = $transaction->getAuthorization();
				$brand         = $transaction->getBrand();

				update_post_meta( $order_id, '_wc_rede_transaction_bin', $transaction->getCardBin() );
				update_post_meta( $order_id, '_wc_rede_transaction_last4', $transaction->getLast4() );
				update_post_meta( $order_id, '_wc_rede_transaction_holder', $transaction->getCardHolderName() );
				update_post_meta( $order_id, '_wc_rede_transaction_expiration', sprintf( '%02d/%04d', $expiration[0], $expiration[1] ) );

				if ( ! is_null( $authorization ) ) {
					update_post_meta( $order_id, '_wc_rede_transaction_authorization_status', $authorization->getStatus() );
				}

				update_post_meta( $order_id, '_wc_rede_brand_tid', $transaction->getBrandTid() );

				if ( ! is_null( $brand ) ) {
					update_post_meta( $order_id, '_wc_rede_brand_name', $brand->getName() );
					update_post_meta( $order_id, '_wc_rede_brand_return_code', $brand->getReturnCode() );
					update_post_meta( $order_id, '_wc_rede_brand_return_message', $brand->getReturnMessage() );
				}

				update_post_meta( $order_id, '_wc_rede_transaction_environment', $this->environment );

				$this->process_order_status( $order, $transaction, '' );

				if ( $valid ) {
					if ( $this->debug ) {
						$this->get_logger()->info( 'Redirecionando para autenticação' );
					}

					return [
						'result'   => 'success',
						'redirect' => $transaction->getThreeDSecure()->getUrl()
					];
				}
			} catch ( Exception $e ) {
				if ( $this->debug ) {
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

	/**
	 * Get the return url (thank you page).
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return string
	 */
	public function get_return_url( $order = null ) {
		if ( $order ) {
			$return_url = $order->get_checkout_order_received_url();
		} else {
			$return_url = wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() );
		}

		return apply_filters( 'woocommerce_get_return_url', $return_url, $order );
	}

	protected function get_checkout_form( $order_total = 0 ) {
		$wc_get_template = 'woocommerce_get_template';

		if ( function_exists( 'wc_get_template' ) ) {
			$wc_get_template = 'wc_get_template';
		}

		$wc_get_template( 'debit-card/rede-payment-form.php', [], 'woocommerce/rede/', WC_Rede::get_templates_path() );
	}
}
