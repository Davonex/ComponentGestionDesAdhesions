const scrap = function (Url,cbPostRequest = null)  {

    Joomla.request({
      method: 'POST',
      url: 'index.php?option=com_gdadhesions&task=adhesion.extract&format=json',
      data: new URLSearchParams({ url: Url }).toString(),
      promise: false,
      onBefore(xhr) {
          xhr.upload.addEventListener('progress', (event) => {
              console.log('Progres', event.loaded, event.total);
          });
      },
      onSuccess: (data) => {
            const response = JSON.parse(data);
            if (response.success) {
                // console.log("Données récupérées :", response);

                if (cbPostRequest !== null) {
                    cbPostRequest (response.data);
                }
                Joomla.renderMessages( {"succes": [response.message]} );


            } else {
                console.error("Erreur dans la réponse :", response.message);
                Joomla.renderMessages( {"error": [response.message]} );
            }
        
      },
      onError(xhr) {
        console.error("Erreur fetch data :", xhr.responseText);
      }
    })
}


//** function call back  pour ajouter les informations & brevets de la FFESSM  */
const AdhesionCB = function (data)  {

    // CurrentLicence = document.getElementById("jform_username");

    // NewLicence = 

    let className = "field-updated";

    if (data.informations.nom ) {
        let elNom = document.querySelector('input#jform_nom')
        elNom.value = data.informations.nom
        highlightField (elNom,className)
    }
     if (data.informations.prenom ) {
        let elPrenom = document.querySelector('input#jform_prenom')
        elPrenom.value = data.informations.prenom
         highlightField (elPrenom,className)}
    if (data.informations.civilite ) {
        let elCiv = document.querySelector('select#jform_civilite')
        elCiv.value = data.informations.civilite
        highlightField (elCiv,className)}

    if (data.informations.licence ) {
        let elLic = document.querySelector('input#jform_username')
        elLic.value = data.informations.licence
        highlightField (elLic,className)}

        if (data.informations.validite ) {
        let elVal = document.querySelector('input#jform_date_licence')
        elVal.value = data.informations.validite
        highlightField (elVal,className)}

    if (data.informations.token ) {
        let elVal = document.querySelector('input#jform_ffessm_token')
        elVal.value = data.informations.token
        }

    // Vider les brevets existants avant d'en ajouter de nouveaux
    clearBrevets();
    
    // ajoute les brevets
    data.brevets.forEach(brevet => {
        /**
         * @external {Function} addBrevet - Voir brevets.js
         */
        addBrevet (brevet);
    });

}


const  highlightField = function (el,className) {
   // const el = document.querySelector(selector);
    if (!el) return;
    el.classList.add(className);
    setTimeout(() => {
        el.classList.remove(className);
    }, 2500);
}