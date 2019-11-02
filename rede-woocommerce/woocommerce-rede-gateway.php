<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$active_plugins = get_option( 'active_plugins', array() );

foreach ( $active_plugins as $key => $active_plugin ) {
	if ( strstr( $active_plugin, '/woocommerce-rede-gateway.php' ) ) {
		$active_plugins[ $key ] = str_replace( '/woocommerce-rede-gateway.php', '/rede-woocommerce.php', $active_plugin );
	}
}

update_option( 'active_plugins', $active_plugins );
