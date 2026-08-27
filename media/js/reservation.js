/**
 * Réservation aux campagnes depuis le dashboard adhérent (natures Formation et Loisir).
 *
 * Dépend de form_modal.js pour simpleCallAjax() et de row_list.js pour RowList (lignes rôle+quantité).
 *
 * Tous les handlers sont délégués sur `document` : les lignes du dashboard sont remplacées en
 * ajax après chaque réservation, un écouteur posé directement dessus serait perdu.
 */

/**
 * Remplace une ligne de formation par le HTML renvoyé par le serveur. response.data est soit une
 * chaîne base64 directe (annuler()), soit un objet {ligne, helloasso_popup} (reserver() - voir
 * ReservationController::reserver(), qui niche tout dans data plutôt que d'ajouter une propriété
 * dynamique à JsonResponse, deprecated depuis PHP 8.2).
 */
const reservationLigneCB = function (response) {
    const ligneBase64 = (response.data && typeof response.data === 'object') ? response.data.ligne : response.data;
    const html = decodeURIComponent(escape(atob(ligneBase64)));
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
 * en réservation directe depuis le dashboard, il est vide. `onDone` reçoit la réponse complète
 * (pas seulement un signal de fin), pour que l'appelant puisse par exemple afficher le popup
 * HelloAsso porté par response.data.helloasso_popup (voir ReservationController::reserver()).
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
        if (typeof onDone === 'function') { onDone(response); }
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
        reservationInitRoleRows();
    }, false);
};

/**
 * Initialise les lignes rôle+quantité du popup (module RowList, cf. row_list.js) : le contenu de
 * la popup étant réinjecté en ajax à chaque ouverture, RowList.init() doit être rappelé à chaque
 * fois plutôt qu'une seule fois au chargement de la page. Préremplit depuis la réservation
 * existante (#reservationExistantes, JSON role => quantité), sinon une ligne vide par défaut.
 */
const reservationInitRoleRows = function () {
    const rows = RowList.init({
        containerId: 'reservationRoleRows',
        templateId: 'reservation-role-template',
        itemClass: 'reservation-role-item',
        namePrefix: 'role_places',
        addBtnId: 'reservationRoleAdd',
        fields: ['role', 'quantite'],
    });

    if (!rows) {
        return;
    }

    const rawData = document.getElementById('reservationExistantes');
    let existantes = {};

    if (rawData && rawData.textContent.trim() !== '') {
        try {
            existantes = JSON.parse(rawData.textContent.trim());
        } catch (e) {
            existantes = {};
        }
    }

    const roles = Object.keys(existantes);

    if (roles.length) {
        roles.forEach(function (role) {
            rows.addRow({ role: role, quantite: existantes[role] });
        });
    } else {
        rows.addRow();
    }
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
        const commentaire = form.querySelector('[name="commentaire"]');

        if (commentaire) { extra.commentaire = commentaire.value; }

        // Une ligne = un rôle + une quantité (role_places[i][role]/role_places[i][quantite],
        // nommés par RowList - cf. row_list.js) : on transmet chaque champ tel quel, quel que
        // soit le nombre de lignes.
        form.querySelectorAll('[name^="role_places["]').forEach(function (input) {
            extra[input.name] = input.value;
        });

        bouton.disabled = true;
        reservationEnvoyer(idCampagne, extra, function (response) {
            bouton.disabled = false;

            // Réservation confirmée sur une campagne HelloAsso non encore payée : le serveur
            // renvoie en plus un popup de paiement (voir reservation.helloasso_popup, niché dans
            // response.data) - on remplace le contenu du popup au lieu de le fermer, plutôt que
            // d'empiler une seconde modal.
            const popupBase64 = response && response.data ? response.data.helloasso_popup : null;

            if (popupBase64) {
                const content = document.getElementById('reservationModalContent');
                if (content) {
                    content.innerHTML = decodeURIComponent(escape(atob(popupBase64)));
                }
            } else {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('reservationModal')).hide();
            }
        });
    });

    // Désistement depuis le popup : confirmation avant envoi, la place étant aussitôt reprise
    // par le premier de la liste d'attente s'il y en a une.
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

        GdaDialog.confirm(
            Joomla.Text._('COM_GDA_RESERVATION_DESISTER_CONFIRM_TITRE'),
            Joomla.Text._('COM_GDA_RESERVATION_DESISTER_CONFIRM_MESSAGE'),
            function () {
                const data = { task: 'reservation.annuler', id_campagne: idCampagne };
                const csrfTokenName = Joomla.getOptions('csrf.token');
                if (csrfTokenName) { data[csrfTokenName] = 1; }

                bouton.disabled = true;
                simpleCallAjax(data, function (response) {
                    reservationLigneCB(response);
                    bouton.disabled = false;
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('reservationModal')).hide();
                });
            }
        );
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
