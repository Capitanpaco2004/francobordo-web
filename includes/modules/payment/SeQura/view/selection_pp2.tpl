<b>Todos los costes incluidos.</b><br/>
Paga ahora <b>{down_payment_total}</b> y después...
<div id="pp2"><ul style="list-style: none"></ul></div>
<script type="text/javascript">
SequraCreditAgreements(
    {
        product: 'pp2',
        //Personalizar si hace falta
        currency_symbol_l: '',
        currency_symbol_r: ' EUR',
        decimal_separator: ',',
        thousands_separator: '.'
    }
);

(jQuery(function (){
    SequraCreditAgreementsInstance.get({total-amount});
    var max = SequraCreditAgreementsInstance.creditAgreements.length;
    for (var i=0;i<max;i++){
        var ca = SequraCreditAgreementsInstance.creditAgreements[i];
        $('#pp2 ul').append("<li><input onclick='$(\"input[value=sequra_pp]\").click()' type='radio' name='instalment_count' value='"+i+"'/> "+ca['instalment_count']+" mensualidades de <span id='sequra_it_"+i+"'>"+ca['instalment_total']['string']+'<span></li>');
    }
    SequraPartPaymentMoreInfo.draw();
}));
</script>
<a rel="sequra_partpayments_popup" style="margin-top:0px">+ info</a>