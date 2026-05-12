<link rel="stylesheet" type="text/css" href="includes/modules/payment/SeQura/view/css/sequrapayment.css" />
<style>
    #fich-cntd {min-height: 348px;}
    #fich .prco-cntd{height: 208px;}
    #sequra_partpayment_teaser {
      font-size: 12px !important;
      margin-top: 20px;
        margin-left: -50px;
        width:100% !important;
        font-family: Arial, Tahoma, Helvetica, sans-serif;
        font-weight: 200;
        line-height: 15px;
    }
    .sequra-pricelike, #sequra_partpayment_teaser span {
      font-size: 13px !important;
    }
    #sequra_partpayment_teaser_low {
        width: 166px;
        height: 33px;
        background: url(../img/teaser_pp_low.png) center no-repeat;
        margin: auto;
        padding-top: 80px;
    }
    div.sequra_white_content h4 {margin-top: 1em;background: transparent;}    
</style>
<script src="includes/modules/payment/SeQura/view/js/sequrapayment.js"></script>
<div id="sequra_partpayment_teaser"></div>
<script type="text/javascript">
window.onload = function(){
  SequraCreditAgreements(
          {
            product: 'pp2',
            //Personalizar si hace falta
            currency_symbol_l: '',
            currency_symbol_r: '€',
            decimal_separator: ',',
            thousands_separator: ''
          }
  );

  SequraPartPaymentTeaser(
          {
            min_amount: 50.00,
            container:'#sequra_partpayment_teaser',
            price_container: '.sequra-product-price-js'
          }
  );
}
</script>