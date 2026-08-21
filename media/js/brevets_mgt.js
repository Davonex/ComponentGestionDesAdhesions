/**
 * Vue « Brevets » (Bureau) : administration du référentiel FFESSM (onglet 1) et rattachement des
 * brevets saisis par les adhérents (onglet 2).
 *
 * Dépendances : simpleCallAjax (form_modal.js), GdaDialog (dialog.js), simple-datatables.
 */
document.addEventListener('DOMContentLoaded', function () {
  const container = document.querySelector('.gda-brevets');

  if (!container) {
    return;
  }

  const datatableApi = globalThis.simpleDatatables;

  /**
   * Construit le jeu de données commun (task + jeton CSRF) pour un appel ajax.
   * @param {string} task Nom complet de la tâche (ex: 'brevets.saveMapping').
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

  /**
   * Instancie une table simple-datatables. fixedColumns désactivé pour la même raison que dans
   * utilisateurs.js : l'onglet 2 démarre caché (display: none), où une mesure JS des largeurs
   * donnerait 0 et figerait des colonnes vides.
   * @param {string} id
   * @param {string} noRowsLabel
   * @returns {Object|null}
   */
  const createDataTable = function (id, noRowsLabel) {
    const table = document.getElementById(id);

    if (!table || !datatableApi || !datatableApi.DataTable) {
      return null;
    }

    return new datatableApi.DataTable(table, {
      fixedColumns: false,
      labels: {
        placeholder: 'Rechercher...',
        perPage: 'lignes par page',
        noRows: noRowsLabel,
        noResults: 'Aucun résultat trouvé',
        info: 'Affichage de {start} à {end} sur {rows} entrées'
      }
    });
  };

  const tableMapping = createDataTable('tableMappingBrevets', 'Aucune correspondance');
  const tableAdherents = createDataTable('tableBrevetsAdherents', 'Aucun brevet');

  // ---------------------------------------------------------------------------------------
  // Onglet 1 — Référentiel FFESSM
  // ---------------------------------------------------------------------------------------

  // Filtres Activité (colonne 1) et Rôle (colonne 2), combinés. multiSearch() (et non search())
  // : search() réinitialise toutes les recherches dès que la requête est vide, ce qui effacerait
  // l'autre filtre en plus du sien.
  const filterMappingActivite = document.getElementById('filterMappingActivite');
  const filterMappingRole = document.getElementById('filterMappingRole');

  const applyFiltersMapping = function () {
    if (!tableMapping) {
      return;
    }

    const queries = [];

    if (filterMappingActivite && filterMappingActivite.value) {
      queries.push({ terms: [filterMappingActivite.value], columns: [1] });
    }

    if (filterMappingRole && filterMappingRole.value) {
      queries.push({ terms: [filterMappingRole.value], columns: [2] });
    }

    tableMapping.multiSearch(queries, 'brevets-mapping-filters');
  };

  if (filterMappingActivite) {
    filterMappingActivite.addEventListener('change', applyFiltersMapping);
  }

  if (filterMappingRole) {
    filterMappingRole.addEventListener('change', applyFiltersMapping);
  }

  const addRow = document.getElementById('mappingAddRow');
  const btnAjouterMapping = document.getElementById('btnAjouterMapping');
  const btnSaveMapping = document.getElementById('btnSaveMapping');
  const btnCancelMapping = document.getElementById('btnCancelMapping');

  /** Vide et masque le formulaire d'ajout. */
  const resetAddRow = function () {
    if (!addRow) {
      return;
    }

    addRow.classList.add('d-none');
    document.getElementById('newMappingLabel').value = '';
    document.getElementById('newMappingCode').value = '';
    document.getElementById('newMappingPoids').value = '0';
  };

  if (btnAjouterMapping && addRow) {
    btnAjouterMapping.addEventListener('click', function () {
      addRow.classList.remove('d-none');
      document.getElementById('newMappingLabel').focus();
    });
  }

  if (btnCancelMapping) {
    btnCancelMapping.addEventListener('click', resetAddRow);
  }

  if (btnSaveMapping) {
    btnSaveMapping.addEventListener('click', function () {
      if (btnSaveMapping.dataset.isSaving === '1') {
        return;
      }

      const ajaxData = buildAjaxData('brevets.saveMapping');
      ajaxData.label_ffessm = document.getElementById('newMappingLabel').value.trim();
      ajaxData.activite = document.getElementById('newMappingActivite').value;
      ajaxData.role = document.getElementById('newMappingRole').value;
      ajaxData.code = document.getElementById('newMappingCode').value.trim();
      ajaxData.poids = document.getElementById('newMappingPoids').value;

      btnSaveMapping.dataset.isSaving = '1';

      simpleCallAjax(ajaxData, function (response) {
        btnSaveMapping.dataset.isSaving = '0';

        if (!response.success) {
          return;
        }

        // La nouvelle ligne est insérée dans le DOM source puis la table est réinitialisée :
        // simple-datatables travaille sur son propre index, un simple appendChild sur le
        // <tbody> serait ignoré au premier tri ou changement de page.
        const tbody = document.getElementById('tbodyMappingBrevets');

        if (tbody) {
          tbody.insertAdjacentHTML('beforeend', response.data.html);

          if (tableMapping) {
            tableMapping.destroy();
            tableMapping.init();
          }
        }

        resetAddRow();
      }, true, function () {
        btnSaveMapping.dataset.isSaving = '0';
      });
    });
  }

  /**
   * Édition inline générique : double-clic sur une cellule, sauvegarde ajax à la validation.
   * Factorisé car quatre cellules suivent exactement ce cycle (code et poids du référentiel,
   * nom du brevet adhérent), avec pour seules différences leurs sélecteurs et la tâche appelée.
   *
   * @param {Object} config
   * @param {string} config.cellSelector    Sélecteur de la cellule éditable.
   * @param {string} config.displaySelector Sélecteur de l'élément d'affichage.
   * @param {string} config.inputSelector   Sélecteur du champ de saisie.
   * @param {string} config.datasetKey      Clé dataset portant la valeur courante.
   * @param {Function} config.buildData     (valeur, cellule) => données ajax.
   */
  const registerInlineEdit = function (config) {
    const ouvrir = function (event) {
      const cell = event.target.closest(config.cellSelector);

      if (!cell) {
        return;
      }

      const display = cell.querySelector(config.displaySelector);
      const input = cell.querySelector(config.inputSelector);

      if (!display || !input) {
        return;
      }

      display.classList.add('d-none');
      input.classList.remove('d-none');
      input.focus();
      input.select();
    };

    const fermer = function (cell, display, input) {
      display.classList.remove('d-none');
      input.classList.add('d-none');
      input.dataset.isSaving = '0';
    };

    const sauver = function (input) {
      if (input.dataset.isSaving === '1') {
        return;
      }

      const cell = input.closest(config.cellSelector);
      const display = cell ? cell.querySelector(config.displaySelector) : null;

      if (!cell || !display) {
        return;
      }

      const nouvelleValeur = input.value.trim();
      const valeurCourante = (input.dataset[config.datasetKey] || '').trim();

      if (nouvelleValeur === valeurCourante) {
        fermer(cell, display, input);
        return;
      }

      input.dataset.isSaving = '1';

      simpleCallAjax(config.buildData(nouvelleValeur, cell), function (response) {
        if (response.success) {
          display.textContent = nouvelleValeur;
          input.dataset[config.datasetKey] = nouvelleValeur;
        } else {
          // Échec métier (code vide, doublon...) : on restaure la valeur d'origine plutôt que
          // de laisser à l'écran une saisie que la base n'a pas acceptée.
          input.value = valeurCourante;
        }

        fermer(cell, display, input);
      }, true, function () {
        input.value = valeurCourante;
        fermer(cell, display, input);
      });
    };

    container.addEventListener('dblclick', ouvrir);

    container.addEventListener('blur', function (event) {
      if (event.target.matches(config.inputSelector)) {
        sauver(event.target);
      }
    }, true);

    container.addEventListener('keydown', function (event) {
      if (!event.target.matches(config.inputSelector)) {
        return;
      }

      if (event.key === 'Enter') {
        event.preventDefault();
        event.target.blur();
      } else if (event.key === 'Escape') {
        const cell = event.target.closest(config.cellSelector);
        const display = cell ? cell.querySelector(config.displaySelector) : null;

        if (display) {
          event.target.value = event.target.dataset[config.datasetKey] || '';
          fermer(cell, display, event.target);
        }
      }
    });
  };

  registerInlineEdit({
    cellSelector: '.js-editable-code',
    displaySelector: '.code-display',
    inputSelector: '.code-input',
    datasetKey: 'currentCode',
    buildData: function (valeur, cell) {
      const ajaxData = buildAjaxData('brevets.updateMappingChamp');
      ajaxData.id_mapping = cell.closest('.js-mapping-row').dataset.idMapping;
      ajaxData.champ = 'code';
      ajaxData.valeur = valeur;

      return ajaxData;
    }
  });

  registerInlineEdit({
    cellSelector: '.js-editable-poids',
    displaySelector: '.poids-display',
    inputSelector: '.poids-input',
    datasetKey: 'currentPoids',
    buildData: function (valeur, cell) {
      const ajaxData = buildAjaxData('brevets.updateMappingChamp');
      ajaxData.id_mapping = cell.closest('.js-mapping-row').dataset.idMapping;
      ajaxData.champ = 'poids';
      ajaxData.valeur = valeur;

      return ajaxData;
    }
  });

  registerInlineEdit({
    cellSelector: '.js-editable-nom-brevet',
    displaySelector: '.nom-brevet-display',
    inputSelector: '.nom-brevet-input',
    datasetKey: 'currentNom',
    buildData: function (valeur, cell) {
      const ajaxData = buildAjaxData('brevets.updateNomBrevet');
      ajaxData.id_brevet = cell.closest('.js-brevet-row').dataset.idBrevet;
      ajaxData.nom = valeur;

      return ajaxData;
    }
  });

  /**
   * Suppression d'une correspondance : la confirmation annonce d'abord combien de brevets
   * adhérents seront détachés (la FK est en ON DELETE SET NULL, ils ne sont pas supprimés).
   */
  container.addEventListener('click', function (event) {
    const btn = event.target.closest('.js-delete-mapping');

    if (!btn) {
      return;
    }

    const row = btn.closest('.js-mapping-row');
    const idMapping = row ? row.dataset.idMapping : null;

    if (!idMapping) {
      return;
    }

    const ajaxData = buildAjaxData('brevets.countBrevetsLies');
    ajaxData.id_mapping = idMapping;

    simpleCallAjax(ajaxData, function (response) {
      if (!response.success) {
        return;
      }

      const nb = parseInt(response.data.count, 10) || 0;
      const impact = nb > 0
        ? Joomla.Text._('COM_GDA_BREVETS_MAPPING_DELETE_IMPACT').replace('%d', nb)
        : Joomla.Text._('COM_GDA_BREVETS_MAPPING_DELETE_NO_IMPACT');

      GdaDialog.confirm(
        Joomla.Text._('COM_GDA_BREVETS_MAPPING_DELETE_TITLE'),
        Joomla.Text._('COM_GDA_BREVETS_MAPPING_DELETE_CONFIRM').replace('%s', btn.dataset.label || ''),
        function () {
          const deleteData = buildAjaxData('brevets.deleteMapping');
          deleteData.id_mapping = idMapping;

          simpleCallAjax(deleteData, function (deleteResponse) {
            if (!deleteResponse.success) {
              return;
            }

            row.remove();

            if (tableMapping) {
              tableMapping.destroy();
              tableMapping.init();
            }
          });
        },
        impact
      );
    }, false);
  });

  // ---------------------------------------------------------------------------------------
  // Onglet 2 — Brevets des adhérents
  // ---------------------------------------------------------------------------------------

  const filterBrevetStatut = document.getElementById('filterBrevetStatut');
  const filterBrevetActivite = document.getElementById('filterBrevetActivite');

  // Les deux filtres portent sur la colonne 0, qui contient les marqueurs cachés "map:" et
  // "act:" posés par adherents_row.php. multiSearch() permet de les combiner sans que l'un
  // n'annule l'autre.
  const applyFiltersAdherents = function () {
    if (!tableAdherents) {
      return;
    }

    const queries = [];

    if (filterBrevetStatut && filterBrevetStatut.value) {
      queries.push({ terms: [filterBrevetStatut.value], columns: [0] });
    }

    if (filterBrevetActivite && filterBrevetActivite.value) {
      queries.push({ terms: [filterBrevetActivite.value], columns: [0] });
    }

    tableAdherents.multiSearch(queries, 'brevets-adherents-filters');
  };

  if (filterBrevetStatut) {
    filterBrevetStatut.addEventListener('change', applyFiltersAdherents);
  }

  if (filterBrevetActivite) {
    filterBrevetActivite.addEventListener('change', applyFiltersAdherents);
  }

  /**
   * Rattachement d'un brevet au référentiel. L'éditeur (liste + boutons) est unique et déplacé
   * dans la cellule au double-clic : le dupliquer sur chaque ligne produirait des dizaines de
   * milliers d'<option> pour un seul utilisé à la fois.
   *
   * La sélection ne sauvegarde rien par elle-même. La liste est longue et un rattachement
   * écrase le libellé saisi par l'adhérent : la validation passe donc par le bouton dédié,
   * comme sur l'onglet Référentiel.
   *
   * Sa liste est toujours complète, groupée en <optgroup> par activité (adherents_table.php).
   * Elle n'est volontairement PAS restreinte par le filtre Activité du tableau : un brevet non
   * rattaché n'a aucune activité, restreindre la liste ferait disparaître les entrées qui
   * servent justement à le corriger.
   */
  const mappingEditor = document.getElementById('mappingEditor');
  const mappingEditorSelect = document.getElementById('mappingEditorSelect');
  const mappingEditorSave = document.getElementById('mappingEditorSave');
  const mappingEditorCancel = document.getElementById('mappingEditorCancel');
  let celluleEnEdition = null;

  /** Range l'éditeur hors du tableau et réaffiche la valeur de la cellule. */
  const fermerEditeurMapping = function () {
    if (!celluleEnEdition) {
      return;
    }

    const display = celluleEnEdition.querySelector('.mapping-display');

    if (display) {
      display.classList.remove('d-none');
    }

    mappingEditor.classList.add('d-none');
    container.appendChild(mappingEditor);
    celluleEnEdition = null;
  };

  /** Envoie le rattachement sélectionné et rafraîchit la ligne. */
  const sauverRattachement = function () {
    if (!celluleEnEdition || !mappingEditorSelect.value) {
      return;
    }

    if (mappingEditorSave.dataset.isSaving === '1') {
      return;
    }

    const cell = celluleEnEdition;
    const row = cell.closest('.js-brevet-row');
    const ajaxData = buildAjaxData('brevets.attacherMapping');
    ajaxData.id_brevet = row.dataset.idBrevet;
    ajaxData.id_mapping = mappingEditorSelect.value;

    mappingEditorSave.dataset.isSaving = '1';

    simpleCallAjax(ajaxData, function (response) {
      mappingEditorSave.dataset.isSaving = '0';

      if (!response.success) {
        fermerEditeurMapping();
        return;
      }

      const display = cell.querySelector('.mapping-display');
      display.textContent = response.data.label_ffessm + ' (' + response.data.activite + ')';
      cell.dataset.idMapping = ajaxData.id_mapping;

      // Le libellé officiel remplace la saisie de l'adhérent : c'est ce qui rend la
      // correction durable (le prochain enregistrement la re-résoudra à l'identique).
      const nomDisplay = row.querySelector('.nom-brevet-display');
      const nomInput = row.querySelector('.nom-brevet-input');

      if (nomDisplay && nomInput) {
        nomDisplay.textContent = response.data.nom;
        nomInput.value = response.data.nom;
        nomInput.dataset.currentNom = response.data.nom;
      }

      // Le marqueur de filtre doit suivre, sinon la ligne resterait listée en "non rattaché".
      const marqueurs = row.querySelector('.js-brevet-marqueurs');

      if (marqueurs) {
        marqueurs.textContent = 'map:oui act:' + response.data.activite;
      }

      fermerEditeurMapping();
    }, true, function () {
      mappingEditorSave.dataset.isSaving = '0';
      fermerEditeurMapping();
    });
  };

  if (mappingEditor && mappingEditorSelect) {
    container.addEventListener('dblclick', function (event) {
      const cell = event.target.closest('.js-editable-mapping');

      if (!cell || cell === celluleEnEdition) {
        return;
      }

      fermerEditeurMapping();

      const display = cell.querySelector('.mapping-display');

      if (!display) {
        return;
      }

      display.classList.add('d-none');
      mappingEditorSelect.value = cell.dataset.idMapping || '';
      mappingEditor.classList.remove('d-none');
      cell.appendChild(mappingEditor);
      mappingEditorSelect.focus();
      celluleEnEdition = cell;
    });

    mappingEditorSave.addEventListener('click', sauverRattachement);
    mappingEditorCancel.addEventListener('click', fermerEditeurMapping);

    mappingEditor.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        fermerEditeurMapping();
      } else if (event.key === 'Enter') {
        // Entrée vaut validation, comme sur les autres champs édités en ligne.
        event.preventDefault();
        sauverRattachement();
      }
    });
  }
});
