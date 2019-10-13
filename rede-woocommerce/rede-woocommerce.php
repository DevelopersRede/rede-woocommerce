<?php
/**
 * Plugin Name: Rede WooCommerce
 * Plugin URI:        https://github.com/DevelopersRede/woocommerce
 * Description: Rede API integration for WooCommerce
 * Author:      Rede
 * Version:     1.2.2
 * Text Domain: rede-woocommerce
 *
 * @package WC_Rede
 */
if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('WC_Rede')) :

    class WC_Rede
    {

        const VERSION = '1.2.2';

        protected static $instance = null;

        private function __construct()
        {
            add_action('init', array(
                $this,
                'load_plugin_textdomain'
            ));

            add_action('woocommerce_order_status_on-hold_to_processing', array($this, 'capture_payment'));

            if (class_exists('WC_Payment_Gateway')) {
                $this->upgrade();
                $this->includes();

                add_filter('woocommerce_payment_gateways', array(
                    $this,
                    'add_gateway'
                ));
                add_action('wp_enqueue_scripts', array(
                    $this,
                    'register_scripts'
                ));

                if (is_admin()) {
                    add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(
                        $this,
                        'plugin_action_links'
                    ));
                }
            } else {
                add_action('admin_notices', array(
                    $this,
                    'woocommerce_missing_notice'
                ));
            }
        }

        public function register_scripts()
        {
        }

        public static function get_instance()
        {
            if (null == self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public static function get_templates_path()
        {
            return plugin_dir_path(__FILE__) . 'templates/';
        }

        public function load_plugin_textdomain()
        {
            load_plugin_textdomain('rede-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }

        private function includes()
        {
            include_once dirname(__FILE__) . '/includes/class-wc-rede-abstract.php';
            include_once dirname(__FILE__) . '/includes/class-wc-rede-credit.php';
            include_once dirname(__FILE__) . '/includes/class-wc-rede-api.php';
            include_once dirname(__FILE__) . '/vendor/autoload.php';
        }

        public function add_gateway($methods)
        {
            array_push($methods, 'WC_Rede_Credit');

            return $methods;
        }

        private function upgrade()
        {
            if (is_admin()) {
                $version = get_option('wc_rede_version', '0');

                if (version_compare($version, WC_Rede::VERSION, '<')) {
                    if ($options = get_option('woocommerce_rede_settings')) {
                        $credit_options = array(
                            'enabled' => $options['enabled'],
                            'title' => 'Ativar',

                            'environment' => $options['environment'],
                            'token' => $options['token'],
                            'pv' => $options['pv'],

                            'soft_descriptor' => $options['soft_descriptor'],
                            'auto_capture' => $options['authorization'],

                            'min_parcels_value' => $options['smallest_installment'],
                            'max_parcels_number' => $options['installments']
                        );

                        update_option('woocommerce_rede_credit_settings', $credit_options);

                        delete_option('woocommerce_rede_settings');
                    }

                    update_option('wc_rede_version', WC_Rede::VERSION);
                }
            }
        }

        public function woocommerce_missing_notice()
        {
            include_once dirname(__FILE__) . '/includes/views/notices/html-notice-woocommerce-missing.php';
        }

        public function plugin_action_links($links)
        {
            $plugin_links = array();

            if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {
                $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=rede_credit')) . '">Configurações</a>';
            } else {
                $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_rede_credit')) . '">Configurações</a>';
            }

            return array_merge($plugin_links, $links);
        }
    }

    add_action('plugins_loaded', array(
        'WC_Rede',
        'get_instance'
    ), 0);

    register_activation_hook(__FILE__, 'rede_activation');

    function rede_activation()
    {
        if (!wp_next_scheduled('update_rede_orders')) {
            wp_schedule_event(current_time('timestamp'), 'hourly', 'update_rede_orders');
        }
    }

    add_action('update_rede_orders', 'update_rede_orders');

    function update_rede_orders()
    {
        $orders = new WP_Query(array(
            'post_type' => 'shop_order',
            'post_status' => array('wc-on-hold', 'wc-processing')
        ));


        foreach ($orders->posts as $order) {
            $wc_order = new WC_Order($order->get_id());
            $wc_id = $wc_order->get_id();
            $payment_gateway = wc_get_payment_gateway_by_order($wc_order);
            $order_id = get_post_meta($wc_id, '_wc_rede_order_id', true);
            $status = get_post_meta($wc_id, '_wc_rede_status', true);
            $tid = $tid = get_post_meta($wc_id, '_wc_rede_transaction_id', true);

            if ($payment_gateway instanceof WC_Rede_Abstract) {
                if ($status == 'PENDING' || $status == 'SUBMITTED') {
                    $payment_gateway->consult_order($wc_order, $order_id, $tid, $status);
                }
            }

        }

    }

    register_deactivation_hook(__FILE__, 'rede_deactivation');

    function rede_deactivation()
    {
        wp_clear_scheduled_hook('update_rede_orders');
    }
endif;
