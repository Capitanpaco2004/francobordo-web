{identity_form}
<script type="text/javascript">
    function wait_for_form_instance(){
        if(typeof window.SequraFormInstance === 'undefined' ){
            setTimeout(wait_for_form_instance, 300);
        } else {
            var sequraCallbackFunction = function() {
                window.SequraFormInstance.defaultCloseCallback();
                jQuery('.restorePayment a').click();
            };
            window.SequraFormInstance.setCloseCallback(sequraCallbackFunction);
            window.SequraFormInstance.show();
        }
    }
    function sq_reload_checkout() {
        setTimeout( function () {
             location.reload();
        }
        , 800);
    }
    (function(){
        wait_for_form_instance();
        jQuery('.sequra_splitpayment span').on('click',sq_reload_checkout)
        jQuery('.sequra_pp span').on('click',sq_reload_checkout)
    })();
</script>