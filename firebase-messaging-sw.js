importScripts('https://www.gstatic.com/firebasejs/4.6.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/4.6.2/firebase-messaging.js');

var config = {
    messagingSenderId: "707813788519"
};

firebase.initializeApp(config);
const messaging = firebase.messaging();

self.addEventListener('notificationclick', e => {
    let found = false;
    let f = clients.matchAll({
        includeUncontrolled: true,
        type: 'window'
    })
        .then(function (clientList) {
            for (let i = 0; i < clientList.length; i ++) {
                if (clientList[i].url === e.notification.data.click_action) {
                    // We already have a window to use, focus it.
                    found = true;
                    clientList[i].focus();
                    break;
                }
            }
            if (! found) {
                clients.openWindow(e.notification.data.click_action).then(function (windowClient) {});
            }
        });
    e.notification.close();
    e.waitUntil(f);
});

messaging.setBackgroundMessageHandler(function(payload) {
    return self.registration.showNotification(payload.data.title, Object.assign({data: payload.data}, payload.data));
});
