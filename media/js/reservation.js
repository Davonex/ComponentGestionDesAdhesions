/**
 * Réservation aux campagnes depuis le dashboard adhérent (nature Formation pour l'instant).
 *
 * Dépend de form_modal.js pour simpleCallAjax().
 *
 * Tous les handlers sont délégués sur `document` : les lignes du dashboard sont remplacées en
 * ajax après chaque réservation, un écouteur posé directement dessus serait perdu.
 */

/**
 * Remplace une ligne de formation par le HTML renvoyé par le serveur.
 */
const reservationLigneCB = function (response) {
    const html = decodeURIComponent(escape(atob(response.data)));
    const doc = document.createElement('div');
    doc.innerHTML = html;

    const source = doc.querySelector('.gda-formation-ligne');

    if (!source) {
        return;
    }

    const cible = document.getElementById(source.id);

    if (cible) {
        cible.replaceWith(source);
    }
};

/**
 * Envoie la réservation. `extra` porte les champs du popup (places, rôle, commentaire) ;
 * en réservation directe depuis le dashboard, il est vide.
 */
const reservationEnvoyer = function (idCampagne, extra, onDone) {
    const data = Object.assign({
        task: 'reservation.reserver',
        id_campagne: idCampagne
    }, extra || {});

    const csrfTokenName = Joomla.getOptions('csrf.token');
    if (csrfTokenName) { data[csrfTokenName] = 1; }

    simpleCallAjax(data, function (response) {
        reservationLigneCB(response);
        if (typeof onDone === 'function') { onDone(); }
    });
};

/**
 * Charge le contenu du popup de réservation puis l'affiche.
 */
const reservationOuvrirPopup = function (idCampagne) {
    const modalEl = document.getElementById('reservationModal');
    const content = document.getElementById('reservationModalContent');

    if (!modalEl || !content) {
        return;
    }

    content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success" role="status">'
        + '<span class="visually-hidden">Chargement...</span></div></div>';
    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    const data = { task: 'reservation.getFormulaire', id_campagne: idCampagne };
    const csrfTokenName = Joomla.getOptions('csrf.token');
    if (csrfTokenName) { data[csrfTokenName] = 1; }

    simpleCallAjax(data, function (response) {
        content.innerHTML = decodeURIComponent(escape(atob(response.data)));
    }, false);
};

document.addEventListener('DOMContentLoaded', function () {

    // Bouton "Réserver" / "Modifier ma réservation" d'une ligne du dashboard.
    // Réservation directe en un clic dans le cas simple ; popup dès qu'une information
    // supplémentaire est nécessaire, ou pour prévenir d'une mise en liste d'attente.
    document.addEventListener('click', function (event) {
        const bouton = event.target.closest('.js-reserver');

        if (!bouton) {
            return;
        }

        event.preventDefault();

        const idCampagne = parseInt(bouton.dataset.idCampagne || '0', 10);

        if (!idCampagne) {
            return;
        }

        if (bouton.dataset.besoinPopup === '1') {
            reservationOuvrirPopup(idCampagne);
        } else {
            bouton.disabled = true;
            reservationEnvoyer(idCampagne, {}, function () {
                bouton.disabled = false;
            });
        }
    });

    // Validation depuis le popup : on collecte les champs du formulaire.
    document.addEventListener('click', function (event) {
        const bouton = event.target.closest('.js-valider-reservation');

        if (!bouton) {
            return;
        }

        event.preventDefault();

        const idCampagne = parseInt(bouton.dataset.idCampagne || '0', 10);
        const form = document.getElementById('formReservation');

        if (!idCampagne || !form) {
            return;
        }

        const extra = {};
        const nbrPlaces = form.querySelector('[name="nbr_places"]');
        const commentaire = form.querySelector('[name="commentaire"]');

        if (nbrPlaces) { extra.nbr_places = nbrPlaces.value; }
        if (commentaire) { extra.commentaire = commentaire.value; }

        // Un rôle par place : autant d'entrées roles[] que de sélecteurs présents.
        form.querySelectorAll('[name="roles[]"]').forEach(function (select, index) {
            extra['roles[' + index + ']'] = select.value;
        });

        bouton.disabled = true;
        reservationEnvoyer(idCampagne, extra, function () {
            bouton.disabled = false;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('reservationModal')).hide();
        });
    });

    // Désistement depuis le popup.
    document.addEventListener('click', function (event) {
        const bouton = event.target.closest('.js-annuler-reservation');

        if (!bouton) {
            return;
        }

        event.preventDefault();

        const idCampagne = parseInt(bouton.dataset.idCampagne || '0', 10);

        if (!idCampagne) {
            return;
        }

        const data = { task: 'reservation.annuler', id_campagne: idCampagne };
        const csrfTokenName = Joomla.getOptions('csrf.token');
        if (csrfTokenName) { data[csrfTokenName] = 1; }

        bouton.disabled = true;
        simpleCallAjax(data, function (response) {
            reservationLigneCB(response);
            bouton.disabled = false;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('reservationModal')).hide();
        });
    });

    // Clic sur le titre d'une formation : affiche l'article lié dans un popup.
    document.addEventListener('click', function (event) {
        const lien = event.target.closest('.js-show-article');

        if (!lien) {
            return;
        }

        event.preventDefault();

        const idArticle = parseInt(lien.dataset.idArticle || '0', 10);
        const modalEl = document.getElementById('articleModal');
        const content = document.getElementById('articleModalContent');

        if (!idArticle || !modalEl || !content) {
            return;
        }

        content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success" role="status">'
            + '<span class="visually-hidden">Chargement...</span></div></div>';
        bootstrap.Modal.getOrCreateInstance(modalEl).show();

        const data = { task: 'reservation.showArticle', id_article: idArticle };
        const csrfTokenName = Joomla.getOptions('csrf.token');
        if (csrfTokenName) { data[csrfTokenName] = 1; }

        simpleCallAjax(data, function (response) {
            content.innerHTML = decodeURIComponent(escape(atob(response.data)));
        }, false);
    });

});
