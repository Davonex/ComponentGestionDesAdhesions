document.addEventListener('DOMContentLoaded', function () {
  //wizardSecretariat


  /**
   * Options de configuration pour simple-datatables avec les labels en français.
   */
  const frenchDataTableOptions = {
    labels: {
      placeholder: 'Rechercher...',
      // Sur cette version de simple-datatables, le select est déjà rendu dans le label.
      // Garder seulement ce texte évite l'affichage littéral de "{select}".
      perPage: 'lignes par page',
      noRows: 'Aucune donnée disponible',
      noResults: 'Aucun résultat trouvé',
      info: 'Affichage de {start} à {end} sur {rows} entrées'
    },
    // fixedColumns (true par defaut) mesure la largeur rendue de chaque colonne et la fige en
    // pourcentage via un style inline sur chaque <th> - une inline style gagne toujours sur le
    // colgroup/CSS des tableaux du Secretariat (#step-0/1/3/4/orphelins table.secretariat-table
    // { table-layout: fixed } dans gda.css), d'ou les colonnes "Action" (icone seule) qui
    // restaient aussi larges que "Nom Prenom". Desactive globalement : aucun de ces tableaux
    // n'a besoin de largeurs figees entre deux tris/recherches.
    fixedColumns: false,
    // Classes custom pour styler simple-datatables dans gda.css
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

  const wizard = document.getElementById('wizardSecretariat');
  const navButtons = document.querySelectorAll('#wizardNav .nav-link');

  // Navigation Précédent/Suivant du bas de page : réutilise tel quel les boutons de la barre du
  // haut (#wizardNav), donc le chargement ajax de chaque étape (loadStepTwo/Three/Four, cf. plus
  // bas) reste déclenché puisqu'il dépend de l'index de la slide, pas du bouton cliqué.
  const btnFooterPrev = document.getElementById('btnFooterPrev');
  const btnFooterNext = document.getElementById('btnFooterNext');
  let currentWizardStep = 0;

  btnFooterPrev?.addEventListener('click', function () {
    navButtons[currentWizardStep - 1]?.click();
  });

  btnFooterNext?.addEventListener('click', function () {
    navButtons[currentWizardStep + 1]?.click();
  });

  const licenceFinalizeModal = document.getElementById('licenceFinalizeModal');
  const licenceFinalizeInput = document.getElementById('licenceFinalizeInput');
  const licenceFinalizeMessage = document.getElementById('licenceFinalizeMessage');
  const licenceFinalizeProfilId = document.getElementById('licenceFinalizeProfilId');
  const licenceFinalizeCampagneId = document.getElementById('licenceFinalizeCampagneId');
  const licenceFinalizeSubmit = document.getElementById('licenceFinalizeSubmit');
  const deleteAdherentModal = document.getElementById('deleteAdherentModal');
  const deleteAdherentMessage = document.getElementById('deleteAdherentMessage');
  const deleteAdherentProfilId = document.getElementById('deleteAdherentProfilId');
  const deleteAdherentCampagneId = document.getElementById('deleteAdherentCampagneId');
  const deleteAdherentSubmit = document.getElementById('deleteAdherentSubmit');
  const licenceFinalizeModalInstance = licenceFinalizeModal && window.bootstrap && bootstrap.Modal
    ? bootstrap.Modal.getOrCreateInstance(licenceFinalizeModal)
    : null;
  const deleteAdherentModalInstance = deleteAdherentModal && window.bootstrap && bootstrap.Modal
    ? bootstrap.Modal.getOrCreateInstance(deleteAdherentModal)
    : null;

  /**
   * Initialise les tooltips Bootstrap declaratifs dans une zone.
   *
   * @param {ParentNode} root Zone racine (document, step-0, step-1, etc.).
   * @returns {void}
   */
  const initTooltips = function (root = document) {
    if (!window.bootstrap || !bootstrap.Tooltip) {
      return;
    }

    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      const existingTooltip = bootstrap.Tooltip.getInstance(el);

      if (existingTooltip) {
        existingTooltip.dispose();
      }

      new bootstrap.Tooltip(el, {
        trigger: 'hover focus',
        container: 'body'
      });
    });
  };

  /**
   * Initialise une DataTable simple-datatables sur une table, et réattache les tooltips à chaque
   * redessin (tri, recherche, pagination, changement du nombre de lignes par page).
   *
   * simple-datatables réutilise les mêmes noeuds `<tr>` pour ces opérations plutôt que de les
   * recréer, alors que Bootstrap fige le titre d'une tooltip dans un attribut interne
   * (`data-bs-original-title`) au moment de sa construction. Sans ce réattachement, une tooltip
   * continuait d'afficher les données de l'adhérent qui occupait ce noeud DOM lors du chargement
   * initial de l'étape, même après un tri/une recherche ayant changé l'adhérent affiché à cet
   * endroit (bug constaté en production sur le bouton de suppression de l'étape 1).
   *
   * @param {HTMLTableElement|null} table     Table à transformer en DataTable.
   * @param {ParentNode}            container Zone à retransmettre à initTooltips() à chaque redessin.
   * @param {Object}                [options] Options simple-datatables à fusionner par-dessus frenchDataTableOptions (ex. `columns`).
   * @returns {void}
   */
  const initDataTableWithTooltips = function (table, container, options = {}) {
    const datatableApi = globalThis.simpleDatatables;

    if (!table) {
      return;
    }

    if (!datatableApi || !datatableApi.DataTable) {
      console.error('simple-datatables n\'est pas chargee');
      return;
    }

    const dataTable = new datatableApi.DataTable(table, Object.assign({}, frenchDataTableOptions, options));

    dataTable.on('datatable.update', function () {
      initTooltips(container);
    });
  };

  /**
   * Construit la config `columns` de simple-datatables (headerClass + cellClass) a partir d'une
   * classe de largeur (col-secretariat-*, cf. gda.css) par colonne, dans l'ordre des colonnes du
   * tableau source.
   *
   * Le <colgroup> HTML ne fonctionne pas avec cette lib : au rendu, elle reconstruit entierement
   * le <thead>/<tbody> depuis ses propres donnees et ne recopie jamais le <colgroup> du tableau
   * source (constate en devtools : la largeur retombait sur un partage egal entre colonnes malgre
   * un <colgroup> et un CSS corrects). headerClass/cellClass est la seule facon documentee de
   * poser une classe CSS sur les <th>/<td> qu'elle genere elle-meme.
   *
   * @param {Array<string|null>} widthClasses Classe col-secretariat-* par colonne (index = position dans le tableau source), null pour ne rien poser sur cette colonne.
   * @returns {Array<Object>} Config `columns` pour simpleDatatables.DataTable.
   */
  const buildWidthColumns = function (widthClasses) {
    return widthClasses.reduce(function (columns, className, index) {
      if (className) {
        columns.push({ select: index, headerClass: className, cellClass: className });
      }

      return columns;
    }, []);
  };

  /**
   * Applique l'état (actif/désactivé) du bouton de validation du CACI d'une ligne, ainsi que
   * l'infobulle qui explique pourquoi (fichier manquant, date manquante, date insuffisante...).
   *
   * La validité (fichier chargé + CACI daté d'au moins 9 mois à compter du 1er septembre de la
   * saison) est une règle métier calculée côté serveur (SouscriptionService::isCaciValidable),
   * pour éviter toute divergence entre le rendu initial du tableau et une mise à jour en AJAX.
   *
   * @param {HTMLElement} row La ligne du tableau contenant le bouton.
   * @param {boolean} canValidate Etat de validité renvoyé par le serveur.
   * @param {string} [reasonText] Texte d'infobulle à afficher quand non valide (calculé par
   *   l'appelant à partir des mêmes data-hint-* que le badge de date, pour rester synchronisé).
   * @returns {void}
   */
  const setValidateCaciButtonState = function (row, canValidate, reasonText) {
    const button = row ? row.querySelector('.js-validate-caci') : null;

    if (!button) {
      return;
    }

    button.disabled = !canValidate;
    button.setAttribute('aria-disabled', canValidate ? 'false' : 'true');

    const tooltipWrapper = button.closest('.js-validate-caci-tooltip');
    const newTitle = canValidate ? (tooltipWrapper?.dataset.labelValide || '') : (reasonText || '');

    if (!tooltipWrapper || newTitle === '') {
      return;
    }

    tooltipWrapper.setAttribute('title', newTitle);
    tooltipWrapper.setAttribute('data-bs-title', newTitle);
    initTooltips(row);
  };


  /**
   *Initialise DataTable pour le tableau de l'etape 1 (contenu charge en AJAX dans #step-0).
   * @returns {void}
   */
  const initStepZeroView = function () {
    const step0Container = document.getElementById('step-0');
    const table1 = document.querySelector('#step-0 table');

    initDataTableWithTooltips(table1, step0Container, {
      columns: buildWidthColumns([
        'col-secretariat-xs', // Action (supprimer)
        'col-secretariat-xs',  // Photo
        'col-secretariat-sm',  // Licence
        'col-secretariat-lg',  // Nom
        'col-secretariat-lg',  // Email
        'col-secretariat-lg',  // Adresse
        'col-secretariat-xs',  // Caci
        'col-secretariat-sm',  // Date Caci
        'col-secretariat-sm',  // Date souscription
        'col-secretariat-xs'  // Action (valider)
      ])
    });

    if (step0Container) {
      initTooltips(step0Container);
    }
  };

  /**
   * Initialise DataTable pour le tableau de l'etape 2 (contenu charge en AJAX dans #step-1).
   * @returns {void}
   */
  const initStepOneView = function () {
    const step1Container = document.getElementById('step-1');
    const table2 = document.querySelector('#step-1 table');

    initDataTableWithTooltips(table2, step1Container, {
      columns: buildWidthColumns([
        'col-secretariat-xs', // Action (devalider CACI)
        'col-secretariat-xs',  // Photo
        'col-secretariat-sm',  // Licence
        'col-secretariat-sm',  // Nom
        'col-secretariat-lg',  // Ville
        'col-secretariat-md',  // Cotisation (Tarification)
        'col-secretariat-md',  // Groupes
        'col-secretariat-xs',  // Categorie
        'col-secretariat-xs',  // Licence EUR
        'col-secretariat-xs',  // Cotisation EUR
        'col-secretariat-md',  // Paiement
        'col-secretariat-sm',  // Date
        'col-secretariat-xs'  // Action (valider paiement)
      ])
    });

    if (step1Container) {
      initTooltips(step1Container);
      // Bouton HelloAsso (.js-show-payement) : géré par le handler délégué global de
      // media/com_gdadhesions/js/form_modal.js, commun à toutes les vues - pas de binding ici.
    }
  };

  /**
   * Charge et remplace le contenu HTML de la step 2.
   * @returns {void}
   */
  const loadStepTwo = function () {
    const step1Container = document.getElementById('step-1');

    if (!step1Container) {
      return;
    }

    const ajaxData = { task: 'secretariat.stepTwo' };
    const csrfTokenName = Joomla.getOptions('csrf.token');
    const hideStep1Loader = function () {
      if (window.GdaSpinner) {
        window.GdaSpinner.hide(step1Container);
      }
    };

    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    if (typeof simpleCallAjax === 'function') {
      if (window.GdaSpinner) {
        window.GdaSpinner.show(step1Container, { text: 'Chargement des payments à valider...' });
      }

      const step1FallbackTimer = window.setTimeout(hideStep1Loader, 15000);

      simpleCallAjax(ajaxData, function (response) {
        window.clearTimeout(step1FallbackTimer);
        hideStep1Loader();

        if (response.success) {
          step1Container.innerHTML = decodeURIComponent(escape(atob(response.data)));
          initStepOneView();
        }
      }, false);
    }
  };

  /**
   * Initialise DataTable pour le tableau de l'etape 3 (contenu charge en AJAX dans #step-2).
   * @returns {void}
   */
  const initStepThreeView = function () {
    const step2Container = document.getElementById('step-2');
    const table3 = document.querySelector('#step-2 table');

    initDataTableWithTooltips(table3, step2Container, {
      columns: buildWidthColumns([
        'col-secretariat-xs', // Action (devalider paiement)
        'col-secretariat-xs',  // Photo
        'col-secretariat-sm',  // Licence
        'col-secretariat-lg',  // Nom
        'col-secretariat-lg',  // Email
        'col-secretariat-sm',  // Date de naissance
        'col-secretariat-md',  // Cotisation
        'col-secretariat-xs',  // Categorie
        'col-secretariat-sm',  // Date
        'col-secretariat-xs'  // Action (finaliser)
      ])
    });

    if (step2Container) {
      initTooltips(step2Container);
    }
  };

  /**
   * Charge et remplace le contenu HTML de la step 3.
   * @returns {void}
   */
  const loadStepThree = function () {
    const step2Container = document.getElementById('step-2');

    if (!step2Container) {
      return;
    }

    const ajaxData = { task: 'secretariat.stepThree' };
    const csrfTokenName = Joomla.getOptions('csrf.token');
    const hideStep3Loader = function () {
      if (window.GdaSpinner) {
        window.GdaSpinner.hide(step2Container);
      }
    };

    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    if (typeof simpleCallAjax === 'function') {
      if (window.GdaSpinner) {
        window.GdaSpinner.show(step2Container, { text: 'Chargement des licences a enregistrer...' });
      }

      const step3FallbackTimer = window.setTimeout(hideStep3Loader, 15000);

      simpleCallAjax(ajaxData, function (response) {
        window.clearTimeout(step3FallbackTimer);
        hideStep3Loader();

        if (response.success) {
          step2Container.innerHTML = decodeURIComponent(escape(atob(response.data)));
          initStepThreeView();
        }
      }, false);
    }
  };

  /**
   * Initialise DataTable pour le tableau de l'etape 4 (contenu charge en AJAX dans #step-3).
   * @returns {void}
   */
  const initStepFourView = function () {
    const step3Container = document.getElementById('step-3');
    const table4 = document.querySelector('#step-3 table');

    initDataTableWithTooltips(table4, step3Container, {
      columns: buildWidthColumns([
        'col-secretariat-xs', // Action (definaliser)
        'col-secretariat-xs',  // Photo
        'col-secretariat-xs',  // Caci
        'col-secretariat-sm',  // Paiement
        'col-secretariat-sm',  // Licence
        'col-secretariat-md',  // Nom
        'col-secretariat-md',  // Email
        'col-secretariat-sm',  // Date de naissance
        'col-secretariat-md',  // Cotisation
        'col-secretariat-xs',  // Categorie
        'col-secretariat-sm'   // Date
      ])
    });

    if (step3Container) {
      initTooltips(step3Container);
      // Bouton HelloAsso (.js-show-payement) : géré par le handler délégué global de
      // media/com_gdadhesions/js/form_modal.js, commun à toutes les vues - pas de binding ici.
    }
  };

  /**
   * Charge et remplace le contenu HTML de la step 4.
   * @returns {void}
   */
  const loadStepFour = function () {
    const step3Container = document.getElementById('step-3');

    if (!step3Container) {
      return;
    }

    const ajaxData = { task: 'secretariat.inscriptionsFinalises' };
    const csrfTokenName = Joomla.getOptions('csrf.token');
    const hideStep4Loader = function () {
      if (window.GdaSpinner) {
        window.GdaSpinner.hide(step3Container);
      }
    };

    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    if (typeof simpleCallAjax === 'function') {
      if (window.GdaSpinner) {
        window.GdaSpinner.show(step3Container, { text: 'Chargement des adhesions finalisees...' });
      }

      const step4FallbackTimer = window.setTimeout(hideStep4Loader, 15000);

      simpleCallAjax(ajaxData, function (response) {
        window.clearTimeout(step4FallbackTimer);
        hideStep4Loader();

        if (response.success) {
          step3Container.innerHTML = decodeURIComponent(escape(atob(response.data)));
          initStepFourView();
        }
      }, false);
    }
  };

  /**
   * Initialise DataTable + tooltips pour le tableau de l'etape 5 (contenu charge en AJAX dans
   * #step-orphelins-content).
   * @returns {void}
   */
  const initStepOrphelinsView = function () {
    const contentContainer = document.getElementById('step-orphelins-content');
    const table5 = document.querySelector('#step-orphelins-content table');

    initDataTableWithTooltips(table5, contentContainer, {
      columns: buildWidthColumns([
        'col-secretariat-narrow', // Commande
        'col-secretariat-narrow', // Date
        null,                     // Payeur (partage le reste)
        null                      // Adherent associe (partage le reste)
      ])
    });

    if (contentContainer) {
      initTooltips(contentContainer);
    }
  };

  // Dernier HTML brut (non filtre) recu du serveur pour l'etape 5, et filtre actuellement
  // selectionne : conserves pour pouvoir re-filtrer cote client (#orphelinsFilter) sans
  // repartir en ajax a chaque changement, cf. renderStepOrphelins() ci-dessous.
  let lastOrphelinsHtml = '';
  let currentOrphelinsFilter = 'non_associes';

  /**
   * (Re)affiche le contenu de l'etape 5 a partir du dernier HTML recu du serveur
   * (lastOrphelinsHtml), en ne gardant que les lignes `<tr>` correspondant au filtre courant
   * (currentOrphelinsFilter) avant d'initialiser simple-datatables : la lib reconstruit sa
   * propre pagination a l'init a partir des lignes presentes dans le DOM source, le filtrage
   * doit donc avoir lieu avant, pas en cachant des lignes apres coup.
   * @returns {void}
   */
  const renderStepOrphelins = function () {
    const contentContainer = document.getElementById('step-orphelins-content');

    if (!contentContainer || !lastOrphelinsHtml) {
      return;
    }

    contentContainer.innerHTML = lastOrphelinsHtml;

    if (currentOrphelinsFilter !== 'tous') {
      const wantResolved = currentOrphelinsFilter === 'associes' ? '1' : '0';

      contentContainer.querySelectorAll('table tbody tr[data-resolved]').forEach(function (row) {
        if (row.dataset.resolved !== wantResolved) {
          row.remove();
        }
      });
    }

    initStepOrphelinsView();
  };

  /**
   * Charge et remplace le contenu HTML de la step 5 (paiements HelloAsso orphelins).
   * @param {boolean} [forceRefresh=false] Ignore le cache fichier HelloAsso de 30 min.
   * @returns {void}
   */
  const loadStepOrphelins = function (forceRefresh = false) {
    const contentContainer = document.getElementById('step-orphelins-content');

    if (!contentContainer) {
      return;
    }

    const ajaxData = { task: 'secretariat.stepOrphelins' };

    if (forceRefresh) {
      ajaxData.force_refresh = 1;
    }

    const csrfTokenName = Joomla.getOptions('csrf.token');
    const hideOrphelinsLoader = function () {
      if (window.GdaSpinner) {
        window.GdaSpinner.hide(contentContainer);
      }
    };

    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    if (typeof simpleCallAjax === 'function') {
      if (window.GdaSpinner) {
        window.GdaSpinner.show(contentContainer, {
          text: forceRefresh ? 'Actualisation depuis HelloAsso...' : 'Chargement des paiements orphelins...'
        });
      }

      const orphelinsFallbackTimer = window.setTimeout(hideOrphelinsLoader, 15000);

      simpleCallAjax(ajaxData, function (response) {
        window.clearTimeout(orphelinsFallbackTimer);
        hideOrphelinsLoader();

        if (response.success) {
          lastOrphelinsHtml = decodeURIComponent(escape(atob(response.data)));
          renderStepOrphelins();

          // Un chargement/rafraîchissement normal renvoie un message vide (simpleCallAjax est
          // appelé avec renderMessage=false pour rester muet) : seul le rafraîchissement ayant
          // réellement déclenché des associations automatiques (voir RapprochementPaiementService::
          // autoAssocierParLicenceExacte()) porte un message, qu'on affiche alors explicitement.
          if (response.message) {
            Joomla.renderMessages({ message: [response.message] });
          }
        }
      }, false);
    }
  };

  /**
   * Envoie la finalisation de l'inscription au serveur.
   * @param {number} idProfil
   * @param {number} idCampagne
   * @param {string} licence
   * @param {HTMLElement|null} sourceButton
   * @returns {void}
   */
  const submitFinalizeInscription = function (idProfil, idCampagne, licence = '', sourceButton = null) {
    if (idProfil <= 0 || idCampagne <= 0) {
      Joomla.renderMessages({ error: ['Identifiants invalides pour finaliser l\'inscription.'] });
      return;
    }

    const ajaxData = {
      task: 'secretariat.finalizeInscription',
      id_profil: idProfil,
      id_campagne: idCampagne
    };

    const normalizedLicence = (licence || '').trim().toUpperCase();

    if (normalizedLicence !== '') {
      ajaxData.licence = normalizedLicence;
    }

    const csrfTokenName = Joomla.getOptions('csrf.token');
    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    if (sourceButton) {
      sourceButton.dataset.isSaving = '1';
    }

    if (licenceFinalizeSubmit) {
      licenceFinalizeSubmit.disabled = true;
    }

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        if (deleteAdherentModalInstance) {
          deleteAdherentModalInstance.hide();
        }

        if (sourceButton) {
          sourceButton.dataset.isSaving = '0';
        }

        if (licenceFinalizeSubmit) {
          licenceFinalizeSubmit.disabled = false;
        }

        if (!response.success) {
          return;
        }

        if (licenceFinalizeModalInstance) {
          licenceFinalizeModalInstance.hide();
        }

        loadStepThree();
      });
    } else {
      if (sourceButton) {
        sourceButton.dataset.isSaving = '0';
      }

      if (licenceFinalizeSubmit) {
        licenceFinalizeSubmit.disabled = false;
      }

      console.error('simpleCallAjax n\'est pas disponible');
    }
  };




  /**
   * gestion de l'affichage des boutons et du header à chaque changement d'étape du carousel
   */
  if (wizard) {
    wizard.addEventListener('slid.bs.carousel', function (e) {
      currentWizardStep = e.to;

      // Mise à jour barre de navigation
      navButtons.forEach(function (btn) {
        btn.classList.remove('active');
      });

      if (navButtons[e.to]) {
        navButtons[e.to].classList.add('active');
      }

      // Navigation Précédent/Suivant du bas de page : Précédent masqué sur la 1ere étape,
      // Suivant masqué sur la dernière.
      if (btnFooterPrev) {
        btnFooterPrev.classList.toggle('invisible', e.to === 0);
      }
      if (btnFooterNext) {
        btnFooterNext.classList.toggle('d-none', e.to === navButtons.length - 1);
      }

      // Chargement AJAX du contenu de l'etape 1 a chaque retour sur le slide 0.
      if (e.to === 0) {
        const step0Container = document.getElementById('step-0');

        if (step0Container) {
          const ajaxData = { task: 'secretariat.stepOne' };
          const csrfTokenName = Joomla.getOptions('csrf.token');
          const hideStep0Loader = function () {
            if (window.GdaSpinner) {
              window.GdaSpinner.hide(step0Container);
            }
          };

          if (csrfTokenName) {
            ajaxData[csrfTokenName] = 1;
          }

          if (typeof simpleCallAjax === 'function') {
            if (window.GdaSpinner) {
              window.GdaSpinner.show(step0Container, { text: 'Chargement des CACI à valider...' });
            }

            const step0FallbackTimer = window.setTimeout(hideStep0Loader, 15000);

            simpleCallAjax(ajaxData, function (response) {
              window.clearTimeout(step0FallbackTimer);
              hideStep0Loader();

              if (response.success) {
                step0Container.innerHTML = decodeURIComponent(escape(atob(response.data)));
                initStepZeroView();
              }
            }, false);
          }
        }
      }

      // Chargement AJAX du contenu de l'etape 2 systematiquement a chaque affiche.
      if (e.to === 1) {
        loadStepTwo();
      }

      // Chargement AJAX du contenu de l'etape 3 systematiquement a chaque affiche.
      if (e.to === 2) {
        loadStepThree();
      }

      // Chargement AJAX du contenu de l'etape 4 systematiquement a chaque affiche.
      if (e.to === 3) {
        loadStepFour();
      }

      // Chargement AJAX du contenu de l'etape 5 (paiements HelloAsso orphelins) systematiquement a chaque affiche.
      if (e.to === 4) {
        loadStepOrphelins();
      }
    });
  }


  initStepZeroView();
  initTooltips(document);

  /**
   * Gestion de l'ouverture de la previsualisation d'image en modal.
   * @returns {void}
   */
  document.addEventListener('click', function (event) {
    const trigger = event.target.closest('.js-image-preview-thumb, .js-caci-thumb');

    const previewImage = document.getElementById('imagePreviewImage');

    if (!trigger || !previewImage) {
      return;
    }

    const src = trigger.dataset.imageSrc || trigger.dataset.caciSrc || '';

    if (!src) {
      event.preventDefault();
      return;
    }

    previewImage.src = src;
    previewImage.alt = trigger.dataset.imageAlt || trigger.getAttribute('aria-label') || '';
  });


  /**
   * Nettoyage de la source quand la modal est fermee.
   * @returns {void}
   */
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



  // Gestion de l'édition inline de la date CACI au double-clic.
  document.addEventListener('dblclick', function (event) {
    const editableCell = event.target.closest('.js-editable-date-caci');

    if (!editableCell) {
      return;
    }

    const display = editableCell.querySelector('.date-display');
    const input = editableCell.querySelector('.date-input');

    if (!display || !input) {
      return;
    }

    // Passer en mode édition. La valeur vient de data-current-date (pas du badge, dont le
    // texte affiche "—" quand la date est vide, ce qui polluerait la saisie).
    display.classList.add('d-none');
    input.classList.remove('d-none');
    input.value = editableCell.dataset.currentDate || '';
    input.focus();
    input.select();
  });

  // Gestion de l'édition inline de la categorie au double-clic.
  document.addEventListener('dblclick', function (event) {
    const editableCell = event.target.closest('.js-editable-categorie');

    if (!editableCell) {
      return;
    }

    const display = editableCell.querySelector('.categorie-display');
    const select = editableCell.querySelector('.categorie-input');

    if (!display || !select) {
      return;
    }

    display.classList.add('d-none');
    select.classList.remove('d-none');

    const currentCategorie = (editableCell.dataset.currentCategorie || '').toUpperCase();
    if (currentCategorie) {
      select.value = currentCategorie;
    }

    select.focus();
  });

  // Gestion de l'édition inline de la tarification (reduction) au double-clic : meme motif
  // que .js-editable-categorie ci-dessus.
  document.addEventListener('dblclick', function (event) {
    const editableCell = event.target.closest('.js-editable-cotisation');

    if (!editableCell) {
      return;
    }

    const display = editableCell.querySelector('.cotisation-display');
    const select = editableCell.querySelector('.cotisation-input');

    if (!display || !select) {
      return;
    }

    display.classList.add('d-none');
    select.classList.remove('d-none');

    const currentReduction = editableCell.dataset.currentReduction || '';
    if (currentReduction) {
      select.value = currentReduction;
    }

    select.focus();
  });

  // Correction d'une association deja resolue (etape 5) au double-clic : meme motif que
  // .js-editable-categorie ci-dessus.
  document.addEventListener('dblclick', function (event) {
    const editableCell = event.target.closest('.js-editable-adherent');

    if (!editableCell) {
      return;
    }

    const display = editableCell.querySelector('.adherent-display');
    const select = editableCell.querySelector('.adherent-input');

    if (!display || !select) {
      return;
    }

    display.classList.add('d-none');
    select.classList.remove('d-none');

    const currentProfil = editableCell.dataset.currentProfil || '';
    if (currentProfil) {
      select.value = currentProfil;
    }

    select.focus();
  });

  // Validation du CACI : met a jour la souscription et retire la ligne du tableau.
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-validate-caci');

    if (!button) {
      return;
    }

    if (button.dataset.isSaving === '1') {
      return;
    }

    const idProfil = parseInt(button.dataset.itemId || '0', 10);
    const idCampagne = parseInt(button.dataset.itemCampagne || '0', 10);

    if (idProfil <= 0 || idCampagne <= 0) {
      Joomla.renderMessages({ error: ['Identifiants invalides pour valider le CACI.'] });
      return;
    }

    const ajaxData = {
      task: 'secretariat.validateCaci',
      id_profil: idProfil,
      id_campagne: idCampagne
    };

    const csrfTokenName = Joomla.getOptions('csrf.token');
    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    button.dataset.isSaving = '1';

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        button.dataset.isSaving = '0';

        if (!response.success) {
          return;
        }

        const row = button.closest('tr');
        if (row) {
          row.classList.add('d-none');
        }
      });
    } else {
      button.dataset.isSaving = '0';
      console.error('simpleCallAjax n\'est pas disponible');
    }
  });

  /**
   * Suppression definitive d'un adherent (stepOne) apres confirmation.
   * @returns {void}
   */
  const submitDeleteAdherent = function (idProfil, idCampagne, sourceButton = null) {
    if (idProfil <= 0 || idCampagne <= 0) {
      Joomla.renderMessages({ error: ['Identifiants invalides pour supprimer l\'adhérent.'] });
      return;
    }

    const ajaxData = {
      task: 'secretariat.deleteAdherent',
      id_profil: idProfil,
      id_campagne: idCampagne
    };

    const csrfTokenName = Joomla.getOptions('csrf.token');
    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    if (sourceButton) {
      sourceButton.dataset.isSaving = '1';
    }

    if (deleteAdherentSubmit) {
      deleteAdherentSubmit.disabled = true;
    }

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        if (sourceButton) {
          sourceButton.dataset.isSaving = '0';
        }

        if (deleteAdherentSubmit) {
          deleteAdherentSubmit.disabled = false;
        }

        if (!response.success) {
          return;
        }

        const row = sourceButton ? sourceButton.closest('tr') : null;
        if (row) {
          row.classList.add('d-none');
        } else {
          initStepZeroView();
        }
      });
    } else {
      if (sourceButton) {
        sourceButton.dataset.isSaving = '0';
      }

      if (deleteAdherentSubmit) {
        deleteAdherentSubmit.disabled = false;
      }

      console.error('simpleCallAjax n\'est pas disponible');
    }
  };

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-delete-adherent');

    if (!button) {
      return;
    }

    if (button.dataset.isSaving === '1') {
      return;
    }

    const idProfil = parseInt(button.dataset.itemId || '0', 10);
    const idCampagne = parseInt(button.dataset.itemCampagne || '0', 10);
    const civilite = (button.dataset.itemCivilite || 'M.').trim();
    const memberName = (button.dataset.itemName || '').trim();
    const licence = (button.dataset.itemLicence || '').trim();
    const displayName = (civilite + ' ' + memberName).trim();

    if (idProfil <= 0 || idCampagne <= 0) {
      Joomla.renderMessages({ error: ['Identifiants invalides pour supprimer l\'adhérent.'] });
      return;
    }

    const confirmMessage = 'Voulez-vous vraiment supprimer l\'adhérent '
      + displayName
      + ' Lic \'\''
      + licence
      + '\'';

    if (!deleteAdherentModalInstance || !deleteAdherentMessage || !deleteAdherentProfilId || !deleteAdherentCampagneId) {
      return;
    }

    deleteAdherentMessage.textContent = confirmMessage;
    deleteAdherentProfilId.value = String(idProfil);
    deleteAdherentCampagneId.value = String(idCampagne);
    deleteAdherentSubmit.dataset.sourceButtonId = button.dataset.itemId || '';

    deleteAdherentModalInstance.show();
  });

  if (deleteAdherentSubmit && deleteAdherentProfilId && deleteAdherentCampagneId) {
    deleteAdherentSubmit.addEventListener('click', function () {
      const sourceButtonId = deleteAdherentSubmit.dataset.sourceButtonId || '';
      const sourceButton = document.querySelector('.js-delete-adherent[data-item-id="' + sourceButtonId + '"]');
      const idProfil = parseInt(deleteAdherentProfilId.value || '0', 10);
      const idCampagne = parseInt(deleteAdherentCampagneId.value || '0', 10);

      if (deleteAdherentModalInstance) {
        deleteAdherentModalInstance.hide();
      }

      submitDeleteAdherent(
        idProfil,
        idCampagne,
        sourceButton
      );
    });
  }

  if (deleteAdherentModal) {
    deleteAdherentModal.addEventListener('hidden.bs.modal', function () {
      if (deleteAdherentMessage) {
        deleteAdherentMessage.textContent = '';
      }

      if (deleteAdherentProfilId) {
        deleteAdherentProfilId.value = '0';
      }

      if (deleteAdherentCampagneId) {
        deleteAdherentCampagneId.value = '0';
      }

      if (deleteAdherentSubmit) {
        deleteAdherentSubmit.disabled = false;
        deleteAdherentSubmit.dataset.sourceButtonId = '';
      }
    });
  }

  /**
   * De-validation du CACI (stepTwo) : repasse caci_check a 0 puis recharge la step 2.
   * @returns {void}
   */
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-unvalidate-caci');

    if (!button) {
      return;
    }

    if (button.dataset.isSaving === '1') {
      return;
    }

    const idProfil = parseInt(button.dataset.itemId || '0', 10);
    const idCampagne = parseInt(button.dataset.itemCampagne || '0', 10);

    if (idProfil <= 0 || idCampagne <= 0) {
      Joomla.renderMessages({ error: ['Identifiants invalides pour de-valider le CACI.'] });
      return;
    }

    const ajaxData = {
      task: 'secretariat.unvalidateCaci',
      id_profil: idProfil,
      id_campagne: idCampagne
    };

    const csrfTokenName = Joomla.getOptions('csrf.token');
    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    button.dataset.isSaving = '1';

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        button.dataset.isSaving = '0';

        if (!response.success) {
          return;
        }

        loadStepTwo();
      });
    } else {
      button.dataset.isSaving = '0';
      console.error('simpleCallAjax n\'est pas disponible');
    }
  });


  /**
   * Validation du paiement (stepTwo) : passe cotisation_check a 1 puis recharge la step 2.
   * @returns {void}
   */
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-validate-payment');

    if (!button) {
      return;
    }

    if (button.dataset.isSaving === '1') {
      return;
    }

    const idProfil = parseInt(button.dataset.itemId || '0', 10);
    const idCampagne = parseInt(button.dataset.itemCampagne || '0', 10);

    if (idProfil <= 0 || idCampagne <= 0) {
      Joomla.renderMessages({ error: ['Identifiants invalides pour valider le paiement.'] });
      return;
    }

    const ajaxData = {
      task: 'secretariat.validatePayment',
      id_profil: idProfil,
      id_campagne: idCampagne
    };

    const csrfTokenName = Joomla.getOptions('csrf.token');
    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    button.dataset.isSaving = '1';

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        button.dataset.isSaving = '0';

        if (!response.success) {
          return;
        }

        loadStepTwo();
      });
    } else {
      button.dataset.isSaving = '0';
      console.error('simpleCallAjax n\'est pas disponible');
    }
  });

  /**
   * De-validation du paiement (stepThree) : repasse cotisation_check a 0, vide la date puis recharge la step 3.
   * @returns {void}
   */
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-unvalidate-payment');

    if (!button) {
      return;
    }

    if (button.dataset.isSaving === '1') {
      return;
    }

    const idProfil = parseInt(button.dataset.itemId || '0', 10);
    const idCampagne = parseInt(button.dataset.itemCampagne || '0', 10);

    if (idProfil <= 0 || idCampagne <= 0) {
      Joomla.renderMessages({ error: ['Identifiants invalides pour de-valider le paiement.'] });
      return;
    }

    const ajaxData = {
      task: 'secretariat.unvalidatePayment',
      id_profil: idProfil,
      id_campagne: idCampagne
    };

    const csrfTokenName = Joomla.getOptions('csrf.token');
    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    button.dataset.isSaving = '1';

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        button.dataset.isSaving = '0';

        if (!response.success) {
          return;
        }

        loadStepThree();
      });
    } else {
      button.dataset.isSaving = '0';
      console.error('simpleCallAjax n\'est pas disponible');
    }
  });

  /**
   * Finalisation de l'inscription (stepThree) : passe licence_check a 1 puis recharge la step 3.
   * @returns {void}
   */
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-finalize-inscription');

    if (!button) {
      return;
    }

    if (button.dataset.isSaving === '1') {
      return;
    }

    const idProfil = parseInt(button.dataset.itemId || '0', 10);
    const idCampagne = parseInt(button.dataset.itemCampagne || '0', 10);

    if (idProfil <= 0 || idCampagne <= 0) {
      Joomla.renderMessages({ error: ['Identifiants invalides pour finaliser l\'inscription.'] });
      return;
    }

    const currentLicence = (button.dataset.itemLicence || '').trim().toUpperCase();
    const memberName = (button.dataset.itemName || '').trim();

    if (currentLicence.startsWith('N')) {
      if (!licenceFinalizeModalInstance || !licenceFinalizeInput || !licenceFinalizeMessage || !licenceFinalizeProfilId || !licenceFinalizeCampagneId) {
        Joomla.renderMessages({ error: ['La popup de saisie licence n\'est pas disponible.'] });
        return;
      }

      licenceFinalizeProfilId.value = String(idProfil);
      licenceFinalizeCampagneId.value = String(idCampagne);
      licenceFinalizeInput.value = '';
      licenceFinalizeInput.setCustomValidity('');
      licenceFinalizeInput.dataset.sourceButtonId = button.dataset.itemId || '';
      licenceFinalizeMessage.textContent = 'Pour finaliser l\'inscription de ' + memberName + ', nous avons besoin de son numero de licence. Merci de le saisir.';
      licenceFinalizeModalInstance.show();
      window.setTimeout(function () {
        licenceFinalizeInput.focus();
      }, 150);
      return;
    }

    submitFinalizeInscription(idProfil, idCampagne, '', button);
  });

  if (licenceFinalizeSubmit && licenceFinalizeInput && licenceFinalizeProfilId && licenceFinalizeCampagneId) {
    licenceFinalizeSubmit.addEventListener('click', function () {
      const licenceValue = licenceFinalizeInput.value.trim().toUpperCase();
      const licencePattern = /^A-[0-9]{2}-[0-9]{6,7}$/;

      licenceFinalizeInput.value = licenceValue;

      if (!licencePattern.test(licenceValue)) {
        licenceFinalizeInput.setCustomValidity('Format attendu: A-00-000000 ou A-00-0000000');
        licenceFinalizeInput.reportValidity();
        return;
      }

      licenceFinalizeInput.setCustomValidity('');

      submitFinalizeInscription(
        parseInt(licenceFinalizeProfilId.value || '0', 10),
        parseInt(licenceFinalizeCampagneId.value || '0', 10),
        licenceValue,
        null
      );
    });
  }

  if (licenceFinalizeModal) {
    licenceFinalizeModal.addEventListener('hidden.bs.modal', function () {
      if (licenceFinalizeInput) {
        licenceFinalizeInput.value = '';
        licenceFinalizeInput.setCustomValidity('');
      }

      if (licenceFinalizeProfilId) {
        licenceFinalizeProfilId.value = '0';
      }

      if (licenceFinalizeCampagneId) {
        licenceFinalizeCampagneId.value = '0';
      }

      if (licenceFinalizeMessage) {
        licenceFinalizeMessage.textContent = '';
      }

      if (licenceFinalizeSubmit) {
        licenceFinalizeSubmit.disabled = false;
      }
    });
  }

  /**
   * De-finalisation de l'inscription (stepFour) : repasse licence_check a 0 puis recharge la step 4.
   * @returns {void}
   */
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-unfinalize-inscription');

    if (!button) {
      return;
    }

    if (button.dataset.isSaving === '1') {
      return;
    }

    const idProfil = parseInt(button.dataset.itemId || '0', 10);
    const idCampagne = parseInt(button.dataset.itemCampagne || '0', 10);

    if (idProfil <= 0 || idCampagne <= 0) {
      Joomla.renderMessages({ error: ['Identifiants invalides pour dé-finaliser l\'inscription.'] });
      return;
    }

    const ajaxData = {
      task: 'secretariat.unfinalizeInscription',
      id_profil: idProfil,
      id_campagne: idCampagne
    };

    const csrfTokenName = Joomla.getOptions('csrf.token');
    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    button.dataset.isSaving = '1';

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        button.dataset.isSaving = '0';

        if (!response.success) {
          return;
        }

        loadStepFour();
      });
    } else {
      button.dataset.isSaving = '0';
      console.error('simpleCallAjax n\'est pas disponible');
    }
  });



  /**
   * Gestion de la validation et de la soumission de la date CACI.
   * @returns {void}
   */
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') {
      return;
    }

    const input = event.target.closest('.date-input:not(.d-none)');

    if (!input) {
      return;
    }

    event.preventDefault();
    input.dataset.ignoreBlurOnce = '1';
    saveDateCaci(input);
  });

  /**
   * Gestion de la perte de focus.
   * @returns {void}
   */
  document.addEventListener('blur', function (event) {
    // En capture, blur remonte aussi les pertes de focus de la fenêtre entière (ex: alt-tab) :
    // event.target vaut alors window/document, sans .closest().
    if (!(event.target instanceof Element)) {
      return;
    }

    const input = event.target.closest('.date-input:not(.d-none)');

    if (!input) {
      return;
    }

    // Evite un second envoi quand Enter declenche ensuite un blur.
    if (input.dataset.ignoreBlurOnce === '1') {
      input.dataset.ignoreBlurOnce = '0';
      return;
    }

    saveDateCaci(input);
  }, true);

  // Validation de la categorie sur Enter.
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') {
      return;
    }

    const select = event.target.closest('.categorie-input:not(.d-none)');

    if (!select) {
      return;
    }

    event.preventDefault();
    select.dataset.ignoreBlurOnce = '1';
    saveCategorie(select);
  });

  // Sauvegarde categorie a la perte de focus.
  document.addEventListener('blur', function (event) {
    if (!(event.target instanceof Element)) {
      return;
    }

    const select = event.target.closest('.categorie-input:not(.d-none)');

    if (!select) {
      return;
    }

    if (select.dataset.ignoreBlurOnce === '1') {
      select.dataset.ignoreBlurOnce = '0';
      return;
    }

    saveCategorie(select);
  }, true);

  // Sauvegarde categorie quand le choix change.
  document.addEventListener('change', function (event) {
    const select = event.target.closest('.categorie-input:not(.d-none)');

    if (!select) {
      return;
    }

    saveCategorie(select);
  });

  // Validation de la tarification (reduction) sur Enter.
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') {
      return;
    }

    const select = event.target.closest('.cotisation-input:not(.d-none)');

    if (!select) {
      return;
    }

    event.preventDefault();
    select.dataset.ignoreBlurOnce = '1';
    saveCotisationCode(select);
  });

  // Sauvegarde tarification a la perte de focus.
  document.addEventListener('blur', function (event) {
    if (!(event.target instanceof Element)) {
      return;
    }

    const select = event.target.closest('.cotisation-input:not(.d-none)');

    if (!select) {
      return;
    }

    if (select.dataset.ignoreBlurOnce === '1') {
      select.dataset.ignoreBlurOnce = '0';
      return;
    }

    saveCotisationCode(select);
  }, true);

  // Sauvegarde tarification quand le choix change.
  document.addEventListener('change', function (event) {
    const select = event.target.closest('.cotisation-input:not(.d-none)');

    if (!select) {
      return;
    }

    saveCotisationCode(select);
  });

  /**
   * Valide et sauvegarde la date CACI via AJAX.
   * @param {HTMLInputElement} input
   */
  const saveDateCaci = function (input) {
    if (input.dataset.isSaving === '1') {
      return;
    }

    const editableCell = input.closest('.js-editable-date-caci');
    const display = editableCell.querySelector('.date-display');
    const newDate = input.value.trim();
    const currentDate = editableCell.dataset.currentDate || '';
    const Id_profil = editableCell.dataset.itemId;



    // Validation du format DD/MM/YYYY si une valeur est saisie.
    if (newDate !== '') {
      const dateRegex = /^(\d{2})\/(\d{2})\/(\d{4})$/;
      const match = newDate.match(dateRegex);

      if (!match) {
        console.warn('Format invalide. Attendu: DD/MM/YYYY');
        // Retour à l'affichage
        display.classList.remove('d-none');
        input.classList.add('d-none');
        return;
      }

      const [, day, month, year] = match;
      const dayNum = parseInt(day, 10);
      const monthNum = parseInt(month, 10);
      const yearNum = parseInt(year, 10);

      // Validation basique de la date
      if (monthNum < 1 || monthNum > 12 || dayNum < 1 || dayNum > 31) {
        console.warn('Date invalide.');
        // Retour à l'affichage
        display.classList.remove('d-none');
        input.classList.add('d-none');
        return;
      }
    }

    // Si la date n'a pas changé, retour à l'affichage
    if (newDate === currentDate) {
      display.classList.remove('d-none');
      input.classList.add('d-none');
      return;
    }

    // Appel AJAX via simpleCallAjax
    const ajaxData = {
      'task': 'secretariat.updateDateCaci',
      'date_caci': newDate,
      'id_profil': Id_profil
    };

    const csrfTokenName = Joomla.getOptions('csrf.token');
    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    input.dataset.isSaving = '1';

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        // Callback de succès : mettre à jour le badge (texte, couleur, infobulle) sans
        // toucher à l'icône fa-file-medical (display.textContent écraserait tout le badge).
        if (response.success) {
          const isCaciValidable = Boolean(response.data?.is_caci_validable);
          const dateValue = display.querySelector('.date-value');
          // Le fichier CACI n'est pas concerné par cette action (édition de la date seule) :
          // son état, posé au rendu initial, reste la source de vérité pour la priorité de la raison.
          const fichierManquant = editableCell.dataset.caciFichierManquant === '1';

          let reasonText;
          if (isCaciValidable) {
            reasonText = editableCell.dataset.hintValid || '';
          } else if (fichierManquant) {
            reasonText = editableCell.dataset.hintFichierManquant || '';
          } else if (newDate === '') {
            reasonText = editableCell.dataset.hintMissing || '';
          } else {
            reasonText = editableCell.dataset.hintInvalid || '';
          }

          if (dateValue) {
            dateValue.textContent = newDate !== '' ? newDate : '—';
          }

          display.classList.remove('bg-success', 'bg-danger');
          display.classList.add(isCaciValidable ? 'bg-success' : 'bg-danger');
          display.title = reasonText;

          editableCell.dataset.currentDate = newDate;
          setValidateCaciButtonState(editableCell.closest('tr'), isCaciValidable, reasonText);
        }
        // Retour à l'affichage
        input.dataset.isSaving = '0';
        display.classList.remove('d-none');
        input.classList.add('d-none');
      });
    } else {
      console.error('simpleCallAjax n\'est pas disponible');
      // Retour à l'affichage
      input.dataset.isSaving = '0';
      display.classList.remove('d-none');
      input.classList.add('d-none');
    }
  };

  

  /**
   * Sauvegarde la categorie via AJAX.
   * @param {HTMLSelectElement} select
   */
  const saveCategorie = function (select) {
    if (select.dataset.isSaving === '1') {
      return;
    }

    const editableCell = select.closest('.js-editable-categorie');
    const display = editableCell ? editableCell.querySelector('.categorie-display') : null;

    if (!editableCell || !display) {
      return;
    }

    const newCategorie = (select.value || '').trim().toUpperCase();
    const currentCategorie = (editableCell.dataset.currentCategorie || '').trim().toUpperCase();
    const idProfil = parseInt(editableCell.dataset.itemId || '0', 10);
    const idCampagne = parseInt(editableCell.dataset.itemCampagne || '0', 10);
    const allowed = [ 'ADULTE', 'JEUNE', 'ENFANT'];

    if (!allowed.includes(newCategorie)) {
      display.classList.remove('d-none');
      select.classList.add('d-none');
      return;
    }

    if (idProfil <= 0 || idCampagne <= 0) {
      Joomla.renderMessages({ error: ['Identifiants invalides pour la mise a jour de la categorie.'] });
      display.classList.remove('d-none');
      select.classList.add('d-none');
      return;
    }

    if (newCategorie === currentCategorie) {
      display.classList.remove('d-none');
      select.classList.add('d-none');
      return;
    }

    const ajaxData = {
      task: 'secretariat.updateCategorie',
      id_profil: idProfil,
      id_campagne: idCampagne,
      categorie: newCategorie
    };

    const csrfTokenName = Joomla.getOptions('csrf.token');
    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    select.dataset.isSaving = '1';

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        if (response.success) {
          display.textContent = newCategorie;
          editableCell.dataset.currentCategorie = newCategorie;

          // Chaine deja formatee par le serveur (symbole € compris) : les tarifs de licence ont
          // des centimes, un parseInt sur "48,50" afficherait 48 €.
          const selectedOption = select.options[select.selectedIndex];
          const licenceCost = selectedOption?.dataset.licenceCostAffiche;
          const licenceCell = editableCell.closest('tr')?.querySelector('.js-licence-cost');

          if (licenceCell && licenceCost) {
            licenceCell.textContent = licenceCost;
          }
        }

        select.dataset.isSaving = '0';
        display.classList.remove('d-none');
        select.classList.add('d-none');
      });
    } else {
      console.error('simpleCallAjax n\'est pas disponible');
      select.dataset.isSaving = '0';
      display.classList.remove('d-none');
      select.classList.add('d-none');
    }
  };

  /**
   * Sauvegarde la tarification (reduction) via AJAX. Le code de cotisation final (lettre +
   * localisation) et le montant sont recalcules cote serveur (age + code postal deja connus
   * du profil), pas de simple remplacement de texte cote client.
   * @param {HTMLSelectElement} select
   */
  const saveCotisationCode = function (select) {
    if (select.dataset.isSaving === '1') {
      return;
    }

    const editableCell = select.closest('.js-editable-cotisation');
    const display = editableCell ? editableCell.querySelector('.cotisation-display') : null;

    if (!editableCell || !display) {
      return;
    }

    const newReduction = parseInt(select.value, 10);
    const currentReduction = parseInt(editableCell.dataset.currentReduction || '0', 10);
    const idProfil = parseInt(editableCell.dataset.itemId || '0', 10);
    const idCampagne = parseInt(editableCell.dataset.itemCampagne || '0', 10);
    const allowed = [0, 1, 2, 4];

    if (!allowed.includes(newReduction)) {
      display.classList.remove('d-none');
      select.classList.add('d-none');
      return;
    }

    if (idProfil <= 0 || idCampagne <= 0) {
      Joomla.renderMessages({ error: ['Identifiants invalides pour la mise a jour de la tarification.'] });
      display.classList.remove('d-none');
      select.classList.add('d-none');
      return;
    }

    if (newReduction === currentReduction) {
      display.classList.remove('d-none');
      select.classList.add('d-none');
      return;
    }

    const ajaxData = {
      task: 'secretariat.updateCotisationCode',
      id_profil: idProfil,
      id_campagne: idCampagne,
      reduction: newReduction
    };

    const csrfTokenName = Joomla.getOptions('csrf.token');
    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    select.dataset.isSaving = '1';

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        if (response.success) {
          display.textContent = response.data?.cotisation_label || display.textContent;
          editableCell.dataset.currentReduction = String(newReduction);

          const montantCell = editableCell.closest('tr')?.querySelector('.js-cotisation-montant');
          if (montantCell && response.data?.cotisation_montant_affiche) {
            montantCell.textContent = response.data.cotisation_montant_affiche;
          }
        }

        select.dataset.isSaving = '0';
        display.classList.remove('d-none');
        select.classList.add('d-none');
      });
    } else {
      console.error('simpleCallAjax n\'est pas disponible');
      select.dataset.isSaving = '0';
      display.classList.remove('d-none');
      select.classList.add('d-none');
    }
  };



  // Bouton HelloAsso (.js-show-payement) : géré par le handler délégué global de
  // media/com_gdadhesions/js/form_modal.js (chargé sur toutes les vues) - l'ancienne fonction
  // showPayement() ici faisait strictement doublon (double appel ajax à chaque clic).

  /**
   * Bouton "Rafraîchir" de l'etape 5 : force le contournement du cache HelloAsso de 30 minutes.
   */
  const btnRefreshOrphelins = document.getElementById('btnRefreshOrphelins');

  if (btnRefreshOrphelins) {
    btnRefreshOrphelins.addEventListener('click', function () {
      if (btnRefreshOrphelins.dataset.isSaving === '1') {
        return;
      }

      btnRefreshOrphelins.dataset.isSaving = '1';
      loadStepOrphelins(true);

      window.setTimeout(function () {
        btnRefreshOrphelins.dataset.isSaving = '0';
      }, 1000);
    });
  }

  /**
   * Filtre Tous/Non associés/Associés de l'etape 5 : purement cote client, cf.
   * renderStepOrphelins() (pas de nouvel appel ajax a chaque changement).
   */
  const orphelinsFilter = document.getElementById('orphelinsFilter');

  if (orphelinsFilter) {
    currentOrphelinsFilter = orphelinsFilter.value;

    orphelinsFilter.addEventListener('change', function () {
      currentOrphelinsFilter = orphelinsFilter.value;
      renderStepOrphelins();
    });
  }

  /**
   * Active/desactive le bouton "Associer" (etape 5) selon la selection du candidat.
   */
  document.addEventListener('change', function (event) {
    const select = event.target.closest('.js-orphelin-candidat');

    if (!select) {
      return;
    }

    const button = select.closest('.input-group')?.querySelector('.js-orphelin-associer');

    if (button) {
      button.disabled = select.value === '';
    }
  });

  /**
   * Association manuelle d'un paiement HelloAsso orphelin (etape 5).
   */
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-orphelin-associer');

    if (!button || button.dataset.isSaving === '1') {
      return;
    }

    const cell = button.closest('.js-orphelin-cell');
    const select = cell?.querySelector('.js-orphelin-candidat');
    const idProfil = parseInt(select?.value || '0', 10);
    const idCampagne = parseInt(cell?.dataset.itemCampagne || '0', 10);
    const idOrder = cell?.dataset.itemOrder || '';

    if (idProfil <= 0 || idCampagne <= 0 || idOrder === '') {
      Joomla.renderMessages({ error: ['Selection invalide pour associer ce paiement.'] });
      return;
    }

    const ajaxData = {
      task: 'secretariat.associerPaiementOrphelin',
      id_profil: idProfil,
      id_campagne: idCampagne,
      id_order: idOrder
    };

    const csrfTokenName = Joomla.getOptions('csrf.token');

    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    button.dataset.isSaving = '1';

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        button.dataset.isSaving = '0';

        if (!response.success) {
          return;
        }

        // Recharge toute la step : la ligne associee passe en lecture seule et le candidat
        // choisi disparait de la liste deroulante des autres lignes orphelines.
        loadStepOrphelins();
      });
    } else {
      button.dataset.isSaving = '0';
      console.error('simpleCallAjax n\'est pas disponible');
    }
  });

  /**
   * Correction (ou dissociation, option "Aucun") d'une association deja resolue (etape 5) : demande
   * confirmation avant d'ecraser l'ancienne association, puis appelle
   * secretariat.associerPaiementOrphelin (avec id_ancien_profil) ou secretariat.dissocierPaiementOrphelin.
   * Appelee a la fois au changement de selection et a la perte de focus (voir listeners plus bas) :
   * un double-clic qui n'aboutit a aucun changement doit pouvoir sortir du mode edition sans laisser
   * le select ouvert indefiniment.
   *
   * @param {HTMLSelectElement} select
   * @returns {void}
   */
  const handleAdherentInputCommit = function (select) {
    const editableCell = select.closest('.js-editable-adherent');
    const display = editableCell ? editableCell.querySelector('.adherent-display') : null;

    if (!editableCell || !display) {
      return;
    }

    const revenirEnLecture = function () {
      display.classList.remove('d-none');
      select.classList.add('d-none');
    };

    const idAncienProfil = parseInt(editableCell.dataset.currentProfil || '0', 10);
    const idNouveauProfil = parseInt(select.value || '0', 10);
    const idCampagne = parseInt(editableCell.dataset.itemCampagne || '0', 10);
    const idOrder = editableCell.dataset.itemOrder || '';

    // Aucun changement (valeur identique, ou select rouvert puis referme sans selection) : simple
    // retour en lecture, sans appel serveur.
    if (idNouveauProfil === idAncienProfil) {
      revenirEnLecture();
      return;
    }

    if (idCampagne <= 0 || idOrder === '') {
      Joomla.renderMessages({ error: ['Selection invalide pour corriger cette association.'] });
      select.value = idAncienProfil;
      revenirEnLecture();
      return;
    }

    // "Aucun" (valeur vide) : dissociation, pas de nouvel adherent a attribuer.
    const estDissociation = idNouveauProfil <= 0;
    const nouveauLabel = estDissociation ? '' : (select.options[select.selectedIndex]?.textContent?.trim() || '');

    // Retour immediat en lecture (valeur precedente) : la confirmation porte sur la mutation en
    // base, pas sur l'etat visuel du select. GdaDialog.confirm() n'expose pas de hook "annulation" ;
    // en cas de confirmation, executerMutation() rechargera de toute facon toute la step au succes.
    select.value = idAncienProfil;
    revenirEnLecture();

    const executerMutation = function () {
      const ajaxData = estDissociation
        ? {
          task: 'secretariat.dissocierPaiementOrphelin',
          id_profil: idAncienProfil,
          id_campagne: idCampagne,
          id_order: idOrder
        }
        : {
          task: 'secretariat.associerPaiementOrphelin',
          id_profil: idNouveauProfil,
          id_campagne: idCampagne,
          id_order: idOrder,
          id_ancien_profil: idAncienProfil
        };

      const csrfTokenName = Joomla.getOptions('csrf.token');

      if (csrfTokenName) {
        ajaxData[csrfTokenName] = 1;
      }

      select.dataset.isSaving = '1';

      if (typeof simpleCallAjax === 'function') {
        simpleCallAjax(ajaxData, function (response) {
          select.dataset.isSaving = '0';

          if (!response.success) {
            select.value = idAncienProfil;
            revenirEnLecture();
            return;
          }

          // Recharge toute la step : l'ancien profil redevient candidat libre sur les autres
          // lignes orphelines, le nouveau (s'il y en a un) devient associe ici.
          loadStepOrphelins();
        });
      } else {
        select.dataset.isSaving = '0';
        select.value = idAncienProfil;
        revenirEnLecture();
        console.error('simpleCallAjax n\'est pas disponible');
      }
    };

    const confirmTitre = estDissociation
      ? Joomla.Text._('COM_GDA_SECRETARIAT_ORPHELINS_DISSOCIATE_CONFIRM_TITRE')
      : Joomla.Text._('COM_GDA_SECRETARIAT_ORPHELINS_CHANGE_CONFIRM_TITRE');
    const confirmMessage = estDissociation
      ? Joomla.Text._('COM_GDA_SECRETARIAT_ORPHELINS_DISSOCIATE_CONFIRM_MESSAGE')
      : Joomla.Text._('COM_GDA_SECRETARIAT_ORPHELINS_CHANGE_CONFIRM_MESSAGE').replace('%s', nouveauLabel);

    if (window.GdaDialog && typeof window.GdaDialog.confirm === 'function') {
      window.GdaDialog.confirm(confirmTitre, confirmMessage, executerMutation);
    } else {
      executerMutation();
    }
  };

  document.addEventListener('change', function (event) {
    const select = event.target.closest('.adherent-input:not(.d-none)');

    if (!select) {
      return;
    }

    handleAdherentInputCommit(select);
  });

  // Sortie du mode edition a la perte de focus (clic ailleurs, y compris sur une autre cellule
  // editable) meme sans changement de selection : sans ce listener, un double-clic suivi d'un clic
  // ailleurs sans modifier le choix laissait le select ouvert indefiniment (aucun evenement 'change'
  // ne se declenche si la valeur n'a pas change). Le listener 'change' ci-dessus a deja masque le
  // select de facon synchrone avant tout appel asynchrone : le filtre ':not(.d-none)' evite donc un
  // double traitement quand une vraie modification a eu lieu.
  document.addEventListener('blur', function (event) {
    if (!(event.target instanceof Element)) {
      return;
    }

    const select = event.target.closest('.adherent-input:not(.d-none)');

    if (!select) {
      return;
    }

    handleAdherentInputCommit(select);
  }, true);

  /**
   * Detail simplifie d'une commande HelloAsso encore orpheline (etape 5), au clic sur son n°.
   * Meme modale que .js-show-payement (form_modal.js) mais tache/donnees differentes : aucun
   * id_profil connu ici, d'ou un handler dedie plutot que de brancher le handler global partage.
   */
  document.addEventListener('click', function (event) {
    const trigger = event.target.closest('.js-show-commande-detail');

    if (!trigger) {
      return;
    }

    event.preventDefault();

    const modalEl = document.getElementById('payementModal');
    const modalContent = document.getElementById('payementModalcontent');

    if (!modalEl || !modalContent || !window.bootstrap) {
      return;
    }

    modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    const ajaxData = {
      task: 'secretariat.getDetailCommandeOrpheline',
      id_order: trigger.dataset.itemOrder || ''
    };

    const csrfTokenName = Joomla.getOptions('csrf.token');

    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    if (typeof simpleCallAjax === 'function') {
      simpleCallAjax(ajaxData, function (response) {
        if (response.success) {
          modalContent.innerHTML = decodeURIComponent(escape(atob(response.data)));
        } else {
          modalContent.innerHTML = '<div class="alert alert-danger">' + (response.message || 'Erreur inconnue') + '</div>';
        }
      }, false);
    } else {
      modalContent.innerHTML = '<div class="alert alert-danger">simpleCallAjax non disponible.</div>';
    }
  });

});
