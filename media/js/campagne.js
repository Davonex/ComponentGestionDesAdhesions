/**
 *  Initialise (ou réinitialise) les tooltips Bootstrap de la zone donnée, sur la base de
 *  l'attribut title (et non data-bs-toggle="tooltip", déjà utilisé par le bouton "éditer"
 *  pour ouvrir la modal). À rappeler après tout remplacement/ajout de contenu (ligne du
 *  tableau remplacée après save/toggle, ...), les tooltips ne suivant pas le innerHTML.
 */
const initTooltips = function (root = document) {
    if (!window.bootstrap || !bootstrap.Tooltip) {
        return;
    }

    root.querySelectorAll('[title]').forEach(function (el) {
        const existingTooltip = bootstrap.Tooltip.getInstance(el);

        if (existingTooltip) {
            existingTooltip.dispose();
        }

        new bootstrap.Tooltip(el, {
            trigger: 'hover focus',
            container: 'body',
            placement: 'top'
        });
    });
}

const campagneAdmCB = function (response) {

    let html = decodeURIComponent(escape(atob(response.data)))
    let doc = document.createElement('table');
    doc.innerHTML = html;

    let IdContainer = doc.querySelector('tr').id;
    let Source = doc.querySelector('TR#' + IdContainer)

    let Cible = document.querySelector('TR#' + IdContainer)
    if (Cible !== null) {
        Cible.innerHTML = Source.innerHTML
        // Les attributs du <tr> lui-même (surbrillance ouverte/fermée, data-active lu par le filtre
        // Ouvertes/Fermées, data-id-type lu par le filtre de nature) ne suivent pas innerHTML : sans
        // cette recopie, une campagne ouverte/fermée restait classée dans l'ancien statut par les
        // filtres jusqu'au rechargement de la page.
        Cible.className = Source.className
        Cible.dataset.active = Source.dataset.active
        Cible.dataset.idType = Source.dataset.idType
    } else {
        Cible = document.querySelector('TABLE#table-campagne TBODY')
        if (Cible !== null) {
            Cible.appendChild(Source);
        } else {
            console.debug('l``élément "TABLE#table-campagne TBODY" est introuvable')
        }
    }
    initTooltips(Cible)
}

/**
 *  Icône poubelle d'une campagne fermée : demande confirmation avant d'appeler campagnes.effacer
 *  (l'effacement est un simple statut "effacer = 1", mais la campagne disparaît de la liste sans
 *  possibilité de la retrouver depuis l'interface). Le titre est passé en "details" de
 *  GdaDialog.confirm, qui l'échappe : aucun risque d'injection HTML.
 * @param {Object} data Données ajax de la ligne (voir layouts/campagnes/row.php, $data_remove)
 */
const campagneAdmConfirmRemove = function (data) {
    GdaDialog.confirm(
        Joomla.Text._('COM_GDA_CAMPAGNE_REMOVE_CONFIRM_TITRE'),
        Joomla.Text._('COM_GDA_CAMPAGNE_REMOVE_CONFIRM_MESSAGE'),
        function () {
            simpleCallAjax(data, campagneAdmRemoveCB)
        },
        data['jform_campagne[titre]']
    )
}

const campagneAdmRemoveCB = function (response) {

    let json_data = decodeURIComponent(escape(atob(response.data)))
    const obj = JSON.parse(json_data);

    let IdContainer = obj.id_campagne;



    let Cible = document.querySelector('TR#campagne-' + IdContainer)
    if (Cible !== null) {
        Cible.remove()
    } else {
        console.debug('l``élément "TR#' + IdContainer + '" est introuvable')
    }
}

/**
 *  fonction de callback pour le rapport de campagne, affiche les données dans la console pour l'instant
 * @param {*} response 
 */
