{identity_form}
<script type="text/javascript">
    (function(){

        setTimeout(function(){
            var sequraCallbackFunction = function() {
                document.location.href = '{back}'
            };
            
            window.SequraFormInstance.setCloseCallback(sequraCallbackFunction);
            window.SequraFormInstance.show();
        }, 300); 

        
    })();
</script>