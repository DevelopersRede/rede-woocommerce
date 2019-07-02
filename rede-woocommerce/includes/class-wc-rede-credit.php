<?php

class WC_Rede_Credit extends WC_Rede_Abstract
{

    public $api = null;

    public function __construct()
    {
        $this->id = 'rede_credit';
        $this->has_fields = true;
        $this->method_title = 'Pague com a Rede';
        $this->method_description = 'Habilita e configura pagamentos com a Rede';
        $this->supports = array(
            'products',
            'refunds'
        );

        $this->init_form_fields();

        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->environment = $this->get_option('environment');
        $this->pv = $this->get_option('pv');
        $this->token = $this->get_option('token');

        $this->soft_descriptor = $this->get_option('soft_descriptor');

        $this->auto_capture = $this->get_option('auto_capture');
        $this->max_parcels_number = $this->get_option('max_parcels_number');
        $this->min_parcels_value = $this->get_option('min_parcels_value');

        $this->partner_module = $this->get_option('module');
        $this->partner_gateway = $this->get_option('gateway');

        $this->debug = $this->get_option('debug');

        if ('yes' == $this->debug) {
            $this->log = $this->get_logger();
        }

        $this->api = new WC_Rede_API($this);

        if (!$this->auto_capture) {
            add_action('woocommerce_order_status_completed', array(
                $this,
                'process_capture'
            ));
        }

        add_action('woocommerce_order_status_cancelled', array(
            $this,
            'process_refund'
        ));
        add_action('woocommerce_order_status_refunded', array(
            $this,
            'process_refund'
        ));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
            $this,
            'process_admin_options'
        ));
        add_action('woocommerce_api_wc_rede_credit', array(
            $this,
            'check_return'
        ));
        add_action('woocommerce_thankyou_' . $this->id, array(
            $this,
            'thankyou_page'
        ));
        add_action('wp_enqueue_scripts', array(
            $this,
            'checkout_scripts'
        ));

        add_filter('woocommerce_get_order_item_totals', array(
            $this,
            'order_items_payment_details'
        ), 10, 2);

        add_action('woocommerce_admin_order_data_after_billing_address', array(
            $this,
            'display_meta'
        ), 10, 1);
    }

    public function display_meta($order)
    {
        ?>
        <h3>Rede</h3>
        <table>
            <tbody>
            <tr>
                <td>Ambiente</td>
                <td><?= $order->get_meta('_wc_rede_transaction_environment'); ?></td>
            </tr>

            <tr>
                <td>Código de Retorno</td>
                <td><?= $order->get_meta('_wc_rede_transaction_return_code'); ?></td>
            </tr>

            <tr>
                <td>Mensagem de Retorno</td>
                <td><?= $order->get_meta('_wc_rede_transaction_return_message'); ?></td>
            </tr>

            <?php if (!empty($order->get_meta('_wc_rede_transaction_id'))) { ?>
                <tr>
                    <td>ID Transação</td>
                    <td><?= $order->get_meta('_wc_rede_transaction_id'); ?></td>
                </tr>
            <?php } ?>

            <?php if (!empty($order->get_meta('_wc_rede_transaction_refund_id'))) { ?>
                <tr>
                    <td>ID Reembolso</td>
                    <td><?= $order->get_meta('_wc_rede_transaction_refund_id'); ?></td>
                </tr>
            <?php } ?>

            <?php if (!empty($order->get_meta('_wc_rede_transaction_cancel_id'))) { ?>
                <tr>
                    <td>Id Cancelamento</td>
                    <td><?= $order->get_meta('_wc_rede_transaction_cancel_id'); ?></td>
                </tr>
            <?php } ?>

            <?php if (!empty($order->get_meta('_wc_rede_transaction_nsu'))) { ?>
                <tr>
                    <td>Nsu</td>
                    <td><?= $order->get_meta('_wc_rede_transaction_nsu'); ?></td>
                </tr>
            <?php } ?>

            <?php if (!empty($order->get_meta('_wc_rede_transaction_authorization_code'))) { ?>
                <tr>
                    <td>Código de autorização</td>
                    <td><?= $order->get_meta('_wc_rede_transaction_authorization_code'); ?></td>
                </tr>
            <?php } ?>

            <tr>
                <td>Bin</td>
                <td><?= $order->get_meta('_wc_rede_transaction_bin'); ?></td>
            </tr>

            <tr>
                <td>Last 4</td>
                <td><?= $order->get_meta('_wc_rede_transaction_last4'); ?></td>
            </tr>

            <tr>
                <td>Parcelas</td>
                <td><?= $order->get_meta('_wc_rede_transaction_installments'); ?></td>
            </tr>


            <tr>
                <td>Portador do cartão</td>
                <td><?= $order->get_meta('_wc_rede_transaction_holder'); ?></td>
            </tr>

            <tr>
                <td>Expiração do cartão</td>
                <td><?= $order->get_meta('_wc_rede_transaction_expiration'); ?></td>
            </tr>
            </tbody>
        </table>

        <?php
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Habilita/Desabilita',
                'type' => 'checkbox',
                'label' => 'Habilita pagamento com a Rede',
                'default' => 'yes'
            ),
            'title' => array(
                'title' => 'Título',
                'type' => 'text',
                'default' => 'Pague com a Rede'
            ),

            'rede' => array(
                'title' => 'Configuração geral',
                'type' => 'title'
            ),
            'environment' => array(
                'title' => 'Ambiente',
                'type' => 'select',
                'description' => 'Escolha o ambiente',
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => 'test',
                'options' => array(
                    'test' => 'Testes',
                    'production' => 'Produção'
                )
            ),
            'pv' => array(
                'title' => 'PV',
                'type' => 'text',
                'default' => ''
            ),
            'token' => array(
                'title' => 'Token',
                'type' => 'text',
                'default' => ''
            ),

            'soft_descriptor' => array(
                'title' => 'Soft Descriptor',
                'type' => 'text',
                'default' => ''
            ),

            'credit_options' => array(
                'title' => 'Configuracões de cartão de crédito',
                'type' => 'title'
            ),

            'auto_capture' => array(
                'title' => 'Autorização e captura',
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => '2',
                'options' => array(
                    '1' => 'Autorize e capture automaticamente',
                    '0' => 'Apenas autorize'
                )
            ),
            'min_parcels_value' => array(
                'title' => 'Valor da menor parcela',
                'type' => 'text',
                'default' => '0'
            ),
            'max_parcels_number' => array(
                'title' => 'Máximo de parcelas',
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => '12',
                'options' => array(
                    '1' => '1x',
                    '2' => '2x',
                    '3' => '3x',
                    '4' => '4x',
                    '5' => '5x',
                    '6' => '6x',
                    '7' => '7x',
                    '8' => '8x',
                    '9' => '9x',
                    '10' => '10x',
                    '11' => '11x',
                    '12' => '12x'
                )
            ),

            'partners' => array(
                'title' => 'Configuracões para parceiros',
                'type' => 'title'
            ),
            'module' => array(
                'title' => 'ID Módulo',
                'type' => 'text',
                'default' => ''
            ),
            'gateway' => array(
                'title' => 'ID Gateway',
                'type' => 'text',
                'default' => ''
            ),

            'developers' => array(
                'title' => 'Configuracões para desenvolvedores',
                'type' => 'title'
            ),

            'debug' => array(
                'title' => 'Depuração',
                'type' => 'checkbox',
                'label' => 'Ativa logs de depuração',
                'default' => 'no'
            )
        );
    }

    public function get_installment_text($quantity, $order_total)
    {
        $installments = $this->get_installments($order_total);

        if (isset($installments[$quantity - 1])) {
            return $installments[$quantity - 1]['label'];
        }

        if (isset($installments[$quantity])) {
            return $installments[$quantity]['label'];
        }

        return $quantity;
    }

    public function get_installments($order_total = 0)
    {
        $installments = [];
        $min_value = $this->min_parcels_value;
        $max_parcels = $this->max_parcels_number;

        for ($i = 1; $i <= $max_parcels; ++$i) {
            $label = sprintf('%dx de R$ %.02f', $i, $order_total / $i);

            if ($i == 1) {
                $label = sprintf('R$ %.02f à vista', $order_total);
            }

            if (($order_total / $i) < $min_value) {
                break;
            }

            $installments[] = array(
                'num' => $i,
                'label' => $label
            );
        }

        if (count($installments) == 0) {
            $installments[] = array(
                'num' => 1,
                'label' => sprintf('R$ %.02f à vista', $order_total)
            );
        }

        return $installments;
    }

    public function checkout_scripts()
    {
        if (!is_checkout()) {
            return;
        }

        if (!$this->is_available()) {
            return;
        }

        wp_enqueue_style('wc-rede-checkout-webservice');
    }

    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);
        $card_number = isset($_POST['rede_credit_number']) ? sanitize_text_field($_POST['rede_credit_number']) : '';
        $valid = true;

        if ($valid) {
            $valid = $this->validate_card_number($card_number);
        }

        if ($valid) {
            $valid = $this->validate_card_fields($_POST);
        }

        if ($valid) {
            $valid = $this->validate_installments($_POST, $order->get_total());
        }

        if ($valid) {
            $installments = isset($_POST['rede_credit_installments']) ? absint($_POST['rede_credit_installments']) : 1;
            $expiration = explode(" / ", $_POST['rede_credit_expiry']);

            $card_data = array(
                'card_number' => preg_replace('/[^\d]/', '', $_POST['rede_credit_number']),
                'card_expiration_month' => $expiration[0],
                'card_expiration_year' => $expiration[1],
                'card_cvv' => $_POST['rede_credit_cvc'],
                'card_holder' => $_POST['rede_credit_holder_name']
            );

            try {
                $order_id = $order->get_id();
                $amount = $order->get_total();
                $transaction = $this->api->do_transaction_request($order_id + time(), $amount, $installments, $card_data);

                update_post_meta($order_id, '_transaction_id', $transaction->getTid());
                update_post_meta($order_id, '_wc_rede_transaction_return_code', $transaction->getReturnCode());
                update_post_meta($order_id, '_wc_rede_transaction_return_message', $transaction->getReturnMessage());
                update_post_meta($order_id, '_wc_rede_transaction_installments', $installments);
                update_post_meta($order_id, '_wc_rede_transaction_id', $transaction->getTid());
                update_post_meta($order_id, '_wc_rede_transaction_refund_id', $transaction->getRefundId());
                update_post_meta($order_id, '_wc_rede_transaction_cancel_id', $transaction->getCancelId());
                update_post_meta($order_id, '_wc_rede_transaction_bin', $transaction->getCardBin());
                update_post_meta($order_id, '_wc_rede_transaction_last4', $transaction->getLast4());
                update_post_meta($order_id, '_wc_rede_transaction_nsu', $transaction->getNsu());
                update_post_meta($order_id, '_wc_rede_transaction_authorization_code', $transaction->getAuthorizationCode());

                $authorization = $transaction->getAuthorization();

                if (!is_null($authorization)) {
                    update_post_meta($order_id, '_wc_rede_transaction_authorization_status', $authorization->getStatus());
                }

                update_post_meta($order_id, '_wc_rede_transaction_holder', $transaction->getCardHolderName());
                update_post_meta($order_id, '_wc_rede_transaction_expiration', sprintf('%02d/%04d', $expiration[0], $expiration[1]));

                update_post_meta($order_id, '_wc_rede_transaction_holder', $transaction->getCardHolderName());

                $authorization = $transaction->getAuthorization();

                if (!is_null($authorization)) {
                    update_post_meta($order_id, '_wc_rede_transaction_authorization_status', $authorization->getStatus());
                }

                update_post_meta($order_id, '_wc_rede_transaction_environment', $this->environment);

                $this->process_order_status($order, $transaction, '');
            } catch (Exception $e) {
                $this->add_error($e->getMessage());
                $valid = false;
            }
        }

        if ($valid) {
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        } else {
            return array(
                'result' => 'fail',
                'redirect' => ''
            );
        }
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = new WC_Order($order_id);

        if (!$order || !$order->get_transaction_id()) {
            return false;
        }

        if (empty($order->get_meta('_wc_rede_transaction_canceled'))) {
            $tid = $order->get_transaction_id();
            $amount = wc_format_decimal($amount);

            try {
                $transaction = $this->api->do_transaction_cancellation($tid, $amount);

                update_post_meta($order_id, '_wc_rede_transaction_refund_id', $transaction->getRefundId());
                update_post_meta($order_id, '_wc_rede_transaction_cancel_id', $transaction->getCancelId());
                update_post_meta($order_id, '_wc_rede_transaction_canceled', true);

                $order->add_order_note('Reembolsado: ' . wc_price($amount));
            } catch (Exception $e) {
                return new WP_Error('rede_refund_error', sanitize_text_field($e->getMessage()));
            }

            return true;
        }

        return false;
    }

    public function process_capture($order_id)
    {
        $order = new WC_Order($order_id);

        if (!$order || !$order->get_transaction_id()) {
            return false;
        }

        if (empty($order->get_meta('_wc_rede_captured'))) {
            $tid = $order->get_transaction_id();
            $amount = $order->get_total();

            try {
                $transaction = $this->api->do_transaction_capture($tid, $amount);

                update_post_meta($order_id, '_wc_rede_transaction_nsu', $transaction->getNsu());
                update_post_meta($order_id, '_wc_rede_captured', true);

                $order->add_order_note('Capturado');
            } catch (Exception $e) {
                return new WP_Error('rede_capture_error', sanitize_text_field($e->getMessage()));
            }

            return true;
        }

        return false;
    }

    protected function get_checkout_form($order_total = 0)
    {
        $wc_get_template = 'woocommerce_get_template';

        if (function_exists('wc_get_template')) {
            $wc_get_template = 'wc_get_template';
        }

        $wc_get_template('credit-card/rede-payment-form.php', array(
            'installments' => $this->get_installments($order_total)
        ), 'woocommerce/rede/', WC_Rede::get_templates_path());
    }
}
