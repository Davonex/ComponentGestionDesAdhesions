# Gestion des adhésions DU NCB  (com_gdadhesions)

Composant Joomla de gestion des adhésions d'un club de plongée, développé pour le Neptune Club de Brunoy.

Il accompagne l'adhérent de sa demande d'adhésion jusqu'à l'accès à son espace personnel, et donne au bureau les outils pour valider les dossiers, gérer les saisons et organiser les activités du club.

## Fonctionnalités

- **Adhésion en ligne** : nouvelle adhésion ou renouvellement, avec récupération des informations et des brevets depuis la licence FFESSM.
- **Espace adhérent** : suivi de l'adhésion, du certificat médical (CACI) et de la licence.
- **Secrétariat** : validation des dossiers en trois étapes (informations, CACI et paiement ; enregistrement FFESSM ; finalisation), et rapprochement des paiements HelloAsso.
- **Campagnes** : formations, sorties loisir et boutique du club, avec inscription des adhérents et validation par l'organisateur.
- **Paiements HelloAsso** : cotisations, inscriptions et achats en boutique.
- **Saisons et tarifs** : ouverture des saisons et tarification administrables par le bureau.
- **Trombinoscope** : membres du bureau et encadrants du club.

## Documentation

### Pour les utilisateurs

| Page | Pour qui |
| --- | --- |
| [Adhérer au club : le formulaire d'adhésion](docs/utilisateur/adhesion.md) | Adhérents |
| [Les campagnes : mode d'emploi](docs/utilisateur/campagnes.md) | Adhérents et organisateurs |

### Pour les développeurs

| Page | Sujet |
| --- | --- |
| [Domaine Campagnes](docs/technique/campagnes.md) | Campagnes, réservations et capacité par rôle |
| [Fiche Profil](docs/technique/fiche_profil.md) | Personnalisation de la carte Profil |

## Installation

1. Télécharger `com_gdadhesions.zip` depuis la page [Releases](../../releases).
2. Dans l'administration Joomla : **Système → Installer → Extensions**, puis déposer le fichier zip.

Le composant cible **Joomla 6**.

## Mise à jour

Une fois installé, le composant se met à jour depuis Joomla : **Système → Mettre à jour → Extensions**. Les nouvelles versions sont annoncées automatiquement.

Le détail des changements de chaque version est dans les [notes de version](admin/RELEASESNOTES.md).

## Licence

GNU General Public License version 2 ou ultérieure.
