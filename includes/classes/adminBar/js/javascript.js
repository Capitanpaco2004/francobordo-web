document.addEventListener('DOMContentLoaded', function () {

  var adminBar = document.getElementById('admin_bar');
  var toggle   = document.querySelector('#admin_bar .admin_bar_close');
  var icon     = toggle ? toggle.querySelector('.fa') : null;

  if (!adminBar || !toggle || !icon) return;

  // --- Leer estado guardado ---
  var savedState = localStorage.getItem('admin_bar_state');

  if (savedState === 'closed') {
    adminBar.classList.remove('open');
    icon.classList.remove('fa-times');
    icon.classList.add('fa-chevron-left');
  } else {
    adminBar.classList.add('open');
    icon.classList.remove('fa-chevron-left');
    icon.classList.add('fa-times');
  }

  // **AHORA sí mostramos la barra**
  adminBar.style.visibility = 'visible';

  // --- Evento click ---
  toggle.addEventListener('click', function () {

    var isOpen = adminBar.classList.toggle('open');

    if (isOpen) {
      icon.classList.remove('fa-chevron-left');
      icon.classList.add('fa-times');
      localStorage.setItem('admin_bar_state', 'open');
    } else {
      icon.classList.remove('fa-times');
      icon.classList.add('fa-chevron-left');
      localStorage.setItem('admin_bar_state', 'closed');
    }
  });

});