const campagneRapportCB = function (response) {

    const frenchDataTableOptions = {
        labels: {
            placeholder: 'Rechercher...',
            perPage: 'lignes par page',
            noRows: 'Aucune donnee disponible',
            noResults: 'Aucun resultat trouve',
            info: 'Affichage de {start} a {end} sur {rows} entrees'
        }
    }

    let html = decodeURIComponent(escape(atob(response.data)))

    // console.log("html: ", html)
    // console.log("html2: ", html2)

    // let parser = new DOMParser();
    // let Source = parser.parseFromString(html, 'text/html');
    let Cible = document.querySelector('#modalRapport div.modal-content');
    Cible.innerHTML = html;

    // console.log(html)

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRapport')).show();

    // Initialiser DataTables après injection
    let rapport = document.querySelector('#rapportTable');
    let datatableApi = globalThis.simpleDatatables;

    if (rapport && datatableApi && datatableApi.DataTable) {
        new datatableApi.DataTable(rapport, {
            perPage: 10,
            ...frenchDataTableOptions
        });
    } else if (rapport) {
        console.error('simple-datatables n\'est pas chargee');
    }

}

/**
 *  Filtres du tableau de gestion des campagnes (statut Ouverte/Fermée + nature),
 *  appliqués côté client sur les attributs data-active / data-id-type des <tr>.
 */
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('table-campagne');

    if (!table) {
        return;
    }

    initTooltips(table);

    const filterActive = document.querySelectorAll('input[name="campagneFilterActive"]');
    const filterType = document.getElementById('campagneFilterType');

    const applyFilters = function () {
        const checkedActive = document.querySelector('input[name="campagneFilterActive"]:checked');
        const activeValue = checkedActive ? checkedActive.value : 'all';
        const typeValue = filterType ? filterType.value : '';

        table.querySelectorAll('tbody tr').forEach(function (tr) {
            const matchActive = activeValue === 'all' || tr.dataset.active === activeValue;
            const matchType = typeValue === '' || tr.dataset.idType === typeValue;

            tr.classList.toggle('d-none', !(matchActive && matchType));
        });
    };

    filterActive.forEach(function (el) {
        el.addEventListener('change', applyFilters);
    });

    if (filterType) {
        filterType.addEventListener('change', applyFilters);
    }
});

/**
 *  Modal d'ajout/édition : adapte le formulaire à la nature sélectionnée. Pour une nature
 *  réservable (Formation, Loisir - voir meta.reservable) : switch "réservation de plusieurs
 *  places" (forcé sur Non pour Formation, une formation étant toujours 1 place), lignes
 *  rôle+capacité (rôle systématiquement demandé, voir ReservationService), choix des groupes.
 *  Pour une nature vitrine sans réservation (ex: Boutique) : ces trois blocs sont masqués (rôles,
 *  "places multiples", groupes) - une vitrine HelloAsso ne répartit personne par rôle ni groupe.
 *
 *  Se déclenche au changement du select ET à l'ouverture de la modal ('shown.bs.modal',
 *  après que LstModal ait préempli/réinitialisé le formulaire) : ouvrir la modal ne déclenche
 *  pas d'évènement 'change' sur le select, sans quoi les lignes de rôles restaient celles de la
 *  précédente édition tant qu'on n'y touchait pas.
 */
