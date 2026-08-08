

document.addEventListener('DOMContentLoaded', () => {

    const addBtn = document.getElementById('add-brevet-btn');
    const container = document.getElementById('brevets-container');
    const template = document.getElementById('brevet-template').content;

    // Ajouter un brevet
    addBtn.addEventListener('click', () => {  addBrevet ()});

    // Supprimer un brevet avec animation
    container.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-brevet-btn');
        if (btn) {
            const item = btn.closest('.brevet-item');
            item.classList.add('removing');
            setTimeout(() => item.remove(), 250);
        }
    });

    /* Vider tous les brevets */
    window.clearBrevets = function () {
        // Supprimer tous les brevets existants du conteneur
        container.querySelectorAll('.brevet-item').forEach(item => {
            item.remove();
        });
    }
    /* Ajouter un brevet */
    window.addBrevet = function (brevet = {}) {
        // On clone le contenu du template
        const clone = document.importNode(template, true);
        const newItem = clone.querySelector('.brevet-item');
        
        // Calculer l'index du nouveau brevet (nombre d'éléments existants)
        const index = container.querySelectorAll('.brevet-item').length;
        
        // Remplir les champs si des données sont fournies avec les noms indexés
        newItem.querySelector('input[name="brevets[][nom]"]').name = `brevets[${index}][nom]`;
        newItem.querySelector('input[name="brevets[][obtention]"]').name = `brevets[${index}][obtention]`;
        newItem.querySelector('input[name="brevets[][lieu]"]').name = `brevets[${index}][lieu]`;
        
        newItem.querySelector(`input[name="brevets[${index}][nom]"]`).value = brevet.nom || '';
        newItem.querySelector(`input[name="brevets[${index}][obtention]"]`).value = brevet.obtention || '';
        newItem.querySelector(`input[name="brevets[${index}][lieu]"]`).value = brevet.lieu || '';

        container.appendChild(clone);

   

        // Forcer une reflow avant d’activer la classe d’animation
        requestAnimationFrame(() => {
            newItem.classList.add('show');
        });
    }
    // Charger les brevets existants au démarrage de la page    
    const brevetOptions = Joomla.getOptions('com_gdadhesions.brevets');
    // console.log(brevetOptions)
    if (brevetOptions) {
        // Parser si c'est une chaîne JSON
        let brevetArray = typeof brevetOptions === 'string' ? JSON.parse(brevetOptions) : brevetOptions;
        
        if (Array.isArray(brevetArray) && brevetArray.length > 0) {
            brevetArray.forEach(brevet => {
                // console.log('Ajout du brevet:', brevet);
                addBrevet(brevet);
            });
        }
    }
});