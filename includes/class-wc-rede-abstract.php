<?php


use Rede\Transaction;

abstract class WC_Rede_Abstract extends WC_Payment_Gateway {
	/**
	 * @var WC_Rede_API
	 */
	public $api;
	public $debug = false;

	public $environment;
	public $pv;
	public $token;
	public $soft_descriptor;
	public $partner_module;
	public $partner_gateway;
	public $auto_capture = false;

	public function __construct() {
		$this->title      = $this->get_option( 'title' );
		$this->has_fields = true;

		$this->environment     = $this->get_option( 'environment' );
		$this->pv              = $this->get_option( 'pv' );
		$this->token           = $this->get_option( 'token' );
		$this->soft_descriptor = $this->get_option( 'soft_descriptor' );
		$this->debug           = $this->get_option( 'debug' ) === 'yes';
	}

	public function get_valid_value( $value ) {
		return preg_replace( '/[^\d\.]+/', '', str_replace( ',', '.', $value ) );
	}

	public function get_api_return_url( $order ) {
		global $woocommerce;

		$url = $woocommerce->api_request_url( get_class( $this ) );

		return urlencode( add_query_arg( [
			'key'   => $order->order_key,
			'order' => $order->get_id()
		], $url ) );
	}

	public function get_logger() {
		if ( $this->debug ) {
			if ( class_exists( 'WC_Logger' ) ) {
				return new WC_Logger();
			} else {
				global $woocommerce;

				return $woocommerce->logger();
			}
		}
	}

	public function order_items_payment_details( $items, $order ) {
		$order_id = $order->get_id();

		if ( $this->id === $order->get_payment_method() ) {
			$tid                = get_post_meta( $order_id, '_wc_rede_transaction_id', true );
			$authorization_code = get_post_meta( $order_id, '_wc_rede_transaction_authorization_code', true );
			$installments       = get_post_meta( $order_id, '_wc_rede_transaction_installments', true );
			$last               = array_pop( $items );

			$items['payment_return']['value'] = sprintf( '<strong>ID do pedido</strong>: %s<br/>', $order_id );
			$items['payment_return']['value'] .= sprintf( '<strong>ID da transação</strong>: %s<br/>', $tid );

			if ( $installments ) {
				$items['payment_return']['value'] .= sprintf( '<strong>Parcelas</strong>: %s<br/>', $installments );
			}

			$items['payment_return']['value'] .= sprintf( '<strong>Código de autorização</strong>: %s',
				$authorization_code );

			$items[] = $last;
		}

		return $items;
	}

	public function get_payment_method_name( $slug ) {
		$methods = 'rede';

		if ( isset( $methods[ $slug ] ) ) {
			return $methods[ $slug ];
		}

		return $slug;
	}

	public function payment_fields() {
		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}

		wp_enqueue_script( 'wc-credit-card-form' );

