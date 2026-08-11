document.addEventListener('DOMContentLoaded', function () {
    //   document.addEventListener('DOMContentLoaded', function() {


    // Synchronisation barre de navigation wizard avec le carousel
    const wizard = document.getElementById('wizardInscription');
    const navButtons = document.querySelectorAll('#wizardNav .nav-link');
    const btnQrScanner = document.getElementById('openQrScanner');
    const btnValider = document.getElementById('btnValider');

    // Récupérer les valeurs du formulaire
    const getValue = id => document.querySelector(`#${id}`)?.value || "";

    // gestion de l'affichage des boutons et du header à chaque changement d'étape du carousel
    wizard.addEventListener('slid.bs.carousel', function (e) {
        // Mise à jour barre de navigation
        navButtons.forEach(function (btn) {
            btn.classList.remove('active');
        });
        if (navButtons[e.to]) {
            navButtons[e.to].classList.add('active');
        }

        // Mise à jour du header
        const headerStep = document.getElementById('headerStep');
        if (headerStep) {
            headerStep.innerHTML = Joomla.JText._(`COM_GDA_ADHESION_HEADER_STEP${e.to + 1}`);
        }

        // QR Code visible uniquement sur step 0 et 1
        if (e.to <= 1) {
            btnQrScanner.classList.remove('d-none');

        } else {
            btnQrScanner.classList.add('d-none');
        }

        // Valider visible uniquement sur step 2
        if (e.to === 2) {
            btnValider.classList.remove('d-none');
            document.querySelector("#recap_cotisation").innerHTML = "";
            const data = {
                task: 'form.CheckCotisation',
                dateDeNaissance: getValue("jform_date_de_naissance"),
                codePostal: getValue("jform_code_postal"),
                reduction: getValue("jform_reduction")
            }
            simpleCallAjax(data, CBCheckCotisation, false);
        } else {
            btnValider.classList.add('d-none');
        }
    });
    //   });getValue("jform_code_postal");




    /**
     * Récupération des données d'adhésion 
     * avant de passer à l'étape 3 (récapitulatif)
     */
    const btnStepRecap = document.querySelector("#btnStepRecap"); // ton bouton "suivant" de la step 2

    // Remplir le récapitulatif avant d’afficher la step 3
    btnStepRecap?.addEventListener("click", function () {


        // Fonction pour récupérer les nouvelle infos concernant la cotisation
        // cotisation_code & cotisation_montant








        document.querySelector("#recap_civilite").textContent = getValue("jform_civilite");
        document.querySelector("#recap_nom").textContent = getValue("jform_nom");
        document.querySelector("#recap_prenom").textContent = getValue("jform_prenom");
        document.querySelector("#recap_adresse").textContent = getValue("jform_adresse");
        document.querySelector("#recap_ville").textContent = getValue("jform_ville");
        document.querySelector("#recap_code_postal").textContent = getValue("jform_code_postal");

        document.querySelector("#recap_email").textContent = getValue("jform_email");
        document.querySelector("#recap_telephone").textContent = getValue("jform_telephone");
        document.querySelector("#recap_date_de_naissance").textContent = getValue("jform_date_de_naissance");

        document.querySelector("#recap_a_prevenir").textContent = getValue("jform_a_prevenir");
        document.querySelector("#recap_a_prevenir_tel").textContent = getValue("jform_a_prevenir_tel");

        /* Photo */
        const scrPhoto = document.querySelector(`#photoPreview`)?.getAttribute("src") || "";
        document.querySelector("#recap_photo").setAttribute('src', scrPhoto);

        const droitImg = document.querySelector("#jform_droit_img")?.checked || false;
        document.querySelector("#recap_droit_img").textContent = droitImg ? Joomla.Text._('COM_GDADHESIONS_DROIT_IMAGE_OUI') : Joomla.Text._('COM_GDADHESIONS_DROIT_IMAGE_NON');

        // recap licence
        const licence = document.querySelector("#jform_username")?.value || "";
        const validite = document.querySelector("#jform_date_de_validite")?.value || "";
        let txtValidite = validite ? '<span class="fst-italic"> (' + validite + ')</span>' : "";
        document.querySelector("#recap_licence").textContent = licence + txtValidite || Joomla.Text._('COM_GDADHESIONS_PAS_DE_LICENCE');

        // recap CACI
        const caci = document.querySelector("#caciFlag")?.value || "";
        const date_caci = getValue("jform_date_caci") || "";
        let Str1 = caci === "1" ? Joomla.Text._('COM_GDA_ADHESION_RECAP_CACI_CHARGE') : Joomla.Text._('COM_GDA_ADHESION_RECAP_CACI_NON_CHARGE');
        let Str2 = date_caci ? ' <span class="fst-italic"> (' + date_caci + ')</span>' : Joomla.Text._('COM_GDA_ADHESION_RECAP_CACI_NON_RENSEIGNE');

        document.querySelector("#recap_caci").innerHTML = Str1 + ' ' + Str2;
        // recap plongees
        document.querySelector("#recap_nbr_plongee").textContent = getValue("jform_nbr_plongee") || "0";
        document.querySelector("#recap_nbr_plongee_35").textContent = getValue("jform_a_nbr_plongee_35") || "0";
        document.querySelector("#recap_nbr_plongee_auto").textContent = getValue("jform_nbr_plongee_auto") || "0";;

        // Groupes
        // Afficher la liste de options dans un element P  et que chaque element soit dans un span avec la class "btn btn-pripary"
        const groupesSelect = document.querySelector("#jform_id_groupes");
        const selectedOptions = Array.from(groupesSelect.selectedOptions).map((option) => "<span class='btn btn-primary me-1 mb-1'>" + option.text + "</span>");

        document.querySelector("#recap_groupes").innerHTML = selectedOptions.join(" ") || "<span class='text-recap'>" + Joomla.Text._('COM_GDA_ADHESION_RECAP_AUCUN_GROUPE') + "</span>";
        // Brevets
        // récupérer tous les inputs du conteneur dont le name contient [nom]
        // console.log("recupe tous les brevets");
        const brevetInputs = document.querySelectorAll('#brevets-container input[name*="[nom]"]');
        // console.log(brevetInputs);      
        const brevetValues = Array.from(brevetInputs).map(input => input.value).filter(value => value.trim() !== '');
        const brevetSpans = brevetValues.map(value => "<span class='btn btn-primary me-1 mb-1'>" + value + "</span>");

        document.querySelector("#recap_brevets").innerHTML = brevetSpans.join(" ") || "<span class='text-recap'>" + Joomla.Text._('COM_GDA_ADHESION_RECAP_AUCUN_BREVET') + "</span>";
        // afficher dans le P recap la liste des brevets et que chaque brevet soit dans un span avec la class "btn btn-pripary"

    });



    /**
     * Validation des étapes du formulaire d'adhésion
     * Empêche de passer à l'étape suivante si des champs requis sont invalides
     */
    const myCarousel = document.getElementById('wizardInscription')
    myCarousel.addEventListener('slide.bs.carousel', event => {
        const step = document.getElementById("step-" + event.from);

        // event.relatedTarget // newly activated item
        // event.direction // direction of the sliding
        // event.from // previous item index
        // event.to // next item index
        let next = true
        step.querySelectorAll(
            'input.required:invalid:not([type="hidden"]), ' +
            'input.required:valid:not([type="hidden"]), ' +
            'input.required.is-invalid:not([type="hidden"])').forEach(el => {
                if (el.classList.contains("server-side-validation")) {
                    // Si la validation serveur a déjà marqué le champ comme invalide, on ne change rien
                    if (el.classList.contains("is-invalid") || !el.classList.contains("is-valid")) {
                        (el.classList.contains("is-invalid")) ? null : el.classList.remove("is-invalid");
                        next = false;
                        console.log(el.name + " is-invalid !");
                    }
                    return
                } else if (el.matches(':invalid')) {
                    // L'élément est invalide
                    next = false;
                    console.log(el.name + " :invalid  détecté !");
                    (el.classList.contains("is-valid")) ? el.classList.remove("is-valid") : null;
                    el.classList.add("is-invalid");

                } else {
                    (el.classList.contains("is-invalid")) ? el.classList.remove("is-invalid") : null;
                    el.classList.add("is-valid");
                }
            });

        if (!next) {
            Joomla.renderMessages({
                "error": ["Champ invalide ou incomplet!"]
            });
            event.preventDefault()
            return;
        } else {
            Joomla.renderMessages({}); // Efface les messages 
        }


        // const invalid = step.querySelector("input:invalid, textarea:invalid, select:invalid");   
        // if (invalid) {
        //     Joomla.renderMessages({error: ['Champ invalide ou incomplet']});
        //     invalid.classList.remove("is-valid");
        //     invalid.classList.add("is-invalid");
        //     invalid.focus();
        //     event.preventDefault()
        //     return;
        // }
    })

});


