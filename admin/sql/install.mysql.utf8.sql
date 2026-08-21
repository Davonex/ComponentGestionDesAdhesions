-- Installation script for com_gdadhesions component v0.7.10
-- Creates all necessary tables with UTF8MB4 charset
-- Uses Joomla table prefix #__


-- drop if exists 
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `#__gda_composition_groupes`;
DROP TABLE IF EXISTS `#__gda_mapping_brevets`;
DROP TABLE IF EXISTS `#__gda_reservation_places`;
DROP TABLE IF EXISTS `#__gda_reservation`;
DROP TABLE IF EXISTS `#__gda_souscriptions`;
DROP TABLE IF EXISTS `#__gda_niveaux`;
DROP TABLE IF EXISTS `#__gda_brevets`;
DROP TABLE IF EXISTS `#__gda_profils`;
DROP TABLE IF EXISTS `#__gda_groupes`;
DROP TABLE IF EXISTS `#__gda_cotisation`;
DROP TABLE IF EXISTS `#__gda_type_de_campagne`;
DROP TABLE IF EXISTS `#__gda_role_de_campagne`;
DROP TABLE IF EXISTS `#__gda_campagnes`;
DROP TABLE IF EXISTS `#__gda_conf`;
SET FOREIGN_KEY_CHECKS = 1;


--
-- Structure de la table `#__gda_type_de_campagne`
--

