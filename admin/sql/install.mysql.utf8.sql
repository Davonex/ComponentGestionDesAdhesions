-- Installation script for com_gdadhesions component v0.7.10
-- Creates all necessary tables with UTF8MB4 charset
-- Uses Joomla table prefix #__


-- drop if exists 
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `#__gda_composition_groupes`;
DROP TABLE IF EXISTS `#__gda_souscriptions`;
DROP TABLE IF EXISTS `#__gda_niveaux`;
DROP TABLE IF EXISTS `#__gda_brevets`;
DROP TABLE IF EXISTS `#__gda_profils`;
DROP TABLE IF EXISTS `#__gda_groupes`;
DROP TABLE IF EXISTS `#__gda_cotisation`;
DROP TABLE IF EXISTS `#__gda_type_de_campagne`;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--
-- Rechargement des données de la table `#__gda_type_de_campagne`
--
INSERT INTO `#__gda_type_de_campagne` (`id_type`, `type_name`, `type_image`, `type_class`) VALUES
(1, 'Saison', 'saison.jpg', 'campagne-saison'),
(2, 'Fosse', 'fosse.jpg', NULL),
(3, 'Sortie technique', 'sortie.jpg', 'campagne-sortie'),
(4, 'Sortie Club', 'sortie.jpg', 'campagne-sortie'),
(5, 'Formation', '', NULL),
(6, 'Fête du club', '', NULL);

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
  `active` tinyint(1) NOT NULL,
  `courante` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Saison de suivi courante (distincte de active = ouverte)',
  `id_article` int unsigned DEFAULT '0',
  `nbr_place` int unsigned DEFAULT NULL COMMENT 'Nombre place totale pour cette campagnes',
  `id_type` int unsigned DEFAULT NULL,
  `id_groupes` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effacer` int unsigned NOT NULL DEFAULT '0' COMMENT 'Campagne Effacer',
  PRIMARY KEY (`id_campagne`),
  UNIQUE KEY `id` (`id_campagne`) USING BTREE,
  CONSTRAINT `gda_campagnes_gda_type_de_campagne_FK` FOREIGN KEY (`id_type`) REFERENCES `#__gda_type_de_campagne` (`id_type`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liste des campagnes';

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
  PRIMARY KEY (`id_profil`),
  UNIQUE KEY `id_profile` (`id_profil`),
  CONSTRAINT `gda_profils_users_FK` FOREIGN KEY (`id_profil`) REFERENCES `#__users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='profil des adhérents (Nouveau ou Ancient)';

--
-- Structure de la table `#__gda_brevets`
--
CREATE TABLE IF NOT EXISTS `#__gda_brevets` (
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lieu` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `obtention` date DEFAULT NULL,
  `id_profil` int DEFAULT NULL,
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




