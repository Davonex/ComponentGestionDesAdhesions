# Personnalisation de la fiche Profil (`layouts/profil/card_profil.php`)

## Vue d'ensemble

Un seul layout affiche la carte "Nom Prénom / photo / coordonnées" dans **deux contextes** :

| Contexte | Où | Mode | Champs affichés |
|---|---|---|---|
| Vue **Profil** (fiche de l'adhérent connecté) | `tmpl/profil/default.php` | éditable (bouton "Modifier") | `ProfilModel::CARD_FIELDS_FULL` (toujours complet) |
| Popup **"fiche adhérent"** (clic sur Nom Prénom) | Vues **Groupes** et **Secrétariat** | lecture seule | `CARD_FIELDS_FULL` ou `CARD_FIELDS_LIGHT` selon le rôle (voir plus bas) |

Fichiers impliqués :
- `components/com_gdadhesions/layouts/profil/card_profil.php` — le layout (rendu HTML).
- `components/com_gdadhesions/src/Model/ProfilModel.php` — définit la **liste des champs visibles** via deux constantes.
- `components/com_gdadhesions/src/Controller/ProfilController.php` (`showCard()`) — endpoint ajax de la popup, décide **côté serveur** quelle constante utiliser.
- `components/com_gdadhesions/src/Helper/UsersHelper.php` — décide qui a droit à la fiche complète.

## 1. Ajouter / retirer un champ existant dans la fiche allégée

C'est le cas le plus courant (retours des beta-testeurs). Un seul endroit à modifier : les constantes en tête de `ProfilModel.php` (ligne ~24) :

```php
const CARD_FIELDS_FULL  = ['photo', 'coordonnees', 'telephone', 'email', 'urgence'];
const CARD_FIELDS_LIGHT = ['photo'];
```

Chaque clé correspond à un bloc du layout (voir tableau ci-dessous). Pour ajouter, par exemple, le téléphone à la fiche allégée :

```php
const CARD_FIELDS_LIGHT = ['photo', 'telephone'];
```

Rien d'autre à toucher : le layout et le contrôleur lisent déjà cette liste dynamiquement.

### Clés reconnues par le layout

| Clé | Bloc affiché | Donnée(s) `profil->...` |
|---|---|---|
| `photo` | Photo de profil | `photo` |
| `coordonnees` | Adresse postale | `adresse`, `code_postal`, `ville` |
| `telephone` | Téléphone | `telephone` |
| `email` | Email | `email` (compte utilisateur Joomla) |
| `urgence` | Personne à prévenir | `a_prevenir`, `a_prevenir_tel` |

L'en-tête (civilité, nom, prénom, licence) est **toujours affiché** quel que soit `$fields` : ce n'est pas une donnée sensible et elle est nécessaire pour identifier l'adhérent.

## 2. Ajouter un nouveau champ (pas encore géré par le layout)

Si le champ voulu n'existe pas encore comme bloc (ex. : date de naissance visible, nombre de plongées, statut licence...), il faut trois étapes :

1. **Vérifier que la donnée est bien chargée** par `ProfilModel::getSelectItemFields()` (`src/Model/ProfilModel.php`, ~ligne 476). La plupart des colonnes de `j8hu1_gda_profils` y sont déjà (`statut`, `date_licence`, `nbr_plongee`, `ffessm_token`, etc.) ; si ce n'est pas le cas, ajouter la colonne au `SELECT`.
2. **Ajouter la clé** dans `layouts/profil/card_profil.php` :
   ```php
   $showNbrPlongee = in_array('nbr_plongee', $fields, true);
   ```
   puis le bloc HTML correspondant (suivre le même modèle que `telephone`/`email`, avec `$this->escape(... ?? '')`).
3. **Référencer la nouvelle clé** dans `CARD_FIELDS_FULL` et/ou `CARD_FIELDS_LIGHT` selon qui doit la voir.

## 3. Qui voit la fiche complète vs allégée ?

La décision est prise **côté serveur uniquement**, dans `ProfilController::showCard()` — jamais via un paramètre envoyé par le client (pour empêcher un Moniteur de demander la fiche complète en modifiant la requête ajax) :

```php
$fields = UsersHelper::isBureauMember() ? ProfilModel::CARD_FIELDS_FULL : ProfilModel::CARD_FIELDS_LIGHT;
```

- **Membre du Bureau** (`UsersHelper::isBureauMember()`, niveau d'accès Joomla `NA Bureau`) → fiche complète.
- **Moniteur** ou **Responsable de Groupe** (mais pas Bureau) → fiche allégée.
- Tout autre utilisateur → accès refusé (`UsersHelper::canViewMemberDetails()` retourne `false`, exception 403).

Pour changer les rôles autorisés à *voir* la popup (indépendamment du niveau de détail), modifier `UsersHelper::canViewMemberDetails()`. Pour changer qui a droit à la fiche *complète* plutôt qu'allégée, modifier la condition dans `ProfilController::showCard()`.

## 4. Ajouter la popup "fiche adhérent" à une nouvelle vue

Le déclencheur est générique (`.js-show-profil-card` dans `media/com_gdadhesions/js/form_modal.js`, délégué au `document`). Pour l'activer sur une nouvelle vue :

1. Charger le script : `$wa->useScript('com_gdadhesions.form_modal');` dans le `default.php` de la vue.
2. Ajouter le bloc modal (une seule fois par vue) :
   ```html
   <div class="modal fade" id="profilCardModal" tabindex="-1" aria-hidden="true">
     <div class="modal-dialog modal-dialog-centered">
       <div class="modal-content" id="profilCardModalContent">
         <!-- Le contenu de la modal est chargé dynamiquement via ajax -->
       </div>
     </div>
   </div>
   ```
3. Rendre le Nom Prénom cliquable dans le layout listant les adhérents :
   ```php
   <a href="#" class="js-show-profil-card" data-id-profil="<?= (int) $item->id_profil ?>"><?= $this->escape($nomComplet) ?></a>
   ```

Aucune autre modification JS/PHP n'est nécessaire : le handler ajax (`task=profil.showCard`), le contrôle d'accès, le choix full/light **et la croix de fermeture** sont déjà génériques et communs à toutes les vues. La croix vit directement dans `card_profil.php` (`card-header`) et s'affiche automatiquement dès que `editable=false` — elle n'a donc pas à être ajoutée dans le markup de la modale de chaque vue.

## Exemple récapitulatif

Ajouter le champ "email" à la fiche allégée, visible uniquement par les Responsables de Groupe (pas les Moniteurs) :

1. `CARD_FIELDS_LIGHT` reste `['photo']` (l'email doit rester réservé).
2. On ne peut pas distinguer Moniteur / Responsable de Groupe avec les seules constantes actuelles (elles ne connaissent que "full vs light"). Il faudrait alors :
   - ajouter une troisième constante, ex. `CARD_FIELDS_RESPONSABLE = ['photo', 'email']` ;
   - ajouter `UsersHelper::isResponsableGroupe(): bool` (même modèle que `isBureauMember()`) ;
   - étendre la logique de `ProfilController::showCard()` avec une condition supplémentaire.

Ce cas illustre la limite volontaire du design actuel (2 niveaux : full/light) — à étendre uniquement si un besoin réel apparaît, pour ne pas complexifier prématurément.
