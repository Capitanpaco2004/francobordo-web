document.addEventListener('DOMContentLoaded', function () {
	/**
	 * Inicialización de Tooltips
	 */
	tippy('.status-pill', {
		allowHTML: true,
		interactive: true,
		interactiveDebounce: 50,
		theme: 'light-border',
		placement: 'top',
		animation: 'scale',
		maxWidth: 350,
	});

	/**
	 * Selección múltiple de checkboxes
	 */
	$('#all_check').on('change', function () {
		$('input[name="ocID[]"]').prop('checked', this.checked);
	});

	/**
	 * Confirmaciones antes de acciones individuales
	 */
	document.addEventListener('click', function (e) {
		const target = e.target.closest('[data-confirm]');
		if (target) {
			if (!confirm(target.getAttribute('data-confirm'))) {
				e.preventDefault();
			}
		}
	});

	/**
	 * Acciones masivas
	 */
	$('.masv').on('click', 'a[data-action]', function (e) {
		e.preventDefault();

		const form = $(this).closest('form');
		const selected = form.find('input[name="ocID[]"]:checked');

		if (selected.length === 0) {
			alert('Para realizar alguna de estas operaciones necesitas seleccionar algún registro.');
			return;
		}

		if (confirm($(this).data('question'))) {
			form.attr('action', $(this).data('action')).submit();
		}
	});

	/**
	 * Redirección por doble clic en filas (ignorando checkbox y tooltip)
	 */
	document.querySelectorAll('table.xform tbody tr').forEach(row => {
		row.addEventListener('dblclick', event => {
			if (event.target.type === 'checkbox' ||
				event.target.closest('input[type="checkbox"]') ||
				event.target.closest('.status-pill')) {
				return;
			}

			const href = row.getAttribute('data-href');
			if (href) {
				window.location = href;
			}
		});
	});
});
