document.addEventListener('DOMContentLoaded', function () {

  /**
   * Options de configuration pour simple-datatables avec les labels en français.
   */
  const frenchDataTableOptions = {
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

});
