{css_to_load}
<div id="sequra_info_container" class="sequra_popup_embedded">
</div>
<p>
Compra sin dejar tarjeta ni datos bancarios,
sin registros, papeleos ni costes adicionales.<br/>
Tienes 7 d&iacute;as desde el env&iacute;o de tu pedido para pagar. 
	<span id="sequra_invoice_method_link"
		class="sequra-educational-popup"
		data-product="i1"> + info</span>
</p>
<script type='text/javascript'>
{include widget_ini}

function waitForSequra() {
    if (typeof Sequra === 'undefined') {
        setTimeout(waitForSequra, 200);
        return;
    }
    Sequra.onLoad(function () {
        Sequra.refreshComponents();
    });
}
waitForSequra();
</script>