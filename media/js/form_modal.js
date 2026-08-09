

const LstModal = function (elModal,module) {

    /**
     * LstModal 
     * 
     *  * @param  {string} elModal l'id de la fenetre qui s'ouvre
     *  * @param  {string} module  chaine de charatere qui permete de trouver les data pour remplir le formulaire modale 
     *      data-bs-id_XXXXX="id_5"
     */

    let myModal = document.getElementById(elModal);

    myModal.addEventListener("show.bs.modal", function (event) { 


         let  Id  = event.relatedTarget.getAttribute("data-bs-id_"+module)
         const Empty = (event.relatedTarget.getAttribute("data-empty") === null)  ? false : true  
            if (Empty) {
                // Cas on on ouvre le formulaire  pour creer nouveau
                openModalEmpty (myModal,"jform_" + module)
            }
            else if (Id !== null) {   
                // Cas on on ouvre le formulaire modale  pour editer un element            
                let Card = document.querySelector("#" + Id);
                // console.log (" show modal");
   
                openModal (Card,myModal,"jform_" + module)
            } else {

            }
        });


    // document.querySelector("#id_1").querySelectorAll('[data-bs]')
    // document.getElementById('myModal').querySelector('#' + formName + '_' + name)

}



const openModal = function (srcElement,desElement,formName,previewSelector = '#image-preview') {

    srcElement.querySelectorAll('[data-bs]').forEach(function (el) {
        switch (el.tagName) {
        case 'IMG' :
            var cible = desElement.querySelector(previewSelector)
             if (cible !== null) {
            cible.src =  el.attributes['src'].value
             } else {
                console.debug ('#image-preview  est introuvable')
             }
            break
        default :
         // element text d'un SPAN
            var name = el.attributes['name'].value
            var cible = desElement.querySelector('#' + formName + '_' + name)
            if (cible !== null) {
                if (cible.tagName === 'INPUT') {
                    cible.value = el.innerHTML
                } else if (cible.tagName === 'SELECT') { 
                    if (cible.attributes['multiple']) {
                        // Select Multi
                        //Set Value pour avec l'API tomselect
                        cible.tomselect.setValue ( el.innerHTML.split(','));
                    }
                    else {
                    cible.value = el.innerHTML}
                } else {
                    cible.innerHTML = el.innerHTML}
                } else {
                    console.debug ('#' + formName + '_' + name + ' est introuvable')
                }
            break

        }
        // cible.classList.add("bg-success")
    });  
}

const openModalEmpty = function (desElement,formName) {


    // desElement.querySelectorAll('#' + formName + '_' + name)
    desElement.querySelectorAll('[name^="'+formName+'["]').forEach(function (el) {
            switch (el.tagName) {   
                case 'INPUT':
                    el.value = "";
                    break
                case 'SELECT':
                    // verifie dans le cas d'un select multiple (tomselect)
                     if (el.attributes['multiple']) {
                        // Select Multi
                        el.tomselect.setValue ([]);
                    }
                    else {
                        el.value = 0;
                    }
            }
            if (el.attributes['modal-title'] ){
                el.innerHTML = desElement.querySelector ("#default_modal-title").innerHTML
            }
           console.debug (el);
    });
}



const closeModal = function (elId) {
     document.getElementById(elId).click();
}


const potsCloseModal = function (srcElement,desElement,formName, expect = []) {
    
    srcElement.querySelectorAll('.form-control').forEach(function (el) {
        var name = el.id.replace(formName+"_" ,"")
        // filtrer  les chamlp exclut
        if (expect.indexOf(name) === -1 )  {
      
            var cible = desElement.querySelector('[name="' + name + '"]')
            switch (el.tagName) {
                case 'INPUT' :
                    // console.log (cible)
                    if (cible.tagName === 'SPAN') {
                        cible.innerHTML = el.value
                    }    
                    break
                case 'SELECT' : 
                    if (cible.tagName === 'SPAN') {
                        cible.innerHTML = el.value  
                    }  
                    break
                // case 'IMG' : 
                //     debugger
                //     break
            }
            // cible.classList.add("bg-success")
        }            
    });  
}

const refreshPreview = function (img) {
    var newSrc = img.src.split('?')[0] + '?id=' + new Date();
    img.src=newSrc;
}   



