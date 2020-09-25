<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
?>

<h3><?php echo $this->method_title; ?></h3>

<?php echo wpautop( $this->method_description ); ?>

<table class="form-table">
	<?php $this->generate_settings_html(); ?>
</table>
