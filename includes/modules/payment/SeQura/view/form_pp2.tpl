<div class="sequra_popup" id="sequra_identity_partpayment_form_popup">
    <div class="sequra_white_content closeable">
        <div class="sequra_content_popup">
            <h1>Fracciona tu pago</h1>
            <div id="first_step">
                <h2 id="sequra_partpayment_tittle">Elige cómo pagar</h2>
                <h2 id="sequra_partpayment_alt_tittle">Paga <span class="sequra_partpayment_down_payment_amount-js"></span> y
                    <span class="sequra_partpayment_instalment_count-js"></span> cuotas de <span class="sequra_partpayment_instalment_amount-js"></span> después.<small>Editar</small></h2>
                <div id="first_step_content">
                    <div id="sequra-wrapper"></div>
                    <div style="border: #C0C0C0 1px solid;width: 80%;margin: 25px auto;"></div>
                    <ul>
                        <li>Sin intereses ni letra pequeña.</li>
                        <li>Puedes pagar la totalidad cuando tú quieras.</li>
                    </ul>
                    <input class="sequra_custom_button" type="button" id="part_payment_last_step" value="Último paso &raquo;">
                </div>
            </div>
            <div id="second_step">
                <div id="second_step_content" style="display: none;">
                    <ul id="description">
                        <li>El primer pago se hace con tarjeta. Los pagos futuros se cargan automáticamente en la misma tarjeta.</li>
                        <li>Puedes pagar la totalidad cuando tú quieras</li>
                        <li>¿Tienes alguna pregunta? Habla con nuestro partner SeQura en el <span>93 176 00 08</span></li>
                    </ul>
                {identity_form}

                </div>
            </div>
        </div>
    </div>
</div>

<div class="sequra_popup" id="sequra_partpayments_popup">
    <div class="sequra_white_content closeable">
        <div class="sequra_content_popup">
            <h3>Con este servicio puedes comprar ahora y dividir el pago en pequeñas cuotas</h3>
            <img src="includes/modules/payment/SeQura/view/img/mrq.png">
            <div>
                <p>Para este servicio nuestro partner <a class="sequra_blank_link" href="https://sequra.es/es/fraccionados" target="_blank">SeQura</a>
                    aplica un pequeño coste fijo a cada cuota. Para tu compra es de <span id="partPayments_fee"></span> por cuota.
                    No hay ninguna otra comisión. Puedes pagar el total del importe cuando desees.
                </p>
                <p>En total habrás pagado <span class="sequra_partpayment_total-js"></span>, de los cuales <span class="sequra_partpayment_fees-js"></span>
                    son comisión.
                </p>
                <p>Para esta compra la <abbr title="Tasa Anual Equivalente">TAE</abbr> es <span class="sequra_partpayment_apr-js"></span>. La TAE no es el interés de la compra, ya que no
                    hay interés (el "TIN" o Tipo de Interés Nominal es fijo al 0%), pero se utiliza para comparar créditos. <em>Este número acostumbra a ser más alto cuando
                        el plazo de pago es más corto.</em>
                </p>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    (function($){
        jQuery(document).delegate('form[name=checkout_confirmation]',"submit",function(){
            alert('Por favor, complete el formulario antes de continuar');
            jQuery('#sequra_identity_partpayment_form_popup').fadeIn();
            new SequraFraction({
                preselectedCreditAgreement: {selected-ca},
                product:"pp2",
                element:document.getElementById('sequra-wrapper')
            });            
            return false;
        });
        SequraHelper.preparePopup();
        if($(".sq_submit .confirm"))  $(".sq_submit .confirm").val("Paga la entrada");
        jQuery(document).delegate("#sequra_identity_partpayment_form_popup .sequra_popup_close", 'click', function() {
            history.back(1);
        });
        jQuery('#sequra_identity_partpayment_form_popup').fadeIn();
        new SequraFraction({
            preselectedCreditAgreement: {selected-ca},
            product:"pp2",
            element:document.getElementById('sequra-wrapper')
        });
        SequraHelper.preparePartPaymentAcordion(true);
    });
</script>