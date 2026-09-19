# Domaine Campagnes (`com_gdadhesions`)

> Couvre la gestion des campagnes (Saison, Formation, Loisir), les réservations hors saison et la capacité par rôle. Mis à jour lors de la refonte des natures de campagne (0.9.10, 2026-08-27) : réduction à 2 natures hors-Saison, rôles librement éditables et toujours actifs, réservations pouvant mélanger plusieurs rôles.

## 1. Vue d'ensemble

Une **campagne** (`#__gda_campagnes`) est un événement ou une période gérée par le club. Sa **nature** (`id_type` → `#__gda_type_de_campagne`) détermine son comportement :

| Nature | Mécanisme d'inscription | Table |
|---|---|---|
| **Saison** | Souscription annuelle, workflow CACI/cotisation/licence du secrétariat | `#__gda_souscriptions` |
| **Formation / Loisir** | Réservation avec places limitées **par rôle**, file d'attente | `#__gda_reservation` / `#__gda_reservation_places` |

`#__gda_souscriptions` et `#__gda_reservation` sont deux mécanismes **distincts et non interchangeables** (voir cartographie §4) : tout ce qui suit dans ce document ne concerne jamais les campagnes de type Saison.

Depuis 0.9.10, une campagne hors saison propose **toujours** un ou plusieurs rôles par place (Formation : Pratiquant/Encadrant par défaut ; Loisir : Plongeur/Non plongeur par défaut) — il n'existe plus de mode "sans rôle". Les rôles proposés par défaut viennent du gabarit `#__gda_role_de_campagne` (par nature), mais le Bureau peut librement **ajouter, renommer ou supprimer** une ligne rôle+capacité pour une campagne donnée depuis le formulaire d'édition (répercuté dans `#__gda_campagne_roles`). La capacité (nombre de places) est **toujours suivie séparément pour chaque rôle** : chaque rôle a sa propre limite et sa propre file d'attente, indépendante des autres rôles de la même campagne.

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
| Layouts (gestion) | `layouts/campagnes/{table,row,rapport,role_row_template,suivi_inscrits}.php` |
| Layout (popup réservation adhérent) | `layouts/reservation/{form,role_row_template,article}.php` |
| Layout (ligne dashboard) | `layouts/accueil/dash_campagne_reservable_ligne.php` |
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
        varchar statut "confirmee | attente | annulee"
        datetime date_rang "Rang FIFO dans la file d'attente de (id_campagne, role)"
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

`RESERVATION_PLACES` est désormais l'**unité atomique** de statut/rang (avant 0.9.10, ces informations vivaient sur `RESERVATION` elle-même, qui ne pouvait porter qu'un seul rôle/statut). Ce changement était nécessaire pour permettre à une réservation Loisir de mélanger plusieurs rôles avec des statuts indépendants (une place Plongeur confirmée, une place Non-Plongeur en attente, dans la même réservation).

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
| `getInscritsCampagne(int $id_campagne, string $titre)` | Adhérents inscrits (hors annulés), **une ligne par place** (un adhérent avec 2 rôles apparaît en 2 lignes), sous la forme d'un groupe `GroupesModel`, pour l'onglet Suivi (réutilise `layouts/groupes/detail.php`). |
| `Activer()` / `Sauver()` / `Effacer()` | CRUD campagne. `Sauver()` calcule `reservation_multiple` selon la nature (forcé à 0 pour Formation) et persiste toujours `role_places` via `saveRolePlaces()`. |
| `getRapport()` / `getRapportHelloAsso()` | Rapport d'inscriptions (popup), une ligne par place, statut + rang de file d'attente via `ReservationService::calculerRangsAttente()`. |

### `AccueilModel` (`src/Model/AccueilModel.php`)

| Méthode | Rôle |
|---|---|
| `getCampagnesReservables($user)` *(renommée depuis `getFormations()`)* | Campagnes Formation **et** Loisir ouvertes pour le dashboard adhérent, enrichies de `places_occupees`, `capacite_totale`, et de l'état de réservation de l'adhérent connecté — `mesPlaces` : un tableau `{role, statut, rang}` par rôle réservé (potentiellement plusieurs si la réservation est mixte). |

### `ReservationController` / `CampagnesController`

