<?php


abstract class WC_Rede_Abstract extends WC_Payment_Gateway
{
    public $debug = 'no';
    public $auto_capture = true;
    public $min_parcels_value = 0;
    public $mas_parcels_number = 12;

    public function get_valid_value($value)
    {
        return preg_replace('/[^\d\.]+/', '', str_replace(',', '.', $value));
    }

    public function get_api_return_url($order)
    {
        global $woocommerce;

        $url = $woocommerce->api_request_url(get_class($this));

        return urlencode(add_query_arg(array(
            'key' => $order->order_key,
            'order' => $order->get_id()
        ), $url));
    }

    public function get_logger()
    {
        if (class_exists('WC_Logger')) {
            return new WC_Logger();
        } else {
            global $woocommerce;

            return $woocommerce->logger();
        }
    }

    public function order_items_payment_details($items, $order)
    {
        $order_id = $order->get_id();

        if ($this->id === $order->get_payment_method()) {
            $tid = get_post_meta($order_id, '_wc_rede_transaction_id', true);
            $authorization_code = get_post_meta($order_id, '_wc_rede_transaction_authorization_code', true);
            $installments = get_post_meta($order_id, '_wc_rede_transaction_installments', true);
            $last = array_pop($items);
            $items['payment_return'] = array(
                'label' => 'Payment:',
                'value' => sprintf('<strong>Order ID</strong>: %s<br /><strong>Installments</strong>: %s<br /><strong>Transaction Id</strong>: %s<br />',
                    $order_id, $installments, $tid)
            );

            $items['payment_return']['value'] .= sprintf('<strong>Autorization Code</strong>: %s', $authorization_code);

            $items[] = $last;
        }

        return $items;
    }

    public function get_payment_method_name($slug)
    {
        $methods = 'rede';

        if (isset($methods[$slug])) {
            return $methods[$slug];
        }

        return $slug;
    }

    public function payment_fields()
    {
        if ($description = $this->get_description()) {
            echo wpautop(wptexturize($description));
        }

        wp_enqueue_script('wc-credit-card-form');

        $this->get_checkout_form($this->get_order_total());
    }

    abstract protected function get_checkout_form($order_total = 0);

    public function get_order_total()
    {
        global $woocommerce;

        $order_total = 0;

        if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {
            $order_id = absint(get_query_var('order-pay'));
        } else {
            $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        }

        if (0 < $order_id) {
            $order = new WC_Order($order_id);
            $order_total = (float)$order->get_total();
        } elseif (0 < $woocommerce->cart->total) {
            $order_total = (float)$woocommerce->cart->total;
        }

        return $order_total;
    }

    public function consult_order($order, $id, $tid, $status)
    {
        $transaction = $this->api->do_transaction_consultation($tid);

        $this->process_order_status($order, $transaction, 'verificação automática');
    }

    /**
     * @param $order
     * @param \Rede\Transaction $transaction
     * @param string $note
     */
    public function process_order_status($order, $transaction, $note = '')
    {
        $status_note = sprintf('Rede[%s]', $transaction->getReturnMessage());

        $order->add_order_note($status_note . ' ' . $note);

        if ($transaction->getReturnCode() == '00') {
            if ($transaction->getCapture()) {
                $order->payment_complete();
            } else {
                $order->update_status('on-hold');
                wc_reduce_stock_levels($order->get_id());
            }
        } else {
            $order->update_status('failed', $status_note);
            $order->update_status('cancelled', $status_note);
        }

        WC()->cart->empty_cart();
    }

