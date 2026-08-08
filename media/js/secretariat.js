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
   * Applique l'état (actif/désactivé) du bouton de validation du CACI d'une ligne.
   *
   * La validité (CACI daté d'au moins 3 mois) est une règle métier calculée côté serveur
   * (SouscriptionService::isCaciValidable), pour éviter toute divergence entre le rendu
   * initial du tableau et une mise à jour en AJAX.
   *
   * @param {HTMLElement} row La ligne du tableau contenant le bouton.
   * @param {boolean} canValidate Etat de validité renvoyé par le serveur.
   * @returns {void}
   */
  const setValidateCaciButtonState = function (row, canValidate) {
    const button = row ? row.querySelector('.js-validate-caci') : null;

    if (!button) {
      return;
    }

    button.disabled = !canValidate;
    button.setAttribute('aria-disabled', canValidate ? 'false' : 'true');
  };


  /**
   *Initialise DataTable pour le tableau de l'etape 1 (contenu charge en AJAX dans #step-0).
   * @returns {void}
   */
  const initStepZeroView = function () {
    const step0Container = document.getElementById('step-0');
    const table1 = document.querySelector('#step-0 table');
    const datatableApi = globalThis.simpleDatatables;

    if (table1 && datatableApi && datatableApi.DataTable) {
      new datatableApi.DataTable(table1, frenchDataTableOptions);
    } else if (table1) {
      console.error('simple-datatables n\'est pas chargee');
    }

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
    const datatableApi = globalThis.simpleDatatables;

    if (table2 && datatableApi && datatableApi.DataTable) {
      new datatableApi.DataTable(table2, frenchDataTableOptions);
    } else if (table2) {
      console.error('simple-datatables n\'est pas chargee');
    }

    if (step1Container) {
      initTooltips(step1Container);

      // Bouton HelloAsso : affiche le détail du paiement dans une modal
      step1Container.querySelectorAll('.js-show-payement').forEach(function (btn) {
        btn.addEventListener('click', function () {
          showPayement(
            parseInt(btn.dataset.itemId || '0', 10),
            parseInt(btn.dataset.itemCampagne || '0', 10),
            btn.dataset.itemOrder,
            btn.dataset.itemUsername,
            btn.dataset.itemCotisation,
          );
        });
      });
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
   * Initialise DataTable pour le tableau de l'etape 3 (contenu charge en AJAX dans #step-3).
   * @returns {void}
   */
  const initStepThreeView = function () {
    const step3Container = document.getElementById('step-3');
    const table3 = document.querySelector('#step-3 table');
    const datatableApi = globalThis.simpleDatatables;

    if (table3 && datatableApi && datatableApi.DataTable) {
      new datatableApi.DataTable(table3, frenchDataTableOptions);
    } else if (table3) {
      console.error('simple-datatables n\'est pas chargee');
    }

    if (step3Container) {
      initTooltips(step3Container);
    }
  };

  /**
   * Charge et remplace le contenu HTML de la step 3.
   * @returns {void}
   */
  const loadStepThree = function () {
    const step3Container = document.getElementById('step-3');

    if (!step3Container) {
      return;
    }

    const ajaxData = { task: 'secretariat.stepThree' };
    const csrfTokenName = Joomla.getOptions('csrf.token');
    const hideStep3Loader = function () {
      if (window.GdaSpinner) {
        window.GdaSpinner.hide(step3Container);
      }
    };

    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    if (typeof simpleCallAjax === 'function') {
      if (window.GdaSpinner) {
        window.GdaSpinner.show(step3Container, { text: 'Chargement des licences a enregistrer...' });
      }

      const step3FallbackTimer = window.setTimeout(hideStep3Loader, 15000);

      simpleCallAjax(ajaxData, function (response) {
        window.clearTimeout(step3FallbackTimer);
        hideStep3Loader();

        if (response.success) {
          step3Container.innerHTML = decodeURIComponent(escape(atob(response.data)));
          initStepThreeView();
        }
      }, false);
    }
  };

  /**
   * Initialise DataTable pour le tableau de l'etape 4 (contenu charge en AJAX dans #step-4).
   * @returns {void}
   */
  const initStepFourView = function () {
    const step4Container = document.getElementById('step-4');
    const table4 = document.querySelector('#step-4 table');
    const datatableApi = globalThis.simpleDatatables;

    if (table4 && datatableApi && datatableApi.DataTable) {
      new datatableApi.DataTable(table4, frenchDataTableOptions);
    } else if (table4) {
      console.error('simple-datatables n\'est pas chargee');
    }

    if (step4Container) {
      initTooltips(step4Container);

      // Bouton HelloAsso : affiche le détail du paiement dans une modal
      step4Container.querySelectorAll('.js-show-payement').forEach(function (btn) {
        btn.addEventListener('click', function () {
          showPayement(
            parseInt(btn.dataset.itemId || '0', 10),
            parseInt(btn.dataset.itemCampagne || '0', 10),
            btn.dataset.itemOrder,
            btn.dataset.itemUsername,
            btn.dataset.itemCotisation,
          );
        });
      });
    }
  };

  /**
   * Charge et remplace le contenu HTML de la step 4.
   * @returns {void}
   */
  const loadStepFour = function () {
    const step4Container = document.getElementById('step-4');

    if (!step4Container) {
      return;
    }

    const ajaxData = { task: 'secretariat.inscriptionsFinalises' };
    const csrfTokenName = Joomla.getOptions('csrf.token');
    const hideStep4Loader = function () {
      if (window.GdaSpinner) {
        window.GdaSpinner.hide(step4Container);
      }
    };

    if (csrfTokenName) {
      ajaxData[csrfTokenName] = 1;
    }

    if (typeof simpleCallAjax === 'function') {
      if (window.GdaSpinner) {
        window.GdaSpinner.show(step4Container, { text: 'Chargement des adhesions finalisees...' });
      }

      const step4FallbackTimer = window.setTimeout(hideStep4Loader, 15000);

      simpleCallAjax(ajaxData, function (response) {
        window.clearTimeout(step4FallbackTimer);
        hideStep4Loader();

        if (response.success) {
          step4Container.innerHTML = decodeURIComponent(escape(atob(response.data)));
          initStepFourView();
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
      // Mise à jour barre de navigation
      navButtons.forEach(function (btn) {
        btn.classList.remove('active');
      });

      if (navButtons[e.to]) {
        navButtons[e.to].classList.add('active');
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

    // Passer en mode édition
    display.classList.add('d-none');
    input.classList.remove('d-none');
    input.value = display.textContent.trim();
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
        // Callback de succès : mettre à jour l'affichage
        if (response.success) {
          display.textContent = newDate;
          editableCell.dataset.currentDate = newDate;
          setValidateCaciButtonState(editableCell.closest('tr'), Boolean(response.data?.is_caci_validable));
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

          const selectedOption = select.options[select.selectedIndex];
          const licenceCost = parseInt(selectedOption?.dataset.licenceCost || '0', 10);
          const licenceCell = editableCell.closest('tr')?.querySelector('.js-licence-cost');

          if (licenceCell) {
            licenceCell.textContent = `${Number.isNaN(licenceCost) ? 0 : licenceCost} €`;
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
   * Charge le détail du paiement HelloAsso et l'affiche dans la modal #payementModal.
   *
   * @param {number} idProfil
   * @param {number} idCampagne
   * @param {number} idOrder
   * @param {string} username
   * @param {number} cotisation
   */
  const showPayement = function (idProfil, idCampagne, idOrder ,username,cotisation) {
    const modalEl   = document.getElementById('payementModal');
    const modalContent = document.getElementById('payementModalcontent');

    if (!modalEl || !modalContent) {
      return;
    }

    // Affiche le spinner de chargement
    modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success" role="status"><span class="visually-hidden">Chargement...</span></div></div>';

    // Ouvre la modal Bootstrap (API compatible Bootstrap 5)
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    const ajaxData = {
      task: 'secretariat.getPayement',
      id_order: idOrder,
      id_profil: idProfil,
      id_campagne: idCampagne,
      username: username,
      cotisation: cotisation
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
      },false);
    } else {
      modalContent.innerHTML = '<div class="alert alert-danger">simpleCallAjax non disponible.</div>';
    }
  };

});