| Méthode | Rôle |
|---|---|
| `ReservationController::reserver()` | Reconstruit `$demandes` (`{role, quantite}[]`) depuis `role_places[]`. Garde-fous serveur : Formation → tronqué à 1 ligne, quantité forcée à 1 ; Loisir sans `reservation_multiple` → total des quantités ≤ 1 (exception sinon). Résout la capacité par rôle (`CampagnesModel::getRolesCapacite()`) et délègue à `ReservationService::reserver()`. |
| `ReservationController::annuler()` | Délègue à `ReservationService::annuler()` (annulation + promotion automatique de la file d'attente, scopée par rôle). |
| `ReservationController::getFormulaire()` | Contenu du popup de réservation : rôles réellement configurés pour la campagne, places restantes **par rôle** (`placesDisponiblesParRole`), préremplissage multi-lignes depuis `$reservation->places` à l'édition. |
| `CampagnesController::sauver()` | Lit `jform_campagne[...]` (dont `role_places[]`, tableau indexé généré dynamiquement par `campagne.js`/`RowList`), délègue à `CampagnesModel::Sauver()`. |
| `CampagnesController::activer()` / `effacer()` / `rapport()` / `suivi()` | CRUD/consultation. `suivi()` élargi aux 2 natures (Formation et Loisir). |

### `ReservationService` (`src/Service/ReservationService.php`)

| Méthode | Rôle |
|---|---|
| `getReservation(idCampagne, idProfil)` | Réservation d'un adhérent (enveloppe + `->places[]`), chaque place avec son `role`/`statut`/`date_rang`/`rang` (rang peuplé uniquement si en attente). |
| `getPlacesOccupeesParRole(idCampagne, role)` / `getPlacesDisponiblesParRole(idCampagne, role, capaciteRole)` | Capacité **par rôle** : chaque rôle a sa propre occupation, lue via `#__gda_reservation_places`. |
| `getPlacesOccupeesTotal(idCampagne)` / `getPlacesDisponiblesTotal(idCampagne, capaciteTotale)` | Équivalents **tous rôles confondus**, pour l'affichage global (colonne "places occupées" de l'onglet Gestion, dashboard adhérent). |
| `getSelectPlacesOccupeesTotal(db, alias)` *(statique)* | Fragment SQL (sous-requête corrélée) de la ligne ci-dessus, pour une **liste** de campagnes sans N+1 (`CampagnesModel::getCampagnes()`, `AccueilModel::getCampagnesReservables()`). |
| `getCapaciteTotale(campagne)` | Capacité totale d'une campagne déjà chargée : somme de `#__gda_campagne_roles`. |
| `getSelectCapaciteTotale(db, alias)` *(statique)* | Même règle, en fragment SQL pour une liste de campagnes. |
| `calculerRangsAttente(places)` *(statique)* | Rang de file d'attente (1 = premier), groupé par `role`, ordonné par `date_rang`. |
| `getRangAttente(idCampagne, role, dateRang)` | Rang de file d'attente pour une seule place (requête ciblée, dashboard adhérent). |
| `reserver(idCampagne, idProfil, demandes, capacitesParRole, ?commentaire, ?idOrder)` | Crée/met à jour une réservation. `$demandes` décrit l'état **cible** par rôle (stratégie table rase) : un rôle absent de `$demandes` revient à 0 place. Statut confirmée/attente calculé indépendamment pour chaque rôle demandé. Transaction complète. |
| `annuler(idCampagne, idProfil)` | Marque l'enveloppe et ses places `annulee`, puis `promouvoirFileAttente()` sur les places libérées, dans une transaction. |
| `ajouterPlaces(...)` / `retirerPlaces(...)` *(privées)* | Écriture des lignes `#__gda_reservation_places` pour un rôle donné (ajout avec calcul confirmée/attente selon la capacité restante ; retrait des places les plus récemment ajoutées de ce rôle, en relançant `promouvoirFileAttente()` si une place confirmée est libérée). |
| `promouvoirFileAttente(idCampagne, role, placesALiberer)` *(privée)* | Promeut les places `attente` les plus anciennes du même rôle (FIFO). |

## 5. Flux métier

### 5.1 Réservation (Formation ou Loisir)

`AccueilModel::getCampagnesReservables($user)` → `dash_campagne_reservable_ligne.php` (badge unique "Inscrit" si toutes les places sont confirmées, un badge par rôle si la réservation est mixte) → clic "Réserver"/"Modifier" → `ReservationController::getFormulaire()` (popup `reservation.form` : une ligne rôle+quantité par rôle demandé, chaque option de rôle annotée des places restantes **pour ce rôle** via `getPlacesDisponiblesParRole()`) → soumission (`role_places[][role]`/`role_places[][quantite]`) → `ReservationController::reserver()` (garde-fous serveur selon la nature) → `ReservationService::reserver()` (statut confirmée/attente calculé indépendamment pour chaque rôle de la demande) → la ligne du dashboard est ré-rendue sans recharger la page.

