<div id="sequra_partpayment_teaser"></div>
<script>
{include widget_ini}
function sq_tocents (text) {
		text = text.replace(/^\D*/,'').replace(/\D*$/,'');
		if(text.indexOf(sequraConfigParams.decimalSeparator)<0){
			text += sequraConfigParams.decimalSeparator + '00';
		}
		return parseInt(
			parseFloat(
				text
				.replace(sequraConfigParams.thousandSeparator,'')
				.replace(sequraConfigParams.decimalSeparator,'.')
			).toFixed(2).replace('.', ''),
			10
		);
}
sequraConfigParams['price_src'] = '.sequra-product-price-js';
  sequraConfigParams['widgets'].push(
        {
          product: 'pp3',
          dest_sel: '#sequra_partpayment_teaser',
          theme: 'L'
        },
  );
</script>
<style>
.sequra-promotion-widget {width:320px}
</style>