document.addEventListener('DOMContentLoaded', function () {
    const modalForm = document.getElementById('modalForm');
    const typeSelect = document.getElementById('jform_campagne_id_type');
    const typeMetaEl = document.getElementById('fieldRolePlaces');
    const fieldsetReservationMultiple = document.getElementById('jform_campagne_reservation_multiple');
    const rolePlacesRawData = document.getElementById('jform_campagne_role_places');
    const idCampagneField = document.getElementById('jform_campagne_id_campagne');
    const eventHelloAssoSelect = document.getElementById('jform_campagne_event_helloasso');
    const fieldReservationMultipleWrapper = document.getElementById('fieldReservationMultiple');
    const fieldIdGroupesWrapper = document.getElementById('fieldIdGroupes');
    const fieldIdResponsableWrapper = document.getElementById('fieldIdResponsable');
    const fieldSousTypeWrapper = document.getElementById('fieldSousType');

    if (!modalForm || !typeSelect || !typeMetaEl) {
        return;
    }

    // Icônes "?" (tooltip) des champs : le contenu de la modal est statique (rendu une seule
    // fois au chargement de la page, pas réinjecté en ajax), une init au chargement suffit.
    initTooltips(modalForm);

    const roleRows = RowList.init({
        containerId: 'jform_campagne_role_places_rows',
        templateId: 'campagne-role-template',
        itemClass: 'campagne-role-item',
        namePrefix: 'jform_campagne[role_places]',
        addBtnId: 'jform_campagne_role_places_add',
        fields: ['role', 'nbr_place'],
    });

    const currentMeta = function () {
        const descriptions = JSON.parse(typeMetaEl.dataset.typeMeta || '{}');
        return descriptions[typeSelect.value] || null;
    };

    /**
     *  Remplit les lignes rôle+capacité : depuis la répartition déjà enregistrée si on édite une
     *  campagne existante (JSON reçu via LstModal/openModal dans #jform_campagne_role_places,
     *  même mécanisme générique que les autres champs - voir campagnes/row.php), sinon depuis les
     *  rôles par défaut de la nature sélectionnée (préremplissage à 0, le Bureau ajuste ensuite).
     */
    const renderRoleRows = function (meta) {
        if (!roleRows) {
            return;
        }

        roleRows.clear();

        const isEditing = idCampagneField && idCampagneField.value !== '';

        if (isEditing && rolePlacesRawData && rolePlacesRawData.textContent.trim() !== '') {
            let existant = {};

            try {
                existant = JSON.parse(rolePlacesRawData.textContent.trim());
            } catch (e) {
                existant = {};
            }

            Object.keys(existant).forEach(function (role) {
                roleRows.addRow({ role: role, nbr_place: existant[role] });
            });

            return;
        }

        const roles = meta && Array.isArray(meta.roles) ? meta.roles : [];
        roles.forEach(function (role) {
            roleRows.addRow({ role: role, nbr_place: 0 });
        });
    };

    /**
     *  Filtre les options du select "Event HelloAsso" selon le formType attendu par la nature
     *  sélectionnée (meta.helloAssoFormType, cf. CampagnesModel::getHelloAssoFormTypeParNature()).
     *  Le serveur (models/fields/eventshelloasso.php, attribut formtype="Event,Shop" sur ce champ)
     *  exclut déjà les formTypes hors du périmètre de la vue Campagnes, mais ne peut pas distinguer
     *  LA nature choisie : celle-ci change sans rechargement de page. Le formType de chaque
     *  formulaire est déjà encodé dans la valeur JSON de l'option (voir eventshelloasso.php),
     *  aucun attribut data-* supplémentaire n'est donc nécessaire.
     */
    const filterEventHelloAssoOptions = function (meta) {
        if (!eventHelloAssoSelect) {
            return;
        }

        const expectedFormType = meta ? meta.helloAssoFormType : null;
        let selectedHidden = false;

        Array.from(eventHelloAssoSelect.options).forEach(function (option) {
            if (!option.value || option.value === 'null') {
                return;
            }

            let formType = null;

            try {
                formType = JSON.parse(option.value).formType || null;
            } catch (e) {
                formType = null;
            }

            const match = !expectedFormType || formType === expectedFormType;
            option.hidden = !match;

            if (!match && option.selected) {
                selectedHidden = true;
            }
        });

        if (selectedHidden) {
            eventHelloAssoSelect.value = 'null';
        }
    };

    /**
     *  Verrouille le champ "Type de campagne" en modification (isEditing) : la nature pilote trop
     *  d'éléments dépendants (rôles, groupes, formType HelloAsso attendu) pour rester modifiable
     *  sans risquer une incohérence avec ce qui a déjà été enregistré pour cette campagne.
     *
     *  Pas d'attribut "disabled" natif : un champ désactivé n'est pas soumis (FormData l'exclut,
     *  voir submitform()/simpleCallAjax() dans form_modal.js), ce qui viderait id_type à
     *  l'enregistrement. Le verrou reste donc purement visuel/interactif : classe CSS
     *  gda-field-locked (fond/bordure/texte assombris + chevron remplacé par un cadenas, cf.
     *  gda.css - un simple ".form-select:disabled" grisé se distinguait trop peu d'un champ actif),
     *  hors tabulation, et l'ouverture du menu déroulant est bloquée via mousedown/keydown
     *  (écouteurs posés une fois plus bas, hors de cette fonction) plutôt qu'un pointer-events:none
     *  CSS, qui aurait aussi empêché le survol - et donc le curseur "not-allowed" et l'infobulle
     *  ci-dessous - de fonctionner. Le champ garde sa valeur et la soumet normalement.
     */
    const applyTypeLock = function () {
        const isEditing = !!(idCampagneField && idCampagneField.value !== '');

        typeSelect.classList.toggle('gda-field-locked', isEditing);
        typeSelect.setAttribute('aria-disabled', isEditing ? 'true' : 'false');
        typeSelect.tabIndex = isEditing ? -1 : 0;
        typeSelect.title = isEditing ? 'Non modifiable après la création de la campagne' : '';
    };

    // Bloque l'ouverture du menu et la navigation clavier tant que le champ est verrouillé (classe
    // posée par applyTypeLock ci-dessus) - posé une seule fois, l'état verrouillé est relu à chaque
    // évènement plutôt que d'être capturé une fois pour toutes.
    typeSelect.addEventListener('mousedown', function (event) {
        if (typeSelect.classList.contains('gda-field-locked')) {
            event.preventDefault();
        }
    });

    typeSelect.addEventListener('keydown', function (event) {
        if (typeSelect.classList.contains('gda-field-locked')) {
            event.preventDefault();
        }
    });

    typeSelect.addEventListener('focus', function () {
        if (typeSelect.classList.contains('gda-field-locked')) {
            typeSelect.blur();
        }
    });

    const updateTypeUi = function () {
        const meta = currentMeta();
        const nonReservable = !!meta && meta.reservable === false;

        applyTypeLock();

        if (fieldsetReservationMultiple) {
            if ((meta && meta.name === 'Formation') || nonReservable) {
                const radioNon = fieldsetReservationMultiple.querySelector('input[type="radio"][value="0"]');
                if (radioNon) {
                    radioNon.checked = true;
                }
                fieldsetReservationMultiple.disabled = true;
            } else {
                fieldsetReservationMultiple.disabled = false;
            }
        }

        // Nature sans réservation (ex: Boutique) : pas de rôles/places, de choix "places multiples"
        // ni de groupes à configurer (une vitrine HelloAsso ne répartit personne par groupe).
        typeMetaEl.classList.toggle('d-none', nonReservable);

        if (fieldReservationMultipleWrapper) {
            fieldReservationMultipleWrapper.classList.toggle('d-none', nonReservable);
        }

        if (fieldIdGroupesWrapper) {
            fieldIdGroupesWrapper.classList.toggle('d-none', nonReservable);

            if (nonReservable) {
                const idGroupesSelect = fieldIdGroupesWrapper.querySelector('select');

                // Vide la selection plutot que de la laisser masquee mais toujours soumise :
                // TomSelect (multiselectInit()) s'attache au <select> via sa propriete .tomselect.
                if (idGroupesSelect && idGroupesSelect.tomselect) {
                    idGroupesSelect.tomselect.clear();
                } else if (idGroupesSelect) {
                    Array.from(idGroupesSelect.options).forEach(function (option) {
                        option.selected = false;
                    });
                }
            }
        }

        // Responsable (mails de demande d'inscription) : sans objet pour une nature sans réservation.
        // Vidé quand masqué, pour ne pas soumettre un responsable sur une Boutique.
        if (fieldIdResponsableWrapper) {
            fieldIdResponsableWrapper.classList.toggle('d-none', nonReservable);

            if (nonReservable) {
                const responsableSelect = fieldIdResponsableWrapper.querySelector('select');

                if (responsableSelect) {
                    responsableSelect.value = '';
                }
            }
        }

        // Sous-type : propre à la nature Formation ; vidé quand masqué pour ne rien soumettre ailleurs.
        if (fieldSousTypeWrapper) {
            const isFormation = !!meta && meta.name === 'Formation';

            fieldSousTypeWrapper.classList.toggle('d-none', !isFormation);

            if (!isFormation) {
                const sousTypeSelect = fieldSousTypeWrapper.querySelector('select');

                if (sousTypeSelect) {
                    sousTypeSelect.value = '';
                }
            }
        }

        renderRoleRows(meta);
        filterEventHelloAssoOptions(meta);
    };

    typeSelect.addEventListener('change', updateTypeUi);
    modalForm.addEventListener('shown.bs.modal', updateTypeUi);

    // LstModal (form_modal.js) préremplit les champs texte depuis el.innerHTML du <span> de la ligne :
    // un titre contenant "&", "<" ou ">" y arrive donc sous forme d'entités ("A &amp; B") et serait
    // ré-enregistré tel quel. Le titre est échappé côté serveur (row.php) : une seule couche
    // d'entités à retirer, à chaque ouverture (le préremplissage repart de zéro à chaque fois).
    const titreInput = document.getElementById('jform_campagne_titre');

    modalForm.addEventListener('shown.bs.modal', function () {
        if (titreInput && titreInput.value.includes('&')) {
            const decodeur = document.createElement('textarea');
            decodeur.innerHTML = titreInput.value;
            titreInput.value = decodeur.value;
        }
    });
});