Pour Formation, l'UX reste inchangée par rapport à avant la refonte : un seul `<select>` de rôle, quantité implicite 1 (pas de bouton d'ajout de ligne). Pour Loisir avec `reservation_multiple = 1`, le popup propose d'ajouter/supprimer des lignes rôle+quantité (`RowList`, même motif que les brevets du formulaire d'adhésion) — permettant par exemple de réserver "2 Plongeur + 1 Non-Plongeur" en une seule soumission.

### 5.2 Annulation et promotion automatique

`ReservationService::annuler()` :
1. Lit la réservation existante (places qu'elle libère, par rôle).
2. Marque l'enveloppe `annulee = 1` et ses places non-annulées `statut = 'annulee'`.
3. Pour chaque rôle qui avait au moins une place `confirmee` libérée, appelle `promouvoirFileAttente()` : promeut en FIFO les places `attente` de **ce rôle uniquement**, dans l'ordre chronologique d'arrivée (`date_rang`), en comblant chacune avant de passer à la suivante.
4. Le tout dans une transaction (annulation + promotion atomiques).

Côté UI, le bouton "Me désinscrire" (`reservation.js`) demande confirmation (`GdaDialog.confirm`) avant d'envoyer l'annulation, la ou les places étant immédiatement reprises par le(s) premier(s) de chaque file d'attente concernée.

### 5.3 Gestion des campagnes (onglet Gestion, Bureau)

`CampagnesModel::getCampagnes()` → `layouts/campagnes/table.php` → `campagne.js` adapte le formulaire selon la nature sélectionnée (Formation ou Loisir uniquement) : masque/force `reservation_multiple` à 0 pour Formation, affiche **toujours** le bloc de rôles+capacités (`RowList`, préremplis depuis `role_places`). Soumission → `CampagnesController::sauver()` → `CampagnesModel::Sauver()` : sauvegarde la campagne, calcule `reservation_multiple`, puis `saveRolePlaces()` remplace inconditionnellement `#__gda_campagne_roles` à partir de `role_places[]`.

## 6. Décisions d'architecture (rappel)

Décisions validées lors de la refonte Formation/Loisir (0.9.10, 2026-08-27) :

1. **Mélange de rôles dans une même réservation** (Loisir) : accepté malgré le coût — cassait l'hypothèse "1 réservation = 1 rôle = 1 statut" posée lors du chantier précédent (capacité par rôle, même version 0.9.10). Correspond à l'usage réel (réserver pour un groupe mixte en un seul geste).
2. **Modèle "place = unité atomique"** : conséquence directe du point 1. Le statut, le rôle et le rang FIFO ont été déplacés de l'enveloppe (`#__gda_reservation`) vers le détail (`#__gda_reservation_places`), qui devient la seule source de vérité pour la capacité/file d'attente. `#__gda_reservation` redevient une simple enveloppe (identité campagne/adhérent, `annulee`, métadonnées).
3. **`role_actif` supprimée** : vérifié qu'aucun autre usage n'en dépendait avant suppression de la colonne. Les rôles sont désormais toujours actifs pour Formation et Loisir — il n'existe plus de campagne "sans rôle" hors Saison.
4. **`reservation_multiple` reste un champ explicite**, modifiable par le Bureau (pas implicite à la nature) : Loisir peut être configuré à 1 place par adhérent si souhaité, Formation reste toujours forcée à 0.
5. **Rôles librement éditables par occurrence de campagne** : le gabarit `#__gda_role_de_campagne` ne fournit que les valeurs par défaut d'une nouvelle campagne ; le Bureau peut ensuite ajouter/renommer/supprimer une ligne rôle+capacité depuis le formulaire (motif UI repris des brevets du formulaire d'adhésion, factorisé dans `media/com_gdadhesions/js/row_list.js`).
6. **Natures réduites à 2 hors-Saison** : Sortie/Soirée/Boutique supprimées (peu abouties, pas de vraie vue Suivi ni de dashboard adhérent) ; leurs éventuelles campagnes existantes remappées sur Loisir (id_type=3 réutilisé). La gestion des groupes (`gda_groupes`/`gda_composition_groupes`) reste hors-scope de cette refonte, non touchée.