// Gestion du QR code  
document.addEventListener("DOMContentLoaded", function () {
    const qrModal = document.getElementById("qrModal");
    const openBtn = document.getElementById("openQrScanner");
    const closeBtn = document.getElementById("closeQrScanner");
    let qrScanner;
    let isScannerRunning = false; // Flag pour tracker l'état du scanner

    // Ouvrir la modale et démarrer la caméra
    openBtn.addEventListener("click", function () {
        qrModal.classList.add("active");

        qrScanner = new Html5Qrcode("qrReader");
        qrScanner.start({
            facingMode: "environment"
        }, // caméra arrière
            {
                fps: 2,
                qrbox: (viewfinderWidth, viewfinderHeight) => {
                    let minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                    return {
                        width: minEdge * 0.8,
                        height: minEdge * 0.8
                    }; // 80% de l’écran
                }
            },
            (decodedText, decodedResult) => {
                // console.log("URL détectée :", decodedText);

                // Fermer la modale automatiquement
                stopScanner();
                /**
                * @external {Function} scrap - see media\com_gdadhesions\js\scrap-ffessm.js
                */
                scrap(decodedText, AdhesionCB);
            },
            (errorMessage) => {
                // erreurs de scan, tu peux les ignorer
            }
        ).then(() => {
            isScannerRunning = true;
        }).catch(err => {
            // Gestion des erreurs d'initialisation du scanner (ex: refus accès caméra)
            console.error("Erreur initialisation scanner:", err);
            isScannerRunning = false;
            stopScanner();
            Joomla.renderMessages({
                "error": ["Impossible d'accéder à la caméra. Vérifiez les permissions."]
            });
        });
    });

    // Fermer la modale manuellement
    closeBtn.addEventListener("click", stopScanner);

    function stopScanner() {
        if (qrScanner && isScannerRunning) {
            try {
                qrScanner.stop()
                    .then(() => {
                        qrScanner.clear();
                    })
                    .catch(err => {
                        console.debug("Erreur arrêt scanner:", err);
                    })
                    .finally(() => {
                        isScannerRunning = false;
                        qrModal.classList.remove("active");
                    });
            } catch (err) {
                console.debug("Erreur synchrone arrêt scanner:", err);
                isScannerRunning = false;
                qrModal.classList.remove("active");
            }
        } else {
            // Fermer la modal en toutes circonstances
            isScannerRunning = false;
            qrModal.classList.remove("active");
        }
    }

});


