{css_to_load}
{js_to_load}
<div id="sequra_partpayment_teaser"></div>
<script type="text/javascript">
  function waitForJquery(){
    if(typeof jQuery === 'undefined'){
      setTimeout(waitForJquery,250);
      return;
    }
    jQuery.getJSON('{costs_json_url}',function (json){
      SequraCreditAgreements(
        {
          costs_json: json,
            product: 'pp3',
            //Personalizar si hace falta
            currency_symbol_l: '{symbol_left}',
            currency_symbol_r: '{symbol_right}',
            decimal_separator: '{decimal_point}',
            thousands_separator: '{thousands_point}'
          }
      );

      SequraPartPaymentTeaser(
              {
                min_amount: 50.00,
                container:'#sequra_partpayment_teaser',
                price_container: '.sequra-product-price-js'
              }
      );
      SequraPartPaymentTeaserInstance.preselect(20);
    });
  }
  waitForJquery();
</script>