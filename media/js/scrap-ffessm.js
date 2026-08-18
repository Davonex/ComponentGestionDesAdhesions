/**
 * Interroge la FFESSM à partir de l'URL du QR code de la carte licence.
 *
 * Transport uniquement : la présentation (popup, bandeau, ...) est laissée à l'appelant via les
 * deux callbacks, car elle diffère d'une vue à l'autre.
 *
 * @param {string}   Url        URL encodée dans le QR code
 * @param {Function} cbSuccess  Reçoit la réponse complète (data.informations, data.brevets, data.porteur)
 * @param {Function} cbError    Reçoit la réponse en échec logique, ou null sur erreur réseau
 */
const scrap = function (Url, cbSuccess = null, cbError = null)  {

    // La clé de réédition permet au serveur d'identifier le dossier en cours quand l'adhérent
    // n'est pas connecté (reprise via le lien reçu par mail). Elle est revalidée côté serveur
    // contre la session, sa présence ici ne contourne donc aucun contrôle.
    const payload = { url: Url };
    const key = document.querySelector('#jform_key')?.value;
    if (key) { payload.key = key; }

    Joomla.request({
      method: 'POST',
      url: 'index.php?option=com_gdadhesions&task=adhesion.extract&format=json',
      data: new URLSearchParams(payload).toString(),
      promise: false,
      onSuccess: (data) => {
            const response = JSON.parse(data);
            if (response.success) {
                if (cbSuccess !== null) {
                    cbSuccess (response);
                }
            } else {
                console.error("Erreur dans la réponse :", response.message);
                if (cbError !== null) {
                    cbError (response);
                } else {
                    Joomla.renderMessages( {"error": [response.message]} );
                }
            }

      },
      onError(xhr) {
        console.error("Erreur fetch data :", xhr.responseText);
        if (cbError !== null) { cbError (null); }
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