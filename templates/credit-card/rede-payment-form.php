<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
?>

<fieldset id="rede-credit-payment-form" class="rede-payment-form">
    <p class="form-row form-row-wide">
        <label for="rede-card-number">Número do cartão<span
                    class="required">*</span></label> <input id="rede-card-number"
                                                             name="rede_credit_number"
                                                             class="input-text wc-credit-card-form-card-number"
                                                             type="tel"
                                                             maxlength="22" autocomplete="off"
                                                             placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;"
                                                             style="font-size: 1.5em; padding: 8px;"/>
    </p>

	<?php if ( is_array( $installments ) && count( $installments ) > 1 ) : ?>
        <p class="form-row form-row-wide">
            <label for="installments">Selecione a quantidade de parcelas<span
                        class="required">*</span></label> <select id="installments"
                                                                  name="rede_credit_installments">
				<?php
				foreach ( $installments as $installment ) {
					printf( '<option value="%d">%s</option>', $installment['num'], $installment['label'] );
				}
				?>
            </select>
        </p>
	<?php endif; ?>
    <p class="form-row form-row-wide">
        <label for="rede-card-holder-name">Nome impresso no cartão<span
                    class="required">*</span></label> <input id="rede-card-holder-name"
                                                             name="rede_credit_holder_name" class="input-text"
                                                             type="text"
                                                             autocomplete="off"
                                                             style="font-size: 1.5em; padding: 8px;"/>
    </p>
    <p class="form-row form-row-first">
        <label for="rede-card-expiry">Validade do cartão<span
                    class="required">*</span></label> <input id="rede-card-expiry"
                                                             name="rede_credit_expiry"
                                                             class="input-text wc-credit-card-form-card-expiry"
                                                             type="tel"
                                                             autocomplete="off"
                                                             placeholder="MM / AAAA"
                                                             style="font-size: 1.5em; padding: 8px;"/>
    </p>
    <p class="form-row form-row-last">
        <label for="rede-card-cvc">Código de segurança<span
                    class="required">*</span></label> <input id="rede-card-cvc"
                                                             name="rede_credit_cvc"
                                                             class="input-text wc-credit-card-form-card-cvc" type="tel"
                                                             autocomplete="off"
                                                             placeholder="CVC"
                                                             style="font-size: 1.5em; padding: 8px;"/>
    </p>
    <div class="clear"></div>
</fieldset>
