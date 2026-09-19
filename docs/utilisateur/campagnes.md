# Les campagnes du club : mode d'emploi

Ce document explique comment fonctionnent les **campagnes** (formations, loisirs, boutique), du point de vue de l'**adhérent** qui s'inscrit et de l'**organisateur** qui les gère.

> Les emplacements « 📷 Capture » indiquent où insérer une capture d'écran.

---

## 1. Vue d'ensemble

Il existe trois sortes de campagnes proposées en dehors de l'adhésion annuelle :

| Type | À quoi ça sert | Comment on y participe |
|---|---|---|
| **Formation** | Une formation ou un stage | On s'inscrit, l'organisateur valide |
| **Loisir** | Une sortie, une soirée, une activité | On s'inscrit, l'organisateur valide |
| **Boutique** | Des articles en vente (vêtements, accessoires…) | On consulte et on achète sur HelloAsso |

Le principe est simple : **l'adhérent demande, l'organisateur décide.** Une inscription n'est jamais acceptée automatiquement : elle est toujours examinée par un organisateur, même s'il reste de la place.

### Le parcours en un coup d'œil

```mermaid
sequenceDiagram
    autonumber
    actor A as Adhérent
    participant S as Site du club
    actor O as Organisateur
    participant H as HelloAsso

    A->>S: Clique sur « Réserver » (choix du rôle, commentaire)
    S-->>A: Inscription « En cours »
    S-->>O: E-mail « Nouvelle demande d'inscription »
    O->>S: Ouvre le Suivi et passe l'inscription en « Validée »
    S-->>A: E-mail « Inscription validée » (récapitulatif)
    opt Campagne payante
        S-->>A: Lien de paiement HelloAsso dans le mail
        A->>H: Paie en ligne
        H-->>S: Le paiement apparaît sur la campagne
    end
```

---

## 2. Les statuts d'une inscription

Une inscription passe par quatre statuts. L'adhérent et l'organisateur voient le même état, avec des mots légèrement différents.

| Ce que voit l'organisateur | Ce que voit l'adhérent | Signification |
|---|---|---|
| 🟠 **En cours** | Inscription en cours de validation | La demande est faite, l'organisateur ne l'a pas encore traitée |
| 🟢 **Validée** | Inscription validée | L'organisateur a accepté |
| 🔴 **Refusée** | Inscription refusée par le responsable de la campagne | L'organisateur a refusé |
| ⚪ **Annulée** | (l'adhérent n'est plus inscrit) | L'adhérent s'est lui-même désinscrit |

**Refusée** et **Annulée** sont volontairement distinctes : *Refusée* est une décision de l'organisateur, *Annulée* est le choix de l'adhérent.

```mermaid
stateDiagram-v2
    [*] --> EnCours : L'adhérent s'inscrit
    EnCours --> Validee : L'organisateur valide
    EnCours --> Refusee : L'organisateur refuse
    Validee --> Refusee : L'organisateur change d'avis
    Refusee --> Validee : L'organisateur change d'avis
    Validee --> EnCours : L'organisateur remet en attente
    Refusee --> EnCours : L'organisateur remet en attente
    EnCours --> Annulee : L'adhérent se désinscrit
    Validee --> Annulee : L'adhérent se désinscrit
    Annulee --> [*]

    state "En cours" as EnCours
    state "Validée" as Validee
    state "Refusée" as Refusee
    state "Annulée" as Annulee
```

Points à retenir :

- L'organisateur peut **changer un statut à tout moment** (validée ↔ refusée ↔ en cours).
- Une inscription **annulée** par l'adhérent ne peut plus être modifiée par l'organisateur : elle reste visible pour mémoire.
- Un adhérent qui s'est désinscrit peut **se réinscrire** : une nouvelle demande « En cours » est alors créée.

---

## 3. Côté adhérent

### 3.1 Où trouver les campagnes

Sur votre page **Accueil** (espace Adhérents), vous trouvez des encarts repliables (la flèche à gauche du titre permet de les ouvrir ou de les fermer) :

- **Campagnes Formation**
- **Campagnes Loisir**
- **Boutique**

