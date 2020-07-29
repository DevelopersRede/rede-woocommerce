<?php
if (!defined('ABSPATH')) {
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

    <?php if (is_array($installments) && count($installments) > 1) : ?>
        <p class="form-row form-row-wide">
            <label for="installments">Parcelas <?= count($installments) ?><span
                        class="required">*</span></label><br>
            <select id="installments"
                    name="rede_credit_installments" class="input-select-cred-card" style="width: 100%">
                <?php
                foreach ($installments as $installment) {
                    printf('<option value="%d">%s</option>', $installment['num'], $installment['label']);
                }
                ?>
            </select>
        </p>
    <?php endif; ?>
    <p class="form-row form-row-wide">
        <label for="rede-card-holder-name">
            Nome impresso no cartão
            <span class="required">*</span>
        </label>
        <input id="rede-card-holder-name"
               name="rede_credit_holder_name" class="input-text"
               type="text"
               autocomplete="off"
               style="font-size: 1.5em; padding: 8px;"/>
    </p>
    <p class="form-row form-row-first">
        <label for="rede-card-expiry">
            Validade do cartão
            <span class="required">*</span>
        </label>
        <br>
        <select id="select_credit_expiry_month" class="input-select-cred-card expiry-month"
                onchange="updateCardExpiry()">
            <?php
            foreach (range(1, 12) as $month) {
                $monthValue = $month > 9 ? (string) $month : "0{$month}";
                printf('<option value="%s">%s</option>', $monthValue, $monthValue);
            }
            ?>
        </select> <span class="input-select-cred-card-bar">/</span>
        <select id="select_credit_expiry_year" class="input-select-cred-card expiry-year"
                onchange="updateCardExpiry()">
            <?php
            foreach (range(date('y'), date('y') + 20) as $year) {
                printf('<option value="%d">%d</option>', "20{$year}", $year);
            }
            ?>
        </select>
        <input id="rede-card-expiry" name="rede_credit_expiry" type="hidden"/>
        <script>
            function updateCardExpiry() {
                var elementMonth = document.getElementById("select_credit_expiry_month");
                var elementYear = document.getElementById("select_credit_expiry_year");
                var elementCardExpiry = document.getElementById("rede-card-expiry")
                elementCardExpiry.value = elementMonth.value + "/" + elementYear.value
            }

            updateCardExpiry();
        </script>
    </p>
    <p class="form-row form-row-first">
        <label for="rede-card-cvc">
            Código de segurança
            <span class="required">*</span>
        </label>
        <input id="rede-card-cvc"
               name="rede_credit_cvc"
               class="input-text wc-credit-card-form-card-cvc" type="tel"
               autocomplete="off"
               placeholder="CVC"
               style="font-size: 1.5em; padding: 8px;"/>
    </p>
    <div class="clear"></div>
</fieldset>
<style>
    .input-select-cred-card {
        font-size: 1.5em;
        padding: 8px 10px !important;
        background: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAB4AAAAeCAYAAAA7MK6iAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAIZJREFUeNrs1MEJwCAMBdA0OIjnDuYIdYY6pcdOIU2KhfbYiBbhC0Iu+vghuoQQViI6aPBi+mkBBgwY8HjYe7/ptp53VpSZY60p55y6J36i1wVSW5J/TlzTpWmHyxlaHaW9r9aWUpJ0Yu+aWAGFWlDzVCuk03zXw55TC4gvEzBgwPPCpwADAJAnLqKmIGxnAAAAAElFTkSuQmCC') 100% 50% no-repeat !important;
        background-color: #fff !important;
        height: 34px;
        border: 1px solid #C8BFC6;
    }
    .input-select-cred-card-bar{
        font-size: 1.5em;
    }
    .input-select-cred-card.expiry-month, .input-select-cred-card.expiry-year {
        width: 70px !important;
    }
</style>