/**
 *  Onglet "Réservations formation" : filtre la liste déroulante par statut (Ouvertes/Fermées/
 *  Toutes) sur l'attribut data-active des <option>, puis charge en ajax le layout de suivi de
 *  la formation sélectionnée.
 */
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('campagneSuiviSelect');

    if (!select) {
        return;
    }

    const filterActive = document.querySelectorAll('input[name="campagneSuiviFilterActive"]');
    const container = document.getElementById('campagneSuiviContent');

    const resetContent = function () {
        if (container) {
            container.innerHTML = '<p class="text-muted">' + (select.dataset.emptyLabel || '') + '</p>';
        }
        setupSuiviRowFilters();
    };

    const applyFilter = function () {
        const checked = document.querySelector('input[name="campagneSuiviFilterActive"]:checked');
        const activeValue = checked ? checked.value : 'all';
        let selectedHidden = false;

        select.querySelectorAll('option').forEach(function (option) {
            if (option.value === '') {
                return;
            }

            const match = activeValue === 'all' || option.dataset.active === activeValue;
            option.hidden = !match;

            if (!match && option.selected) {
                selectedHidden = true;
            }
        });

        if (selectedHidden) {
            select.value = '';
            resetContent();
        }
    };

    filterActive.forEach(function (el) {
        el.addEventListener('change', applyFilter);
    });

    select.addEventListener('change', function () {
        if (!select.value) {
            resetContent();
            return;
        }

        simpleCallAjax(
            { task: 'campagnes.suivi', id_campagne: select.value },
            campagneSuiviCB,
            false
        );
    });
});