//     document.getElementById(buttonId).addEventListener("click", function(e) {
//         e.preventDefault()
//          let form = document.querySelector("#" + formId + ".form-validate");
//         //  Joomla.submitbutton(formId)


//          if (!form ||  document.formvalidator.isValid(form))
//         {
//             var formData = new FormData(document.getElementById(formId))
//             Joomla.request({
//                 method: 'POST',
//                 url: 'index.php?option=com_gdadhesions&format=json',  
//                 promise: false,
//                 data: formData, 
//             onSuccess: (data) => {
//                     const response = JSON.parse(data);
//                     if (response.success) {
//                         console.log("success")
//                         Joomla.renderMessages( {"message": [response.message]} );
//                         //.log(atob(response.data));
//                         myCallback (response);

//                     }else {
//                         console.error("error: " + response.message);
//                         Joomla.renderMessages( {"error": [response.message]} );
//                     } 
//                     closeModal(btnClose)
//                 },
//             onError: (xhr) => {
//                 const response = JSON.parse(xhr.response);
//                 console.error("error: " + response.message);
//                 Joomla.renderMessages( {"error": [response.message]} );
//             }
            
//             });
//         } else {
//             console.debug (formId + " not Valid")
//         }

//     });
 /**
     *     const submitform = function (formId,myCallback,btnClose = "btnClose") {
 
     * @param {e} event
     * @param  {formId} #Form
     * @param  {myCallback} module  chaine de charatere qui permete de trouver les data pour remplir le formulaire modale 
     * @param  {btnClose} id du bouton de fermeture de la modale
     *      data-bs-id_XXXXX="id_5"
     */

    const submitform = function (e,formId,myCallback,btnClose = "btnClose") {


        e.preventDefault();

        let form = document.querySelector("#" + formId + ".form-validate");
        let formData = new FormData(document.getElementById(formId));
        // importe les upload sir il existe.
        const upPhoto = window.UploadPhoto;
        if (upPhoto && upPhoto.File) {
            formData.append('jform[upload.photo]', upPhoto.File);
            }
        const upCaci = window.UploadCaci;
        if (upCaci && upCaci.File) {
            formData.append('jform[upload.caci]', upCaci.File);
            }   

        //  Joomla.submitbutton(formId)
        
         if (!form ||  document.formvalidator.isValid(form))
        {
           
            const basePath = Joomla.getOptions('system.paths')?.baseFull || '';
            Joomla.request({
                method: 'POST',
                url: `${basePath}index.php?option=com_gdadhesions&format=json`,  
                promise: false,
                data: formData, 
            onSuccess: (data) => {
                    const response = JSON.parse(data);
                    if (response.success) {
                        // console.log("submitform: success") 
                        //.log(atob(response.data));
                        if (myCallback !== null)  {
                            myCallback(response);
                        }

                    }else {
                        console.error("error: " + response.message);
                        Joomla.renderMessages( {"error": [response.message]} );
                    } 
                    // si le btn est vlide on le ferme 
                    if (btnClose !== null)  {
                        closeModal(btnClose)
                    }
                },
            onError: (xhr) => {
                const response = JSON.parse(xhr.response);
                //console.error("error: " + response.message);
                Joomla.renderMessages( {"error": [response.message]} );
            }
            
            });
        } else {
            console.debug (formId + " not Valid")
        }
}





 
 // je veux ajouter un parametre a cette function qui est par defaut vrai, pour valider l'envoi du rendermessage
 // car dans certain cas je ne veux pas afficher le message de succes ou d'erreur qui est geré dans le callback
