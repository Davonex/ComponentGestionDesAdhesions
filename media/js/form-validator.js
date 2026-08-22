
const ServerSideValidation = function (elementId, task) {

    const elementInput = document.getElementById(elementId);
    
    elementInput.classList.add("server-side-validation");
    // const form = document.getElementById("adminForm"); // ou l'ID de ton form

    // let emailValid = false;

    elementInput.addEventListener("blur", function () {

        ajaxRequest(elementInput, task);
       
    });

    const observer = new MutationObserver(() => {
    if (elementInput.matches(":-webkit-autofill")) {
        console.log(elementInput.name + " Autofill détecté !");
        elementInput.dispatchEvent(new Event("change", { bubbles: true }));
    }
    });

    observer.observe(elementInput, { attributes: true, attributeFilter: ["class", "style"] });




    // form.addEventListener("submit", function (e) {
    //     if (!emailValid) {
    //         e.preventDefault();
    //         alert("Veuillez saisir une adresse email valide et unique !");
    //     }
    // });
}




/**
 * Effectue une requête AJAX pour valider un élément de formulaire côté serveur.
 * le mail ou le username
 * 
 * @param {*} elementInput 
 * @param {*} task 
 * @returns 
 */

const ajaxRequest = function (elementInput, task) {

     const elementValue = elementInput.value.trim();

        if ( elementInput.matches(':invalid') ) {
            // L'élément est invalide
                Joomla.renderMessages({ "error": ["Champ invalide ou incomplet!"] });
                elementInput.classList.remove("is-valid");
                elementInput.classList.add("is-invalid");
            return;
        } else {
             Joomla.renderMessages({}); // Efface les messages
            elementInput.classList.remove("is-invalid");
            elementInput.classList.add("is-valid");

        }

        if (!elementValue) return;

            const basePath = Joomla.getOptions('system.paths')?.baseFull || '';
            Joomla.request({
                url: `${basePath}index.php?option=com_gdadhesions&task=form.` + task + "&format=json",
                method: "POST",
                data: new URLSearchParams({ elementValue }).toString(),
                perform: true,
                onSuccess: (data) => {
                    const response = JSON.parse(data);
                    if (!response.success) {
                        // Email/licence déjà connu(e) : message bloquant, plus visible en popup
                        // qu'au bandeau Joomla (peu visible sur mobile). Rendu côté serveur par
                        // adhesion.alert, cf. showAdhesionAlert() dans adhesions.js.
                        if (response.data) {
                            showAdhesionAlert(response.data);
                        } else {
                            Joomla.renderMessages({ "error": [response.message] });
                        }
                        elementInput.classList.remove("is-valid");
                        elementInput.classList.add("is-invalid");
                    } else {
                        Joomla.renderMessages({});
                        elementInput.classList.remove("is-invalid");
                        elementInput.classList.add("is-valid");
                        // Joomla.renderMessages({ "succes": ["Email valide"] });
                    }
                },
                onError: () => {
                    console.error("Erreur checkEmail !");
                }
            });

}


const ClientSideValidation = function (container) {

    const step = document.getElementById(container);

    step.querySelectorAll('input.required:not([type="hidden"])').forEach(el => {

        el.addEventListener("blur", function () {
            if (el.classList.contains("server-side-validation")) {
            // Si la validation serveur a déjà marqué le champ comme invalide, on ne change rien
            return
            } else if (el.matches(':invalid')) {
            // L'élément est invalide
                (el.classList.contains("is-valid")) ? el.classList.remove("is-valid") : null;
                el.classList.add("is-invalid"); 
                
            } else  {
                (el.classList.contains("is-invalid")) ? el.classList.remove("is-invalid") : null;
                el.classList.add("is-valid");
            }
        });
    }); 


}