/**
 *  Onglet "Récapitulatif" : chargé en ajax à chaque affichage de l'onglet (données toujours à jour).
 */
document.addEventListener('shown.bs.tab', function (event) {
    if (!event.target || event.target.id !== 'campagnes-tab-recap') {
        return;
    }

    const container = document.getElementById('campagneRecapContent');

    simpleCallAjax({ task: 'campagnes.recapitulatif' }, function (response) {
        if (container) {
            container.innerHTML = decodeURIComponent(escape(atob(response.data)));

            // Sélection multiple des rôles (TomSelect, déjà chargé sur la page).
            const roleSelect = document.getElementById('campagneRecapFilterRole');

            if (roleSelect && typeof TomSelect !== 'undefined') {
                new TomSelect(roleSelect, { plugins: ['remove_button'], create: false });
            }
        }
    }, false);
});

/**
 *  Filtres du récapitulatif : Sous-type (masque les colonnes des campagnes d'un autre sous-type) et
 *  Rôle(s) (sélection multiple : chaque cellule affiche le dernier statut parmi les places des rôles
 *  choisis, ou « — » s'il n'y en a aucune). Les adhérents sans aucun statut visible sont masqués.
 */
const applyRecapFilters = function () {
    const container = document.getElementById('campagneRecapContent');
    const filters = document.getElementById('campagneRecapFilters');

    if (!container || !filters) {
        return;
    }

    const sousTypeSelect = document.getElementById('campagneRecapFilterSousType');
    const roleSelect = document.getElementById('campagneRecapFilterRole');
    const sousType = sousTypeSelect ? sousTypeSelect.value : '';
    const roles = roleSelect ? Array.from(roleSelect.selectedOptions).map(function (option) { return option.value; }) : [];
    const badges = JSON.parse(filters.dataset.badges || '{}');
    const hiddenCampagnes = new Set();

    container.querySelectorAll('th[data-campagne]').forEach(function (th) {
        if (sousType !== '' && th.dataset.sousType !== sousType) {
            hiddenCampagnes.add(th.dataset.campagne);
        }
    });

    container.querySelectorAll('[data-campagne]').forEach(function (cell) {
        cell.classList.toggle('d-none', hiddenCampagnes.has(cell.dataset.campagne));

        if (cell.tagName !== 'TD') {
            return;
        }

        const places = JSON.parse(cell.dataset.places || '[]').filter(function (place) {
            return roles.length === 0 || roles.indexOf(place[0]) !== -1;
        });
        const dernier = places.length > 0 ? places[places.length - 1][1] : null;

        cell.textContent = '';

        if (dernier !== null && badges[dernier]) {
            const badge = document.createElement('span');
            badge.className = 'badge ' + badges[dernier][0];
            badge.dataset.recapStatut = '';
            badge.textContent = badges[dernier][1];
            cell.appendChild(badge);
        } else {
            const vide = document.createElement('span');
            vide.className = 'text-muted';
            vide.innerHTML = '&mdash;';
            cell.appendChild(vide);
        }
    });

    container.querySelectorAll('tr[data-recap-adherent]').forEach(function (row) {
        const hasVisibleStatut = row.querySelector('td[data-campagne]:not(.d-none) [data-recap-statut]') !== null;

        row.classList.toggle('d-none', !hasVisibleStatut);
    });
};

