const campagneCB = function (response) {

    // console.log("campagneCB called with data: ", data);
    // let html = atob(response.data)
    let html = decodeURIComponent(escape(atob(response.data)))

    // console.log("html: ", html)
    // console.log("html2: ", html2)

    let parser = new DOMParser();
    let doc = parser.parseFromString(html, 'text/html');
    let IdContainer = doc.querySelector('div').id;

    // console.log("IdContainer: ", IdContainer)

    let Source = doc.querySelector('.card-footer')
    let Cible = document.querySelector('#' + IdContainer + ' .card-footer');

    Cible.innerHTML = Source.innerHTML
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
            perPage: '{select} lignes par page',
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
 *  fonction de callback pour le rapport de campagne, affiche les données dans la console pour l'instant
 * @param {*} response 
 */
const campagneSouscritCB = function (response) {
    let html = decodeURIComponent(escape(atob(response.data)))

    //console.log("html: ", html)
    // console.log("html2: ", html2)

    let parser = new DOMParser();
    let doc = parser.parseFromString(html, 'text/html');
    let Source = doc.querySelector('.card.dashboard-campagne');
    let Cible = document.querySelector('.card.dashboard-campagne');

    Cible.innerHTML = Source.innerHTML




}




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

