document.addEventListener('DOMContentLoaded', function () {
  const carousel = document.getElementById('trombinoscopeCarousel');
  const navButtons = document.querySelectorAll('#trombinoscopeNav .nav-link');

  if (!carousel || !navButtons.length) {
    return;
  }

  // Bootstrap ne synchronise pas nativement la classe "active" d'une nav personnalisée avec la
  // slide affichée (contrairement aux .carousel-indicators) : on le fait ici, même pattern que
  // #wizardNav (media/com_gdadhesions/js/secretariat.js).
  carousel.addEventListener('slid.bs.carousel', function (event) {
    navButtons.forEach(function (btn) {
      btn.classList.remove('active');
    });

    if (navButtons[event.to]) {
      navButtons[event.to].classList.add('active');
    }
  });
});