Un encart n'apparaît que s'il y a au moins une campagne ouverte. Une campagne est visible uniquement entre sa date d'ouverture et sa date de fermeture.

📷 *Capture : la page Accueil avec les encarts Formation, Loisir et Boutique.*

### 3.2 S'inscrire

1. Repérez la campagne dans l'encart Formation ou Loisir. Vous y voyez son titre, sa description, ses dates, et un lien vers l'article si l'organisateur en a lié un.
2. Cliquez sur **Réserver**.
3. Dans la fenêtre qui s'ouvre :
   - choisissez votre **rôle** (par exemple *Pratiquant* ou *Encadrant* pour une formation) ;
   - indiquez le **nombre de places** si la campagne le permet ;
   - ajoutez, si vous le souhaitez, un **commentaire** pour l'organisateur (une contrainte, une question, du matériel apporté…).
4. Validez.

Votre inscription apparaît immédiatement avec le statut **« Inscription en cours de validation »**.

À savoir :

- Pour une **Formation**, une seule place par adhérent.
- Pour un **Loisir**, vous pouvez réserver plusieurs places (par exemple pour vous et un invité) **uniquement si l'organisateur a autorisé les places multiples**.
- Le nombre de places affiché est **indicatif** : c'est l'organisateur qui décide en dernier ressort.
- Votre **profil adhérent** doit exister pour pouvoir s'inscrire. Si un message vous l'indique, contactez le secrétariat.

📷 *Capture : la fenêtre « Réserver » (rôle, nombre de places, commentaire).*

### 3.3 Suivre son inscription

Sur la ligne de la campagne, vous voyez en permanence votre statut :

- **Inscription en cours de validation** (orange) : patientez, l'organisateur a été prévenu.
- **Inscription validée** (vert) : vous êtes accepté. Un e-mail de confirmation vous est envoyé.
- **Inscription refusée par le responsable de la campagne** (rouge) : n'hésitez pas à le contacter.

Si vous avez plusieurs places sur des rôles différents, chacune affiche son propre statut (par exemple « Pratiquant ×2 »).

Si la campagne est payante via HelloAsso, un petit **badge de paiement** indique si votre paiement a bien été retrouvé (« payé ») ou non.

### 3.4 Modifier son inscription ou son commentaire

Cliquez sur **Modifier** : vous pouvez changer le nombre de places, le rôle ou votre commentaire. L'organisateur est prévenu de chaque changement.

### 3.5 Se désinscrire

Dans la fenêtre de modification, cliquez sur **Me désinscrire** et confirmez. Votre inscription passe à **Annulée** et l'organisateur en est informé par e-mail.

### 3.6 L'e-mail de validation

Dès que l'organisateur valide votre inscription, vous recevez un e-mail avec :

- le **titre** de la campagne, votre **rôle** et la **date** de l'événement ;
- la **description** et le **lien vers l'article** s'il y en a un ;
- si la campagne est payante sur HelloAsso et que votre paiement n'a pas encore été retrouvé : le **lien pour payer**.

### 3.7 La boutique

L'encart **Boutique** présente les articles en vente : photo, prix et disponibilité.

- **Disponible** : vous pouvez acheter. Si le stock est limité, « Il reste N » est indiqué.
- **Bientôt** : la vente n'est pas encore ouverte.
- **Terminé / épuisé** : l'article n'est plus disponible.

Le bouton **Acheter** (en bas à droite) vous emmène sur la page HelloAsso de la boutique, où se fait le paiement. Le bouton **Rafraîchir** (en haut à droite) met à jour les prix et les stocks.

📷 *Capture : l'encart Boutique.*

---

## 4. Côté organisateur

Les organisateurs sont les **membres du Bureau** et les **responsables de groupe**. Ils accèdent à la page **Campagnes**, qui comporte trois onglets : **Suivi des inscriptions**, **Gestion des campagnes** et **Récapitulatif formations**.

### 4.1 Onglet « Suivi des inscriptions »

C'est l'écran de travail quotidien : il liste les inscrits d'une campagne.

