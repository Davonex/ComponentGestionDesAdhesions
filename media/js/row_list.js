/**
 * Factory générique de liste de lignes ajoutables/supprimables (clone de <template>, réindexation
 * à l'ajout, suppression déléguée). Même mécanique que Brevets (brevets.js), généralisée : deux
 * nouveaux consommateurs quasi-identiques arrivent dans le même chantier (rôles+capacité du
 * formulaire de campagne, rôle+quantité du formulaire de réservation), d'où la factorisation ;
 * Brevets.js lui-même n'est pas touché (domaine stable, aucun bénéfice à le refactorer ici).
 *
 * Contrairement à Brevets (module singleton, un seul conteneur actif à la fois), RowList.init()
 * retourne une instance indépendante par appel : plusieurs listes peuvent coexister sur une même
 * page si besoin.
 *
 * Les champs de chaque ligne sont repérés par attribut data-field (et non par un nom de champ
 * "placeholder" comme brevets[][x]) : chaque champ cloné est renommé namePrefix[index][field] à
 * l'ajout, index = position de la ligne dans la liste.
 */
const RowList = (function () {

  /**
   * @param {Object} options
   * @param {string} options.containerId Conteneur des lignes.
   * @param {string} options.templateId  <template> de la ligne (contenant un unique élément racine
   *                                     portant la classe options.itemClass).
   * @param {string} options.itemClass   Classe CSS de l'élément racine d'une ligne.
   * @param {string} options.namePrefix  Préfixe des champs soumis (ex: 'role_places').
   * @param {string[]} options.fields    Noms des champs à renommer, doivent correspondre aux
   *                                     attributs data-field="..." du template (ex: ['role', 'nbr_place']).
   * @param {string} [options.addBtnId]  Bouton "Ajouter une ligne" (optionnel, l'appelant peut
   *                                     aussi déclencher addRow() lui-même).
   * @returns {Object|null} { addRow, clear, count, container } ou null si le conteneur/template
   *                        n'est pas présent sur la page (vue qui ne rend pas cette liste).
   */
  function init(options) {
    const containerEl = document.getElementById(options.containerId);
    const templateEl = document.getElementById(options.templateId);
    const addBtn = options.addBtnId ? document.getElementById(options.addBtnId) : null;

    if (!containerEl || !templateEl) {
      return null;
    }

    const template = templateEl.content;
    const itemClass = options.itemClass;
    const namePrefix = options.namePrefix;
    const fields = options.fields;

    function addRow(values = {}) {
      const clone = document.importNode(template, true);
      const newItem = clone.querySelector('.' + itemClass);
      const index = containerEl.querySelectorAll('.' + itemClass).length;

      fields.forEach(function (field) {
        const input = newItem.querySelector('[data-field="' + field + '"]');

        if (!input) {
          return;
        }

        input.name = namePrefix + '[' + index + '][' + field + ']';

        if (values[field] !== undefined) {
          input.value = values[field];
        }
      });

      containerEl.appendChild(clone);

      return newItem;
    }

    function clear() {
      containerEl.querySelectorAll('.' + itemClass).forEach(function (item) {
        item.remove();
      });
    }

    function count() {
      return containerEl.querySelectorAll('.' + itemClass).length;
    }

    if (addBtn) {
      addBtn.addEventListener('click', function () {
        addRow();
      });
    }

    // Suppression déléguée sur le conteneur : fonctionne aussi pour les lignes ajoutées après init().
    containerEl.addEventListener('click', function (event) {
      const btn = event.target.closest('.js-row-list-remove');

      if (!btn) {
        return;
      }

      const item = btn.closest('.' + itemClass);

      if (item) {
        item.remove();
      }
    });

    return { addRow, clear, count, container: containerEl };
  }

  return { init };
})();
