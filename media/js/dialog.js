/**
 * Boîtes de dialogue du composant, au-dessus de JoomlaDialog (joomla.dialog).
 *
 * Utilisées à la place de Joomla.renderMessages() lorsque le message doit être vu à coup sûr :
 * le bandeau de messages Joomla s'affiche en haut de page et passe inaperçu sur mobile, où la
 * zone visible est souvent bien plus bas (typiquement après un scan de QR code).
 */
const GdaDialog = (function () {

  /** Échappe le texte injecté dans popupContent, qui est interprété comme du HTML. */
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  }

  /** Corps de la popup : message principal, et lignes secondaires optionnelles. */
  function buildContent(message, details) {
    let html = '<div class="p-3"><p class="mb-0">' + escapeHtml(message) + '</p>';

    if (details) {
      html += '<p class="mb-0 mt-2 text-muted">' + escapeHtml(details) + '</p>';
    }

    return html + '</div>';
  }

  function open(options) {
    return import('joomla.dialog').then(({ default: JoomlaDialog }) => {
      const dialog = new JoomlaDialog({
        popupType: 'inline',
        textHeader: options.title || '',
        popupContent: buildContent(options.message, options.details),
        width: '32rem',
      });

      dialog.popupButtons = options.buttons(dialog);
      dialog.show();

      return dialog;
    });
  }

  /**
   * Popup d'information, un seul bouton de fermeture.
   */
  function alert(title, message, details) {
    return open({
      title,
      message,
      details,
      buttons: (dialog) => [{
        label: Joomla.Text._('JOK') || 'Ok',
        onClick: () => dialog.destroy(),
        className: 'btn btn-success ms-2',
      }],
    });
  }

  /**
   * Popup de confirmation : onConfirm n'est appelé que sur le bouton de validation.
   */
  function confirm(title, message, onConfirm, details) {
    return open({
      title,
      message,
      details,
      buttons: (dialog) => [
        {
          label: Joomla.Text._('COM_GDA_CANCEL') || 'Annuler',
          onClick: () => dialog.destroy(),
          className: 'btn btn-secondary',
        },
        {
          label: Joomla.Text._('COM_GDA_CONFIRM') || 'Confirmer',
          onClick: () => {
            dialog.destroy();
            if (typeof onConfirm === 'function') { onConfirm(); }
          },
          className: 'btn btn-success ms-2',
        },
      ],
    });
  }

  return { alert, confirm };
})();
