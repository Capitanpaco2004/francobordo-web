{css_to_load}
<div id="sequra_partpayment_info_container" class="sequra_popup_embedded">
	<div id="sequra_partpayment_installment_info_container"></div>
  <b>Finaliza tu compra para poder elegir tu plan de pago 
		<span id="sequra_partpayment_method_link"
			class="sequra-educational-popup"
			data-amount="{total-amount}"
			data-product="pp3"> + info</span>
	</b>
</div>
<script type='text/javascript'>
{include widget_ini}

function waitForSequrapp() {
    if (typeof Sequra === 'undefined') {
        setTimeout(waitForSequrapp, 200);
        return;
    }
    Sequra.onLoad(function () {
        var container = document.getElementById("sequra_partpayment_installment_info_container");
        if(container.getAttribute('done')){
            return;
        }
        var creditAgreements = Sequra.computeCreditAgreements({
            amount: "{total-amount}",
            product: "pp3"
        });
        var ca = creditAgreements["pp3"];
        var instalment_total = ca[ca.length - 1]["instalment_total"]["string"].replace('€','&euro;');
        var method_name = "<?php esc_html_e( 'Desde 00,00&euro;/mes', 'wc_sequra' ); ?>";
        var el = document.getElementById("sequra_partpayment_instalment_amount");
        el.innerHTML = instalment_total;
        var i=0;
        var html = "";
        for(i=0;i<ca.length;i++){
            html +="<div>" + ca[i]["instalment_count"] + " cuotas de " + ca[i]["instalment_total"]["string"].replace('€','&euro;') + "/mes</div>";
        }
        container.innerHTML = html;
        container.setAttribute('done',true);
        Sequra.refreshComponents();
    });
}
waitForSequrapp();
</script>