/**
 * 
 *  Definition des 2 zone pour uploader
 *  La photo
 *  Le Caci
 * 
 */
document.addEventListener('DOMContentLoaded', function () {
    /**
     * Creation des zones de drag&drop pour la photo et le CACI via la factory générique
     * FileUpload.create() (media/com_gdadhesions/js/file_upload.js).
     * Les instances sont accessibles via window.GdaFileUploads.photo / .caci
     */
    FileUpload.create('photo', {
        mediaBrowserId: 'step-0',
        dropAreaId: 'photoDropArea',
        previewId: 'photoPreview',
        inputId: 'photoUpload',
        flagId: 'photoFlag',
    });

    FileUpload.create('caci', {
        mediaBrowserId: 'step-1',
        dropAreaId: 'caciDropArea',
        previewId: 'caciPreview',
        inputId: 'caciUpload',
        flagId: 'caciFlag',
        acceptPdf: true,
    });

    // Alias conservés pour compatibilité avec form_modal.js::submitform()
    window.UploadPhoto = window.GdaFileUploads.photo;
    window.UploadCaci = window.GdaFileUploads.caci;
});



// Gestion du tooltips
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});

// https://l.ffessm.fr/c.asp?id=1035990_KIMUAC
//https://l.ffessm.fr/c.asp?id=062553_D5EC46


