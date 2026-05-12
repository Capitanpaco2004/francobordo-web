$(document).ready(function() {
    $('input[name=international]').change(function() {
        if ($(this).is(':checked'))  {
            $('.shipToLocations').addClass('Visible')
        } else {
            $('.shipToLocations').removeClass('Visible')
        }
    })
    $(".select2").select2();

	$('.ebayCheck').click(function() {
		check = $(this)
		flag = (check.hasClass('Check') ? 0 : 1)
		check.removeClass('Check')
		check.addClass('Loading')
		idCategory = parseInt(check.data("id"))
		url = check.data('url')
        if (idCategory > 0) {
            $.get('ebay.php', {
                action: "set-ebay-categories",
                cID: idCategory,
                flag: flag
            }, function( data ) {
				check.removeClass('Loading')
				if (data != '') {
					check.addClass(data)
					check.parents('tr.groupCategory').addClass('Check')
				} else {
					check.parents('tr.groupCategory').removeClass('Check')
				}
			});
            return;
        }
	})
    $('.saveIncremento').click(function() {
        incremento = parseFloat($(this).siblings('.incrementoEbay').val())
        idCategory = parseInt($(this).data("id"))
        $.post("ebay.php", {
            action: "update-ebay-incremento",
            id_category: idCategory,
            incremento: incremento
        });
        return
    })

    $(".changeAjax").change(function() {
		select = $(this)
		select.addClass('Loading')
        sAction = select.data('action')
        idCategory = parseInt(select.data("id"))
        if (idCategory > 0) {
            idValue = select.val()
            $.post("ebay.php", {
                action: sAction,
                id_category: idCategory,
                id_value: idValue
            }, function() {
				select.removeClass('Loading')
			});
            return;
        }

        idProduct = parseInt($(this).data("pid"))
        if (idProduct > 0) {
            idEbay = $(this).val()
            $.post("ebay.php", {
                action: sAction,
                products_id: idProduct,
                id_ebay: idEbay
            }, function() {
				select.removeClass('Loading')
			});
            return;
        }
    })
    $('.Confirm').click(function() {
		ajax = $(this).hasClass('ajaxLink')
        link = $(this).attr('href')
		button = $(this)
        if (confirm('¿Estas seguro?')) {
			if (ajax && !$('input[name=show-no-sync]').is(':checked')) {
				button.text('Quitando...')
				$.get( link, function( data ) {
                    var data = jQuery.parseJSON( data )
					if (parseInt(data.id) > 0) {
						id = '#elemento-' + parseInt(data)
	  				  	$(id).find('td').slideUp(200)
					}

                    if (data.text != true) {
                        alert(data.text)
                    }
                    location.reload();
                    button.text('Quitar')
				});
			} else {
				location.href = link
			}
        }
        return false;
    })
	$('.toolBar select[name=method]').change(function() {
		if ($(this).val() == 'category') {
			$('.categoriesEbayGeneral').css('display', 'inline-block')
		} else {
			$('.categoriesEbayGeneral').css('display', 'hidden')
		}
	})
	$('.changePagination').change(function() {
		location.href = $(this).val()
	})
	$('#checkAll').change(function() {
		$('table.Products td input').prop('checked', $(this).prop('checked'))
	})

    $('#stopSync').click(function() {
        $.ajaxq.abort('ebay');
    })
    if ("undefined" !== typeof productsSync) {
        n = 0
		nAlt = 1;
        total = productsSync.length
        $.each(productsSync, function(key, value) {
            var start = new Date().getTime();

			$.ajaxq('ebay', {
	            type: "POST",
	            url: "ebay.php",
				data : {
	                action: "sync",
	                products_id: value
	            },
	            success: function(salida) {
					++n
					$('<div style="display: none;">' + salida + '</div>').prependTo('#Log').slideDown(200)
					if (n == total) {
						$('#infoLog').text('Exportación finalizada')
					} else {
						$('#infoLog').text('Exportando ' + n + ' de ' + total)
					}
					percent = (n * 100) / total
                    $('#Percent p').css('width', percent + '%')
					$('#Percent p').text(parseInt(percent) + '%')

	            },
	            complete: function(jqXHR, textStatus) {

				/*	++nAlt
					var contador = total - (nAlt - 1);
	                var duration = (new Date().getTime() - start) / 1000;
					var estimado = parseInt(contador * duration)

					if (estimado > 60) {
						estimado = (estimado / 60)
						texto = duration.toFixed(2) + ' min. <em>(Último: ' + duration +')</em>'
					} else {
						texto = estimado + ' seg. <em>(Último: ' + duration +')</em>'
					}

					$('#timeEstimated').html(texto)*/

	            }
	        });
        });


    }

    if ($('#chart').length > 0) {
        var ctx = document.getElementById("chart").getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartDias,
                datasets: [{
                    label: '# llamadas',
                    data: chartLlamadas,
                    borderWidth: 1
                }]
            }
        });
    }
});
