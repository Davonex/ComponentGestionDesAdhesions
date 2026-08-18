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
      // fixedColumns vaut true par défaut dans simple-datatables : la librairie mesure alors
      // les colonnes au chargement et fige leur largeur en style inline (style="width: X%"),
      // qui prime sur n'importe quelle règle CSS (y compris #tableUtilisateurs th:nth-child).
      // Désactivé pour laisser gda.css piloter entièrement la largeur des colonnes.
      fixedColumns: false,
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
   * Filtres combinés (Adhésion / Groupes). On utilise multiSearch() avec un jeu de
   * contraintes recalculé à chaque changement plutôt que deux appels séparés à search() :
   * search(query, columns, source) réinitialise TOUTES les recherches (toutes sources
   * confondues) dès que query est vide (vérifié dans le code source de simple-datatables),
   * ce qui effacerait l'autre filtre actif au lieu de ne réinitialiser que le sien.
   */
  const filterAdhesionStatus = document.getElementById('filterAdhesionStatus');
  const filterGroupe = document.getElementById('filterGroupe');

  const applyFilters = function () {
    if (!dataTable) {
      return;
    }

    const queries = [];

    if (filterAdhesionStatus && filterAdhesionStatus.value) {
      queries.push({ terms: [filterAdhesionStatus.value], columns: [0] });
    }

    if (filterGroupe && filterGroupe.value) {
      queries.push({ terms: [filterGroupe.value], columns: [5] });
    }

    dataTable.multiSearch(queries, 'utilisateurs-filters');
  };

  if (filterAdhesionStatus && dataTable) {
    filterAdhesionStatus.addEventListener('change', applyFilters);
  }

  if (filterGroupe && dataTable) {
    filterGroupe.addEventListener('change', applyFilters);
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

  /**
   * Edition inline de la fonction (double-clic pour éditer, sauvegarde automatique).
   * Même pattern que .js-editable-date-caci / .js-editable-categorie (media/com_gdadhesions/js/secretariat.js).
   */
  document.addEventListener('dblclick', function (event) {
    const editableCell = event.target.closest('.js-editable-fonction');

    if (!editableCell) {
      return;
    }

    const display = editableCell.querySelector('.fonction-display');
    const input = editableCell.querySelector('.fonction-input');

    if (!display || !input) {
      return;
    }

    display.classList.add('d-none');
    input.classList.remove('d-none');
    input.value = display.textContent.trim();
    input.focus();
    input.select();
  });

  /**
   * Valide et sauvegarde la fonction via AJAX.
   * @param {HTMLInputElement} input
   */
  const saveFonction = function (input) {
    if (input.dataset.isSaving === '1') {
      return;
    }

    const editableCell = input.closest('.js-editable-fonction');
    const display = editableCell ? editableCell.querySelector('.fonction-display') : null;

    if (!editableCell || !display) {
      return;
    }

    const newFonction = input.value.trim();
    const currentFonction = (input.dataset.currentFonction || '').trim();
    const idUser = editableCell.getAttribute('data-id-user');

    if (newFonction === currentFonction) {
      display.classList.remove('d-none');
      input.classList.add('d-none');
      return;
    }

    const ajaxData = buildAjaxData('utilisateurs.updateFonction');
    ajaxData.id_user = idUser;
    ajaxData.fonction = newFonction;

    input.dataset.isSaving = '1';

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        if (response.success) {
          display.textContent = newFonction;
          input.dataset.currentFonction = newFonction;
        }

        input.dataset.isSaving = '0';
        display.classList.remove('d-none');
        input.classList.add('d-none');
      }, true, function () {
        input.dataset.isSaving = '0';
        display.classList.remove('d-none');
        input.classList.add('d-none');
      });
    } else {
      console.error('simpleCallAjax n\'est pas disponible');
      input.dataset.isSaving = '0';
      display.classList.remove('d-none');
      input.classList.add('d-none');
    }
  };

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') {
      return;
    }

    const input = event.target.closest('.fonction-input:not(.d-none)');

    if (!input) {
      return;
    }

    event.preventDefault();
    input.dataset.ignoreBlurOnce = '1';
    saveFonction(input);
  });

  document.addEventListener('blur', function (event) {
    // En capture, blur remonte aussi les pertes de focus de la fenêtre entière (ex: alt-tab) :
    // event.target vaut alors window/document, sans .closest().
    if (!(event.target instanceof Element)) {
      return;
    }

    const input = event.target.closest('.fonction-input:not(.d-none)');

    if (!input) {
      return;
    }

    // Evite un second envoi quand Enter declenche ensuite un blur.
    if (input.dataset.ignoreBlurOnce === '1') {
      input.dataset.ignoreBlurOnce = '0';
      return;
    }

    saveFonction(input);
  }, true);
});