1. Filtrez la liste des campagnes avec **Toutes / Ouvertes / Fermées** si besoin.
2. Choisissez la campagne dans la liste déroulante.
3. Vous pouvez aussi filtrer par **Rôle** et par **Statut** (voir plus bas).
4. Le tableau affiche, pour chaque inscription : la photo, le nom, les brevets, la **licence** et le **CACI** (l'un au-dessus de l'autre), le **rôle**, le **statut**, la **date de réservation** et le **commentaire** laissé par l'adhérent.

**Filtrer les inscriptions** : deux filtres, **Rôle** et **Statut**, permettent de ne voir que, par exemple, les encadrants « En cours ». Ils se combinent et se remettent à zéro quand on change de campagne.

**Valider ou refuser** : double-cliquez sur le statut d'une ligne (un petit crayon apparaît au survol), choisissez le nouveau statut dans la liste. C'est enregistré aussitôt.

- Quand vous passez une inscription à **Validée**, l'adhérent reçoit automatiquement son e-mail de confirmation (avec le lien de paiement si nécessaire). Vous êtes averti si l'envoi n'a pas pu se faire.
- Repasser plusieurs fois sur « Validée » n'envoie pas de nouvel e-mail.
- Les inscriptions **Annulées** par l'adhérent sont affichées en gris, en fin de liste, et ne se modifient pas.

Cliquer sur le **nom** d'un adhérent ouvre sa fiche ; « Voir tout » affiche tous ses brevets ; cliquer sur le CACI ou la photo les agrandit.

📷 *Capture : l'onglet Suivi avec les filtres Rôle / Statut.*

### 4.2 Onglet « Gestion des campagnes »

Il liste toutes les campagnes (hors adhésion annuelle) avec, pour chacune :

- le titre, le type (suivi du sous-type pour une formation), les dates (événement, ouverture, fermeture) ;
- la colonne **Places**, détaillée par rôle : ✔ vertes = validées, ⏳ orange = en cours, 👥 = capacité prévue (absente si illimitée). Pour la Boutique : « N/A » ;
- le lien vers l'article, le bouton **ouvrir / fermer** et les boutons de **rapport**.

