{css_to_load}
{js_to_load}
<script type="text/javascript">
jQuery.getJSON('{costs_json_url}',function (json){
    SequraCreditAgreements({
        costs_json: json,
        product: 'pp3',
        //Personalizar si hace falta
        currency_symbol_l: '{symbol_left}',
        currency_symbol_r: '{symbol_right}',
        decimal_separator: '{decimal_point}',
        thousands_separator: '{thousands_point}'
    });
    SequraCreditAgreementsInstance.get({total-amount});
    SequraPartPaymentMoreInfo.draw(false);
});
</script>
<style>
    #sequra_pp {background: url(/includes/modules/payment/SeQura/view/img/sequra.gif) no-repeat center 0}
    div.sequra_white_content h4 {margin-top: 1em;}
</style>