document.addEventListener('change', function (event) {
    if (event.target && (event.target.id === 'campagneRecapFilterSousType' || event.target.id === 'campagneRecapFilterRole')) {
        applyRecapFilters();
    }
});

const campagneSuiviCB = function (response) {
    const html = decodeURIComponent(escape(atob(response.data)));
    const container = document.getElementById('campagneSuiviContent');

    if (container) {
        container.innerHTML = html;
    }

    setupSuiviRowFilters();
};

/**
 *  Filtres Rôle / Statut de l'onglet Suivi : purement côté client, sur les attributs data-role et
 *  data-statut des <tr> rendus par layouts/groupes/detail.php. La liste des rôles est reconstruite
 *  à chaque chargement d'une campagne ; les filtres sont réinitialisés.
 */
const applySuiviRowFilters = function () {
    const role = document.getElementById('campagneSuiviFilterRole');
    const statut = document.getElementById('campagneSuiviFilterStatut');

    if (!role || !statut) {
        return;
    }

    document.querySelectorAll('#campagneSuiviContent tbody tr[data-statut]').forEach(function (row) {
        const match = (role.value === '' || row.dataset.role === role.value)
            && (statut.value === '' || row.dataset.statut === statut.value);
        row.classList.toggle('d-none', !match);
    });
};

