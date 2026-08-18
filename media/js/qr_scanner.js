/**
 * Factory générique de scanner QR code (html5-qrcode), sur le modèle de FileUpload.create()
 * (file_upload.js) : chaque vue déclare ses propres identifiants d'éléments plutôt que de
 * dépendre d'IDs figés.
 *
 * Utilisé par :
 * - la vue Adhésion (adhesions.js)      : scan de la carte licence pour préremplir le formulaire
 * - la vue Profil   (profil_brevets.js) : scan de la carte licence pour importer les brevets
 *
 * Les instances créées sont accessibles via window.GdaQrScanners[name].
 */
const QrScanner = (function () {

  /**
   * @param {string} name    Clé d'enregistrement dans window.GdaQrScanners
   * @param {Object} options
   * @param {string} options.openBtnId    Bouton qui ouvre le scanner
   * @param {string} options.closeBtnId   Bouton qui ferme le scanner
   * @param {string} options.modalId      Conteneur plein écran (classe .qr-modal)
   * @param {string} options.readerId     Div dans lequel html5-qrcode injecte le flux vidéo
   * @param {Function} options.onScan     Callback appelé avec le texte décodé, scanner déjà arrêté
   */
  function create(name, options) {
    const openBtn = document.getElementById(options.openBtnId);
    const closeBtn = document.getElementById(options.closeBtnId);
    const modal = document.getElementById(options.modalId);

    // Une vue qui ne rend pas le scanner (ex: page sans bouton) ne doit pas casser le reste du script.
    if (!openBtn || !closeBtn || !modal) { return null; }

    let scanner;
    let isRunning = false;

    function stop() {
      if (scanner && isRunning) {
        try {
          scanner.stop()
            .then(() => { scanner.clear(); })
            .catch((err) => { console.debug('Erreur arrêt scanner:', err); })
            .finally(() => {
              isRunning = false;
              modal.classList.remove('active');
            });
        } catch (err) {
          console.debug('Erreur synchrone arrêt scanner:', err);
          isRunning = false;
          modal.classList.remove('active');
        }
      } else {
        // Fermer la modale en toutes circonstances
        isRunning = false;
        modal.classList.remove('active');
      }
    }

    function start() {
      modal.classList.add('active');

      scanner = new Html5Qrcode(options.readerId);
      scanner.start(
        { facingMode: 'environment' }, // caméra arrière
        {
          fps: 2,
          qrbox: (viewfinderWidth, viewfinderHeight) => {
            const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
            return { width: minEdge * 0.8, height: minEdge * 0.8 }; // 80% de l'écran
          },
        },
        (decodedText) => {
          // Fermer la modale automatiquement avant de traiter le résultat
          stop();
          if (typeof options.onScan === 'function') {
            options.onScan(decodedText);
          }
        },
        () => {
          // erreurs de scan (image floue, pas de QR dans le cadre) : sans intérêt, on ignore
        }
      ).then(() => {
        isRunning = true;
      }).catch((err) => {
        // Erreur d'initialisation (ex: refus d'accès à la caméra)
        console.error('Erreur initialisation scanner:', err);
        isRunning = false;
        stop();
        Joomla.renderMessages({ error: [Joomla.Text._('COM_GDA_QRCODE_CAMERA_ERROR')] });
      });
    }

    openBtn.addEventListener('click', start);
    closeBtn.addEventListener('click', stop);

    const instance = { start, stop };

    window.GdaQrScanners = window.GdaQrScanners || {};
    window.GdaQrScanners[name] = instance;

    return instance;
  }

  return { create };
})();
