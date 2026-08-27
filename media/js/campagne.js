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
 *  Modal d'ajout/édition : adapte le switch "réservation de plusieurs places" (forcé sur Non
 *  pour Formation - une formation est toujours 1 place, libre pour Loisir), et remplit les
 *  lignes rôle+capacité (Formation et Loisir demandent toutes deux systématiquement un rôle par
 *  place, voir ReservationService), selon la nature sélectionnée.
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

    const updateTypeUi = function () {
        const meta = currentMeta();

        if (fieldsetReservationMultiple) {
            if (meta && meta.name === 'Formation') {
                const radioNon = fieldsetReservationMultiple.querySelector('input[type="radio"][value="0"]');
                if (radioNon) {
                    radioNon.checked = true;
                }
                fieldsetReservationMultiple.disabled = true;
            } else {
                fieldsetReservationMultiple.disabled = false;
            }
        }

        renderRoleRows(meta);
    };

    typeSelect.addEventListener('change', updateTypeUi);
    modalForm.addEventListener('shown.bs.modal', updateTypeUi);
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

const campagneSuiviCB = function (response) {
    const html = decodeURIComponent(escape(atob(response.data)));
    const container = document.getElementById('campagneSuiviContent');

    if (container) {
        container.innerHTML = html;
    }
};

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