const setupSuiviRowFilters = function () {
    const role = document.getElementById('campagneSuiviFilterRole');
    const statut = document.getElementById('campagneSuiviFilterStatut');

    if (!role || !statut) {
        return;
    }

    const rows = document.querySelectorAll('#campagneSuiviContent tbody tr[data-statut]');
    const roles = Array.from(new Set(Array.from(rows).map(function (row) { return row.dataset.role; }).filter(Boolean)));
    const allLabel = role.options[0].textContent;

    role.innerHTML = '';
    role.appendChild(new Option(allLabel, ''));
    roles.forEach(function (name) {
        role.appendChild(new Option(name, name));
    });

    statut.value = '';
    role.disabled = rows.length === 0;
    statut.disabled = rows.length === 0;
};

document.addEventListener('change', function (event) {
    if (event.target && (event.target.id === 'campagneSuiviFilterRole' || event.target.id === 'campagneSuiviFilterStatut')) {
        applySuiviRowFilters();
    }
});

/**
 *  Classe de badge par statut d'inscription, cf. ReservationService::STATUT_* (composant PHP) -
 *  a tenir synchronisee avec layouts/groupes/detail.php.
 */
const statutInscriptionBadgeClass = {
    attente: 'bg-warning text-dark',
    refusee: 'bg-danger',
    confirmee: 'bg-success',
    annulee: 'bg-secondary'
};

/**
 *  Edition du statut d'une inscription (onglet "Suivi des inscriptions") au double-clic : meme
 *  motif que les cellules editables du Secretariat/Saisons (span display + select cache, bascule
 *  au double-clic, sauvegarde au changement). Pas de gestion Entree/Echap ici : un <select> n'a
 *  pas d'etat "saisi mais pas valide" a annuler, contrairement a un champ texte.
 */
document.addEventListener('dblclick', function (event) {
    const cell = event.target.closest('.js-editable-statut-inscription');

    if (!cell) {
        return;
    }

    const display = cell.querySelector('.statut-inscription-display');
    const select = cell.querySelector('.statut-inscription-input');

    if (!display || !select) {
        return;
    }

    display.classList.add('d-none');
    select.classList.remove('d-none');
    select.value = cell.dataset.currentStatut || select.value;
    select.focus();
});

/**
 *  Sauvegarde le statut choisi via AJAX (task campagnes.changerStatutInscription), et met a jour
 *  le badge (texte + classe) sans recharger tout l'onglet.
 *  @param {HTMLSelectElement} select
 */
const saveStatutInscription = function (select) {
    if (select.dataset.isSaving === '1') {
        return;
    }

    const cell = select.closest('.js-editable-statut-inscription');
    const display = cell ? cell.querySelector('.statut-inscription-display') : null;

    if (!cell || !display) {
        return;
    }

    const idPlace = parseInt(cell.dataset.idPlace || '0', 10);
    const newStatut = select.value;
    const currentStatut = cell.dataset.currentStatut || '';

    if (idPlace <= 0 || newStatut === currentStatut) {
        display.classList.remove('d-none');
        select.classList.add('d-none');
        return;
    }

    const ajaxData = {
        task: 'campagnes.changerStatutInscription',
        id_place: idPlace,
        statut: newStatut
    };

    const csrfTokenName = Joomla.getOptions('csrf.token');
    if (csrfTokenName) {
        ajaxData[csrfTokenName] = 1;
    }

    select.dataset.isSaving = '1';

    const resetSaving = function () {
        select.dataset.isSaving = '0';
        display.classList.remove('d-none');
        select.classList.add('d-none');
    };

    simpleCallAjax(ajaxData, function () {
        const selectedOption = select.options[select.selectedIndex];

        display.textContent = selectedOption ? selectedOption.textContent : newStatut;
        display.className = 'statut-inscription-display badge ' + (statutInscriptionBadgeClass[newStatut] || 'bg-secondary');
        cell.dataset.currentStatut = newStatut;

        const row = cell.closest('tr');
        if (row) {
            row.dataset.statut = newStatut;
            applySuiviRowFilters();
        }

        resetSaving();
    }, true, function () {
        // Echec (rejete par le serveur ou reseau) : le select reste sur sa valeur choisie mais
        // n'est pas considere sauvegarde, currentStatut n'est pas mis a jour - un nouveau
        // double-clic proposera de nouveau la valeur reellement enregistree.
        select.value = currentStatut;
        resetSaving();
    });
};