// Handler délégué global pour js-show-payement (toutes les vues, pas seulement secretariat)
document.addEventListener('click', function (event) {
  const btn = event.target.closest('.js-show-payement');
  if (!btn) { return; }

  const modalEl = document.getElementById('payementModal');
  const modalContent = document.getElementById('payementModalcontent');
  if (!modalEl || !modalContent) { return; }

  modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
  bootstrap.Modal.getOrCreateInstance(modalEl).show();

  const ajaxData = {
    task: 'secretariat.getPayement',
    id_order:    btn.dataset.itemOrder    || '0',
    id_profil:   parseInt(btn.dataset.itemId       || '0', 10),
    id_campagne: parseInt(btn.dataset.itemCampagne || '0', 10),
    username:    btn.dataset.itemUsername || '',
    cotisation:  btn.dataset.itemCotisation || '0',
  };
  const csrfTokenName = Joomla.getOptions('csrf.token');
  if (csrfTokenName) { ajaxData[csrfTokenName] = 1; }

  if (typeof simpleCallAjax === 'function') {
    simpleCallAjax(ajaxData, function (response) {
      if (response.success) {
        modalContent.innerHTML = decodeURIComponent(escape(atob(response.data)));
      } else {
        modalContent.innerHTML = '<div class="alert alert-danger">' + (response.message || 'Erreur inconnue') + '</div>';
      }
    }, false);
  } else {
    modalContent.innerHTML = '<div class="alert alert-danger">simpleCallAjax non disponible.</div>';
  }
});

// Handler délégué global pour js-show-profil-card (Groupe, Secretariat, ... toute vue chargeant ce script)
document.addEventListener('click', function (event) {
  const trigger = event.target.closest('.js-show-profil-card');
  if (!trigger) { return; }

  event.preventDefault();

  const modalEl = document.getElementById('profilCardModal');
  const modalContent = document.getElementById('profilCardModalContent');
  if (!modalEl || !modalContent) { return; }

  modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
  bootstrap.Modal.getOrCreateInstance(modalEl).show();

  const ajaxData = {
    task: 'profil.showCard',
    id_profil: parseInt(trigger.dataset.idProfil || '0', 10),
  };
  const csrfTokenName = Joomla.getOptions('csrf.token');
  if (csrfTokenName) { ajaxData[csrfTokenName] = 1; }

  simpleCallAjax(ajaxData, function (response) {
    modalContent.innerHTML = decodeURIComponent(escape(atob(response.data)));
  }, false, function (response) {
    modalContent.innerHTML = '<div class="alert alert-danger">' + ((response && response.message) || 'Erreur inconnue') + '</div>';
  });
});

/**
 * simpleCallAjax
 *
 * @param {Object|FormData} data Soit un objet clé/valeur (ex: {task: 'x.y', 'jform[champ]': 'valeur'}),
 *   soit une instance FormData déjà construite (ex: new FormData(monForm)) - pratique pour réutiliser
 *   directement un <form> existant, fichiers uploadés compris, sans devoir énumérer chaque champ.
 * @param {Function|null} cbPostRequest Callback appelé avec la réponse en cas de succès (response.success === true)
 * @param {boolean} renderMessage Si true, affiche response.message via Joomla.renderMessages en cas de succès
 * @param {Function|null} cbOnFailure Callback appelé (avec la réponse si disponible) en cas d'échec logique
 *   (response.success === false) ou d'erreur réseau/serveur - utile pour masquer un spinner par exemple,
 *   puisque le message d'erreur est de toute façon toujours affiché via Joomla.renderMessages.
 */
const simpleCallAjax = (data, cbPostRequest = null, renderMessage = true, cbOnFailure = null) => {

        const formData = data instanceof FormData ? data : new FormData();
        if (!(data instanceof FormData)) {
            Object.entries(data).forEach(([key, value]) => {
                    formData.append(key, value);
            });
        }
        const basePath = Joomla.getOptions('system.paths')?.baseFull || '';
        Joomla.request({
                method: 'POST',
                url: `${basePath}index.php?option=com_gdadhesions&format=json`,
                promise: false,
                data: formData,
            onSuccess: (data) => {
                    const response = JSON.parse(data);
                    if (response.success) {
                        // console.log("simpleCallAjax: success")
                        if (renderMessage) {
                            Joomla.renderMessages( {"message": [response.message]} );
                        }
                        //.log(atob(response.data));
                        if (cbPostRequest !== null) {
                            cbPostRequest (response);
                        }

                    }else {
                        console.error("error: " + response.message);
                        Joomla.renderMessages( {"error": [response.message]} );
                        if (cbOnFailure !== null) {
                            cbOnFailure(response);
                        }
                    }

                },
            onError: (xhr) => {
                const response = JSON.parse(xhr.response);
                console.error("error: " + response.message);
                Joomla.renderMessages( {"error": [response.message]} );
                if (cbOnFailure !== null) {
                    cbOnFailure(response);
                }
            }

            });
    }