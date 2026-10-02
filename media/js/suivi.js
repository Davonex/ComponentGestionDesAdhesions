/**
 * Vue Suivi : tableau élèves x séances par groupe (un onglet par groupe), ajout d'une séance,
 * formulaire d'évaluation des compétences dans #suiviEvaluationModal et bilan d'un élève
 * dans #suiviBilanModal.
 *
 * Dépendances : simpleCallAjax (form_modal.js), Bootstrap (Tab, Modal, Tooltip).
 */
(function () {
  'use strict';

  const decoder = function (data) {
    return decodeURIComponent(escape(atob(data)));
  };

  const spinner = '<div class="text-center py-4"><div class="spinner-border text-success" role="status"></div></div>';

  /**
   * Charge (ou recharge) le tableau d'un onglet. Les séances ajoutées à l'écran mais pas encore
   * évaluées sont transmises au serveur pour garder leur colonne.
   */
  const chargerTableau = function (pane) {
    const container = pane.querySelector('.js-suivi-tableau');

    if (!container) {
      return;
    }

    pane.dataset.charge = '1';

    simpleCallAjax({
      task: 'suivi.tableau',
      id_groupe: pane.dataset.idGroupe,
      seances: pane.dataset.seancesAjoutees || '',
    }, function (response) {
      container.innerHTML = decoder(response.data);

      // Les séances sont triées de la plus ancienne à la plus récente : on montre la plus récente.
      const scroll = container.querySelector('.gda-suivi-scroll');

      if (scroll) {
        scroll.scrollLeft = scroll.scrollWidth;
      }
    }, false, function (response) {
      container.innerHTML = '<div class="alert alert-danger">' + ((response && response.message) || '') + '</div>';
    });
  };

  /**
   * Charge l'onglet « Compétences » (référentiel, réservé aux Responsables de Groupe).
   */
  const chargerCompetences = function (pane) {
    pane.dataset.charge = '1';

    simpleCallAjax({ task: 'suivi.competences' }, function (response) {
      pane.innerHTML = decoder(response.data);
      filtrerCompetences(pane);
    }, false, function (response) {
      pane.innerHTML = '<div class="alert alert-danger">' + ((response && response.message) || '') + '</div>';
    });
  };

  const chargerOnglet = function (pane) {
    if (!pane || pane.dataset.charge === '1') {
      return;
    }

    if (pane.classList.contains('js-suivi-competences-pane')) {
      chargerCompetences(pane);
    } else if (pane.classList.contains('js-suivi-pane')) {
      chargerTableau(pane);
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    chargerOnglet(document.querySelector('#suiviTabContent > .tab-pane.active'));
  });

  document.addEventListener('shown.bs.tab', function (event) {
    if (!event.target || !event.target.closest('#suiviTabNav')) {
      return;
    }

    chargerOnglet(document.querySelector(event.target.dataset.bsTarget));
  });

  // Bouton « Ajouter une séance » : affiche le choix de la date.
  document.addEventListener('click', function (event) {
    const trigger = event.target.closest('.js-suivi-ajouter-seance');

    if (!trigger) {
      return;
    }

    const pane = trigger.closest('.js-suivi-pane');
    const saisie = pane ? pane.querySelector('.js-suivi-nouvelle-seance') : null;

    if (saisie) {
      saisie.classList.toggle('d-none');
      saisie.querySelector('.js-suivi-date-seance')?.focus();
    }
  });

  // Validation de la date : ajoute une colonne vide, enregistrée à la première évaluation.
  document.addEventListener('click', function (event) {
    const trigger = event.target.closest('.js-suivi-valider-seance');

    if (!trigger) {
      return;
    }

    const pane = trigger.closest('.js-suivi-pane');
    const saisie = pane.querySelector('.js-suivi-nouvelle-seance');
    const input = saisie.querySelector('.js-suivi-date-seance');
    const date = input.value;

    if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || (input.max && date > input.max)) {
      Joomla.renderMessages({ error: [Joomla.Text._('COM_GDA_SUIVI_ERR_DATE_SEANCE')] });
      return;
    }

    saisie.classList.add('d-none');

    if (pane.querySelector('th[data-date-seance="' + date + '"]')) {
      return;
    }

    const seances = (pane.dataset.seancesAjoutees || '').split(',').filter(Boolean);
    seances.push(date);
    pane.dataset.seancesAjoutees = seances.join(',');

    chargerTableau(pane);
  });

  // Case du tableau : ouvre le formulaire d'évaluation de l'élève pour la séance.
  document.addEventListener('click', function (event) {
    const trigger = event.target.closest('.js-suivi-evaluer');

    if (!trigger) {
      return;
    }

    const modalEl = document.getElementById('suiviEvaluationModal');
    const modalContent = document.getElementById('suiviEvaluationModalContent');

    if (!modalEl || !modalContent) {
      return;
    }

    modalContent.innerHTML = spinner;
    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    simpleCallAjax({
      task: 'suivi.formulaire',
      id_profil: trigger.dataset.idProfil,
      id_groupe: trigger.dataset.idGroupe,
      date_seance: trigger.dataset.dateSeance,
    }, function (response) {
      modalContent.innerHTML = decoder(response.data);
    }, false, function (response) {
      modalContent.innerHTML = '<div class="modal-header border-0"><button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button></div>'
        + '<div class="modal-body"><div class="alert alert-danger mb-0">' + ((response && response.message) || '') + '</div></div>';
    });
  });

  /**
   * Infobulles des appréciations du bilan : survol sur ordinateur, toucher sur téléphone
   * (pas de survol au doigt). Une seule infobulle ouverte à la fois.
   */
  const initialiserInfobulles = function (container) {
    const trigger = window.matchMedia('(hover: hover)').matches ? 'hover focus' : 'click';

    container.querySelectorAll('.js-suivi-infobulle').forEach(function (element) {
      bootstrap.Tooltip.getOrCreateInstance(element, { trigger: trigger });

      element.addEventListener('show.bs.tooltip', function () {
        container.querySelectorAll('.js-suivi-infobulle').forEach(function (autre) {
          if (autre !== element) {
            bootstrap.Tooltip.getInstance(autre)?.hide();
          }
        });
      });
    });
  };

  const detruireInfobulles = function (container) {
    container.querySelectorAll('.js-suivi-infobulle').forEach(function (element) {
      bootstrap.Tooltip.getInstance(element)?.dispose();
    });
  };

  // Nom de l'élève : ouvre le bilan de son évaluation (compétences x séances).
  document.addEventListener('click', function (event) {
    const trigger = event.target.closest('.js-suivi-bilan');

    if (!trigger) {
      return;
    }

    event.preventDefault();

    const modalEl = document.getElementById('suiviBilanModal');
    const modalContent = document.getElementById('suiviBilanModalContent');

    if (!modalEl || !modalContent) {
      return;
    }

    detruireInfobulles(modalContent);
    modalContent.innerHTML = spinner;
    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    simpleCallAjax({
      task: 'suivi.bilan',
      id_profil: trigger.dataset.idProfil,
      id_groupe: trigger.dataset.idGroupe,
    }, function (response) {
      modalContent.innerHTML = decoder(response.data);
      initialiserInfobulles(modalContent);

      const scroll = modalContent.querySelector('.gda-suivi-scroll');

      if (scroll) {
        scroll.scrollLeft = scroll.scrollWidth;
      }
    }, false, function (response) {
      modalContent.innerHTML = '<div class="modal-header border-0"><button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button></div>'
        + '<div class="modal-body"><div class="alert alert-danger mb-0">' + ((response && response.message) || '') + '</div></div>';
    });
  });

  // Une infobulle restée ouverte ne doit pas survivre à la fermeture de la popup.
  document.addEventListener('hide.bs.modal', function (event) {
    if (event.target && event.target.id === 'suiviBilanModal') {
      detruireInfobulles(event.target);
    }
  });

  // Enregistrement : remplace la case du tableau puis ferme la popup.
  document.addEventListener('submit', function (event) {
    const form = event.target.closest('.js-suivi-formulaire');

    if (!form) {
      return;
    }

    event.preventDefault();

    const bouton = form.querySelector('.js-suivi-sauver');

    if (bouton.disabled) {
      return;
    }

    bouton.disabled = true;

    const formData = new FormData(form);
    formData.append('task', 'suivi.sauver');

    simpleCallAjax(formData, function (response) {
      const selecteur = '.js-suivi-evaluer'
        + '[data-id-profil="' + form.elements.id_profil.value + '"]'
        + '[data-id-groupe="' + form.elements.id_groupe.value + '"]'
        + '[data-date-seance="' + form.elements.date_seance.value + '"]';
      const cellule = document.querySelector(selecteur);

      if (cellule) {
        cellule.outerHTML = decoder(response.data);
      }

      bootstrap.Modal.getOrCreateInstance(document.getElementById('suiviEvaluationModal')).hide();
    }, true, function (response) {
      bouton.disabled = false;

      // Le message général est rendu en haut de page, masqué par la popup (plein écran sur
      // téléphone) : on le répète dans la popup.
      const corps = form.querySelector('.modal-body');
      let alerte = form.querySelector('.js-suivi-erreur');

      if (!alerte && corps) {
        alerte = document.createElement('div');
        alerte.className = 'alert alert-danger js-suivi-erreur';
        corps.prepend(alerte);
      }

      if (alerte) {
        alerte.textContent = (response && response.message) || '';
        alerte.scrollIntoView({ block: 'nearest' });
      }
    });
  });

  /* ------------------------------------------------------------------------------------------
   * Onglet « Compétences » : filtre par niveau, ajout d'une ligne, édition au double-clic avec
   * sauvegarde automatique. Le serveur renvoie la ligne re-rendue après chaque modification.
   * ---------------------------------------------------------------------------------------- */

  /** Affiche les lignes du niveau choisi (toutes si « Tous ») ; l'ajout exige un niveau. */
  function filtrerCompetences(pane) {
    const filtre = pane ? pane.querySelector('.js-suivi-competences-filtre') : null;

    if (!filtre) {
      return;
    }

    let nbVisibles = 0;

    pane.querySelectorAll('.js-suivi-competence-ligne').forEach(function (ligne) {
      const visible = filtre.value === '' || ligne.dataset.idGroupe === filtre.value;
      ligne.classList.toggle('d-none', !visible);
      nbVisibles += visible ? 1 : 0;
    });

    pane.querySelector('.js-suivi-competences-vide')?.classList.toggle('d-none', nbVisibles > 0);

    const ajouter = pane.querySelector('.js-suivi-ajouter-competence');

    if (ajouter) {
      ajouter.disabled = filtre.value === '';
    }
  }

  document.addEventListener('change', function (event) {
    const filtre = event.target.closest('.js-suivi-competences-filtre');

    if (filtre) {
      filtrerCompetences(filtre.closest('.js-suivi-competences-pane'));
    }
  });

  /** Construit une ligne <tr> à partir du HTML renvoyé par le serveur. */
  const creerLigne = function (html) {
    const modele = document.createElement('tbody');
    modele.innerHTML = html;

    return modele.firstElementChild;
  };

  const fermerEdition = function (cellule) {
    cellule.querySelector('.js-edition')?.classList.add('d-none');
    cellule.querySelector('.js-affichage')?.classList.remove('d-none');
    delete cellule.dataset.enEdition;
  };

  const ouvrirEdition = function (cellule) {
    const champ = cellule ? cellule.querySelector('.js-edition') : null;

    if (!champ || cellule.dataset.enEdition === '1') {
      return;
    }

    cellule.dataset.enEdition = '1';
    cellule.querySelector('.js-affichage')?.classList.add('d-none');
    champ.classList.remove('d-none');
    champ.focus();

    if (champ.tagName === 'INPUT') {
      champ.select();
    }
  };

  const enregistrerCompetence = function (ligne, champ, valeur, cbEchec) {
    if (ligne.dataset.enregistrement === '1') {
      return;
    }

    ligne.dataset.enregistrement = '1';

    simpleCallAjax({
      task: 'suivi.modifierCompetence',
      id_competence: ligne.dataset.idCompetence,
      champ: champ,
      valeur: valeur,
    }, function (response) {
      const pane = ligne.closest('.js-suivi-competences-pane');
      ligne.replaceWith(creerLigne(decoder(response.data)));
      filtrerCompetences(pane);
    }, true, function () {
      delete ligne.dataset.enregistrement;
      cbEchec();
    });
  };

  /** Sortie du champ : enregistre si la valeur a changé, sinon referme simplement. */
  const validerEdition = function (cellule) {
    const champ = cellule.querySelector('.js-edition');

    if (!champ || cellule.dataset.enEdition !== '1') {
      return;
    }

    const initiale = champ.dataset.valeurInitiale || '';

    if (champ.value.trim() === initiale.trim()) {
      champ.value = initiale;
      fermerEdition(cellule);
      return;
    }

    enregistrerCompetence(cellule.closest('tr'), cellule.dataset.champ, champ.value, function () {
      champ.value = initiale;
      fermerEdition(cellule);
    });
  };

  document.addEventListener('dblclick', function (event) {
    const cellule = event.target.closest('.js-editable-competence');

    if (cellule && !event.target.closest('.js-edition')) {
      ouvrirEdition(cellule);
    }
  });

  // blur ne remonte pas : écoute en phase de capture.
  document.addEventListener('blur', function (event) {
    const champ = event.target.closest ? event.target.closest('.js-editable-competence .js-edition') : null;

    if (champ) {
      validerEdition(champ.closest('.js-editable-competence'));
    }
  }, true);

  // Niveau : le choix dans la liste vaut validation.
  document.addEventListener('change', function (event) {
    if (event.target.matches('.js-editable-competence select.js-edition')) {
      event.target.blur();
    }
  });

  // Entrée valide (Ctrl+Entrée dans les techniques, où Entrée passe à la ligne), Échap annule.
  document.addEventListener('keydown', function (event) {
    const champ = event.target.closest ? event.target.closest('.js-editable-competence .js-edition') : null;

    if (!champ) {
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      champ.value = champ.dataset.valeurInitiale || '';
      fermerEdition(champ.closest('.js-editable-competence'));
    } else if (event.key === 'Enter' && (champ.tagName !== 'TEXTAREA' || event.ctrlKey)) {
      event.preventDefault();
      champ.blur();
    }
  });

  document.addEventListener('change', function (event) {
    const interrupteur = event.target.closest('.js-suivi-competence-actif');

    if (!interrupteur) {
      return;
    }

    enregistrerCompetence(interrupteur.closest('tr'), 'actif', interrupteur.checked ? '1' : '0', function () {
      interrupteur.checked = !interrupteur.checked;
    });
  });

  // « Ajouter une compétence » : ligne créée côté serveur dans le niveau filtré, saisie ouverte.
  document.addEventListener('click', function (event) {
    const bouton = event.target.closest('.js-suivi-ajouter-competence');

    if (!bouton) {
      return;
    }

    const pane = bouton.closest('.js-suivi-competences-pane');
    const filtre = pane.querySelector('.js-suivi-competences-filtre');

    if (!filtre.value) {
      return;
    }

    bouton.disabled = true;

    simpleCallAjax({ task: 'suivi.ajouterCompetence', id_groupe: filtre.value }, function (response) {
      const lignes = pane.querySelector('.js-suivi-competences-lignes');
      const nouvelle = creerLigne(decoder(response.data));

      lignes.insertBefore(nouvelle, lignes.querySelector('.js-suivi-competences-vide'));
      filtrerCompetences(pane);
      ouvrirEdition(nouvelle.querySelector('[data-champ="competence"]'));
    }, true, function () {
      filtrerCompetences(pane);
    });
  });
})();