// Form Validator
document.addEventListener("DOMContentLoaded", function () {
    ServerSideValidation('jform_email', 'checkEmail');
    ServerSideValidation('jform_username', 'checkUserName');
    ClientSideValidation('step-0')
});



// tom select // multi select
document.addEventListener("DOMContentLoaded", function () {
    new TomSelect('#jform_id_groupes', {
        dropdownParent: 'body',
        plugins: {
            remove_button: {
                title: 'Remove this item',
            }
        },
        create: false,
        persist: false
    });
    // multiselectInit ('jform_id_groupes')
});



/** CBsubmitformAdhesion 
 * Callback après soumission du formulaire d'adhésion
 * @param {Object} response - Données retournées par le serveur après soumission du formulaire
 * @returns {void}
*/
function CBsubmitformAdhesion(response) {

    response.data = JSON.parse(atob(response.data));

    // if (response.data.username[0] == "N") {
    //console.log ("CBsubmitformAdhesion : change la Data suite a l'inscription d'un nouveau");

    document.querySelector("#jform_id").value = response.data.id;
    //jform_username
    document.querySelector("#jform_username").value = response.data.username;
    document.querySelector("#jform_username").readOnly = true;
    // }

    document.querySelector("#jform_prenom").value = response.data.prenom;
    document.querySelector("#jform_nom").value = response.data.nom;
    document.querySelector("#jform_ville").value = response.data.ville;
    document.querySelector("#jform_nbr_plongee").value = response.data.nbr_plongee;
    document.querySelector("#jform_nbr_plongee_35").value = response.data.nbr_plongee_35;
    document.querySelector("#jform_nbr_plongee_auto").value = response.data.nbr_plongee_auto;



    //Joomla.renderMessages( {"message": [response.message]} );
    // const carousel = bootstrap.Carousel.getInstance(document.getElementById('wizardInscription'));
    // carousel.to(0);

    // Afficher la boite de dialogue Joomla
    import('joomla.dialog').then(({ default: JoomlaDialog }) => {
        const dialog = new JoomlaDialog({
            popupType: 'inline',
            // textHeader: response.message,
            popupContent: response.data.popupcontent,
            width: '50%',
            // height: 'fit-content',
        });
        dialog.popupButtons = [{
            label: 'Ok',
            onClick: () => dialog.destroy(),
            className: 'btn btn-success ms-2'
        }];
        dialog.show();
    });


}
// Exposer globalement la fonction callback
window.CBsubmitformAdhesion = CBsubmitformAdhesion;




function CBCheckCotisation(response) {
    // console.log (response.data);       
    //sprintf(Text::_('COM_GDA_COTISATION_TARIF_'+response.data.code), response.data.montant);
    document.querySelector("#recap_cotisation").innerHTML = response.data.innerHtml;
    document.querySelector("#jform_cotisation_code").value = response.data.code;
    document.querySelector("#jform_cotisation_montant").value = response.data.montant;
}