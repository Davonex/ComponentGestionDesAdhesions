document.addEventListener('DOMContentLoaded', function () {

  /**
   * Options de configuration pour simple-datatables avec les labels en français.
   */
  const frenchDataTableOptions = {
    // 50 lignes par page par défaut (la liste par défaut de simple-datatables s'arrête à 25).
    perPage: 50,
    perPageSelect: [10, 25, 50, 100],
    labels: {
      placeholder: 'Rechercher...',
      perPage: 'lignes par page',
      noRows: 'Aucune donnée disponible',
      noResults: 'Aucun résultat trouvé',
      info: 'Affichage de {start} à {end} sur {rows} entrées'
    },
    classes: {
      container: 'datatable-container gda-dt-container',
      top: 'datatable-top gda-dt-top',
      input: 'datatable-input gda-dt-input',
      selector: 'datatable-selector gda-dt-selector',
      table: 'datatable-table gda-dt-table',
      bottom: 'datatable-bottom gda-dt-bottom',
      info: 'datatable-info gda-dt-info',
      pagination: 'datatable-pagination gda-dt-pagination',
      active: 'datatable-active gda-dt-active',
      disabled: 'datatable-disabled gda-dt-disabled'
    }
  };

  /**
   * Instances DataTable actives, indexees par element <table> pour eviter une double initialisation
   * et pour retrouver l'instance a exporter/imprimer.
   */
  const dataTableInstances = new Map();

  /**
   * Initialise (une seule fois) la DataTable d'un onglet groupe.
   *
   * @param {HTMLElement} pane Le panneau .tab-pane du groupe.
   * @returns {void}
   */
  const initGroupeTable = function (pane) {
    if (!pane) {
      return;
    }

    const table = pane.querySelector('.gda-groupes-view--detail table');
    const datatableApi = globalThis.simpleDatatables;

    if (!table || dataTableInstances.has(table)) {
      return;
    }

    if (datatableApi && datatableApi.DataTable) {
      dataTableInstances.set(table, new datatableApi.DataTable(table, frenchDataTableOptions));
    } else {
      console.error('simple-datatables n\'est pas chargee');
    }
  };

  /**
   * Indique si la vue detail (tableau) est actuellement affichee (non masquee par le mode vignette).
   *
   * @returns {boolean}
   */
  const isDetailModeActive = function () {
    const detailView = document.querySelector('.gda-groupes-view--detail');

    return !!detailView && !detailView.classList.contains('d-none');
  };

  // Initialise la table du premier onglet, seulement si la vue detail est affichee par defaut
  // (un tableau cache par le mode vignette ou par un onglet inactif fausse les largeurs de colonnes).
  const activePane = document.querySelector('#groupesTabContent .tab-pane.active');

  if (isDetailModeActive()) {
    initGroupeTable(activePane);
  }

  // Initialise la table d'un onglet a sa premiere ouverture en mode detail (les tableaux caches faussent les largeurs de colonnes).
  document.querySelectorAll('#groupesTabNav button[data-bs-toggle="tab"]').forEach(function (tabButton) {
    tabButton.addEventListener('shown.bs.tab', function (event) {
      if (!isDetailModeActive()) {
        return;
      }

      const targetSelector = event.target.getAttribute('data-bs-target');
      const pane = targetSelector ? document.querySelector(targetSelector) : null;

      initGroupeTable(pane);
    });
  });

  /**
   * Bascule l'affichage des onglets sans adherent.
   *
   * @param {boolean} hiding Masquer (true) ou reafficher (false) les groupes vides.
   * @returns {void}
   */
  const applyHideEmptyGroups = function (hiding) {
    const emptyTabItems = document.querySelectorAll('#groupesTabNav .gda-groupes-tab-item[data-count="0"]');
    let activeTabHidden = false;

    emptyTabItems.forEach(function (tabItem) {
      tabItem.classList.toggle('d-none', hiding);

      if (hiding && tabItem.querySelector('.nav-link.active')) {
        activeTabHidden = true;
      }
    });

    // Meme filtre sur la liste deroulante mobile ; disabled en plus de hidden car Safari iOS ignore hidden sur <option>.
    document.querySelectorAll('#groupesSelect option[data-count="0"]').forEach(function (option) {
      option.hidden = hiding;
      option.disabled = hiding;
    });

    // Si l'onglet actif vient d'etre masque, on bascule sur le premier onglet visible.
    if (activeTabHidden) {
      const firstVisibleTabButton = document.querySelector('#groupesTabNav .gda-groupes-tab-item:not(.d-none) .nav-link');

      if (firstVisibleTabButton && window.bootstrap && bootstrap.Tab) {
        bootstrap.Tab.getOrCreateInstance(firstVisibleTabButton).show();
      }
    }
  };

  /**
   * Liste deroulante des groupes (telephone, < md) : pilote les memes onglets Bootstrap que la barre
   * d'onglets masquee, et reste synchronisee quand l'onglet actif change par ailleurs.
   */
  const groupesSelect = document.getElementById('groupesSelect');

  if (groupesSelect) {
    groupesSelect.addEventListener('change', function () {
      const tabButton = document.getElementById(groupesSelect.value);

      if (tabButton && window.bootstrap && bootstrap.Tab) {
        bootstrap.Tab.getOrCreateInstance(tabButton).show();
      }
    });

    document.querySelectorAll('#groupesTabNav button[data-bs-toggle="tab"]').forEach(function (tabButton) {
      tabButton.addEventListener('shown.bs.tab', function (event) {
        groupesSelect.value = event.target.id;
      });
    });
  }

  const switchHideEmpty = document.getElementById('switchGroupesHideEmpty');

  if (switchHideEmpty) {
    // Applique l'etat par defaut du switch (coche) des le chargement de la page.
    applyHideEmptyGroups(switchHideEmpty.checked);

    switchHideEmpty.addEventListener('change', function () {
      applyHideEmptyGroups(switchHideEmpty.checked);
    });
  }

  /**
   * Bascule le mode d'affichage (detail / vignette) pour tous les onglets.
   */
  const btnDisplayDetail = document.getElementById('btnGroupesDisplayDetail');
  const btnDisplayVignette = document.getElementById('btnGroupesDisplayVignette');

  const setDisplayMode = function (mode) {
    document.querySelectorAll('.gda-groupes-view').forEach(function (view) {
      view.classList.toggle('d-none', view.dataset.viewMode !== mode);
    });

    if (btnDisplayDetail) {
      btnDisplayDetail.classList.toggle('active', mode === 'detail');
    }

    if (btnDisplayVignette) {
      btnDisplayVignette.classList.toggle('active', mode === 'vignette');
    }

    // Bascule vers le detail : initialise la DataTable de l'onglet actif si ce n'est pas deja fait
    // (jusque-la elle etait masquee par le mode vignette, donc jamais initialisee).
    if (mode === 'detail') {
      initGroupeTable(document.querySelector('#groupesTabContent .tab-pane.active'));
    }
  };

  if (btnDisplayDetail) {
    btnDisplayDetail.addEventListener('click', function () {
      setDisplayMode('detail');
    });
  }

  if (btnDisplayVignette) {
    btnDisplayVignette.addEventListener('click', function () {
      setDisplayMode('vignette');
    });
  }

  // Sur telephone (< md) la bascule Detail/Vignette est masquee : on force les vignettes si l'ecran
  // passe sous ce seuil alors que le tableau est affiche (rotation, fenetre redimensionnee).
  const mobileQuery = window.matchMedia('(max-width: 767.98px)');

  mobileQuery.addEventListener('change', function (event) {
    if (event.matches && isDetailModeActive()) {
      setDisplayMode('vignette');
    }
  });

  /**
   * Export PDF : declenche l'impression (navigateur) de la DataTable de l'onglet actif,
   * l'utilisateur choisit "Enregistrer au format PDF" dans la boite de dialogue.
   */
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-groupe-export-pdf');

    if (!button) {
      return;
    }

    const table = document.querySelector(button.dataset.target || '');
    const instance = table ? dataTableInstances.get(table) : null;

    if (instance && typeof instance.print === 'function') {
      instance.print();
    } else {
      window.print();
    }
  });

  /**
   * Ouverture de la previsualisation d'image (photo / CACI) en modal.
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
   * Colonne Groupes de l'onglet « Tous les groupes » (Responsables de Groupe) : un clic ouvre une
   * liste deroulante multiple (TomSelect) des groupes publies ; la perte de focus enregistre la
   * nouvelle composition (groupes.updateGroupesAdherent), Echap annule.
   */
  const groupesDisponibles = (window.Joomla && Joomla.getOptions('com_gdadhesions.groupesDisponibles')) || [];

  /**
   * Indique si deux listes d'identifiants contiennent les memes valeurs, quel que soit l'ordre.
   *
   * @param {string[]} premiere
   * @param {string[]} seconde
   * @returns {boolean}
   */
  const memesIdentifiants = function (premiere, seconde) {
    return premiere.length === seconde.length && premiere.every(function (id) {
      return seconde.includes(id);
    });
  };

  /**
   * Remplace la cellule Groupes par celle re-rendue par le serveur. La ligne est mise a jour dans
   * les donnees de simple-datatables (sinon la modification disparaitrait au prochain tri, a la
   * prochaine recherche ou au changement de page) ; a defaut, la cellule est remplacee dans le DOM.
   *
   * @param {HTMLTableCellElement} cellule Cellule affichee.
   * @param {string} html Cellule <td> re-rendue (layout groupes.cellule_groupes).
   * @returns {void}
   */
  const remplacerCelluleGroupes = function (cellule, html) {
    const modele = document.createElement('template');
    modele.innerHTML = html.trim();

    const nouvelleCellule = modele.content.firstElementChild;
    const ligne = cellule.closest('tr');
    const table = cellule.closest('table');
    const instance = table ? dataTableInstances.get(table) : null;
    const index = ligne ? parseInt(ligne.dataset.index || '', 10) : NaN;

    if (!nouvelleCellule) {
      return;
    }

    if (instance && !Number.isNaN(index) && instance.data.data[index]) {
      const colonne = Array.prototype.indexOf.call(ligne.children, cellule);
      const attributs = {};

      Array.from(nouvelleCellule.attributes).forEach(function (attribut) {
        attributs[attribut.name] = attribut.value;
      });

      const cellules = instance.data.data[index].cells.slice();
      cellules[colonne] = { data: nouvelleCellule.innerHTML, attributes: attributs };
      instance.rows.updateRow(index, cellules);
    } else {
      cellule.replaceWith(nouvelleCellule);
    }
  };

  /**
   * Referme la liste deroulante et enregistre la composition si elle a change.
   *
   * @param {HTMLTableCellElement} cellule Cellule en cours d'edition.
   * @param {TomSelect} tomSelect Instance TomSelect de la cellule.
   * @param {boolean} annulee Edition abandonnee (Echap) : rien n'est enregistre.
   * @returns {void}
   */
  const fermerEditionGroupes = function (cellule, tomSelect, annulee) {
    const affichage = cellule.querySelector('.js-groupes-affichage');
    const select = tomSelect.input;
    const initiaux = (cellule.dataset.idGroupes || '').split(',').filter(Boolean);
    const nouveaux = tomSelect.getValue();

    tomSelect.destroy();
    select.remove();
    affichage.classList.remove('d-none');
    delete cellule.dataset.enEdition;

    if (annulee || memesIdentifiants(initiaux, nouveaux)) {
      return;
    }

    const ajaxData = {
      task: 'groupes.updateGroupesAdherent',
      id_profil: cellule.dataset.idProfil || '0'
    };
    const csrfTokenName = Joomla.getOptions('csrf.token');

    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    nouveaux.forEach(function (idGroupe, position) {
      ajaxData['id_groupes[' + position + ']'] = idGroupe;
    });

    affichage.classList.add('opacity-50');

    simpleCallAjax(ajaxData, function (response) {
      remplacerCelluleGroupes(cellule, decodeURIComponent(escape(atob(response.data.html))));
    }, true, function () {
      affichage.classList.remove('opacity-50');
    });
  };

  /**
   * Ouvre la liste deroulante des groupes dans la cellule.
   *
   * @param {HTMLTableCellElement} cellule Cellule .js-editable-groupes.
   * @returns {void}
   */
  const ouvrirEditionGroupes = function (cellule) {
    const affichage = cellule.querySelector('.js-groupes-affichage');

    if (!affichage || cellule.dataset.enEdition === '1' || typeof TomSelect === 'undefined') {
      return;
    }

    cellule.dataset.enEdition = '1';

    const initiaux = (cellule.dataset.idGroupes || '').split(',').filter(Boolean);
    const select = document.createElement('select');
    select.multiple = true;
    select.setAttribute('aria-label', cellule.getAttribute('title') || '');

    groupesDisponibles.forEach(function (groupe) {
      const id = String(groupe.id);
      select.add(new Option(groupe.nom, id, false, initiaux.includes(id)));
    });

    affichage.classList.add('d-none');
    cellule.appendChild(select);

    let annulee = false;
    const tomSelect = new TomSelect(select, {
      plugins: {
        remove_button: {
          title: Joomla.Text._('COM_GDA_GROUPES_COMPOSITION_RETIRER', 'Retirer ce groupe')
        }
      },
      create: false,
      persist: false,
      dropdownParent: 'body'
    });

    // Echap ferme d'abord la liste ouverte (comportement TomSelect), puis annule l'edition.
    tomSelect.control_input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !tomSelect.isOpen) {
        annulee = true;
        tomSelect.blur();
      }
    });

    tomSelect.on('blur', function () {
      // Hors du gestionnaire d'evenement de TomSelect, qui ne doit pas etre detruit pendant son blur.
      setTimeout(function () {
        fermerEditionGroupes(cellule, tomSelect, annulee);
      }, 0);
    });

    tomSelect.focus();
  };

  document.addEventListener('click', function (event) {
    const cellule = event.target.closest('.js-editable-groupes');

    if (cellule) {
      ouvrirEditionGroupes(cellule);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && event.target.classList && event.target.classList.contains('js-editable-groupes')) {
      event.preventDefault();
      ouvrirEditionGroupes(event.target);
    }
  });

});
