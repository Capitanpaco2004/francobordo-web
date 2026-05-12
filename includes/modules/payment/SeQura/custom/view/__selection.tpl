<a id="sequra_identity_form_popup_trigger" rel="sequra_factura_popup" style="margin-top:0px;float:none">+ info</a>

<div class="sequra_popup" id="sequra_factura_popup">
    <div class="sequra_white_content closeable" id="sequra_factura_white_content">
        <div class="sequra_content_popup">
            <h4 id="sequra_text_title">{service-name}</h4>
            <p>Compra hoy y paga despu&eacute;s de recibir tus pedidos. As&iacute; de sencillo.</p>
            <div class="sequra_image_wrapper"></div>
            <p>&iexcl;Por sólo 1,95€!</p>
            <ul>
                <li>Sin registros ni tarjetas. La forma m&aacute;s segura de comprar en Internet.</li>
                <li>Paga hasta 7 d&iacute;as despu&eacute;s de la fecha de env&iacute;o.</li>
                <li>Paga con transferencia bancarios, ingreso en cuenta o tarjeta.</li>
            </ul>
            <small>Servicio ofrecido en colaboraci&oacute;n con  <a class="sequra_blank_link" href="https://sequra.es/es/consumidores" target="_blank">SeQura</a></small>
        </div>
    </div>
</div>
<script language="javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js" type="text/javascript"></script>
<script>
  jQuery('#sequra_identity_form_popup_trigger').appendTo('#sequra');
  SequraHelper.preparePopup();
</script>
<style>
#sequra {background: url(/includes/modules/payment/SeQura/view/img/7dias.png) no-repeat center 0;background-size: 48px;}
#sequra_factura_popup {
    display: none;
    min-width: 320px;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: black;
    background: rgba(0, 0, 0, 0.8);
    z-index: 99998;
    overflow: auto;
    font-size: 12px;
    font-style: normal;
    font-variant: normal;
    font-weight: 400;
}
div.sequra_white_content:hover ,div.sequra_white_content  DIV:hover {
    background: #fff;
}
div.sequra_white_content {
    min-width: 320px;
    position: relative;
    width: 50%;
    padding: 0;
    border-radius: 1em;
    -moz-border-radius: 1em;
    -webkit-border-radius: 1em;
    background-color: white;
    overflow: auto;
    font-size: 120%;
    line-height: 1.2em;
    margin: 5% auto 0 auto;
    z-index: 99999;
    text-align: left;
    float:  none;
    height: auto;    
}

div.sequra_content_popup {
    overflow: hidden;
    padding: 1em 1.5em;
    float: none;
    height: auto;
    padding: 0 10px 10px;
    width: auto;
    border: none;
    z-index: -1;
}
div.sequra_image_wrapper{
    border: none;
}
div.sequra_white_content h4 {
    font-size: 1.7em;
    margin-top: 1em;
    line-height: normal;
    margin-left: 15%;
    color: #000;
    background: none;
}
</style>