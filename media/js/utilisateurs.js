document.addEventListener('DOMContentLoaded', function () {
  const container = document.querySelector('.gda-utilisateurs');
  const table = document.getElementById('tableUtilisateurs');

  if (!container || !table) {
    return;
  }

  const datatableApi = globalThis.simpleDatatables;
  let dataTable = null;

  if (datatableApi && datatableApi.DataTable) {
    dataTable = new datatableApi.DataTable(table, {
      labels: {
        placeholder: 'Rechercher...',
        perPage: 'lignes par page',
        noRows: 'Aucun utilisateur',
        noResults: 'Aucun résultat trouvé',
        info: 'Affichage de {start} à {end} sur {rows} entrées'
      }
    });
  }

  /**
   * Filtre les lignes sur la colonne "Adhésion" (index 0) selon le libellé sélectionné.
   * Un value vide ("Tous") réinitialise le filtre.
   */
  const filterAdhesionStatus = document.getElementById('filterAdhesionStatus');

  if (filterAdhesionStatus && dataTable) {
    filterAdhesionStatus.addEventListener('change', function () {
      dataTable.search(filterAdhesionStatus.value, [0]);
    });
  }

  /**
   * Construit le jeu de données commun (task + jeton CSRF) pour un appel ajax.
   * @param {string} task Nom complet de la tâche (ex: 'utilisateurs.updateGroups').
   * @returns {Object}
   */
  const buildAjaxData = function (task) {
    const ajaxData = { task: task };
    const csrfTokenName = Joomla.getOptions('csrf.token');

    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    return ajaxData;
  };

  // Délégation d'événements sur le conteneur : reste valide même si simple-datatables
  // réorganise/pagine les lignes du tableau.
  container.addEventListener('change', function (event) {
    const target = event.target;

    if (target.classList.contains('js-utilisateur-group')) {
      const idUser = target.getAttribute('data-id-user');
      const checkedGroups = container.querySelectorAll(
        '.js-utilisateur-group[data-id-user="' + idUser + '"]:checked'
      );
      const groupIds = Array.from(checkedGroups).map(function (el) {
        return el.getAttribute('data-id-groupe');
      });

      const ajaxData = buildAjaxData('utilisateurs.updateGroups');
      ajaxData.id_user = idUser;

      groupIds.forEach(function (groupId, index) {
        ajaxData['groups[' + index + ']'] = groupId;
      });

      if (typeof simpleCallAjax === 'function') {
        simpleCallAjax(ajaxData, null, true, function () {
          // En cas d'echec, on remet la case a son etat precedent.
          target.checked = !target.checked;
        });
      }
    } else if (target.classList.contains('js-utilisateur-block')) {
      const idUser = target.getAttribute('data-id-user');
      const ajaxData = buildAjaxData('utilisateurs.toggleBlock');
      ajaxData.id_user = idUser;
      ajaxData.blocked = target.checked ? 0 : 1;

      if (typeof simpleCallAjax === 'function') {
        simpleCallAjax(ajaxData, null, true, function () {
          target.checked = !target.checked;
        });
      }
    }
  });

  /**
   * Ouverture de la prévisualisation de la photo en modal.
   */
  document.addEventListener('click', function (event) {
    const trigger = event.target.closest('.js-image-preview-thumb');
    const previewImage = document.getElementById('imagePreviewImage');

    if (!trigger || !previewImage) {
      return;
    }

    const src = trigger.dataset.imageSrc || '';

    if (!src) {
      event.preventDefault();
      return;
    }

    previewImage.src = src;
    previewImage.alt = trigger.dataset.imageAlt || trigger.getAttribute('aria-label') || '';
  });

  document.addEventListener('hidden.bs.modal', function (event) {
    if (!event.target || event.target.id !== 'imagePreviewModal') {
      return;
    }

    const previewImage = event.target.querySelector('#imagePreviewImage');

    if (previewImage) {
      previewImage.src = '';
      previewImage.alt = '';
    }
  });
});
