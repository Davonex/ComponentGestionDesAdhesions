# Domaine Campagnes (`com_gdadhesions`)

> Couvre la gestion des campagnes (Saison, Formation, Loisir, Boutique), les réservations hors saison et la capacité par rôle. Refonte 0.9.10 (rôles librement éditables, réservations mixtes), puis chantier « Campagnes 1.0 » (0.9.18, mis à jour le 2026-09-20) : **validation manuelle des inscriptions** (plus de file d'attente ni de confirmation automatique), statuts `attente`/`confirmee`/`refusee`/`annulee`, verrou des inscriptions refusées/annulées, sous-type de formation, onglet Récapitulatif, nature Boutique.

## 1. Vue d'ensemble

Une **campagne** (`#__gda_campagnes`) est un événement ou une période gérée par le club. Sa **nature** (`id_type` → `#__gda_type_de_campagne`) détermine son comportement :

| Nature | Mécanisme d'inscription | Table |
|---|---|---|
| **Saison** | Souscription annuelle, workflow CACI/cotisation/licence du secrétariat | `#__gda_souscriptions` |
| **Formation / Loisir** | Réservation par **rôle**, **validée à la main** par le responsable (capacité indicative, pas de file d'attente) | `#__gda_reservation` / `#__gda_reservation_places` |
| **Boutique** | Vitrine : articles d'un formulaire HelloAsso Shop, aucune réservation | — |

`#__gda_souscriptions` et `#__gda_reservation` sont deux mécanismes **distincts et non interchangeables** (voir cartographie §4) : tout ce qui suit dans ce document ne concerne jamais les campagnes de type Saison.

Depuis 0.9.10, une campagne hors saison propose **toujours** un ou plusieurs rôles par place (Formation : Pratiquant/Encadrant par défaut ; Loisir : Plongeur/Non plongeur par défaut) — il n'existe plus de mode "sans rôle". Les rôles proposés par défaut viennent du gabarit `#__gda_role_de_campagne` (par nature), mais le Bureau peut librement **ajouter, renommer ou supprimer** une ligne rôle+capacité pour une campagne donnée depuis le formulaire d'édition (répercuté dans `#__gda_campagne_roles`). La capacité (nombre de places) est renseignée **séparément pour chaque rôle** mais reste **indicative** : elle est affichée (badges du tableau de gestion, « il reste N » sur le dashboard) sans jamais bloquer une inscription.

Différence restante entre les deux natures : **Formation** reste limitée à 1 place par adhérent (quel que soit le rôle choisi). **Loisir** autorise, si `reservation_multiple = 1` (champ explicite, modifiable par le Bureau), la réservation de plusieurs places **potentiellement réparties sur plusieurs rôles différents** en une seule réservation (ex : 2 Plongeur + 1 Non-Plongeur en un seul geste pour un groupe).

## 2. Fichiers impliqués

| Rôle | Fichier |
|---|---|
| Vue (gestion, Bureau) | `src/View/Campagnes/HtmlView.php` → `tmpl/campagnes/default.php` |
| Contrôleur (gestion) | `src/Controller/CampagnesController.php` |
| Contrôleur (réservation adhérent) | `src/Controller/ReservationController.php` |
| Modèle (gestion) | `src/Model/CampagnesModel.php` |
| Modèle (dashboard adhérent) | `src/Model/AccueilModel.php` |
| Service (règles de réservation) | `src/Service/ReservationService.php` |
| Formulaire d'édition | `models/forms/campagnes.xml` (plus de champ `role_actif`) |
| Layouts (gestion) | `layouts/campagnes/{table,row,rapport,role_row_template,suivi_inscrits,recapitulatif}.php`, `layouts/groupes/detail.php` (tableau du Suivi) |
| Layout (popup réservation adhérent) | `layouts/reservation/{form,role_row_template,article}.php` |
| Layouts (dashboard) | `layouts/accueil/{dash_campagnes_reservables,dash_campagne_reservable_ligne,dash_reservation_modals}.php` (encart rendu une fois par nature : Formation, Loisir) |
| JS (gestion) | `media/com_gdadhesions/js/campagne.js` |
| JS (réservation adhérent) | `media/com_gdadhesions/js/reservation.js` |
| JS (ajout/suppression de lignes rôle+capacité, réutilisable) | `media/com_gdadhesions/js/row_list.js` (factory `RowList`) |

## 3. Schéma des tables SQL

```mermaid
erDiagram
    TYPE_DE_CAMPAGNE ||--o{ CAMPAGNES : "nature de"
    TYPE_DE_CAMPAGNE ||--o{ ROLE_DE_CAMPAGNE : "gabarit de rôles par défaut pour"
    CAMPAGNES ||--o{ CAMPAGNE_ROLES : "capacité par rôle (toujours)"
    CAMPAGNES ||--o{ RESERVATION : "réservations (hors Saison)"
    CAMPAGNES ||--o{ SOUSCRIPTIONS : "souscriptions (Saison)"
    CAMPAGNES ||--o{ COMPOSITION_GROUPES : "groupes composés pour"
    RESERVATION ||--o{ RESERVATION_PLACES : "1 réservation peut porter plusieurs rôles"
    PROFILS ||--o{ RESERVATION : "réserve"
    PROFILS ||--o{ SOUSCRIPTIONS : "souscrit"
    PROFILS ||--o{ COMPOSITION_GROUPES : "appartient à"
    GROUPES ||--o{ COMPOSITION_GROUPES : "regroupe"

    TYPE_DE_CAMPAGNE {
        int id_type PK
        varchar type_name "Saison, Formation, Loisir"
        varchar type_class
    }

    ROLE_DE_CAMPAGNE {
        int id_type PK_FK
        varchar roles "Gabarit par défaut, séparés par ; (ex Pratiquant;Encadrant)"
    }

    CAMPAGNES {
        int id_campagne PK
        varchar titre
        date date_debut
        date date_fin
        datetime date_evenement
        tinyint active "Inscription ouverte"
        tinyint courante "Saison de suivi (Saison uniquement)"
        int id_type FK
        varchar sous_type "Formation : fosse_apnee, fosse_technique_20, fosse_technique_12, rifax"
        int id_responsable FK "Compte prévenu par mail des demandes"
        int nbr_place "Vestigial depuis 0.9.10, plus utilisée"
        tinyint reservation_multiple "Champ explicite : autorise plusieurs places/rôles en une réservation (Loisir)"
        varchar id_groupes
        int id_article
    }

    CAMPAGNE_ROLES {
        int id_campagne PK_FK
        varchar role PK "Texte libre : gabarit par défaut, ou renommé/ajouté par le Bureau"
        int nbr_place "Capacité de CE rôle pour CETTE campagne"
    }

    RESERVATION {
        int id_reservation PK
        int id_campagne FK
        int id_profil FK
        tinyint annulee "Enveloppe annulée ('Me désinscrire')"
        datetime date_reservation "Informative : 1ère demande"
        varchar id_order "Commande HelloAsso"
    }

    RESERVATION_PLACES {
        int id_place PK
        int id_reservation FK
        int id_campagne "Dénormalisé : évite une jointure sur les requêtes d'occupation/rang"
        varchar role "Rôle de cette place"
        varchar statut "attente | confirmee | refusee | annulee"
        datetime date_rang "Date de la demande (ordre d'arrivée)"
        int tri
    }

    SOUSCRIPTIONS {
        int id_campagne PK_FK
        int id_profil PK_FK
        varchar cotisation_code
        tinyint caci_check
        tinyint cotisation_check
        tinyint licence_check
    }

    COMPOSITION_GROUPES {
        int id_groupe FK
        int id_campagne FK
        int id_profil FK
    }

    GROUPES {
        int id_groupe PK
        varchar groupe_name
        varchar activite
    }

    PROFILS {
        int id_profil PK
        varchar nom
        varchar prenom
    }
```

`CAMPAGNE_ROLES` ne duplique pas `nbr_place` : la capacité totale affichée est calculée à la volée comme la somme de ses lignes (`ReservationService::getCapaciteTotale()`/`getSelectCapaciteTotale()`), `gda_campagnes.nbr_place` restant sans effet (vestigial, conservé pour ne pas casser une éventuelle lecture existante — suppression envisageable en chantier séparé).

`RESERVATION_PLACES` est désormais l'**unité atomique** de statut (avant 0.9.10, ces informations vivaient sur `RESERVATION` elle-même, qui ne pouvait porter qu'un seul rôle/statut). Ce changement était nécessaire pour permettre à une réservation Loisir de mélanger plusieurs rôles avec des statuts indépendants (une place Plongeur confirmée, une place Non-Plongeur en attente, dans la même réservation).

## 4. Méthodes par classe

### `CampagnesModel` (`src/Model/CampagnesModel.php`)

| Méthode | Rôle |
|---|---|
| `getCampagne(int $id_campagne)` | Une campagne, enrichie de `places_occupees`, `capacite_totale` et `role_places` (préremplissage du formulaire d'édition, tableau **indexé** `[{role, nbr_place}, ...]`). |
| `getCampagnes()` | Liste des campagnes hors Saison, mêmes enrichissements que `getCampagne()`, en une seule requête groupée (pas de N+1). |
| `getTypes()` | Natures disponibles (hors Saison) : Formation, Loisir. |
| `getRolesDeCampagne()` | Gabarit de rôles par défaut par nature (`#__gda_role_de_campagne`), non administrable (édition SQL directe) — sert uniquement à préremplir une **nouvelle** campagne. |
| `getRolesCapacite(int[] $idsCampagne)` | Capacité par rôle **réellement configurée** (`#__gda_campagne_roles`) pour une ou plusieurs campagnes : `id_campagne => [role => nbr_place]`. |
| `saveRolePlaces(int $idCampagne, array $rolePlaces)` *(privée)* | Remplace la répartition par rôle d'une campagne (stratégie table rase, appelée **inconditionnellement** par `Sauver()`). Contrat : tableau indexé `[{role, nbr_place}, ...]` — pas associatif, un rôle est du texte libre renommable. Une ligne `nbr_place = 0` reste persistée (visible/modifiable) ; seules les lignes `role === ''` sont ignorées. |
| `getInscritsCampagne(int $id_campagne, string $titre)` | Inscrits, **une ligne par place** (un adhérent avec 2 rôles apparaît en 2 lignes), **places annulées comprises et classées en dernier**, avec `statut` et `commentaire`, sous la forme d'un groupe `GroupesModel`, pour l'onglet Suivi (réutilise `layouts/groupes/detail.php`). |
| `getRecapitulatifFormations()` | Onglet Récapitulatif : campagnes Formation (avec `sous_type`) + adhérents ayant au moins une place, `statuts[id_campagne]` = statut de la place la plus récente. |
| `changerStatutInscription(idPlace, statut)` | Façade sur `ReservationService::changerStatutPlace()` ; retourne le statut précédent (le contrôleur envoie le mail d'acceptation sur la transition vers `confirmee`). |
| `Activer()` / `Sauver()` / `Effacer()` | CRUD campagne (`Effacer()` = effacement logique). `Sauver()` calcule `reservation_multiple` (forcé à 0 pour Formation), valide `sous_type` (liste `SOUS_TYPES_FORMATION`, Formation uniquement) et `id_responsable`, et persiste `role_places` via `saveRolePlaces()`. |
| `getRapport()` / `getRapportHelloAsso()` / `getRapportHelloAssoBoutique()` | Rapports (popup) : réservations (une ligne par place, statut, commentaire), paiements HelloAsso Event, achats HelloAsso Shop. |

### `AccueilModel` (`src/Model/AccueilModel.php`)

| Méthode | Rôle |
|---|---|
| `getCampagnesReservables($user)` *(renommée depuis `getFormations()`)* | Campagnes Formation **et** Loisir ouvertes pour le dashboard adhérent, enrichies de `places_occupees`, `capacite_totale`, et de l'état de réservation de l'adhérent connecté — `mes_places` : une entrée `{role, statut}` par place, **places annulées comprises** (l'adhérent voit « Annulée »). |

### `ReservationController` / `CampagnesController`

| Méthode | Rôle |
|---|---|
| `ReservationController::reserver()` | Reconstruit `$demandes` (`{role, quantite}[]`) depuis `role_places[]`. Garde-fous serveur : Formation → tronqué à 1 ligne, quantité forcée à 1 ; Loisir sans `reservation_multiple` → total des quantités ≤ 1 (exception sinon). Résout la capacité par rôle (`CampagnesModel::getRolesCapacite()`) et délègue à `ReservationService::reserver()`. |
| `ReservationController::annuler()` | Délègue à `ReservationService::annuler()` (voir §5.2) ; prévient le responsable par e-mail. |
| `ReservationController::getFormulaire()` | Contenu du popup de réservation : rôles configurés, places restantes **par rôle** (avertissement « complet » par rôle), préremplissage multi-lignes. Refusé (403) si la réservation est verrouillée. |
| `CampagnesController::sauver()` | Lit `jform_campagne[...]` (dont `role_places[]`, tableau indexé généré dynamiquement par `campagne.js`/`RowList`), délègue à `CampagnesModel::Sauver()`. |
| `CampagnesController::activer()` / `effacer()` / `rapport()` / `suivi()` / `recapitulatif()` / `changerStatutInscription()` | CRUD/consultation, réservés Bureau ou Responsable de Groupe. `suivi()` couvre Formation et Loisir ; `recapitulatif()` alimente le 3ᵉ onglet. |

### `ReservationService` (`src/Service/ReservationService.php`)

| Méthode | Rôle |
|---|---|
| `getReservation(idCampagne, idProfil, avecAnnulees = false)` | Réservation d'un adhérent (enveloppe + `->places[]`, chaque place avec `role`/`statut`/`date_rang`) ; les places annulées ne sont incluses que sur demande (dashboard). |
| `getPlacesOccupeesParRole(idCampagne, role)` / `getPlacesDisponiblesParRole(idCampagne, role, capaciteRole)` | Capacité **par rôle** : chaque rôle a sa propre occupation, lue via `#__gda_reservation_places`. |
| `getPlacesOccupeesTotal(idCampagne)` / `getPlacesDisponiblesTotal(idCampagne, capaciteTotale)` | Équivalents **tous rôles confondus**, pour l'affichage global (colonne "places occupées" de l'onglet Gestion, dashboard adhérent). |
| `getSelectPlacesOccupeesTotal(db, alias)` *(statique)* | Fragment SQL (sous-requête corrélée) de la ligne ci-dessus, pour une **liste** de campagnes sans N+1 (`CampagnesModel::getCampagnes()`, `AccueilModel::getCampagnesReservables()`). |
| `getCapaciteTotale(campagne)` | Capacité totale d'une campagne déjà chargée : somme de `#__gda_campagne_roles`. |
| `getSelectCapaciteTotale(db, alias)` *(statique)* | Même règle, en fragment SQL pour une liste de campagnes. |
| `reserver(idCampagne, idProfil, demandes, ?commentaire, ?idOrder)` | Crée/met à jour une réservation. `$demandes` décrit l'état **cible** par rôle (table rase) ; toute nouvelle place est créée `attente`. Refusée si la réservation est verrouillée. Transaction. |
| `annuler(idCampagne, idProfil)` | Désistement (voir §5.2). Refusé si la réservation est verrouillée. |
| `estVerrouillee(idCampagne, idProfil)` / `assertModifiable(...)` | Verrou : au moins une place `refusee` ou `annulee` ; `assertModifiable()` lève `DomainException` 403 (`COM_GDA_RESERVATION_VERROUILLEE`). |
| `changerStatutPlace(idPlace, statut)` | Décision du responsable (`attente`/`confirmee`/`refusee`), y compris depuis `annulee` (déverrouille l'adhérent et remet `annulee = 0` sur l'enveloppe). Retourne le statut précédent. |
| `getPlaceContexte(idPlace)` / `profilExiste(idProfil)` | Contexte d'une place (mails) ; existence du profil (FK). |
| `ajouterPlaces(...)` / `retirerPlaces(...)` *(privées)* | Insertion de places `attente` ; retrait des places les plus récentes d'un rôle. |

## 5. Flux métier

### 5.1 Réservation (Formation ou Loisir)

`AccueilModel::getCampagnesReservables($user)` → `dash_campagne_reservable_ligne.php` (un badge de statut, ou un par rôle si la réservation est mixte ; bouton désactivé avec cadenas si verrouillée) → clic "Réserver"/"Modifier" → `ReservationController::getFormulaire()` (popup `reservation.form` : une ligne rôle+quantité par rôle demandé, chaque option de rôle annotée des places restantes **pour ce rôle** via `getPlacesDisponiblesParRole()`) → soumission (`role_places[][role]`/`role_places[][quantite]`) → `ReservationController::reserver()` (garde-fous serveur selon la nature) → `ReservationService::reserver()` (toutes les places créées `attente`, mail au responsable) → la ligne du dashboard est ré-rendue sans recharger la page.

Pour Formation, l'UX reste inchangée par rapport à avant la refonte : un seul `<select>` de rôle, quantité implicite 1 (pas de bouton d'ajout de ligne). Pour Loisir avec `reservation_multiple = 1`, le popup propose d'ajouter/supprimer des lignes rôle+quantité (`RowList`, même motif que les brevets du formulaire d'adhésion) — permettant par exemple de réserver "2 Plongeur + 1 Non-Plongeur" en une seule soumission.

### 5.2 Statuts, désistement et verrou

Statuts d'une place : `attente` (« En cours »), `confirmee` (« Validée »), `refusee` (« Non retenue »), `annulee`. Le responsable décide depuis l'onglet Suivi (double-clic sur le statut → `changerStatutInscription()` ; passage à `confirmee` ⇒ mail d'acceptation à l'adhérent).

`ReservationService::annuler()` (bouton « Me désinscrire », confirmation `GdaDialog.confirm`) :
1. Place `attente` → **supprimée** : l'adhérent redevient « Non inscrit », sans trace côté responsable.
2. Place `confirmee` → `annulee` : visible « Annulée » pour l'adhérent comme pour le responsable.
3. Enveloppe `annulee = 1`, le tout dans une transaction ; le responsable est prévenu par mail.

**Verrou** : dès qu'une place est `refusee` ou `annulee`, l'adhérent ne peut plus modifier, se désinscrire ni se réinscrire (bouton désactivé + 403 côté serveur via `assertModifiable()`) ; seul le responsable peut faire évoluer le statut (y compris depuis « Annulée », ce qui déverrouille).

### 5.3 Gestion des campagnes, suivi et récapitulatif (Bureau / Responsable de Groupe)

`CampagnesModel::getCampagnes()` → `layouts/campagnes/table.php` → `campagne.js` adapte le formulaire selon la nature sélectionnée (Formation ou Loisir uniquement) : masque/force `reservation_multiple` à 0 pour Formation, affiche **toujours** le bloc de rôles+capacités (`RowList`, préremplis depuis `role_places`). Le champ `sous_type` n'apparaît que pour une Formation. Soumission → `CampagnesController::sauver()` → `CampagnesModel::Sauver()` : sauvegarde la campagne, calcule `reservation_multiple`, valide `sous_type`/`id_responsable`, puis `saveRolePlaces()` remplace inconditionnellement `#__gda_campagne_roles` à partir de `role_places[]`.

La page Campagnes a trois onglets : **Suivi des inscriptions** (filtres Rôle/Statut côté client, colonne Commentaire), **Gestion des campagnes**, **Récapitulatif formations** (adhérents × formations, dernier statut, filtre Sous-type).

## 6. Décisions d'architecture (rappel)

Décisions validées lors de la refonte Formation/Loisir (0.9.10, 2026-08-27) :

1. **Mélange de rôles dans une même réservation** (Loisir) : accepté malgré le coût — cassait l'hypothèse "1 réservation = 1 rôle = 1 statut" posée lors du chantier précédent (capacité par rôle, même version 0.9.10). Correspond à l'usage réel (réserver pour un groupe mixte en un seul geste).
2. **Modèle "place = unité atomique"** : conséquence directe du point 1. Le statut, le rôle et le rang FIFO ont été déplacés de l'enveloppe (`#__gda_reservation`) vers le détail (`#__gda_reservation_places`), qui devient la seule source de vérité pour la capacité/file d'attente. `#__gda_reservation` redevient une simple enveloppe (identité campagne/adhérent, `annulee`, métadonnées).
3. **`role_actif` supprimée** : vérifié qu'aucun autre usage n'en dépendait avant suppression de la colonne. Les rôles sont désormais toujours actifs pour Formation et Loisir — il n'existe plus de campagne "sans rôle" hors Saison.
4. **`reservation_multiple` reste un champ explicite**, modifiable par le Bureau (pas implicite à la nature) : Loisir peut être configuré à 1 place par adhérent si souhaité, Formation reste toujours forcée à 0.
5. **Rôles librement éditables par occurrence de campagne** : le gabarit `#__gda_role_de_campagne` ne fournit que les valeurs par défaut d'une nouvelle campagne ; le Bureau peut ensuite ajouter/renommer/supprimer une ligne rôle+capacité depuis le formulaire (motif UI repris des brevets du formulaire d'adhésion, factorisé dans `media/com_gdadhesions/js/row_list.js`).
6. **Natures hors-Saison** : Formation et Loisir (réservation) ; la nature **Boutique** a été ajoutée en 0.9.18 comme simple vitrine HelloAsso Shop.

Décisions du chantier « Campagnes 1.0 » (0.9.18) :

7. **Validation manuelle** : toute inscription part `attente` ; plus de confirmation automatique ni de file d'attente (`promouvoirFileAttente()`, rangs supprimés). La capacité par rôle est indicative.
8. **Statuts distincts** : `refusee` (« Non retenue », décision du responsable) ≠ `annulee` (désistement après validation) ; un désistement « En cours » ne laisse aucune trace.
9. **Verrou** des réservations comportant une place non retenue ou annulée, levé uniquement par le responsable.