**Créer ou modifier une campagne** (bouton crayon, ou bouton d'ajout). Le formulaire présente d'abord le **Titre** et le **Type**, puis le **Responsable** et le **Sous-type**, puis la description, les dates, les rôles, etc. :

| Information | Utilité |
|---|---|
| Titre, description | Ce que voient les adhérents |
| Type | Formation, Loisir ou Boutique (non modifiable ensuite) |
| **Sous-type** (Formation uniquement) | Précise la nature de la formation : *Fosse Apnée*, *Fosse Technique 20M*, *Fosse Technique 12M* ou *RIFAx*. Champ facultatif, masqué pour les autres types. Il s'affiche sous le titre dans la liste et sert de filtre dans le récapitulatif |
| Dates d'ouverture et de fermeture | Période pendant laquelle la campagne est visible et ouverte aux inscriptions |
| Date de l'événement | Rappelée dans l'e-mail de validation |
| Places par rôle | Capacité **indicative** de chaque rôle (0 = illimité) |
| Places multiples | Autorise un adhérent à réserver plusieurs places (Loisir) |
| Article | Lien vers un article du site, proposé aux adhérents |
| Événement HelloAsso | Relie la campagne à son formulaire HelloAsso (paiement, rapports) |
| **Responsable** | Personne prévenue par e-mail des demandes (Formation et Loisir) |

**Le responsable de campagne** est choisi parmi les membres du Bureau et les responsables de groupe. Il reçoit un e-mail à chaque :

- nouvelle demande d'inscription ;
- modification (nombre de places ou rôle) ;
- désinscription ;
- nouveau commentaire de l'adhérent.

Chaque e-mail indique l'adhérent (nom, licence, téléphone, e-mail), **l'ancien et le nouveau statut** et le commentaire. Sans responsable désigné, aucun e-mail n'est envoyé.

**Ouvrir / fermer** : le bouton se trouve dans la colonne dédiée. Une campagne fermée disparaît du tableau de bord des adhérents.

**Supprimer** : possible uniquement sur une campagne fermée, avec confirmation. La campagne est masquée (les données ne sont pas détruites).

📷 *Capture : l'onglet Gestion et le formulaire de campagne.*

### 4.3 Les rapports

Chaque ligne propose, **seulement s'ils ont un sens**, jusqu'à deux boutons :

- 📊 **Rapport des réservations** (Formation et Loisir) : adhérent, niveau, rôle, date, statut et commentaire. Les inscriptions annulées y figurent mais ne sont pas comptées dans le total.
- **Rapport HelloAsso** (si la campagne est reliée à HelloAsso) : les paiements reçus. Pour une formation ou un loisir : qui a payé. Pour la boutique : acheteur, article, montant, date.

### 4.4 Onglet « Récapitulatif formations »

Une vue d'ensemble de **toutes les formations** en un seul tableau, pour voir d'un coup d'œil qui a participé à quoi.

- **Une ligne par adhérent** ayant réservé au moins une fois une formation (photo, civilité, nom, prénom ; un clic sur le nom ouvre sa fiche).
- **Une colonne par formation**, avec sa date d'événement.
- À l'intersection : le **dernier statut** de l'adhérent pour cette formation (*En cours*, *Validée*, *Refusée* ou *Annulée*, avec les mêmes couleurs que dans le Suivi), ou « — » s'il ne s'y est jamais inscrit. Si l'adhérent s'est désinscrit puis réinscrit, c'est son inscription la plus récente qui compte.
- **Filtre Sous-type** : n'affiche que les formations d'un sous-type (par exemple *Fosse Apnée*) et retire les adhérents qui n'y ont pas réservé. Le filtre n'apparaît que si au moins une formation a un sous-type ; il revient sur « Tous » à chaque ouverture de l'onglet.

Les données sont relues à chaque ouverture de l'onglet : elles sont toujours à jour.

📷 *Capture : l'onglet Récapitulatif formations.*

---

## 5. Qui voit quoi, qui fait quoi

```mermaid
flowchart LR
    subgraph ADH["Adhérent (page Accueil)"]
        A1[Réserver / Modifier]
        A2[Me désinscrire]
        A3[Consulter la Boutique]
    end

    subgraph ORG["Organisateur (page Campagnes)"]
        O1[Suivi : valider / refuser]
        O2[Gestion : créer, ouvrir, fermer]
        O3[Rapports]
        O4[Récapitulatif formations]
    end

    subgraph MAIL["E-mails automatiques"]
        M1[Au responsable :<br/>demande, modification,<br/>désinscription, commentaire]
        M2[À l'adhérent :<br/>inscription validée<br/>+ lien de paiement]
    end

    A1 --> M1
    A2 --> M1
    M1 --> O1
    O1 -->|Validée| M2
    M2 --> A1
    O2 -->|Campagne ouverte| A1
    O2 -->|Campagne ouverte| A3
    O3 -.-> O1
    O1 -.-> O4
```

---

## 6. Questions fréquentes

**Je m'inscris : est-ce que ma place est réservée ?**
Non, pas avant la validation. « En cours » signifie que l'organisateur doit encore examiner votre demande.

**Il n'y a plus de places affichées, puis-je quand même m'inscrire ?**
Oui : le nombre de places est indicatif, l'organisateur décide.

**J'ai été refusé, que faire ?**
Contactez le responsable de la campagne : il peut revenir sur sa décision.

**Je ne vois pas la campagne sur mon Accueil.**
Elle est peut-être fermée ou pas encore ouverte. Vérifiez ses dates auprès de l'organisateur.

**Un adhérent a payé mais le badge n'indique pas « payé ».**
Le rapprochement se fait à partir du numéro de licence saisi lors du paiement HelloAsso. Vérifiez qu'il est exact ; l'organisateur peut consulter le rapport HelloAsso.

**J'ai reçu un message « Votre session a expiré ».**
Rechargez la page (F5), reconnectez-vous si besoin, puis recommencez.