CREATE TABLE IF NOT EXISTS `#__gda_type_de_campagne` (
  `id_type` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_name` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_image` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_class` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_type`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--
-- Rechargement des données de la table `#__gda_type_de_campagne`
--
INSERT INTO `#__gda_type_de_campagne` (`id_type`, `type_name`, `type_image`, `type_class`) VALUES
(1, 'Saison', 'saison.jpg', 'campagne-saison'),
(2, 'Formation', 'fosse.jpg', 'campagne-formation'),
(3, 'Sortie', 'sortie.jpg', 'campagne-sortie'),
(4, 'Soirée', 'sortie.jpg', 'campagne-soiree'),
(5, 'Boutique', '', 'campagne-boutique');

--
-- Structure de la table `#__gda_campagnes`
--
CREATE TABLE `#__gda_campagnes` (
  `id_campagne` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '	',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `event_helloasso` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `date_evenement` datetime DEFAULT NULL COMMENT 'Date et heure de l''événement (distinct de la période de souscription)',
  `active` tinyint(1) NOT NULL,
  `courante` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Saison de suivi courante (distincte de active = ouverte)',
  `id_article` int unsigned DEFAULT '0',
  `nbr_place` int unsigned DEFAULT NULL COMMENT 'Nombre place totale pour cette campagnes',
  `reservation_multiple` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Active la demande du nombre de places à la souscription (0 = 1 place fixe, 1 = le nombre est demandé)',
  `role_actif` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Un rôle est demandé par place à la souscription (liste fixe déterminée par la nature)',
  `id_type` int unsigned DEFAULT NULL,
  `id_groupes` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effacer` int unsigned NOT NULL DEFAULT '0' COMMENT 'Campagne Effacer',
  PRIMARY KEY (`id_campagne`),
  UNIQUE KEY `id` (`id_campagne`) USING BTREE,
  CONSTRAINT `gda_campagnes_gda_type_de_campagne_FK` FOREIGN KEY (`id_type`) REFERENCES `#__gda_type_de_campagne` (`id_type`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liste des campagnes';

--
-- Structure de la table `#__gda_role_de_campagne`
--
CREATE TABLE IF NOT EXISTS `#__gda_role_de_campagne` (
  `id_type` int unsigned NOT NULL,
  `roles` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Rôles proposés pour cette nature, séparés par ;',
  PRIMARY KEY (`id_type`),
  CONSTRAINT `gda_role_de_campagne_gda_type_de_campagne_FK` FOREIGN KEY (`id_type`) REFERENCES `#__gda_type_de_campagne` (`id_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liste fixe des rôles par nature de campagne (Formation, Sortie, ...)';

INSERT INTO `#__gda_role_de_campagne` (`id_type`, `roles`) VALUES
(2, 'Encadrants;Participants'),
(3, 'Plongeur;Non Plongeur');

--
-- Structure de la table `#__gda_conf`
--

CREATE TABLE IF NOT EXISTS `#__gda_conf` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `#__gda_conf`
--

INSERT INTO `#__gda_conf` (`id`, `key`, `value`) VALUES
(1, 'ProfilPhotoPath', '/images/GestionDesAdhesions/PhotoDeProfil/'),
(2, 'DefaultProfilPhoto', 'diver.png'),
(10, 'ImagesPath', '/media/com_gdadhesions/images/'),
(4, 'CampagneImageDefault', 'campagnes_default.jpg'),
(5, 'IdTypeSaison', '1'),
(6, 'IdCategorieCampagne', '16'),
(24, 'IdTypeFormation', '2'),
(7, 'DefaultCaci', 'caci.png'),
(8, 'CaciPath', '/images/GestionDesAdhesions/Caci/'),
(9, 'IdArticleAdhesionClos', '23'),
(17,'HelloAssoBaseUrl','https://api.helloasso-sandbox.com'),
(19,'HelloAssoOrganizationSlug','asso-didou'),
(20,'LicJEUNE','30,50'),
(21,'LicADULTE','48,50'),
(22,'LicENFANT','14,00'),
(23,'DevMailOverride','');

--
-- Structure de la table `#__gda_groupes`
--
CREATE TABLE IF NOT EXISTS `#__gda_groupes` (
  `id_groupe` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `groupe_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activite` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Toutes' COMMENT 'Activite FFESSM du groupe (valeurs de #__gda_mapping_brevets.activite, ou Toutes)',
  `groupe_tri` int UNSIGNED DEFAULT NULL,
  `published` tinyint DEFAULT NULL,
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_groupe`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `#__gda_groupes` (groupe_name,groupe_tri,published,icon) VALUES
	 ('Prépa N1',1,1,'fa-person-swimming'),
	 ('Prépa N2',2,1,'fa-water'),
	 ('Apnéiste débutant',30,1,'fa-lungs'),
	 ('Apnéiste confirmé',31,1,'fa-stopwatch'),
	 ('Prépa N3',3,1,'fa-compass'),
	 ('Prépa (E1/GP)',10,1,'fa-chalkboard-user'),
	 ('Photo/Video',40,1,'fa-camera-retro'),
	 ('Biologie Subaqua',20,1,'fa-fish'),
	 ('Maintien des Acquis',5,1,'fa-arrows-rotate'),
	 ('Plongée Handi',50,1,'fa-universal-access'),
	 ('Encadrant Impliqué',60,1,'fa-medal'),
	 ('Section jeune',0,1,'fa-child');





--
-- Structure de la table `#__gda_profils`
--
CREATE TABLE IF NOT EXISTS `#__gda_profils` (
  `id_profil` int NOT NULL AUTO_INCREMENT,
  `civilite` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_de_naissance` date DEFAULT NULL,
  `adresse` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ville` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code_postal` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `a_prevenir` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `a_prevenir_tel` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caci` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_caci` date DEFAULT NULL COMMENT 'Date de validité du Caci',
  `ffessm_token` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Token FFESM',
  `droit_img` tinyint(1) DEFAULT '0',
  `reduction` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `on_behalf` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_at` time DEFAULT NULL,
  `date_licence` date DEFAULT NULL COMMENT 'Date de validité de la licence',
  `nbr_plongee` smallint UNSIGNED DEFAULT '0' COMMENT 'Nombre de plongée totale',
  `nbr_plongee_35` smallint UNSIGNED DEFAULT '0' COMMENT 'Nombre de plongée en dessous de 35m',
  `nbr_plongee_auto` smallint UNSIGNED DEFAULT '0' COMMENT 'Nombre de plongée en  autonomie',
  `key` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fonction` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Fonction au sein du Bureau',
  `ordre_bureau` smallint unsigned DEFAULT NULL COMMENT 'Ordre d''affichage dans le trombinoscope du Bureau (NULL = tri alphabétique en repli)',
  PRIMARY KEY (`id_profil`),
  UNIQUE KEY `id_profile` (`id_profil`),
  CONSTRAINT `gda_profils_users_FK` FOREIGN KEY (`id_profil`) REFERENCES `#__users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='profil des adhérents (Nouveau ou Ancient)';

--
-- Structure de la table `#__gda_brevets`
--
CREATE TABLE IF NOT EXISTS `#__gda_brevets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Cle technique : permet de cibler une ligne precise depuis la vue Brevets',
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lieu` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `obtention` date DEFAULT NULL,
  `id_mapping` int unsigned DEFAULT NULL COMMENT 'Correspondance résolue à l''enregistrement vers #__gda_mapping_brevets.id (NULL = libellé non reconnu)',
  `id_profil` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gda_brevets_id_mapping_IDX` (`id_mapping`),
  CONSTRAINT `gda_brevets_gda_profils_FK` FOREIGN KEY (`id_profil`) REFERENCES `#__gda_profils` (`id_profil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Liste des brevets';

--
-- Structure de la table `#__gda_niveaux`
--
CREATE TABLE IF NOT EXISTS `#__gda_niveaux` (
  `id_profil` int NOT NULL,
  `id_brevet` int NOT NULL,
  `code` varchar(5) DEFAULT NULL,
  `obtention` date DEFAULT NULL,
  `lieu` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  UNIQUE KEY `profil_brevet` (`id_profil`,`id_brevet`),
  CONSTRAINT `gda_niveaux_gda_profils_FK` FOREIGN KEY (`id_profil`) REFERENCES `#__gda_profils` (`id_profil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Structure de la table `#__gda_souscriptions`
--
CREATE TABLE IF NOT EXISTS `#__gda_souscriptions` (
  `id_campagne` int NOT NULL,
  `id_profil` int NOT NULL,
  `date_souscription` datetime DEFAULT NULL,
  `cotisation_code` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caci_check` tinyint(1) NOT NULL DEFAULT '0',
  `date_caci_check` datetime DEFAULT NULL,
  `cotisation_check` tinyint(1) NOT NULL DEFAULT '0',
  `date_cotisation_check` datetime DEFAULT NULL,
  `licence_check` tinyint(1) NOT NULL DEFAULT '0',
  `id_order` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `last_update` datetime DEFAULT NULL,
  `date_licence_check` datetime DEFAULT NULL,
  `categorie` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  UNIQUE KEY `uniq` (`id_campagne`,`id_profil`),
  KEY `gda_souscriptions_profils_FK` (`id_profil`),
  CONSTRAINT `gda_souscriptions_gda_campagnes_FK` FOREIGN KEY (`id_campagne`) REFERENCES `#__gda_campagnes` (`id_campagne`),
  CONSTRAINT `gda_souscriptions_gda_profils_FK` FOREIGN KEY (`id_profil`) REFERENCES `#__gda_profils` (`id_profil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table qui enregistre les souscriptions';

--
-- Structure de la table `#__gda_reservation`
--
-- #__gda_souscriptions ci-dessus reste réservée aux souscriptions de la SAISON (workflow CACI /
-- cotisation / licence géré par le secrétariat). Les réservations aux autres campagnes
-- (Formation / Sortie / Soirée / Boutique) vivent ici : pas de colonnes de validation saison,
-- mais une notion de places, de file d'attente et de rôle.
--
CREATE TABLE IF NOT EXISTS `#__gda_reservation` (
  `id_reservation` int unsigned NOT NULL AUTO_INCREMENT,
  `id_campagne` int NOT NULL COMMENT 'Campagne concernée (jamais une campagne de type Saison)',
  `id_profil` int NOT NULL,
  `date_reservation` datetime DEFAULT NULL COMMENT 'Horodatage de la réservation initiale : rang dans la file d''attente',
  `date_demande` datetime DEFAULT NULL COMMENT 'Horodatage de la dernière demande de places supplémentaires : rang du complément dans la file',
  `nbr_places` int unsigned NOT NULL DEFAULT 1 COMMENT 'Places demandées',
  `nbr_places_confirmees` int unsigned NOT NULL DEFAULT 0 COMMENT 'Places effectivement accordées (< nbr_places = complément en attente)',
  `statut` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'attente' COMMENT 'confirmee | attente | annulee',
  `commentaire` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_order` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Commande HelloAsso (paiement)',
  `last_update` datetime DEFAULT NULL,
  PRIMARY KEY (`id_reservation`),
  UNIQUE KEY `uniq_campagne_profil` (`id_campagne`,`id_profil`),
  KEY `gda_reservation_gda_profils_FK` (`id_profil`),
  CONSTRAINT `gda_reservation_gda_campagnes_FK` FOREIGN KEY (`id_campagne`) REFERENCES `#__gda_campagnes` (`id_campagne`),
  CONSTRAINT `gda_reservation_gda_profils_FK` FOREIGN KEY (`id_profil`) REFERENCES `#__gda_profils` (`id_profil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Réservations aux campagnes hors saison';

--
-- Structure de la table `#__gda_reservation_places`
--
-- Une ligne par place réservée, uniquement quand la campagne a role_actif = 1.
-- Sinon la table reste vide : nbr_places de la réservation parente fait foi.
--
CREATE TABLE IF NOT EXISTS `#__gda_reservation_places` (
  `id_place` int unsigned NOT NULL AUTO_INCREMENT,
  `id_reservation` int unsigned NOT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rôle choisi pour cette place, parmi #__gda_role_de_campagne',
  `tri` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_place`),
  KEY `gda_reservation_places_gda_reservation_FK` (`id_reservation`),
  CONSTRAINT `gda_reservation_places_gda_reservation_FK` FOREIGN KEY (`id_reservation`) REFERENCES `#__gda_reservation` (`id_reservation`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rôle par place réservée (campagnes avec role_actif = 1)';

--
-- Structure de la table `#__gda_composition_groupes`
--
CREATE TABLE IF NOT EXISTS `#__gda_composition_groupes` (
   `id_groupe` int UNSIGNED DEFAULT NULL,
  `id_campagne` int DEFAULT NULL,
  `id_profil` int DEFAULT NULL,
  KEY `gda_composition_groupes_profils_FK` (`id_profil`),
  CONSTRAINT `gda_composition_groupes_gda_campagnes_FK` FOREIGN KEY (`id_campagne`) REFERENCES `#__gda_campagnes` (`id_campagne`),
  CONSTRAINT `gda_composition_groupes_gda_profils_FK` FOREIGN KEY (`id_profil`) REFERENCES `#__gda_profils` (`id_profil`),
  CONSTRAINT `gda_composition_groupes_gda_groupes_FK` FOREIGN KEY (`id_groupe`) REFERENCES `#__gda_groupes` (`id_groupe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='composition des groupes';

--
-- Structure de la table `#__gda_cotisation`
--

CREATE TABLE `#__gda_cotisation` (
  `code` varchar(1) DEFAULT NULL,
  `tarif_vy` int unsigned DEFAULT NULL,
  `tarif_hvy` int unsigned DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tarif des cotisations';
--
-- Données initiales cotisations (reprise saison courante)
--
INSERT INTO `#__gda_cotisation` (`code`, `tarif_vy`, `tarif_hvy`) VALUES
	 ('A',205,220),
	 ('B',190,205),
	 ('C',180,190),
	 ('D',165,165),
	 ('F',135,135),
	 ('G',98,98),
	 ('E',150,150);

--
-- Structure de la table `#__gda_mapping_brevets`
--
-- Table de référence : correspondance entre les libellés officiels FFESSM et le code métier.
-- Un même code porte plusieurs libellés (équivalences, passerelles, recyclages), d'où la clé
-- unique sur le couple (code, label_ffessm_norm).
--
-- label_ffessm_norm : libellé normalisé (MAJUSCULE, sans accents, sans ponctuation) utilisé pour
-- le rapprochement avec les libellés bruts FFESSM (BrevetService::replaceBrevets) ; label_ffessm
-- reste le libellé officiel lisible, pour l'affichage.
--
-- poids : rang d'importance au sein du couple (activite, role) - plus haut = plus important.
-- Permet de retrouver le brevet le plus élevé d'un adhérent par activité (ex: en Plongée
-- pratiquant, Niveau 3 est plus important que Niveau 1). Cette colonne est volontairement absente
-- de #__gda_brevets : elle peut être réévaluée sans jamais toucher aux brevets déjà enregistrés.
--
CREATE TABLE IF NOT EXISTS `#__gda_mapping_brevets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL,
  `activite` VARCHAR(100) NOT NULL,
  `role` ENUM('pratiquant','encadrant') NOT NULL,
  `label_ffessm` VARCHAR(150) NOT NULL,
  `label_ffessm_norm` VARCHAR(150) NOT NULL,
  `poids` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_code_label_norm` (`code`, `label_ffessm_norm`),
  KEY `idx_code` (`code`),
  KEY `idx_activite` (`activite`),
  KEY `idx_role` (`role`),
  KEY `idx_label_norm` (`label_ffessm_norm`),
  KEY `idx_activite_role_poids` (`activite`, `role`, `poids`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Correspondance code métier / libellé officiel FFESSM';

--
-- Données initiales : 78 correspondances issues du référentiel FFESSM
--
INSERT IGNORE INTO `#__gda_mapping_brevets` (`code`, `activite`, `role`, `label_ffessm`, `label_ffessm_norm`, `poids`) VALUES
('PB', 'Technique', 'pratiquant', 'Plongeur Bronze', 'PLONGEUR BRONZE', 1),
('PA', 'Technique', 'pratiquant', 'Plongeur Argent', 'PLONGEUR ARGENT', 2),
('PO', 'Technique', 'pratiquant', 'Plongeur Or', 'PLONGEUR OR', 3),
('P1', 'Technique', 'pratiquant', 'Niveau 1', 'NIVEAU 1', 4),
('P2', 'Technique', 'pratiquant', 'Niveau 2', 'NIVEAU 2', 8),
('P3', 'Technique', 'pratiquant', 'Niveau 3', 'NIVEAU 3', 10),
('P4', 'Technique', 'pratiquant', 'Niveau IV-GP', 'NIVEAU IV-GP', 11),
('P5', 'Technique', 'pratiquant', 'Plongeur Niveau 5', 'PLONGEUR NIVEAU 5', 12),
('PA20', 'Technique', 'pratiquant', 'Plongeur en autonomie 20 metres', 'PLONGEUR EN AUTONOMIE 20 METRES', 2),
('PE40', 'Technique', 'pratiquant', 'Plongeur encadré 40 mètres', 'PLONGEUR ENCADRE 40 METRES', 4),
('PA40', 'Technique', 'pratiquant', 'Plongeur en autonomie 40 mètres', 'PLONGEUR EN AUTONOMIE 40 METRES', 7),
('PE60', 'Technique', 'pratiquant', 'Plongeur encadré 60 mètres', 'PLONGEUR ENCADRE 60 METRES', 9),
('E1', 'Technique', 'encadrant', 'E1 - Initiateur-Directeur de bassin', 'E1 - INITIATEUR-DIRECTEUR DE BASSIN', 1),
('E2', 'Technique', 'encadrant', 'E2 - Niveau IV/GP/Directeur de bassin', 'E2 - NIVEAU IV/GP/DIRECTEUR DE BASSIN', 2),
('E3', 'Technique', 'encadrant', 'E3 - M.F.1.', 'E3 - MF1', 3),
('E4', 'Technique', 'encadrant', 'E4 - M.F.2.', 'E4 - MF2', 5),
('MF1(TSI)', 'Technique', 'encadrant', 'Tuteur de stage initiateur', 'TUTEUR DE STAGE INITIATEUR', 4),
('A1', 'Apnée', 'pratiquant', 'Apnéiste Niveau 1', 'APNEISTE NIVEAU 1', 1),
('A2', 'Apnée', 'pratiquant', 'Apnéiste Niveau 2', 'APNEISTE NIVEAU 2', 2),
('A3', 'Apnée', 'pratiquant', 'Apnéiste Niveau 3', 'APNEISTE NIVEAU 3', 3),
('A4', 'Apnée', 'pratiquant', 'Apnéiste Niveau 4', 'APNEISTE NIVEAU 4', 4),
('IE1', 'Apnée', 'encadrant', 'Initiateur-Entraîneur Apnée Niveau 1', 'INITIATEUR-ENTRAINEUR APNEE NIVEAU 1', 1),
('IE2', 'Apnée', 'encadrant', 'Initiateur-Entraîneur Apnée Niveau 2', 'INITIATEUR-ENTRAINEUR APNEE NIVEAU 2', 2),
('MEF1', 'Apnée', 'encadrant', 'Moniteur entraîneur Fédéral Apnée 1er degré', 'MONITEUR ENTRAINEUR FEDERAL APNEE 1ER DEGRE', 3),
('MEF2', 'Apnée', 'encadrant', 'Moniteur entraîneur Fédéral Apnée 2ème degré', 'MONITEUR ENTRAINEUR FEDERAL APNEE 2EME DEGRE', 4),
('Bio1', 'Biologie subaquatique', 'pratiquant', 'Plongeur Biologiste Niveau 1', 'PLONGEUR BIOLOGISTE NIVEAU 1', 1),
('Bio2', 'Biologie subaquatique', 'pratiquant', 'Plongeur Biologiste Niveau 2', 'PLONGEUR BIOLOGISTE NIVEAU 2', 2),
('FBio1', 'Biologie subaquatique', 'encadrant', 'Formateur Biologie 1er degré', 'FORMATEUR BIOLOGIE 1ER DEGRE', 1),
('FBio2', 'Biologie subaquatique', 'encadrant', 'Formateur Biologie 2ème degré', 'FORMATEUR BIOLOGIE 2EME DEGRE', 2),
('F1', 'Photographie subaquatique', 'pratiquant', 'Plongeur photographe Niveau 1', 'PLONGEUR PHOTOGRAPHE NIVEAU 1', 1),
('F2', 'Photographie subaquatique', 'pratiquant', 'Plongeur photographe Niveau 2', 'PLONGEUR PHOTOGRAPHE NIVEAU 2', 2),
('FF1', 'Photographie subaquatique', 'encadrant', 'Formateur photo Niveau 1', 'FORMATEUR PHOTO NIVEAU 1', 1),
('FF2', 'Photographie subaquatique', 'encadrant', 'Formateur photo Niveau 2', 'FORMATEUR PHOTO NIVEAU 2', 2),
('V1', 'Vidéo subaquatique', 'pratiquant', 'Plongeur videaste Niveau 1', 'PLONGEUR VIDEASTE NIVEAU 1', 1),
('V2', 'Vidéo subaquatique', 'pratiquant', 'Plongeur videaste Niveau 2', 'PLONGEUR VIDEASTE NIVEAU 2', 2),
('FV1', 'Vidéo subaquatique', 'encadrant', 'Formateur videaste Niveau 1', 'FORMATEUR VIDEASTE NIVEAU 1', 1),
('FV2', 'Vidéo subaquatique', 'encadrant', 'Formateur videaste Niveau 2', 'FORMATEUR VIDEASTE NIVEAU 2', 2),
('HP6', 'Handisub', 'pratiquant', 'P.E.S.H.1 - 6 mètres', 'PESH1 - 6 METRES', 1),
('HP12', 'Handisub', 'pratiquant', 'P.E.S.H.1 - 12 mètres', 'PESH1 - 12 METRES', 2),
('EH1', 'Handisub', 'encadrant', 'EH1 - Scaphandre', 'EH1 - SCAPHANDRE', 1),
('EH2', 'Handisub', 'encadrant', 'EH2 - Scaphandre', 'EH2 - SCAPHANDRE', 3),
('RIFAP', 'Secourisme (RIFA)', 'pratiquant', 'RIFA Plongée', 'RIFA PLONGEE', 1),
('RIFAA', 'Secourisme (RIFA)', 'pratiquant', 'RIFA Apnée', 'RIFA APNEE', 1),
('RIFAN', 'Secourisme (RIFA)', 'pratiquant', 'RIFA Nage avec palmes', 'RIFA NAGE AVEC PALMES', 1),
('ANTE', 'Secourisme (RIFA)', 'encadrant', 'Anteor', 'ANTEOR', 1),
('PN', 'Nitrox', 'pratiquant', 'Plongeur Nitrox', 'PLONGEUR NITROX', 1),
('PN-C', 'Nitrox', 'pratiquant', 'Plongeur Nitrox confirmé', 'PLONGEUR NITROX CONFIRME', 2),
('MONITEUR PN-C', 'Nitrox', 'encadrant', 'Moniteur Nitrox Confirmé', 'MONITEUR NITROX CONFIRME', 1),
('IENA', 'Nage avec palmes', 'encadrant', 'Initiateur entraîneur Nage avec Palmes', 'INITIATEUR ENTRAINEUR NAGE AVEC PALMES', 1),
('TIV', 'Contrôle des blocs (TIV)', 'pratiquant', 'Technicien Inspection Visuelle', 'TECHNICIEN INSPECTION VISUELLE', 1),
('AP', 'Apnée', 'pratiquant', 'Apnéiste / Indoor Freediver 1 CMAS', 'APNEISTE / INDOOR FREEDIVER 1 CMAS', 1),
('ACP', 'Apnée', 'pratiquant', 'Apnéiste confirmé / Indoor Freediver 2 CMAS', 'APNEISTE CONFIRME / INDOOR FREEDIVER 2 CMAS', 2),
('EH1M', 'Handisub', 'encadrant', 'MHPC - Module complémentaire Handi-Psychique Cognitif', 'MHPC - MODULE COMPLEMENTAIRE HANDI-PSYCHIQUE COGNITIF', 2),
('AEL', 'Apnée', 'pratiquant', 'Apnéiste en eau Libre / Outdoor Freediver 1 CMAS', 'APNEISTE EN EAU LIBRE / OUTDOOR FREEDIVER 1 CMAS', 1),
('ACEL', 'Apnée', 'pratiquant', 'Apnéiste confirmé en eau Libre / Outdoor Freediver 2 CMAS', 'APNEISTE CONFIRME EN EAU LIBRE / OUTDOOR FREEDIVER 2 CMAS', 2),
('AEEL', 'Apnée', 'pratiquant', 'Apnéiste expert en eau Libre / Outdoor Freediver 3 CMAS', 'APNEISTE EXPERT EN EAU LIBRE / OUTDOOR FREEDIVER 3 CMAS', 3),
('E3', 'Technique', 'encadrant', 'E3 - B.E.E.S. 1er degré', 'E3 - BEES 1ER DEGRE', 3),
('FB1', 'Biologie subaquatique', 'encadrant', 'Formateur Biologie 1er degré – Aptitude Formateur PB2', 'FORMATEUR BIOLOGIE 1ER DEGRE - APTITUDE FORMATEUR PB2', 1),
('IE2', 'Apnée', 'encadrant', 'Initiateur-Entraîneur Apnée N2 / 1* Star Freediving Instructor', 'INITIATEUR-ENTRAINEUR APNEE N2 / 1* STAR FREEDIVING INSTRUCTOR', 2),
('TIV', 'Contrôle des blocs (TIV)', 'pratiquant', 'Technicien Inspection Visuelle - Réactivation', 'TECHNICIEN INSPECTION VISUELLE - REACTIVATION', 1),
('F3', 'Photographie subaquatique', 'pratiquant', 'Plongeur photographe Niveau 3', 'PLONGEUR PHOTOGRAPHE NIVEAU 3', 3),
('JFA1', 'Apnée', 'encadrant', 'Juge Fédéral Apnée 1er degré', 'JUGE FEDERAL APNEE 1ER DEGRE', 5),
('RIFATSC', 'Secourisme (RIFA)', 'pratiquant', 'RIFA Tir sur cible', 'RIFA TIR SUR CIBLE', 1),
('IETSC', 'Tir sur cible subaquatique', 'encadrant', 'Initiateur entraîneur Tir sur Cible', 'INITIATEUR ENTRAINEUR TIR SUR CIBLE', 1),
('MFEH1', 'Handisub', 'encadrant', 'Moniteur Fédéral E.H.1 Scaphandre', 'MONITEUR FEDERAL EH1 SCAPHANDRE', 4),
('EH1A', 'Handisub', 'encadrant', 'EH1 - Apnée', 'EH1 - APNEE', 1),
('P1', 'Technique', 'pratiquant', 'Niveau 1 UCPA', 'NIVEAU 1 UCPA', 1),
('P1', 'Technique', 'pratiquant', 'Niveau 1 passerelle PADI', 'NIVEAU 1 PASSERELLE PADI', 1),
('P2', 'Technique', 'pratiquant', 'Niveau 2 UCPA', 'NIVEAU 2 UCPA', 3),
('TIV', 'Contrôle des blocs (TIV)', 'pratiquant', 'Technicien Inspection Visuelle - Recyclage', 'TECHNICIEN INSPECTION VISUELLE - RECYCLAGE', 1),
('APSP', 'Plongée sportive en piscine (PSP)', 'encadrant', 'Arbitre Plongée Sportive Piscine', 'ARBITRE PLONGEE SPORTIVE PISCINE', 1),
('CE', 'Technique', 'pratiquant', 'Combinaison étanche', 'COMBINAISON ETANCHE', 1),
('JFPSP1', 'Plongée sportive en piscine (PSP)', 'encadrant', 'Juge Fédéral Plongée Sportive en Piscine - 1er degré', 'JUGE FEDERAL PLONGEE SPORTIVE EN PISCINE - 1ER DEGRE', 2),
('JRNAP', 'Nage avec palmes', 'encadrant', 'Juge Régional Nage Avec Palmes', 'JUGE REGIONAL NAGE AVEC PALMES', 2),
('MSS', 'Technique', 'pratiquant', 'Module Sport Santé', 'MODULE SPORT SANTE', 1),
('RCFAP', 'Plongée technique / Recycleur', 'pratiquant', 'Plongeur Recycleur Circuit Fermé AP-Diving Inspiration diluant Air', 'PLONGEUR RECYCLEUR CIRCUIT FERME AP-DIVING INSPIRATION DILUANT AIR', 1),
('PSOUT1', 'Plongée souterraine', 'pratiquant', 'Plongeur souterrain Niveau 1', 'PLONGEUR SOUTERRAIN NIVEAU 1', 1),
('PSOUT2', 'Plongée souterraine', 'pratiquant', 'Plongeur souterrain Niveau 2', 'PLONGEUR SOUTERRAIN NIVEAU 2', 2);



--
-- Contrainte différée : ajoutée ici (après la création de gda_mapping_brevets) plutôt que
-- dans la définition de #__gda_brevets, qui est créée plus haut dans ce script.
--
ALTER TABLE `#__gda_brevets`
  ADD CONSTRAINT `gda_brevets_gda_mapping_brevets_FK` FOREIGN KEY (`id_mapping`) REFERENCES `#__gda_mapping_brevets` (`id`) ON DELETE SET NULL;
