{css_to_load}
<div id="sequra_splitpayment_info_container">
	Paga tu compra en 3 meses por solo un coste de apertura en el primer pago.<br/>
	Al instante, sin papeleos ni trucos.
	<span id="sequra_invoice_method_link"
		class="sequra-educational-popup"
		data-amount="{total-amount}"
		data-product="sp1"> + info</span>
</div>
<script type="text/javascript">
{include widget_ini}

function waitForSequrasplitpayment() {
    if (typeof Sequra === 'undefined') {
        setTimeout(waitForSequrasplitpayment, 200);
        return;
    }

    Sequra.onLoad(function () {
        var container = document.getElementById("sequra_splitpayment_info_container");
        if(container.getAttribute('done')){
            return;
        }
        var creditAgreements = Sequra.computeCreditAgreements({
            amount: "{total-amount}",
            product: "sp1",
        });
        var ca = creditAgreements["sp1"];
        el = document.getElementById("sequra_splitpayment_title_amount");
        el.innerHTML = ca[0]["instalment_total"]["string"].replace('€','&euro;');
        if( ca[0]["setup_fee"]["value"] > 0){
            el.parent.innerHTML = el.parent.innerHTML + ' por solo ' + ca[0]["setup_fee"]["string"].replace('€','&euro;') + '/mes';
        }
        container.setAttribute('done',true);
        Sequra.refreshComponents();
    });
}
waitForSequrasplitpayment() ;
</script>