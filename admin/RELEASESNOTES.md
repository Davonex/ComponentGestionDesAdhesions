# Component com_gadhesions

## Version 0.9.9
  - 📰 Nouveautés

  - 📈 Améliorations
    - Adhesion : contraint bloquante enfant de moin de 8an au 1 sept.
    - Adhesion: contrainte non bloquante choisir reduction famille pour un mineur.
    - Adhesion: message popup et mail pour aider l"enregistrement dans HelloAsso 
    - Campagnes: Ajout du rôle
    
    

  - 🪲 Bugs
    - Secretariat/Utilisateurs - Erreur JS possible lors de la perte de focus de la fenêtre (édition en ligne Date CACI / Catégorie / Fonction)
    - les Label et description dans la creation des menus.
    - Secretariat: Correction  du retours de l'API d'HelloAsso
      - Plusieurs commande avec 1 piement 
      - Prendre en compte les reductions

## Version 0.9.8

  - 📰 Nouveautés
    - Saisons - Activité rattachée à chaque groupe du club (référentiel FFESSM ou « Toutes »)
    - Gestiondes des Brevets - Nouvelle vue (MDB) : administration du référentiel FFESSM et rattachement des brevets adhérents
    - Brevets - Rattacher un brevet le renomme avec le libellé officiel FFESSM, qui devient non modifiable par l'adhérent

  - 🔧 Améliorations
    - Base - Colonne `niveau` du référentiel renommée en `poids` (le « niveau » restant celui du plongeur)
    - Saisons - Largeur des colonnes du panneau « Groupes du club »
    - Secretariat - Fixe la date de validité de licence pendant l'etape de Finalization 
    - Groupes - Amélioration de la vue Détail et Vignette
    - Utilisateurs - Amelioration des col Lic et Caci


## Version 0.9.7

  - 📰 Nouveautés
    - Base - Table de mapping des brevets FFESSM (78 libellés officiels / code / activité)
    - Trombinoscope - Membre du bureau 

  - 🔧 Améliorations
    - Utilisateurs - Separer la vue en 3 onglets
    - Utilisateurs - bouton supprimer un adhérent 
    - Utilisateurs - Possibilité de regenerer le mot de passe et de l'envoyer.

  - 🪲 Bugs
    - La photo de profil par défaut ne s'affichait pas 
    - Sauvegarde validité de la licence.

## Version 0.9.6

  - 🔧 Améliorations
    - Installation - Nettoyage des fichiers obsolètes hérités des versions antérieures

  - 🪲 Bugs
    - Les CSS et JS versionnés ce qui force le rechargement à chaque livraison.

## Version 0.9.5

 - 📰 Nouveautés
    - Campagnes - Gerer campagne [Formation]
    - Accueil - Reservation une campagne  [Formation]

  - 🔧 Améliorations
    - Accueil - Menage ancien dashboard
    - Profil - Liste des brevets "CARD_FIELDS_LIGHT"
    - profil - edition brevets (Création  BrevetsService)
    - Adhesion - Message plus specifique apres scan QR Code
    - Adhesion/Secretariat - Btn suivant et precedent 
    - 

  - 🪲 Bugs
    - Les champs editables, se sauvegarde correctement 
    - Adhesion - Fautes d'orthographe
    - Adhesion - Placeholder du champ date des brevets
    - Adhesion - Le recap des plongées sous 35m
    - Adhesion - Gestion echec HelloAsso 
    - Icon HelloAsso disponible offline

## Version 0.9.0

  - 📰 Nouveautés
    - Vue pour gerer Les saisons!
      - Modifier les infos de la courante (Titre lienn dates, helloAsso etc..)
      - Modifier les groupes ouverts aux adhésions
      - Ouvrir / fermé  les saisons 

  - 🔧 Améliorations
    - site: Nettoyage du fichier com_gdadhesions.ini
    - site: vue utilisateur 
      - Ajout de la fontion pour les membre du bureau 
      - Ajout du filtre pour afficher que les membre d'un groupe
      - Ajout du lien pour editer le profil de chaque utilisateur 
    - Site: possibilité de telecharger le CACI au format PDF  ( Adhesion & Profils)

  - 🪲 Bugs
    - pb de case avec ToolHelper.
    - save profil: ajout le control du  niveau d'acces

## Version 0.8.2

 - 📰 Nouveautés
    - Affichage du role utilisateur  dans *Accueil / Adhérents*
    - Site: Nouvelle vue *Utilisateur* reservé au **Membre du Bureau** pour donner les roles et Activer/bloquer les utilisateurs.

- 🔧 Améliorations
    - Ajouter du loggin pour toutes les étapes de validation ou de-validation de la vue Secretariat

- 🪲 Bugs
  - Mise a jour du tooltips: "Licence temporaire ..."
  - Vue *Secrétariat* : validation/dé-validation d'un paiement toujours loggée comme réussie même en cas d'échec (log de succès placé après le bloc try/catch, donc exécuté inconditionnellement)

