document.addEventListener('DOMContentLoaded', function () {
  const container = document.querySelector('.gda-utilisateurs');

  if (!container) {
    return;
  }

  /**
   * La vue est découpée en 3 onglets (Profils / Niveau d'accès / Trombinoscope), chacun avec son
   * propre <table> : simple-datatables ne gère qu'un tableau par instance. Toutes les lignes des
   * 3 tableaux partagent le même data-id-user, et les 2 filtres (Adhésion / Groupe) restent
   * communs, affichés une seule fois au-dessus des onglets. Chaque table porte, en plus de ses
   * colonnes visibles, un marqueur caché "adh:"/"grp:" dans la cellule Nom (utilisateurs.cell_nom)
   * quand l'info correspondante n'a pas de colonne visible dédiée sur cet onglet - d'où la colonne
   * de filtre différente par tableau ci-dessous.
   */
  const datatableApi = globalThis.simpleDatatables;
  const dataTableDefs = [
    { id: 'tableUtilisateursProfils', adhesionColumn: 0, groupeColumn: 2 },
    { id: 'tableUtilisateursAcces', adhesionColumn: 1, groupeColumn: 2 },
    { id: 'tableUtilisateursTrombinoscope', adhesionColumn: 1, groupeColumn: 1 },
  ];

  const dataTables = dataTableDefs
    .map(function (def) {
      const table = document.getElementById(def.id);

      if (!table || !datatableApi || !datatableApi.DataTable) {
        return null;
      }

      const instance = new datatableApi.DataTable(table, {
        // fixedColumns vaut true par défaut dans simple-datatables : la librairie mesure alors
        // les colonnes au chargement et fige leur largeur en style inline (style="width: X%"),
        // qui prime sur n'importe quelle règle CSS (y compris #tableUtilisateursProfils
        // th:nth-child). Désactivé pour laisser gda.css piloter entièrement la largeur des
        // colonnes - important ici puisque les onglets Niveau d'accès/Trombinoscope démarrent
        // cachés (display: none), où une mesure JS des largeurs donnerait 0.
        fixedColumns: false,
        labels: {
          placeholder: 'Rechercher...',
          perPage: 'lignes par page',
          noRows: 'Aucun utilisateur',
          noResults: 'Aucun résultat trouvé',
          info: 'Affichage de {start} à {end} sur {rows} entrées'
        }
      });

      return { instance: instance, adhesionColumn: def.adhesionColumn, groupeColumn: def.groupeColumn };
    })
    .filter(function (entry) { return entry !== null; });

  /**
   * Filtres combinés (Adhésion / Groupes), appliqués simultanément aux 3 tableaux. On utilise
   * multiSearch() avec un jeu de contraintes recalculé à chaque changement plutôt que deux appels
   * séparés à search() : search(query, columns, source) réinitialise TOUTES les recherches
   * (toutes sources confondues) dès que query est vide (vérifié dans le code source de
   * simple-datatables), ce qui effacerait l'autre filtre actif au lieu de ne réinitialiser que
   * le sien.
   */
  const filterAdhesionStatus = document.getElementById('filterAdhesionStatus');
  const filterGroupe = document.getElementById('filterGroupe');

  const applyFilters = function () {
    dataTables.forEach(function (entry) {
      const queries = [];

      if (filterAdhesionStatus && filterAdhesionStatus.value) {
        queries.push({ terms: [filterAdhesionStatus.value], columns: [entry.adhesionColumn] });
      }

      if (filterGroupe && filterGroupe.value) {
        queries.push({ terms: [filterGroupe.value], columns: [entry.groupeColumn] });
      }

      entry.instance.multiSearch(queries, 'utilisateurs-filters');
    });
  };

  if (filterAdhesionStatus && dataTables.length > 0) {
    filterAdhesionStatus.addEventListener('change', applyFilters);
  }

  if (filterGroupe && dataTables.length > 0) {
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
    const trigger = event.target.closest('.js-image-preview-thumb, .js-caci-thumb');
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
   * Edition inline de l'ordre d'affichage dans le trombinoscope du Bureau. Même pattern que
   * l'édition inline de la fonction ci-dessus.
   */
  document.addEventListener('dblclick', function (event) {
    const editableCell = event.target.closest('.js-editable-ordre');

    if (!editableCell) {
      return;
    }

    const display = editableCell.querySelector('.ordre-display');
    const input = editableCell.querySelector('.ordre-input');

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

  /**
   * Valide et sauvegarde l'ordre d'affichage via AJAX. Même logique que saveFonction : une
   * saisie vide efface la valeur (le membre retombe sur le tri alphabétique en repli).
   * @param {HTMLInputElement} input
   */
  const saveOrdre = function (input) {
    if (input.dataset.isSaving === '1') {
      return;
    }

    const editableCell = input.closest('.js-editable-ordre');
    const display = editableCell ? editableCell.querySelector('.ordre-display') : null;

    if (!editableCell || !display) {
      return;
    }

    const newOrdre = input.value.trim();
    const currentOrdre = (input.dataset.currentOrdre || '').trim();
    const idUser = editableCell.getAttribute('data-id-user');

    if (newOrdre === currentOrdre) {
      display.classList.remove('d-none');
      input.classList.add('d-none');
      return;
    }

    const ajaxData = buildAjaxData('utilisateurs.updateOrdre');
    ajaxData.id_user = idUser;
    ajaxData.ordre = newOrdre;

    input.dataset.isSaving = '1';

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        if (response.success) {
          display.textContent = newOrdre;
          input.dataset.currentOrdre = newOrdre;
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

    const fonctionInput = event.target.closest('.fonction-input:not(.d-none)');

    if (fonctionInput) {
      event.preventDefault();
      fonctionInput.dataset.ignoreBlurOnce = '1';
      saveFonction(fonctionInput);
      return;
    }

    const ordreInput = event.target.closest('.ordre-input:not(.d-none)');

    if (!ordreInput) {
      return;
    }

    event.preventDefault();
    ordreInput.dataset.ignoreBlurOnce = '1';
    saveOrdre(ordreInput);
  });

  document.addEventListener('blur', function (event) {
    // En capture, blur remonte aussi les pertes de focus de la fenêtre entière (ex: alt-tab) :
    // event.target vaut alors window/document, sans .closest().
    if (!(event.target instanceof Element)) {
      return;
    }

    const fonctionInput = event.target.closest('.fonction-input:not(.d-none)');

    if (fonctionInput) {
      // Evite un second envoi quand Enter declenche ensuite un blur.
      if (fonctionInput.dataset.ignoreBlurOnce === '1') {
        fonctionInput.dataset.ignoreBlurOnce = '0';
        return;
      }

      saveFonction(fonctionInput);
      return;
    }

    const ordreInput = event.target.closest('.ordre-input:not(.d-none)');

    if (!ordreInput) {
      return;
    }

    if (ordreInput.dataset.ignoreBlurOnce === '1') {
      ordreInput.dataset.ignoreBlurOnce = '0';
      return;
    }

    saveOrdre(ordreInput);
  }, true);

  /**
   * Suppression définitive d'un adhérent, avec confirmation (même mécanique que
   * SecretariatModel::deleteAdherentDefinitif(), réutilisée via UtilisateursController::deleteAdherent()).
   */
  const deleteUtilisateurModalEl = document.getElementById('deleteUtilisateurModal');
  const deleteUtilisateurModalInstance = deleteUtilisateurModalEl && window.bootstrap
    ? bootstrap.Modal.getOrCreateInstance(deleteUtilisateurModalEl)
    : null;
  const deleteUtilisateurId = document.getElementById('deleteUtilisateurId');
  const deleteUtilisateurName = document.getElementById('deleteUtilisateurName');
  const deleteUtilisateurUsername = document.getElementById('deleteUtilisateurUsername');
  const deleteUtilisateurEmail = document.getElementById('deleteUtilisateurEmail');
  const deleteUtilisateurPhoto = document.getElementById('deleteUtilisateurPhoto');
  const deleteUtilisateurSubmit = document.getElementById('deleteUtilisateurSubmit');

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-delete-utilisateur');

    if (!button || !deleteUtilisateurModalInstance) {
      return;
    }

    const civilite = (button.dataset.itemCivilite || '').trim();
    const name = (button.dataset.itemName || '').trim();
    const photo = button.dataset.itemPhoto || '';

    deleteUtilisateurId.value = button.dataset.itemId || '0';
    deleteUtilisateurName.textContent = (civilite + ' ' + name).trim();
    deleteUtilisateurUsername.textContent = button.dataset.itemUsername || '';
    deleteUtilisateurEmail.textContent = button.dataset.itemEmail || '';

    if (photo) {
      deleteUtilisateurPhoto.src = photo;
      deleteUtilisateurPhoto.alt = name;
      deleteUtilisateurPhoto.classList.remove('d-none');
    } else {
      deleteUtilisateurPhoto.src = '';
      deleteUtilisateurPhoto.classList.add('d-none');
    }

    deleteUtilisateurModalInstance.show();
  });

  if (deleteUtilisateurSubmit) {
    deleteUtilisateurSubmit.addEventListener('click', function () {
      const idUser = parseInt(deleteUtilisateurId.value || '0', 10);

      if (idUser <= 0) {
        return;
      }

      const ajaxData = buildAjaxData('utilisateurs.deleteAdherent');
      ajaxData.id_profil = idUser;

      deleteUtilisateurSubmit.disabled = true;

      if (typeof simpleCallAjax === 'function') {
        simpleCallAjax(ajaxData, function (response) {
          deleteUtilisateurSubmit.disabled = false;

          if (deleteUtilisateurModalInstance) {
            deleteUtilisateurModalInstance.hide();
          }

          if (!response.success) {
            return;
          }

          // L'adhérent supprimé a une ligne dans les 3 onglets (même data-id-user) : les masquer
          // toutes, pas seulement celle de l'onglet Profils où se trouve le bouton Effacer.
          document.querySelectorAll('tr[data-id-user="' + idUser + '"]').forEach(function (row) {
            row.classList.add('d-none');
          });
        }, true, function () {
          deleteUtilisateurSubmit.disabled = false;
        });
      } else {
        console.error('simpleCallAjax n\'est pas disponible');
        deleteUtilisateurSubmit.disabled = false;
      }
    });
  }

  if (deleteUtilisateurModalEl) {
    deleteUtilisateurModalEl.addEventListener('hidden.bs.modal', function () {
      deleteUtilisateurId.value = '0';
      deleteUtilisateurName.textContent = '';
      deleteUtilisateurUsername.textContent = '';
      deleteUtilisateurEmail.textContent = '';
      deleteUtilisateurPhoto.src = '';
      deleteUtilisateurPhoto.classList.add('d-none');
    });
  }

  /**
   * Réinitialisation du mot de passe d'un compte (onglet Niveau d'accès), avec confirmation.
   * Même mécanique que la suppression définitive ci-dessus (popup avec les infos de la personne
   * ciblée pour éviter les erreurs), thème orange/warning pour se distinguer du rouge de
   * suppression.
   */
  const resetPasswordModalEl = document.getElementById('resetPasswordUtilisateurModal');
  const resetPasswordModalInstance = resetPasswordModalEl && window.bootstrap
    ? bootstrap.Modal.getOrCreateInstance(resetPasswordModalEl)
    : null;
  const resetPasswordId = document.getElementById('resetPasswordUtilisateurId');
  const resetPasswordName = document.getElementById('resetPasswordUtilisateurName');
  const resetPasswordUsername = document.getElementById('resetPasswordUtilisateurUsername');
  const resetPasswordEmail = document.getElementById('resetPasswordUtilisateurEmail');
  const resetPasswordPhoto = document.getElementById('resetPasswordUtilisateurPhoto');
  const resetPasswordSubmit = document.getElementById('resetPasswordUtilisateurSubmit');

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-reset-password-utilisateur');

    if (!button || !resetPasswordModalInstance) {
      return;
    }

    const civilite = (button.dataset.itemCivilite || '').trim();
    const name = (button.dataset.itemName || '').trim();
    const photo = button.dataset.itemPhoto || '';

    resetPasswordId.value = button.dataset.itemId || '0';
    resetPasswordName.textContent = (civilite + ' ' + name).trim();
    resetPasswordUsername.textContent = button.dataset.itemUsername || '';
    resetPasswordEmail.textContent = button.dataset.itemEmail || '';

    if (photo) {
      resetPasswordPhoto.src = photo;
      resetPasswordPhoto.alt = name;
      resetPasswordPhoto.classList.remove('d-none');
    } else {
      resetPasswordPhoto.src = '';
      resetPasswordPhoto.classList.add('d-none');
    }

    resetPasswordModalInstance.show();
  });

  if (resetPasswordSubmit) {
    resetPasswordSubmit.addEventListener('click', function () {
      const idUser = parseInt(resetPasswordId.value || '0', 10);

      if (idUser <= 0) {
        return;
      }

      const ajaxData = buildAjaxData('utilisateurs.resetPassword');
      ajaxData.id_user = idUser;

      resetPasswordSubmit.disabled = true;

      if (typeof simpleCallAjax === 'function') {
        simpleCallAjax(ajaxData, function () {
          resetPasswordSubmit.disabled = false;

          if (resetPasswordModalInstance) {
            resetPasswordModalInstance.hide();
          }
        }, true, function () {
          resetPasswordSubmit.disabled = false;
        });
      } else {
        console.error('simpleCallAjax n\'est pas disponible');
        resetPasswordSubmit.disabled = false;
      }
    });
  }

  if (resetPasswordModalEl) {
    resetPasswordModalEl.addEventListener('hidden.bs.modal', function () {
      resetPasswordId.value = '0';
      resetPasswordName.textContent = '';
      resetPasswordUsername.textContent = '';
      resetPasswordEmail.textContent = '';
      resetPasswordPhoto.src = '';
      resetPasswordPhoto.classList.add('d-none');
    });
  }
});
