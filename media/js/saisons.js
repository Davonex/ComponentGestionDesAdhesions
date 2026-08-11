/**
 * Callback ajax pour toggleActive/toggleCourante (onglet Historique) : remplace la <tr>
 * de la saison concernée par le HTML renvoyé par le serveur. Calque de campagneAdmCB
 * (media/com_gdadhesions/js/campagne.js).
 */
const saisonsListeCB = function (response) {
    const html = decodeURIComponent(escape(atob(response.data)));
    const doc = document.createElement('table');
    doc.innerHTML = html;

    const source = doc.querySelector('tr');
    if (!source) {
        return;
    }

    const cible = document.getElementById(source.id);
    if (cible !== null) {
        cible.replaceWith(source);
    } else {
        const tbody = document.querySelector('#table-historique-saisons tbody');
        if (tbody !== null) {
            tbody.appendChild(source);
        }
    }
};

/**
 * Callback ajax pour toggleCourante : contrairement à toggleActive (une seule ligne concernée),
 * déclarer une nouvelle saison courante ferme automatiquement l'ancienne — deux lignes peuvent
 * changer d'état. Le serveur renvoie donc la liste complète re-rendue ; on remplace tout le
 * <tbody> plutôt qu'une seule <tr>.
 */
const saisonsCouranteListeCB = function (response) {
    const html = decodeURIComponent(escape(atob(response.data)));
    const tbody = document.querySelector('#table-historique-saisons tbody');

    if (tbody) {
        tbody.innerHTML = html;
    }
};

/**
 * Callback ajax de la modal "Ajouter une saison" : ajoute la nouvelle ligne au tableau
 * historique (pas de rechargement complet de la page).
 */
const saisonsAjoutCB = function (response) {
    const html = decodeURIComponent(escape(atob(response.data)));
    const doc = document.createElement('table');
    doc.innerHTML = html;

    const source = doc.querySelector('tr');
    const tbody = document.querySelector('#table-historique-saisons tbody');

    if (source && tbody) {
        tbody.appendChild(source);
    }
};