document.addEventListener('change', function (event) {
    const select = event.target.closest('.statut-inscription-input:not(.d-none)');

    if (!select) {
        return;
    }

    saveStatutInscription(select);
});

document.addEventListener('blur', function (event) {
    if (!(event.target instanceof Element)) {
        return;
    }

    const select = event.target.closest('.statut-inscription-input:not(.d-none)');
    const cell = select ? select.closest('.js-editable-statut-inscription') : null;

    if (!select || !cell || select.dataset.isSaving === '1') {
        return;
    }

    // Perte de focus sans changement (Tab/clic ailleurs) : referme sans appel serveur.
    const display = cell.querySelector('.statut-inscription-display');

    if (display) {
        display.classList.remove('d-none');
        select.classList.add('d-none');
    }
}, true);

const multiselectInit = function (selectId) {
    // document.addEventListener('DOMContentLoaded', function() {

    if (document.querySelector('#' + selectId)) {
        new TomSelect('#' + selectId, {
            plugins: {
                remove_button: {
                    title: 'Remove this item',
                }
            },
            create: false,
            persist: false
        });
    } else {
        console.log('Ne touve pas le champ selection multiple: ' + selectId)
    }
    // });
}

// EventListener pour le select des events helloasso dans le formulaire de campagne, affiche les données du formulaire helloasso dans la console pour l'instant
document.addEventListener('change', function (e) {
    if (e.target.id === 'jform_campagne_event_helloasso') {
        const selectedValue = e.target.value;
        // test si selectedValue n'est pas null ou vide
        if (selectedValue && selectedValue !== "null") {
            const formData = JSON.parse(selectedValue);
            console.log('formSlug:', formData.formSlug, 'formType:', formData.formType);
            // tablea tableau data pour l'appel ajax
            const data = {
                task: 'campagnes.getformDetailHelloAsso',
                formSlug: formData.formSlug,
                formType: formData.formType
            }
            simpleCallAjax(data, getformDetailHelloAssoCB, false);

        } // sinon on fait rien 
    }
});

/*
** Remplit les champs du formulaire jform_campagne avec les données de l'event HelloAsso sélectionné
*/
const getformDetailHelloAssoCB = function (response) {
    const json = decodeURIComponent(escape(atob(response.data)));
    const data = JSON.parse(json);

    // Titre
    const titre = document.getElementById('jform_campagne_titre');
    if (titre) titre.value = data.title ?? '';

    // Description
    const description = document.getElementById('jform_campagne_description');
    if (description) description.value = data.description ?? '';


    /** C'est la date de l'venement et pas la date de la campagne.
     * 
        // Date début (champ calendar Joomla : déjà formaté en dd/mm/yyyy côté serveur)
        const dateDebut = document.getElementById('jform_campagne_date_debut');
        if (dateDebut) {
            dateDebut.value = data.startDate;
            dateDebut.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Date fin
        const dateFin = document.getElementById('jform_campagne_date_fin');
        if (dateFin) {
            dateFin.value = data.endDate;
            dateFin.dispatchEvent(new Event('change', { bubbles: true }));
        }

    **/

}