## Version 0.8.1

  - 📰 Nouveautés
    - Site: ajout d'une carte "Mise à jour du CACI" dans la vue Profil (`layouts/profil/mgn_caci.php`) : dépôt par drag&drop ou sélection classique, prise de photo via l'appareil photo natif sur mobile, saisie de la date de fin de validité. Permet à l'adhérent de renouveler son CACI sans repasser par le formulaire d'adhésion complet.
    - Site: popup "fiche adhérent" en lecture seule, accessible en cliquant sur le Nom Prénom d'un adhérent dans les vues Groupes et Secrétariat (`ProfilController::showCard()`) : fiche allégée (photo/nom/licence) pour les Moniteurs et Responsables de Groupe, fiche complète (coordonnées, personne à prévenir) pour le Bureau.

  - 🔧 Améliorations
    - `media/com_gdadhesions/js/file_upload.js` : factorisation du câblage drag&drop (`FileUpload.create()`), désormais réutilisé par les vues Adhésion et Profil au lieu d'être dupliqué par vue.
    - `media/com_gdadhesions/js/form_modal.js` : `simpleCallAjax()` accepte désormais directement un `FormData` (en plus d'un objet clé/valeur) et un callback d'échec ; ajout d'un handler générique `.js-show-profil-card` réutilisable par toute vue chargeant ce script.
    - `src/Model/ProfilModel.php` : extraction de `showCardProfil()` vers le layout `layouts/profil/card_profil.php`, réutilisé à la fois par la vue Profil (éditable) et la popup fiche adhérent (lecture seule).

  - 🪲 Bugs
    - Vue Adhésion : la zone de dépôt du CACI ne se surlignait pas pendant le glisser-déposer (incohérence d'ID entre le template et le JS).
    - Vue Profil : une erreur JavaScript pouvait survenir en déposant un fichier invalide dans la zone photo (mauvais paramètre passé au composant d'upload).


## Version 0.8.0
  - Nouveautés
    - Site: ajout de la notion de saison courante (`#__gda_campagnes.courante`), distincte de la saison ouverte (`active`)
      - `SaisonService::getSaisonCourante()` : saison de suivi (CACI, licence, groupes) de l'année en cours, indépendante de l'ouverture des inscriptions
      - Corrige le blocage des vues Secrétariat/Groupes/Accueil une fois la saison fermée aux inscriptions
    - Site: ajout de la vue `Groupes` (liste des groupes de formation avec leurs adhérents pour la saison ouverte)
      - Un onglet Bootstrap par groupe, plus un onglet "Tous les groupes" (union dédupliquée des adhérents, premier onglet actif par défaut)
      - Switch "Masquer les groupes vides" activé par défaut
      - Tableau détaillé par groupe (simple-datatables : recherche/tri) : photo, nom/prénom, CACI, date de fin de validité, statut
      - Prévisualisation agrandie de la photo et du CACI en modal (clic sur la miniature)
      - Export PDF du tableau via impression navigateur
      - Bascule d'affichage Détail / Vignette : vignette en `card` par adhérent (nom/prénom, photo, statut CACI avec lien vers l'image en grand, date de fin de validité)
    - script.php: création automatique du menu frontend "Groupes" (sous "Adhérents"), à l'installation et lors de la mise à jour depuis une version antérieure
      - Nouveau niveau d'accès "NA Groupes" (groupes Membre du Bureau + Moniteur)
    - #__gda_groupes: attribution d'une icône Font Awesome (fa-solid) à chaque groupe pour l'affichage des onglets de la vue Groupes

## Version 0.7.16
  - Améliorations
    - Admin: renommage de la vue `helloasso` en `configuration`
    - Admin: ajout d'un onglet "Email (mode debug)" dans la vue Configuration pour piloter la cle `DevMailOverride` (`#__gda_conf`)
    - script.php: ajout de la methode `update()` pour nettoyer les fichiers/dossiers obsoletes de l'ancienne vue `helloasso` lors d'une mise a jour
    - Ajout du Release Notes
    - Ajout du fichier de log.

## Version 0.7.15
  - Améliorations
    - Optimization des images dans la vue secretariat
    - Refond du layout  secretariat/payment.php 
    - Ajout du d'un dasboard Suivi Adhésion, avec les différentes etapes.
  - Corrections
    - Correction QR Code: Non connecter mais licence existe deja !
    - Lien de re-edition , quand le compte est bloqué.
    - Suppimer les message d'erreur apres 10 secondes [gda.js]
    - Correction du bug cans le scanner QRcode empeche la fermuture la la modale. [adhesions.js]
    - mise à jour des class pour la vue dasboard.


## Version 0.7.14
  - Bug - Template mail pour les nouveaux (Demarre par N)


## Version: 0.7.13
  - correction Bugs mail 


## Version: 0.7.12
  - Secretariat: Effacer la photo et la Caci durant le delete.
  - Mise a jour des templates de mail (finalization et adhesion)
  - Ajout du logger dans chaque étape de validation de la secraitaire.

## Version: 0.7.11
 - Ajoute d'un looger Helper/GdaLogger
 - Ajoute d'une entré dans la table `#__gda_groupes`
 - Ajout du service NotificationMailService avec les layouts :
  - finalization_[html|text].php
  - profile_lifecycle_[html|text].php