$( document ).ready(function() {
	//submit agree
	/*$('#license_agreement').bind('scroll', function(){
		if ($(this).scrollTop() + $(this).height() >= $(this)[0].scrollHeight - 4) {
			$('#submit_agree').show();
			$('#license_agreement').unbind('scroll');
		  }
	});	*/
	
	$('#submit_agree').click(function(){
		if(!$("input[name='agree_license_agreement']:checked").length){
			alert(UPS_LICENSE_AGREEMENT_ERROR);
			return false;
		}
		return true;
	});	
	//main tabs
	$('.nav-tabs > li > a').click(function(){
		$('.tab-content').hide();
		$('.nav-tabs > li > a').removeClass('active');
		$('.tab-content').removeClass('active');
		$(this).addClass('active');
		$($(this.hash)).addClass('active').show();
		return false;
	});
	//account tabs
	$('.account-nav-tabs > li > a').click(function(){
		$('.account-tab-content').hide();
		$('.account-nav-tabs > li > a').removeClass('active');
		$('.account-tab-content').removeClass('active');
		$(this).addClass('active');
		$($(this.hash)).addClass('active').show();
		return false;
	});
	//shipping tabs	
	$('.shipping-nav-tabs > li > a').click(function(){
		$('.shipping-tab-content').hide();
		$('.shipping-nav-tabs > li > a').removeClass('active');
		$('.shipping-tab-content').removeClass('active');
		$(this).addClass('active');
		$($(this.hash)).addClass('active').show();
		return false;
	});	
	//service - accounts list
	$('#id_account').change(function(){
		$.ajax({
			type: "POST",
			url: ups_ajax_url,
			async: true,
			cache: false,
			data: {
				'action': 'getServices',
				'id_account': $(this).val(),
			},
			error:function(msg){
				$('#services .msg').html('<font color="red"><b>'+msg+'</b></font>');
			},
			success:function(response){
				$('#id_service').html(response);
				updateServiceDest($('#id_service').val(), $('#id_account').val());
			}
		});
	});	
	//service - services list
	$('#id_service').change(function(){
		updateServiceDest($(this).val(), $('#id_account').val());
	});	
	//tracking by
	$("input[name='tracking_by']").change(function(){
		trackingBy();
	});
	$(".export").click(function(){
		setTimeout(function(){
		    location.reload();
		},5000); 
	});
	trackingBy();
});

function updateServiceDest(code, id_account){
	$.ajax({
		type: "POST",
		url: ups_ajax_url,
		async: true,
		cache: false,
		data: {
			'action': 'getServiceDest',
			'code': code,
			'id_account': id_account,
		},
		error:function(msg){
			$('#services .msg').html('<font color="red"><b>'+msg+'</b></font>');
		},
		success:function(response){
			$('#serviceDestCountriesList').html(response);
		}
	});
}

function trackingBy(){
	if(parseFloat($("input[name='tracking_by']:checked").val())){
		$('#cron_link_row').show();
	}
	else{
		$('#cron_link').val('');
		$('#cron_link_row').hide();
	}
		
}