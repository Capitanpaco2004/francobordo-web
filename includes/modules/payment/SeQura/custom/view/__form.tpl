<div class="sequra_popup" id="sequra_identity_form_popup">
    <div class="sequra_white_content">
        <div class="sequra_content_popup">
            <div id="before-sequra">
                <h2>Pagar&aacute;s una vez hayas recibido y comprobado tu pedido.</h2>
            </div>
            {identity_form}
            <div id="after-sequra">
                <p>Este es un servicio ofrecido por {shop_name} para que te sea f&aacute;cil, r&aacute;pido y c&oacute;modo comprar con nosotros. Pagar&aacute;s con tarjeta o transferencia bancaria en 7 d&iacute;as y despu&eacute;s de haber comprobado tu pedido. <a class="sequra_other_payment_methods" href="{back}" id="sequra_back">{back-text}</a>.</p>
            </div>
        </div>
    </div>
</div>
<input type='hidden' value="0" id="sequra_approved" name="sequra_approved"/>
<input type='hidden' value="0" id="dxconfianza" name="dxconfianza"/>
<script language="javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.12.0/jquery.min.js" type="text/javascript"></script>
<script type="text/javascript">
jQuery.noConflict();
jQuery(function($) {
    $('body').append($('#sequra_identity_form_popup'));
    $('#sequra_identity_form_popup').fadeIn();
    if($(".sq_submit .confirm"))  $(".sq_submit .confirm").val("Finalizar");
    $('form[name="checkout_confirmation"]').on("submit",function(){
        if(1==jQuery('#sequra_approved').val()){
            return true;
        }
        alert('Por favor, complete el formulario antes de continuar');
        $('#sequra_identity_form_popup').fadeIn();
        return false;
    });
});

function shop_callback_sequra_approved(){
    console.log('shop_callback_sequra_approved');
    jQuery('#sequra_approved').val(1);
    jQuery('#sequra_identity_form_popup').fadeOut();
}
</script>

