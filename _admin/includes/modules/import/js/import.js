$(document).ready(function() {
	if ($('.select2').length > 0) {
		$('.select2').select2();

		$('.select2').on('change.select2', function (e) {
			$.ajax({
				method: "POST",
				url: "import.php?module=" + $(this).data('module') + "&action=mapping",
				data: {id:$(this).data('id'),selected:e.val}
			});
		});
	}

	$(".chg-stts a").click(function(e) {
		e.stopPropagation();

		var el = $(this);

		if (el.data("action") == "enable") {
			if (! confirm('Vas a activar la categoría para importar los productos de ' + el.data("title") + ', ¿deseas continuar?')) {
				return false;
			}
		} else if (el.data("action") == "disable") {
			if (! confirm('En la próxima importación se van a eliminar los productos asociados a ' + el.data("title") + ', ¿deseas continuar?')) {
				return false;
			}
		}

		$.ajax({url: $(this).attr("href"), success: function() {
			el.parent().find("img").each(function(i) {
				if ($(this).attr("src").match(/light/i)) {
					$(this).attr("src", $(this).attr("src").replace("_light", ""));
				} else {
					$(this).attr("src", $(this).attr("src").replace(".png", "_light.png"));
				}
			});
		}});

		return false;
	});

    if ($(".fa-check-circle").length > 0) {
		var el = $(".fa-check-circle").parent();
		var regex = new RegExp('(lanzado)');

		if (regex.test(el.html())) {
			var text = el.html();

			window.setInterval(function() {
				$.ajax({url: "import.php?action=log", success: function(html) {
					if (html != "") {
						el.html(text + html);
					}
				}});
			}, 2000);
		}
	}

	$("#cnfg_clen").click(function(e) {
		e.stopPropagation();

		if (confirm('¿Estás seguro que deseas realizar la limpieza de datos?')) {
			$("#cnfg_clen_load").find("img").css("display", "inline-block");
			
			$.ajax({
				url: $(this).attr('href'),
				context: document.body
			}).done(function() {
				$("#cnfg_clen_load").find("img").css("display", "none");
			});

			window.setInterval(function() {
				$.ajax({url: "import.php?action=log", success: function(html) {
					if (html != "") {
						$("#cnfg_clen_load").find("label").html(html.replace("<br />", ""));
					}
				}});
			}, 2000);
		}

		return false;
	});
});