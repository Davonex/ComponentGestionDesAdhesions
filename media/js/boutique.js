/**
 * Encart "Boutique" du dashboard adhérent (layouts/accueil/dash_boutique.php).
 *
 * Le bouton "Rafraîchir" vit dans le même fragment HTML que l'encart lui-même (remplacé en bloc
 * après chaque rafraîchissement) : un écouteur posé directement dessus serait donc perdu dès le
 * premier clic réussi - délégué sur `document` plutôt, même motif que reservation.js.
 */

const gdaBoutiqueStopSpinning = function (btnRefresh) {
    btnRefresh.disabled = false;
    const icon = btnRefresh.querySelector('i');
    if (icon) {
        icon.classList.remove('fa-spin');
    }
};

document.addEventListener('click', function (event) {
    const btnRefresh = event.target.closest('#btnRefreshBoutique');

    if (!btnRefresh || btnRefresh.disabled) {
        return;
    }

    btnRefresh.disabled = true;
    const icon = btnRefresh.querySelector('i');
    if (icon) {
        icon.classList.add('fa-spin');
    }

    simpleCallAjax({ task: 'accueil.refreshBoutique' }, function (response) {
        const html = decodeURIComponent(escape(atob(response.data)));
        const doc = document.createElement('div');
        doc.innerHTML = html;

        const source = doc.querySelector('#gda-boutique-card');
        const cible = document.getElementById('gda-boutique-card');

        // source absent : plus aucune campagne Boutique active (cas rare, campagne fermée entre
        // temps) - le layout ne rend alors rien du tout, rien à remplacer.
        if (source && cible) {
            cible.replaceWith(source);
        } else {
            gdaBoutiqueStopSpinning(btnRefresh);
        }
    }, true, function () {
        gdaBoutiqueStopSpinning(btnRefresh);
    });
});
