$(document).ready(function(){
message_index = $("#especial").val();
if (message_index==0)	{
	$("#especial_razon").hide();	
}

$("#especial").change(function() 
    { 
        message_index = $("#especial").val(); 
        if (message_index == 0) 
            $("#especial_razon").hide();
		else
			{
			$("#especial_razon").show();
			$("#especial_razon").focus();
			$("#especial_razon").select();
			}
    });						   
});