		$this->get_checkout_form( $this->get_order_total() );
	}

	abstract protected function get_checkout_form( $order_total = 0 );

	public function get_order_total() {
		global $woocommerce;

		$order_total = 0;

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
		} else {
			$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		}

		if ( 0 < $order_id ) {
			$order       = new WC_Order( $order_id );
			$order_total = (float) $order->get_total();
		} elseif ( 0 < $woocommerce->cart->total ) {
			$order_total = (float) $woocommerce->cart->total;
		}

		return $order_total;
	}

	public function consult_order( $order, $id, $tid, $status ) {
		$transaction = $this->api->do_transaction_consultation( $tid );

		$this->process_order_status( $order, $transaction, 'verificação automática' );
	}

	public function display_meta( $order ) {
		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}
		?>
        <h3><?php $this->method_title ?></h3>
        <table>
            <tbody>
            <tr>
                <td>Ambiente</td>
                <td><?= $order->get_meta( '_wc_rede_transaction_environment' ) == 'test' ? 'Testes' : 'Produção'; ?></td>
            </tr>

            <tr>
                <td>Código de Retorno</td>
                <td><?= $order->get_meta( '_wc_rede_transaction_return_code' ); ?></td>
            </tr>

            <tr>
                <td>Mensagem de Retorno</td>
                <td><?= $order->get_meta( '_wc_rede_transaction_return_message' ); ?></td>
            </tr>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_brand_name' ) ) ) { ?>
                <tr>
                    <td>Nome da bandeira</td>
                    <td><?= $order->get_meta( '_wc_rede_brand_name' ); ?></td>
                </tr>
			<?php } ?>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_brand_return_code' ) ) ) { ?>
                <tr>
                    <td>Código de retorno da bandeira</td>
                    <td><?= $order->get_meta( '_wc_rede_brand_return_code' ); ?></td>
                </tr>
			<?php } ?>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_brand_return_message' ) ) ) { ?>
                <tr>
                    <td>Mensagem de retorno da bandeira</td>
                    <td><?= $order->get_meta( '_wc_rede_brand_return_message' ); ?></td>
                </tr>
			<?php } ?>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_brand_tid' ) ) ) { ?>
                <tr>
                    <td>Tid da bandeira</td>
                    <td><?= $order->get_meta( '_wc_rede_brand_tid' ) ?></td>
                </tr>
			<?php } ?>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_transaction_id' ) ) ) { ?>
                <tr>
                    <td>ID Transação</td>
                    <td><?= $order->get_meta( '_wc_rede_transaction_id' ); ?></td>
                </tr>
			<?php } ?>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_transaction_refund_id' ) ) ) { ?>
                <tr>
                    <td>ID Reembolso</td>
                    <td><?= $order->get_meta( '_wc_rede_transaction_refund_id' ); ?></td>
                </tr>
			<?php } ?>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_transaction_cancel_id' ) ) ) { ?>
                <tr>
                    <td>Id Cancelamento</td>
                    <td><?= $order->get_meta( '_wc_rede_transaction_cancel_id' ); ?></td>
                </tr>
			<?php } ?>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_transaction_nsu' ) ) ) { ?>
                <tr>
                    <td>Nsu</td>
                    <td><?= $order->get_meta( '_wc_rede_transaction_nsu' ); ?></td>
                </tr>
			<?php } ?>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_transaction_authorization_code' ) ) ) { ?>
                <tr>
                    <td>Código de autorização</td>
                    <td><?= $order->get_meta( '_wc_rede_transaction_authorization_code' ); ?></td>
                </tr>
			<?php } ?>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_transaction_bin' ) ) ) { ?>
                <tr>
                    <td>Bin</td>
                    <td><?= $order->get_meta( '_wc_rede_transaction_bin' ); ?></td>
                </tr>
			<?php } ?>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_transaction_last4' ) ) ) { ?>
                <tr>
                    <td>Last 4</td>
                    <td><?= $order->get_meta( '_wc_rede_transaction_last4' ); ?></td>
                </tr>
			<?php } ?>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_transaction_installments' ) ) ) { ?>
                <tr>
                    <td>Parcelas</td>
                    <td><?= $order->get_meta( '_wc_rede_transaction_installments' ); ?></td>
                </tr>
			<?php } ?>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_transaction_holder' ) ) ) { ?>
                <tr>
                    <td>Portador do cartão</td>
                    <td><?= $order->get_meta( '_wc_rede_transaction_holder' ); ?></td>
                </tr>
			<?php } ?>

			<?php if ( ! empty( $order->get_meta( '_wc_rede_transaction_expiration' ) ) && $order->get_meta( '_wc_rede_transaction_expiration' ) != '00/0000' ) { ?>
                <tr>
                    <td>Expiração do cartão</td>
                    <td><?= $order->get_meta( '_wc_rede_transaction_expiration' ); ?></td>
                </tr>
			<?php } ?>
            </tbody>
        </table>

		<?php
	}

	/**
	 * @param $order
	 * @param Transaction $transaction
	 * @param string $note
	 */
	public function process_order_status( $order, $transaction, $note = '' ) {
		$returnCode    = $transaction->getReturnCode();
		$returnMessage = $transaction->getReturnMessage();

		if ( empty( $returnCode ) ) {
			$returnCode    = $transaction->getAuthorization()->getReturnCode();
			$returnMessage = $transaction->getAuthorization()->getReturnMessage();
		}

		$status_note = sprintf( 'Rede[%s]: %s', $returnCode, $returnMessage );

		if ( $this->debug ) {
			$this->get_logger()->info( $status_note );
		}

		$order->add_order_note( trim( $status_note . ' ' . $note ) );

		if ( $returnCode == '00' ) {
			if ( $transaction->getCapture() ) {
				$order->payment_complete();
			} else {
				$order->update_status( 'on-hold' );
				wc_reduce_stock_levels( $order->get_id() );
			}
		} elseif ( $returnCode == '220' ) {
			$order->update_status( 'pending', 'Redirecionado para autenticação' );
		} else {
			$order->update_status( 'failed', $status_note );
		}

		WC()->cart->empty_cart();
	}

	protected function process_return( $order ) {
		if ( isset( $_POST['tid'] ) && isset( $_POST['threeDSecure_returnCode'] ) && $_POST['threeDSecure_returnCode'] == '200' ) {
			$order_id   = $order->get_id();
			$mpi_return = get_post_meta( $order_id, '_mpi_return', true );

			if ( ! $mpi_return ) {
				$transaction = $this->api->do_transaction_consultation( $_POST['tid'] );

				if ( $transaction != null ) {
					$authorization = $transaction->getAuthorization();

					update_post_meta( $order_id, '_mpi_return', true );
					update_post_meta( $order_id, '_transaction_id', $_POST['tid'] );
					update_post_meta( $order_id, '_wc_rede_transaction_return_code', $authorization->getReturnCode() );
					update_post_meta( $order_id, '_wc_rede_transaction_return_message',
						$authorization->getReturnMessage() );
					update_post_meta( $order_id, '_wc_rede_transaction_id', $_POST['tid'] );
					update_post_meta( $order_id, '_wc_rede_transaction_nsu', $authorization->getNsu() );
					update_post_meta( $order_id, '_wc_rede_transaction_authorization_code',
						$authorization->getAuthorizationCode() );

					$authorization = $transaction->getAuthorization();

					if ( ! is_null( $authorization ) ) {
						update_post_meta( $order_id, '_wc_rede_transaction_authorization_status',
							$authorization->getStatus() );
					}

					if ( ! is_null( $authorization ) ) {
						update_post_meta( $order_id, '_wc_rede_transaction_authorization_status',
							$authorization->getStatus() );
					}

					update_post_meta( $order_id, '_wc_rede_transaction_holder', $transaction->getCardHolderName() );
					update_post_meta( $order_id, '_wc_rede_transaction_expiration',
						sprintf( '%02d/%04d', $expiration[0], $expiration[1] ) );
					update_post_meta( $order_id, '_wc_rede_transaction_environment', $this->environment );

					$order->payment_complete();
					$order->reduce_order_stock();
					$this->process_order_status( $order, $transaction, '' );
				}
			}
		}
	}

	public function thankyou_page( $order_id ) {
		$order = new WC_Order( $order_id );
		$this->process_return( $order );

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			$order_url = $order->get_view_order_url();
		} else {
			$order_url = add_query_arg( 'order', $order_id, get_permalink( woocommerce_get_page_id( 'view_order' ) ) );
		}

		if ( $order->get_status() == 'on-hold' || $order->get_status() == 'processing' || $order->get_status() == 'completed' ) {
			echo '<div class="woocommerce-message">Seu pedido já está sendo processado. Para mais informações, <a href="' . esc_url( $order_url ) . '" class="button" style="display: block !important; visibility: visible !important;">veja os detalhes do pedido</a><br /></div>';
		} else {
			echo '<div class="woocommerce-info">Para mais detalhes sobre seu pedido, acesse <a href="' . esc_url( $order_url ) . '">página de detalhes do pedido</a></div>';
		}
	}

	protected function validate_card_number( $card_number ) {
		$card_number_checksum = '';

		foreach ( str_split( strrev( preg_replace( '/[^\d]/', '', $card_number ) ) ) as $i => $d ) {
			$card_number_checksum .= $i % 2 !== 0 ? $d * 2 : $d;
		}

		if ( array_sum( str_split( $card_number_checksum ) ) % 10 !== 0 ) {
			if ( $this->debug ) {
				$this->get_logger()->debug( "Número do cartão é inválido" );
			}

			throw new Exception( 'Por favor, informe um número válido de cartão de crédito' );
		}

		return true;
	}

	protected function fix_date( $posted ) {
		if ( preg_match( '/(\d{2})\s*\/\s*(\d{2})$/', $posted[ $this->id . '_expiry' ], $matches ) ) {
			$posted[ $this->id . '_expiry' ] = sprintf( '%d/%04d', $matches[1], 2000 + $matches[2] );
		}

		$posted[ $this->id . '_expiry' ] = preg_replace( '/\s*\/\s*/', '/', $posted[ $this->id . '_expiry' ] );
		$_POST[ $this->id . '_expiry' ]  = $posted[ $this->id . '_expiry' ];

		return $posted;
	}

	protected function validate_card_fields( $posted ) {
		$posted = $this->fix_date( $posted );

		try {
			if ( ! isset( $posted[ $this->id . '_number' ] ) || '' === $posted[ $this->id . '_number' ] ) {
				if ( $this->debug ) {
					$this->get_logger()->debug( "Número do cartão não informado" );
				}

				throw new Exception( 'Por favor informe o número do cartão.' );
			}

			if ( ! isset( $posted[ $this->id . '_holder_name' ] ) || '' === $posted[ $this->id . '_holder_name' ] ) {
				if ( $this->debug ) {
					$this->get_logger()->debug( "Portador do cartão não informado" );
				}

				throw new Exception( 'Por favor informe o nome do titular do cartão' );
			}

			if ( preg_replace( '/[^a-zA-Z\s]/', '',
					$posted[ $this->id . '_holder_name' ] ) != $posted[ $this->id . '_holder_name' ] ) {
				if ( $this->debug ) {
					$this->get_logger()->debug( "Portador do cartão inválido" );
				}

				throw new Exception( 'O nome do titular do cartão só pode conter letras' );
			}

			if ( ! isset( $posted[ $this->id . '_expiry' ] ) || '' === $posted[ $this->id . '_expiry' ] ) {
				if ( $this->debug ) {
					$this->get_logger()->debug( "Data de expiração do cartão não informada" );
				}

				throw new Exception( 'Por favor, informe a data de expiração do cartão' );
			}

			if ( strtotime( preg_replace( '/(\d{2})\s*\/\s*(\d{4})/', '$2-$1-01',
					$posted[ $this->id . '_expiry' ] ) ) < strtotime( date( 'Y-m' ) . '-01' ) ) {
				if ( $this->debug ) {
					$this->get_logger()->debug( "Data de expiração do cartão expirada" );
				}

				throw new Exception( 'A data de expiração do cartão deve ser futura' );
			}

			if ( ! isset( $posted[ $this->id . '_cvc' ] ) || '' === $posted[ $this->id . '_cvc' ] ) {
				if ( $this->debug ) {
					$this->get_logger()->debug( "CVV não informado" );
				}

				throw new Exception( 'Por favor, informe o código de segurança do cartão' );
			}

			if ( preg_replace( '/[^0-9]/', '', $posted[ $this->id . '_cvc' ] ) != $posted[ $this->id . '_cvc' ] ) {
				if ( $this->debug ) {
					$this->get_logger()->debug( "CVV inválido" );
				}

				throw new Exception( 'O código de segurança deve conter apenas números' );
			}
		} catch ( Exception $e ) {
			$this->add_error( $e->getMessage() );

			return false;
		}

		return true;
	}

	public function add_error( $message ) {
		global $woocommerce;

		$title = '<strong>' . esc_attr( $this->title ) . ':</strong> ';

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $title . $message, 'error' );
		} else {
			$woocommerce->add_error( $title . $message );
		}
	}

	protected function validate_installments( $posted, $order_total ) {
		if ( ! isset( $posted['rede_credit_installments'] ) ) {
			$posted['rede_credit_installments'] = 1;
		}

		if ( $posted['rede_credit_installments'] == 1 ) {
			return true;
		}

		try {
			if ( ! isset( $posted['rede_credit_installments'] ) || '' === $posted['rede_credit_installments'] ) {
				if ( $this->debug ) {
					$this->get_logger()->debug( "Número de parcelas não informada" );
				}

				throw new Exception( 'Por favor, informe o número de parcelas' );
			}

			$installments = absint( $posted['rede_credit_installments'] );
			$min_value    = $this->get_option( 'min_parcels_value' );
			$max_parcels  = $this->get_option( 'max_parcels_number' );

			if ( $installments > $max_parcels || ( ( $min_value != 0 ) && ( ( $order_total / $installments ) < $min_value ) ) ) {
				if ( $this->debug ) {
					$this->get_logger()->debug( "Número inválido de parcelas" );
				}

				throw new Exception( 'Número inválido de parcelas' );
			}
		} catch ( Exception $e ) {
			$this->add_error( $e->getMessage() );

			return false;
		}

		return true;
	}
}