document.addEventListener('DOMContentLoaded', function () {
    /**
     * Construit le jeu de données commun (task + jeton CSRF) pour un appel ajax direct
     * (hors formulaire), à l'image du pattern utilisé par utilisateurs.js.
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
     * Bascule immédiate (sans confirmation), qu'elle vienne du bouton de l'onglet 1
     * ("Ouvert/Fermé aux adhésions") ou d'un badge double-cliqué dans l'historique.
     */
    const toggleActive = function (idCampagne, currentActive, onSuccess) {
        const ajaxData = buildAjaxData('saisons.toggleActive');
        ajaxData.id_campagne = idCampagne;
        ajaxData.active = currentActive ? 0 : 1;
        simpleCallAjax(ajaxData, onSuccess || saisonsListeCB);
    };

    const toggleCourante = function (idCampagne, currentCourante) {
        const ajaxData = buildAjaxData('saisons.toggleCourante');
        ajaxData.id_campagne = idCampagne;
        ajaxData.courante = currentCourante ? 0 : 1;
        simpleCallAjax(ajaxData, saisonsCouranteListeCB);
    };

    // Bouton "Ouvert/Fermé aux adhésions" de l'onglet Saison courante : met à jour son propre
    // libellé/icône après succès (il ne fait pas partie du tableau historique remplacé par
    // saisonsListeCB), et met aussi à jour la ligne de l'historique si la même saison y est listée.
    document.addEventListener('click', function (event) {
        const btn = event.target.closest('.js-toggle-active-courante');
        if (!btn) {
            return;
        }

        const idCampagne = parseInt(btn.dataset.idCampagne, 10);
        const currentActive = btn.dataset.active === '1';

        toggleActive(idCampagne, currentActive, function (response) {
            const newActive = !currentActive;
            btn.dataset.active = newActive ? '1' : '0';
            btn.classList.toggle('btn-success', newActive);
            btn.classList.toggle('btn-dark', !newActive);

            const icon = btn.querySelector('.js-toggle-active-courante-icon');
            if (icon) {
                icon.classList.toggle('fa-door-open', newActive);
                icon.classList.toggle('fa-lock', !newActive);
            }

            const label = btn.querySelector('.js-toggle-active-courante-label');
            if (label) {
                label.textContent = newActive ? btn.dataset.labelClose : btn.dataset.labelOpen;
            }

            saisonsListeCB(response);
        });
    });

    // Badge "ouverture" de l'onglet Historique : double-clic (souris) ou Entrée/Espace
    // (clavier, pour rester accessible sans souris) — ce n'est pas un <button>, donc pas
    // d'activation clavier native.
    document.addEventListener('dblclick', function (event) {
        const target = event.target.closest('.js-toggle-active-saison');
        if (target) {
            toggleActive(parseInt(target.dataset.idCampagne, 10), target.dataset.active === '1');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        const target = event.target.closest('.js-toggle-active-saison');
        if (target) {
            event.preventDefault();
            toggleActive(parseInt(target.dataset.idCampagne, 10), target.dataset.active === '1');
        }
    });

    // Bouton "courante" de l'onglet Historique : ce changement ferme automatiquement une autre
    // saison (l'ancienne courante, ou la saison elle-même si on la retire) — on demande donc
    // confirmation avant d'appliquer, via la modal partagée #modalConfirmCourante.
    let pendingCouranteToggle = null;

    document.addEventListener('click', function (event) {
        const target = event.target.closest('.js-toggle-courante-saison');
        if (!target) {
            return;
        }

        const idCampagne = parseInt(target.dataset.idCampagne, 10);
        const currentCourante = target.dataset.courante === '1';
        const titre = target.dataset.titre || '';

        pendingCouranteToggle = { idCampagne: idCampagne, currentCourante: currentCourante };

        const messageEl = document.getElementById('modalConfirmCouranteMessage');
        if (messageEl) {
            const messageKey = currentCourante
                ? 'COM_GDA_SAISONS_COURANTE_CONFIRM_RETIRER'
                : 'COM_GDA_SAISONS_COURANTE_CONFIRM_DECLARER';
            messageEl.innerHTML = Joomla.Text._(messageKey).replace('%s', titre);
        }

        const modalEl = document.getElementById('modalConfirmCourante');
        if (modalEl) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    });

    const btnConfirmCourante = document.getElementById('btnConfirmToggleCourante');
    if (btnConfirmCourante) {
        btnConfirmCourante.addEventListener('click', function () {
            if (pendingCouranteToggle) {
                toggleCourante(pendingCouranteToggle.idCampagne, pendingCouranteToggle.currentCourante);
                pendingCouranteToggle = null;
            }
        });
    }

    // --- Onglet "Saison courante" : champs de définition + groupes du club, sauvegarde groupée ---

    const formCourante = document.getElementById('form_saison_courante');
    const btnSave = document.getElementById('btnSaveSaisonCourante');

    if (!formCourante || !btnSave) {
        return;
    }

    let baseline = new FormData(formCourante);

    // Sérialisation canonique (triée) d'un FormData : gère correctement les champs à valeurs
    // multiples partageant le même name (ex: groupes[0][published] : input hidden + checkbox).
    const serializeFormData = function (formData) {
        const pairs = [];
        formData.forEach(function (value, key) {
            pairs.push(key + '=' + value);
        });
        return pairs.sort().join('&');
    };

    const isDirty = function () {
        return serializeFormData(new FormData(formCourante)) !== serializeFormData(baseline);
    };

    const refreshSaveButtonVisibility = function () {
        btnSave.classList.toggle('d-none', !isDirty());
    };

    // Tous les champs sont directement éditables (pas de verrou/double-clic) : le bouton
    // Sauvegarder apparaît dès qu'un champ change, qu'il s'agisse de la définition de la
    // saison ou d'une ligne de groupe.
    formCourante.addEventListener('input', refreshSaveButtonVisibility);
    formCourante.addEventListener('change', refreshSaveButtonVisibility);

    // Aperçu de l'icône Font Awesome saisie pour un groupe : mis à jour en direct à chaque
    // frappe (délégation : couvre aussi bien les lignes existantes que les lignes ajoutées
    // dynamiquement via le bouton "Ajouter un groupe").
    formCourante.addEventListener('input', function (event) {
        if (!event.target.classList.contains('js-groupe-icon-input')) {
            return;
        }

        const row = event.target.closest('.js-groupe-row');
        const preview = row ? row.querySelector('.js-groupe-icon-preview') : null;

        if (preview) {
            const iconClass = event.target.value.trim();
            preview.className = 'fa-solid js-groupe-icon-preview' + (iconClass ? ' ' + iconClass : '');
        }
    });

    // Bouton "Ajouter un groupe" : clone le modèle de ligne vide, réindexe ses champs, l'ajoute
    // au tableau. L'ajout compte lui-même comme une modification (bouton Sauvegarder affiché).
    // Délégation sur formCourante (élément stable) : le panneau groupes, et donc le bouton
    // #btnAjouterGroupe lui-même, est remplacé après chaque sauvegarde réussie.
    formCourante.addEventListener('click', function (event) {
        if (!event.target.closest('#btnAjouterGroupe')) {
            return;
        }

        const template = document.getElementById('tpl-groupe-row');
        const tbody = document.getElementById('tbody-groupes-club');
        if (!template || !tbody) {
            return;
        }

        const index = tbody.querySelectorAll('.js-groupe-row').length;
        const fragment = template.content.cloneNode(true);

        fragment.querySelectorAll('[name]').forEach(function (el) {
            el.name = el.name.replace('__INDEX__', String(index));
        });

        tbody.appendChild(fragment);
        refreshSaveButtonVisibility();
    });

    // Clic sur "Sauvegarder" : ouvre la modal de confirmation plutôt que de soumettre directement.
    btnSave.addEventListener('click', function () {
        const modalEl = document.getElementById('modalConfirmSaison');
        if (modalEl) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    });

    // Confirmation dans la modal : soumission ajax effective du formulaire (définition + groupes).
    const btnConfirm = document.getElementById('btnConfirmSaveSaisonCourante');
    if (btnConfirm) {
        btnConfirm.addEventListener('click', function () {
            const formData = new FormData(formCourante);

            simpleCallAjax(formData, function (response) {
                // Succès : remplace le panneau groupes par la version fraîche du serveur (les
                // nouvelles lignes reçoivent leur id_groupe réel), puis réinitialise la baseline
                // et masque le bouton Sauvegarder.
                const panel = document.getElementById('saisons-groupes-panel');
                if (panel && response.data) {
                    const html = decodeURIComponent(escape(atob(response.data)));
                    const doc = document.createElement('div');
                    doc.innerHTML = html;
                    const freshPanel = doc.querySelector('#saisons-groupes-panel');
                    if (freshPanel) {
                        panel.replaceWith(freshPanel);
                    }
                }

                baseline = new FormData(formCourante);
                refreshSaveButtonVisibility();
            });
            // En cas d'échec, simpleCallAjax affiche déjà le message d'erreur ; le formulaire
            // reste inchangé et le bouton Sauvegarder reste visible (pas de perte de saisie).
        });
    }
});
