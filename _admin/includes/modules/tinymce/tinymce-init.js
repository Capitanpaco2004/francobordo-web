function initializeTinyMCEForSelector(selector = 'textarea.tinymce, textarea#tinymce, textarea#tinymce-lng') {
	tinymce.init({
		selector: selector,
		base_url: '../includes/vendor/tinymce/tinymce',
		suffix: '.min',
		skin_url: '../includes/vendor/tinymce/tinymce/skins/ui/oxide',
		language: 'es',
		branding: false,
		height: 600,
		plugins: 'accordion advlist anchor autolink autosave charmap code codesample directionality emoticons help image link lists media nonbreaking pagebreak preview quickbars save searchreplace table visualblocks visualchars wordcount',
		toolbar1: 'undo redo | styles formatselect fontfamily fontsize | forecolor backcolor | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify',
		toolbar2: 'copy paste | bullist numlist outdent indent | link unlink image media table | blockquote hr pagebreak | subscript superscript | emoticons charmape | print | codesample code',
		menubar: 'file edit view insert format tools table help',
		toolbar_mode: 'sliding',
		contextmenu: false,
		resize: true,
		license_key: 'gpl',
		font_family_formats:
			"Andale Mono=andale mono,times;" +
			"Arial=arial,helvetica,sans-serif;" +
			"Arial Black=arial black,avant garde;" +
			"Book Antiqua=book antiqua,palatino;" +
			"Comic Sans MS=comic sans ms,sans-serif;" +
			"Courier New=courier new,courier;" +
			"Georgia=georgia,palatino;" +
			"Helvetica=helvetica;" +
			"Impact=impact,chicago;" +
			"Symbol=symbol;" +
			"Tahoma=tahoma,arial,helvetica,sans-serif;" +
			"Terminal=terminal,monaco;" +
			"Times New Roman=times new roman,times;" +
			"Trebuchet MS=trebuchet ms,geneva;" +
			"Verdana=verdana,geneva;" +
			"Webdings=webdings;" +
			"Wingdings=wingdings,zapf dingbats",
		quickbars_insert_toolbar: 'paste quickimage quicktable | quicklink',
		quickbars_selection_toolbar: 'copy | bold italic underline | fontsize fontfamily styles | forecolor backcolor | alignleft aligncenter alignright alignjustify',
		content_css: '//www.tiny.cloud/css/codepen.min.css',
		image_advtab: true,
		image_caption: true,
		file_picker_types: 'image',
		automatic_uploads: true,
		images_upload_url: 'includes/modules/tinymce/uploadImages.php', // aunque no se usa aquí directamente
		images_upload_handler: function (blobInfo, progress) {
			return new Promise(function (resolve, reject) {
				var xhr = new XMLHttpRequest();
				xhr.withCredentials = false;
				xhr.open('POST', 'includes/modules/tinymce/uploadImages.php');

				// Progreso de carga
				xhr.upload.onprogress = function (e) {
					progress(e.loaded / e.total * 100);
				};

				xhr.onload = function() {
					if (xhr.status !== 200) {
						reject('HTTP Error: ' + xhr.status);
						return;
					}

					try {
						var json = JSON.parse(xhr.responseText);
						if (!json || typeof json.location !== 'string') {
							reject('Invalid JSON: ' + xhr.responseText);
							return;
						}
						resolve(json.location); // Asegúrate que 'location' es la URL final del archivo
					} catch (err) {
						reject('JSON Parse Error: ' + err);
					}
				};

				xhr.onerror = function() {
					reject('Error al subir la imagen.');
				};

				var formData = new FormData();
				formData.append('file', blobInfo.blob(), blobInfo.filename());
				xhr.send(formData);
			});
		},
		paste_as_text: false
	});
}

function initializeTinyMCESimple(selector = 'textarea.tinymce-simple') {
	tinymce.init({
		selector: selector,
		base_url: '../includes/vendor/tinymce/tinymce',
		suffix: '.min',
		skin_url: '../includes/vendor/tinymce/tinymce/skins/ui/oxide',
		language: 'es',
		branding: false,
		height: 200,
		plugins: 'link lists autolink',
		toolbar: 'bold italic underline | alignleft aligncenter alignright | bullist numlist | link unlink | removeformat',
		menubar: false,
		statusbar: false,
		contextmenu: false,
		resize: true,
		license_key: 'gpl',
	});
}

// Inicialización automática al cargar la página
document.addEventListener('DOMContentLoaded', function () {
	initializeTinyMCEForSelector();  // Inicializa todos los que existan al principio
	initializeTinyMCESimple();       // Inicializa los simples (comentarios pedidos, etc.)
});

// Ocultar promoción "Get all features"
const style = document.createElement('style');
style.innerHTML = '.tox-promotion{display:none!important;}';
document.head.appendChild(style);
