/**
 * Vue Profil : édition des brevets d'un adhérent depuis la carte "Brevets".
 *
 * - ouverture de #brevetsModal : vide puis repeuple les lignes de saisie à partir des brevets
 *   portés par la carte cliquée (attribut data-brevets), ce qui permet de partager une seule
 *   modale entre le profil principal et les profils "on behalf" de la page ;
 * - scan du QR code de la carte licence : import des brevets FFESSM en annule et remplace,
 *   après confirmation si des lignes sont déjà saisies ;
 * - sauvegarde : POST vers profil.saveBrevets, qui renvoie la carte ré-rendue.
 *
 * Dépendances : brevets.js (Brevets), qr_scanner.js (QrScanner), form_modal.js (simpleCallAjax),
 * spinner.js (showspinner/hidespinner).
 */
document.addEventListener('DOMContentLoaded', function () {

  const modalEl = document.getElementById('brevetsModal');
  const idProfilInput = document.getElementById('brevetsIdProfil');

  // Vue sans carte brevets éditable : rien à câbler.
  if (!modalEl || !idProfilInput) { return; }

  /**
   * Peuplement de la modale à l'ouverture, à partir de la carte à l'origine du clic.
   */
  modalEl.addEventListener('show.bs.modal', function (event) {
    const trigger = event.relatedTarget;
    if (!trigger) { return; }

    const idProfil = trigger.getAttribute('data-bs-id_profil') || '0';
    idProfilInput.value = idProfil;

    Brevets.clearBrevets();

    const card = document.querySelector('#brevets_' + idProfil + ' .card');
    let brevets = [];

    try {
      brevets = JSON.parse(card?.dataset.brevets || '[]');
    } catch (err) {
      console.error('data-brevets illisible :', err);
    }

    brevets.forEach((brevet) => Brevets.addBrevet(brevet));
  });

  /**
   * Scanner QR : importe les brevets de la carte licence FFESSM.
   * La tâche serveur vérifie que la licence scannée est bien celle du profil édité, et complète
   * au passage le ffessm_token du profil s'il était vide.
   */
  QrScanner.create('brevets', {
    openBtnId: 'openBrevetsQrScanner',
    closeBtnId: 'closeBrevetsQrScanner',
    modalId: 'brevetsQrModal',
    readerId: 'brevetsQrReader',
    onScan: function (decodedText) {
      const ajaxData = {
        task: 'profil.extractBrevets',
        id_profil: parseInt(idProfilInput.value || '0', 10),
        url: decodedText,
      };
      const csrfTokenName = Joomla.getOptions('csrf.token');
      if (csrfTokenName) { ajaxData[csrfTokenName] = 1; }

      showspinner(modalEl);

      simpleCallAjax(ajaxData, function (response) {
        hidespinner(modalEl);

        const brevets = response.data?.brevets || [];
        const porteur = response.data?.porteur || '';

        if (!brevets.length) {
          Joomla.renderMessages({ warning: [Joomla.Text._('COM_GDA_BREVETS_SCAN_EMPTY')] });
          return;
        }

        // Import en annule et remplace : on ne fusionne pas, la carte licence fait foi.
        // Le titulaire de la carte est rappelé dans la confirmation, pour que l'utilisateur
        // vérifie qu'il importe bien les brevets de la bonne personne.
        // Message en deux clés : les fichiers .ini de Joomla sont lus en INI_SCANNER_RAW, qui
        // n'interprète pas "\n" et casse le parsing du fichier entier — le saut de ligne est
        // donc composé ici.
        const confirmMessage = Joomla.Text._('COM_GDA_BREVETS_CONFIRM_REPLACE').replace('%s', porteur)
          + '\n\n' + Joomla.Text._('COM_GDA_BREVETS_CONFIRM_REPLACE_SUITE');

        if (Brevets.count() > 0 && !window.confirm(confirmMessage)) {
          return;
        }

        Brevets.clearBrevets();
        brevets.forEach((brevet) => Brevets.addBrevet(brevet));
      }, true, () => hidespinner(modalEl));
    },
  });

  /**
   * Sauvegarde : le serveur renvoie la carte "Brevets" ré-rendue (liste + data-brevets à jour).
   */
  document.getElementById('SaveBrevetsModalForm').addEventListener('click', function (e) {
    e.preventDefault();
    showspinner(modalEl);

    const idProfil = idProfilInput.value;
    const formData = new FormData(document.getElementById('brevetsAdminForm'));

    // La carte doit être re-rendue avec la même largeur de colonne que celle qu'elle remplace.
    const oldCard = document.querySelector('#brevets_' + idProfil);
    if (oldCard) {
      formData.append('taille', oldCard.className.replace('h-100', '').trim());
    }

    simpleCallAjax(formData, function (response) {
      hidespinner(modalEl);

      if (oldCard) {
        oldCard.outerHTML = decodeURIComponent(escape(atob(response.data)));
      }

      document.getElementById('btnCloseBrevets').click();
    }, true, () => hidespinner(modalEl));
  });

});
