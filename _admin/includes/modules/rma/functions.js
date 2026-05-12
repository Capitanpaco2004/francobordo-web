document.addEventListener('DOMContentLoaded', function() {
	var rmaChangeStatus = document.querySelectorAll('.rmaChangeStatus');
	var rmaRemove = document.querySelectorAll('.rmaRemove');

	rmaChangeStatus.forEach(function(item) {
		item.addEventListener('click', function() {
			var rmaStatus = this.closest('.rmaStatus');
			rmaStatus.classList.toggle('Active');
		});
	});

	rmaRemove.forEach(function(item) {
		item.addEventListener('click', function(event) {
			event.preventDefault();
			var sUrl = this.getAttribute('href');
			if (confirm('¿Estás seguro de que deseas borrar el elemento?')) {
				location.href = sUrl;
			}
		});
	});
});
