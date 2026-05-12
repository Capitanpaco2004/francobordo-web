if(typeof sequraConfigParams === 'undefined'){
  sequraConfigParams = {
    merchant:'{merchant}',
    assetKey:'{assetKey}',
    decimalSeparator: '{decimal_point}',
    thousandSeparator: '{thousands_point}',
    scriptUri: '{scriptUri}',
    rebranding: true,
    widgets: [],
  };
  (function(){
    var script = document.createElement("script");
    script.src = "https://s3-eu-west-1.amazonaws.com/shop-assets.sequrapi.com/base/js/sequrapayment_v2.js";
    script.async = true;
    document.getElementsByTagName("head")[0].appendChild(script);
  })();
}
