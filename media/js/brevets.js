/**
 * Gestion des lignes de saisie des brevets (ajout, suppression, remplissage).
 *
 * Les lignes sont clonées depuis le <template id="brevet-template"> rendu par
 * layouts/brevets/row_template.php, partagé par la vue Adhésion et la modale d'édition des
 * brevets de la vue Profil.
 *
 * Init explicite (Brevets.init) plutôt qu'un câblage sur DOMContentLoaded : la vue Profil rend
 * les mêmes éléments dans une modale et a besoin de vider/repeupler le conteneur à chaque
 * ouverture. Les helpers historiques window.addBrevet / window.clearBrevets restent exposés,
 * ils sont utilisés par le callback de scan de la vue Adhésion (scrap-ffessm.js).
 */
const Brevets = (function () {

  let container = null;
  let template = null;

  /**
   * @param {Object} [options]
   * @param {string} [options.containerId='brevets-container'] Conteneur des lignes
   * @param {string} [options.addBtnId='add-brevet-btn']       Bouton "Ajouter un brevet"
   * @param {string} [options.templateId='brevet-template']    <template> de la ligne
   */
  function init(options = {}) {
    const containerId = options.containerId || 'brevets-container';
    const addBtnId = options.addBtnId || 'add-brevet-btn';
    const templateId = options.templateId || 'brevet-template';

    const containerEl = document.getElementById(containerId);
    const templateEl = document.getElementById(templateId);
    const addBtn = document.getElementById(addBtnId);

    // Vue qui ne rend pas de formulaire de brevets : ne rien câbler plutôt que de planter.
    if (!containerEl || !templateEl) { return false; }

    container = containerEl;
    template = templateEl.content;

    if (addBtn) {
      addBtn.addEventListener('click', () => addBrevet());
    }

    // Supprimer un brevet avec animation
    container.addEventListener('click', (e) => {
      const btn = e.target.closest('.remove-brevet-btn');
      if (btn) {
        const item = btn.closest('.brevet-item');
        item.classList.add('removing');
        setTimeout(() => item.remove(), 250);
      }
    });

    return true;
  }

  /* Vider tous les brevets */
  function clearBrevets() {
    if (!container) { return; }
    container.querySelectorAll('.brevet-item').forEach((item) => item.remove());
  }

  /* Ajouter un brevet */
  function addBrevet(brevet = {}) {
    if (!container || !template) { return; }

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

  /* Nombre de lignes actuellement saisies */
  function count() {
    return container ? container.querySelectorAll('.brevet-item').length : 0;
  }

  return { init, addBrevet, clearBrevets, count };
})();

// Helpers globaux historiques, utilisés par AdhesionCB (scrap-ffessm.js)
window.addBrevet = (brevet) => Brevets.addBrevet(brevet);
window.clearBrevets = () => Brevets.clearBrevets();

// Câblage automatique sur les IDs par défaut : le conteneur, le template et le bouton "Ajouter"
// sont présents dès le chargement dans les deux vues (formulaire pour Adhésion, corps de modale
// rendu côté serveur pour Profil). init() ne fait rien si la vue ne les rend pas.
//
// Les brevets déjà enregistrés ne sont préchargés ici que pour la vue Adhésion, via
// Joomla.getOptions() ; la vue Profil vide et repeuple le conteneur à chaque ouverture de la
// modale, à partir du profil ciblé (profil_brevets.js).
document.addEventListener('DOMContentLoaded', () => {
  if (!Brevets.init()) { return; }

  const brevetOptions = Joomla.getOptions('com_gdadhesions.brevets');

  if (!brevetOptions) { return; }

  // Parser si c'est une chaîne JSON
  const brevetArray = typeof brevetOptions === 'string' ? JSON.parse(brevetOptions) : brevetOptions;

  if (Array.isArray(brevetArray)) {
    brevetArray.forEach((brevet) => Brevets.addBrevet(brevet));
  }
});
