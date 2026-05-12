// Al hacer clic en la lupa, almacenar el orderId en localStorage
document.querySelectorAll('.lupa-icon').forEach(function(element) {
	element.addEventListener('click', function(event) {
		const orderId = event.currentTarget.dataset.orderId; // Obtener el ID del pedido
		localStorage.setItem('selectedOrderId', orderId);
	});
});

// Detectar el evento "load" para manejar el desplazamiento hacia atrás
window.addEventListener('load', function() {
	const currentPath = window.location.pathname;
	const urlParams = new URLSearchParams(window.location.search);

	// Si estamos en la página de edición (orders.php con action=edit y oID)
	if (currentPath.includes('/orders.php') && urlParams.get('action') === 'edit' && urlParams.has('oID')) {
		// No hacemos nada aquí, ya que solo estamos editando
	} else if (localStorage.getItem('selectedOrderId')) {
		// Si venimos de una edición y tenemos un pedido almacenado, desplazarse a él
		handleScrollToOrder(); // Desplazar al pedido seleccionado al volver al listado
	}

	// Limpiar localStorage si no estamos en orders.php
	if (!currentPath.includes('/orders.php')) {
		localStorage.removeItem('selectedOrderId');
	}
});

// Función para desplazarse hasta el pedido seleccionado y hacer que parpadee suavemente
function handleScrollToOrder() {
	const selectedOrderId = localStorage.getItem('selectedOrderId');
	if (selectedOrderId) {
		const orderRow = document.querySelector(`tr[data-order-id="${selectedOrderId}"]`);
		if (orderRow) {
			orderRow.scrollIntoView({ behavior: 'smooth', block: 'center' });

			// Guardar el color de fondo original de cada celda
			const originalColors = [];
			orderRow.querySelectorAll('td').forEach((cell, index) => {
				originalColors[index] = cell.style.backgroundColor || ''; // Guardar color original
				cell.classList.add('blinking'); // Añadir la clase para la transición suave
			});

			// Iniciar el parpadeo (alternar colores) cada 500ms con suavizado
			let blinkCount = 0;
			const blinkInterval = setInterval(() => {
				orderRow.querySelectorAll('td').forEach((cell) => {
					cell.style.backgroundColor = (blinkCount % 2 === 0) ? 'yellow' : originalColors[cell.cellIndex]; // Alternar entre amarillo y el color original con transición
				});
				blinkCount++;

				// Detener el parpadeo después de 4 ciclos (2 segundos)
				if (blinkCount === 6) {
					clearInterval(blinkInterval);
					// Restaurar el color original al finalizar con la transición suave
					orderRow.querySelectorAll('td').forEach((cell, index) => {
						cell.style.backgroundColor = originalColors[index];
						cell.classList.remove('blinking'); // Quitar la clase de transición
					});
				}
			}, 1000); // Intervalo de parpadeo (1 segundo para dar más tiempo al suavizado)

			// Eliminar el localStorage después de hacer el scroll
			localStorage.removeItem('selectedOrderId');
		}
	}
}

// No realizar evento click en la fila cuando se pulsa un td con checkbox
$(".checkbox_orders").click(function(e) {
  e.stopPropagation();
});

// Cuando pulsamos el checkbox all
$("#orders_all").click(function(e) {
  if ($(this).is(':checked'))
    $("tbody .checkbox_orders input:not(:checked)").click();
  else
    $("tbody .checkbox_orders input:checked").click();
});

// === Selección múltiple de pedidos (Shift+Click + Drag Select) ===
(function() {
  let isMouseDown = false;
  let dragging = false;   // <- nuevo flag
  let checkState = null;
  let lastChecked = null;

  function pintarFila($cb, $tr) {
    if ($cb.is(":checked")) {
      $tr.css("background", "#cceaff");
      $tr.effect('highlight', {color: "#b1defe"}, 250, function () {
        $tr.css("background", "#d8efff");
      });
      $tr.attr("onmouseout", "");
      $tr.attr("onmouseover", "");
    } else {
      $tr.css("background", "");
      $tr.attr("onmouseout", "rowOutEffect(this)");
      $tr.attr("onmouseover", "rowOverEffect(this)");
    }
  }

  const $checkboxes = $("tbody .checkbox_orders input");

  // Mousedown solo prepara el drag
  $("tbody .checkbox_orders").on("mousedown", function(e) {
    if (e.shiftKey) return; // si es ShiftClick, que lo maneje el click normal

    const $cb = $(this).find("input");
    isMouseDown = true;
    dragging = false;
    checkState = !$cb.is(":checked");
    lastChecked = $cb[0];
    e.preventDefault(); // <- evitamos texto seleccionado, pero no bloqueamos click shift
  });

  // Arrastrando
  $("tbody .checkbox_orders").on("mouseenter", function() {
    if (isMouseDown && lastChecked) {
      dragging = true;
      const $cb = $(this).find("input");
      const start = $checkboxes.index($cb);
      const end   = $checkboxes.index(lastChecked);
      const min   = Math.min(start, end);
      const max   = Math.max(start, end);

      $checkboxes.slice(min, max + 1).each(function() {
        $(this).prop("checked", checkState).trigger("change");
      });
    }
  });

  // Mouseup = fin arrastre
  $(document).on("mouseup", function() {
    isMouseDown = false;
  });

  // Click normal y Shift+Click
  $checkboxes.on("click", function(e) {
    // Si fue drag, cancelamos el click para no invertir
    if (dragging) {
      e.preventDefault();
      dragging = false;
      return;
    }

    // Shift+Click = seleccionar rango
    if (e.shiftKey && lastChecked) {
      const start = $checkboxes.index(this);
      const end   = $checkboxes.index(lastChecked);
      const min   = Math.min(start, end);
      const max   = Math.max(start, end);

      $checkboxes.slice(min, max + 1).each(function() {
        $(this).prop("checked", $(lastChecked).is(":checked")).trigger("change");
      });
    }

    lastChecked = this;
  });

  // Pintar siempre que cambie
  $checkboxes.on("change", function() {
    pintarFila($(this), $(this).closest("tr"));
  });
})();

// Mostrar/ocultar check extra en cambio de estado
$("#changeStatus select[name='status']").change(function (e) {
  let status = $(this).val();
  if (status == 9 || status == 13)
    $("#changeStatus .check").show();
  else
    $("#changeStatus .check").hide();
});
