# Component com_gadhesions


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