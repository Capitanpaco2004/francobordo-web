function getBrowser() {
	var ua = navigator.userAgent, tem,
		M = ua.match(/(opera|chrome|safari|firefox|msie|trident(?=\/))\/?\s*(\d+)/i) || [];
	if (/trident/i.test(M[1])) {
		tem = /\brv[ :]+(\d+)/g.exec(ua) || [];
		return {name: 'IE', version: (tem[1] || '')};
	}
	if (M[1] === 'Chrome') {
		tem = ua.match(/\bOPR|Edge\/(\d+)/)
		if (tem != null) {
			return {name: 'Opera', version: tem[1]};
		}
	}
	M = M[2] ? [M[1], M[2]] : [navigator.appName, navigator.appVersion, '-?'];
	if ((tem = ua.match(/version\/(\d+)/i)) != null) {
		M.splice(1, 1, tem[1]);
	}
	return {
		name: M[0],
		version: M[1]
	};
}

var browser = getBrowser();
var acceptNotifications = true;
if (browser.name.toLowerCase() == 'ie' && browser.version < 12) {
	acceptNotifications = false;
}
if (browser.name.toLowerCase() == 'safari') {
	acceptNotifications = false;
}

if (notificationsActive == true && acceptNotifications == true) {

	firebase.initializeApp(config);
	var messaging = firebase.messaging();
	messaging.onTokenRefresh(function () {
		messaging.getToken()
			.then(function (refreshedToken) {
				//console.log('Token renovado.');
				setTokenGuardado(false);
				guardarToken(refreshedToken);
				resetUI();
			})
			.catch(function (err) {
				//console.log('Unable to retrieve refreshed token ', err);
				mostrarLog('Unable to retrieve refreshed token ', err);
			});
	});


	messaging.onMessage(function (payload) {
		//console.log("Message received. ", payload);
		mostrarMensaje(payload);
	});

	function resetUI() {
		//borrarMensajes();
		mostrarLog('Cargando...');
		messaging.getToken()
			.then(function (currentToken) {
				if (currentToken) {
					guardarToken(currentToken);
					updateUIForPushEnabled(currentToken);
				} else {
					mostrarLog('No hay token, se necesita uno ');
					updateUIForPushPermissionRequired();
					setTokenGuardado(false);
				}
			})
			.catch(function (err) {
				mostrarLog('Error obteniendo el token ', err);
				setTokenGuardado(false);
			});
	}


	function mostrarLog(currentToken) {
		jQuery('#token').text(currentToken);
	}

	function guardarToken(currentToken) {
		if (!isTokenGuardado()) {
			//console.log('Guardando token en el servidor...');
			jQuery.post("notifications.php", {
				token: currentToken,
				method: 'save-token'
			});
			setTokenGuardado(true);
		}
	}

	function isTokenGuardado() {
		return window.localStorage.getItem('sentToServer') == 1;
	}

	function setNoMostrar(val) {
		//console.log('Desahibilitar token')
		window.localStorage.setItem('noMostrarNotificacion', val);
	}

	function setTokenGuardado(sent) {
		window.localStorage.setItem('sentToServer', sent ? 1 : 0);
	}

	function obtenerPermiso() {
		//console.log('Obteniendo permiso...');
		messaging.requestPermission()
			.then(function () {
				//console.log('Notification permission granted.');
				resetUI();
			})
			.catch(function (err) {
				//console.log('Unable to get permission to notify.', err);
			});
	}

	function borrarToken() {
		messaging.getToken()
			.then(function (currentToken) {
				jQuery.post("notifications.php", {
					token: currentToken,
					method: 'delete-token'
				});
				messaging.deleteToken(currentToken)
					.then(function () {
						//console.log('Token borrado.');
						setTokenGuardado(false);
						resetUI();
					})
					.catch(function (err) {
						//console.log('Unable to delete token. ', err);
					});
			})
			.catch(function (err) {
				//console.log('Error retrieving Instance ID token. ', err);
				mostrarLog('Error retrieving Instance ID token. ', err);
			});
	}

	function mostrarMensaje(payload) {
		//console.log(payload.data)
		Push.create(payload.data.title, {
			body: payload.data.body,
			icon: payload.data.icon,
			link: payload.data.click_action,
			timeout: 1200000,
			requireInteraction: true,
			onClick: function () {
				window.focus();
				this.close();
			}
		});
	}


	function updateUIForPushEnabled(currentToken) {
		mostrarLog(currentToken);
		jQuery('#token').html('<a href="javascript:borrarToken()">reset</a>');
	}

	function updateUIForPushPermissionRequired() {
		preguntarNotificationPermissions()
	}

	resetUI();

	function preguntarNotificationPermissions() {
		if (!isTokenGuardado()) {
			jQuery.post("notifications.php", {
				'method': 'text-notifications',
				'push': Push.Permission.get()
			}, function (data) {
				if (window.localStorage.getItem('noMostrarNotificacion') != null) {
					dateToday = new Date();

					datePermission = new Date(window.localStorage.getItem('noMostrarNotificacion'));
					datePermission.setDate(datePermission.getDate() + 7);
					if (dateToday.getTime() > datePermission.getTime()) {
						setNoMostrar(0)
					}
				}

				if (data.platform.toLowerCase() != 'iphone' && (window.localStorage.getItem('noMostrarNotificacion') === null || parseInt(window.localStorage.getItem('noMostrarNotificacion')) == 0)) {
					jQuery('<div id="push" class="notificationsQuestion Transition"><div class="web-cntd"><div class="titu">' + data.text + '</div>' + data.buttons + '</div></div>').appendTo("body");

					setTimeout(function () {
						jQuery('.notificationsQuestion').addClass('Show');
						jQuery("body").addClass('push');
					}, 1500);
				}

			});
		}

	}


	function notificationsFunctions() {
		jQuery("body").on("click", "#deny-notifications", function () {
			jQuery(this).addClass('Loading')
			jQuery.post("notifications.php", {
				'method': 'deny-notifications',
				'push': Push.Permission.get()
			}, function (data) {
				jQuery('.notificationsQuestion').removeClass('Show');
				jQuery('body').removeClass('push');
				setNoMostrar(new Date())
			});
		})
		jQuery("body").on("click", "#accept-notifications", function () {
			jQuery(this).addClass('Loading')
			Notification.requestPermission().then(function (result) {
				jQuery.post("notifications.php", {
					'method': 'accept-notifications',
					'push': Push.Permission.get()
				}, function (data) {
					jQuery('.notificationsQuestion').removeClass('Show')
					jQuery('body').removeClass('push');
					mostrarNotificacion(data.title, data.text)
					obtenerPermiso()
					setNoMostrar(new Date())
				});
			});

		})
		jQuery("body").on("click", ".MnTp-Nottify .notificacionesActivas", function () {
			jQuery('.MnTp-Nottify').toggleClass('Active')
		})

	}

	function checkNotificaciones() {
		return (Push.Permission.get() == "granted");
	}

	function mostrarNotificacion(titulo, texto, icono, url, id) {
		if (checkNotificaciones() == true) {
			Push.create(titulo, {
				body: texto,
				icon: icono,
				timeout: 4000,
				//link: url,
				onClick: function () {
					window.focus();
					this.close();
					if (url != '' && parseInt(id) > 0) {
						jQuery.post("notifications.php", {
							'method': 'click-notification',
							'id': id
						}, function (data) {
							location.href = url
						});
					}
				}
			});
		}
	}
}