    public function thankyou_page($order_id)
    {
        $order = new WC_Order($order_id);

        if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {
            $order_url = $order->get_view_order_url();
        } else {
            $order_url = add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('view_order')));
        }

        if ($order->get_status() == 'on-hold' || $order->get_status() == 'processing' || $order->get_status() == 'completed') {
            echo '<div class="woocommerce-message">Seu pedido já está sendo processado. Para mais informações, <a href="' . esc_url($order_url) . '" class="button" style="display: block !important; visibility: visible !important;">veja os detalhes do pedido</a><br /></div>';
        } else {
            echo '<div class="woocommerce-info">Para mais detalhes sobre seu pedido, acesse <a href="' . esc_url($order_url) . '">página de detalhes do pedido</a></div>';
        }
    }

    protected function validate_card_number($card_number)
    {
        $card_number_checksum = '';

        foreach (str_split(strrev(preg_replace('/[^\d]/', '', $card_number))) as $i => $d) {
            $card_number_checksum .= $i % 2 !== 0 ? $d * 2 : $d;
        }

        if (array_sum(str_split($card_number_checksum)) % 10 !== 0) {
            throw new Exception('Por favor, informe um número válido de cartão de crédito');
        }

        return true;
    }

    protected function validate_card_fields($posted)
    {

        try {
            if (!isset($posted[$this->id . '_holder_name']) || '' === $posted[$this->id . '_holder_name']) {
                throw new Exception('Por favor informe o nome do titular do cartão');
            }

            if (preg_replace('/[^a-zA-Z\s]/', '',
                    $posted[$this->id . '_holder_name']) != $posted[$this->id . '_holder_name']) {
                throw new Exception('O nome do titular do cartão só pode conter letras');
            }

            if (!isset($posted[$this->id . '_expiry']) || '' === $posted[$this->id . '_expiry']) {
                throw new Exception('Por favor, informe a data de expiração do cartão');
            }

            //if user filled expiry date with 3 digits,
            // throw an exception and let him/her/they know.
            if ( isset($posted[$this->id . '_expiry'][2]) && !isset($posted[$this->id . '_expiry'][3])) {
                throw new Exception('A data de expiração deve conter 2 ou 4 dígitos');
            }

           if (strtotime(preg_replace('/(\d{2})\s*\/\s*(\d{4})/', '$2-$1-01',
                    $this->normalize_expiration_date( $posted[$this->id . '_expiry'] ) ) ) < strtotime(date('Y-m') . '-01')) {
                throw new Exception('A data de expiração do cartão deve ser futura.');
            }

            if (!isset($posted[$this->id . '_cvc']) || '' === $posted[$this->id . '_cvc']) {
                throw new Exception('Por favor, informe o código de segurança do cartão');
            }

            if (preg_replace('/[^0-9]/', '', $posted[$this->id . '_cvc']) != $posted[$this->id . '_cvc']) {
                throw new Exception('O código de segurança deve conter apenas números');
            }
        } catch (Exception $e) {
            $this->add_error($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Normalize expiry date.
     *
     * Normalize expiry date by adding the '20' when the year has only 2 digits.
     *
     * @param string $date
     * @return string $date
     */
    protected function normalize_expiration_date($date) {

        // Check the length of the string. This way of checking length is faster.
        // see https://coderwall.com/p/qgeuna/php-string-length-the-right-way
        if ( !isset($date[7])) {
            $date = str_replace ( '/ ', '/ 20', $date);
        }

        return $date;
    }

    /**
     * Normalize expiry year.
     *
     * Normalize expiry year by adding the '20' when the year has only 2 digits.
     *
     * @param string $year
     * @return string $year
     */
    protected function normalize_expiration_year($year) {
        if ( !isset($year[3])) {
            $year = '20' . $year;
        }

        return $year;
    }

    public function add_error($message)
    {
        global $woocommerce;

        $title = '<strong>' . esc_attr($this->title) . ':</strong> ';

        if (function_exists('wc_add_notice')) {
            wc_add_notice($title . $message, 'error');
        } else {
            $woocommerce->add_error($title . $message);
        }
    }

    protected function validate_installments($posted, $order_total)
    {
        if (!isset($posted['rede_credit_installments'])) {
            $posted['rede_credit_installments'] = 1;
        }

        if ($posted['rede_credit_installments'] == 1) {
            return true;
        }

        try {
            if (!isset($posted['rede_credit_installments']) || '' === $posted['rede_credit_installments']) {
                throw new Exception('Por favor, informe o número de parcelas');
            }

            $installments = absint($posted['rede_credit_installments']);
            $min_value = $this->get_option('min_parcels_value');
            $max_parcels = $this->get_option('max_parcels_number');

            if ($installments > $max_parcels || (($min_value != 0) && (($order_total / $installments) < $min_value))) {
                throw new Exception('Número inválido de parcelas');
            }
        } catch (Exception $e) {
            $this->add_error($e->getMessage());

            return false;
        }

        return true;
    }
}
