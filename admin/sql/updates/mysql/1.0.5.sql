-- ---------------------------------------------------------------------------------------------
-- 1.0.5 - Suivi et évaluation des élèves en formation (vue Suivi).
--
-- #__gda_competences : référentiel des compétences évaluées, par groupe de formation. Un groupe
-- qui porte au moins une compétence active apparaît comme un onglet de la vue Suivi.
-- #__gda_suivi_competences : une appréciation (et une observation facultative) par élève,
-- compétence et séance, pour une saison (id_campagne). La clé unique interdit les doublons : une
-- nouvelle sauvegarde de la même séance met la ligne à jour.
-- ---------------------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__gda_competences` (
  `id_competence` int unsigned NOT NULL AUTO_INCREMENT,
  `competence` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `techniques` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Techniques associées à la compétence, une par ligne',
  `id_groupe` int unsigned NOT NULL COMMENT 'Groupe de formation évalué (#__gda_groupes)',
  `ordre` int unsigned NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage dans le formulaire d''évaluation',
  `actif` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = retirée du formulaire, historique conservé',
  PRIMARY KEY (`id_competence`),
  KEY `gda_competences_groupe_IDX` (`id_groupe`, `ordre`),
  CONSTRAINT `gda_competences_gda_groupes_FK` FOREIGN KEY (`id_groupe`) REFERENCES `#__gda_groupes` (`id_groupe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Compétences évaluées par groupe de formation';

CREATE TABLE IF NOT EXISTS `#__gda_suivi_competences` (
  `id_suivi` int unsigned NOT NULL AUTO_INCREMENT,
  `id_campagne` int NOT NULL COMMENT 'Saison de la formation',
  `id_profil` int NOT NULL COMMENT 'Élève évalué',
  `id_competence` int unsigned NOT NULL,
  `date_seance` date NOT NULL,
  `appreciation` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'en_cours | acquis | maitrise (SuiviService)',
  `observation` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_evaluation` datetime NOT NULL,
  `id_moniteur` int DEFAULT NULL COMMENT 'Compte Joomla auteur de la dernière modification',
  PRIMARY KEY (`id_suivi`),
  UNIQUE KEY `uniq_suivi_seance` (`id_campagne`, `id_profil`, `id_competence`, `date_seance`),
  KEY `gda_suivi_competences_profil_IDX` (`id_profil`),
  KEY `gda_suivi_competences_competence_IDX` (`id_competence`),
  KEY `gda_suivi_competences_moniteur_IDX` (`id_moniteur`),
  CONSTRAINT `gda_suivi_competences_gda_campagnes_FK` FOREIGN KEY (`id_campagne`) REFERENCES `#__gda_campagnes` (`id_campagne`),
  CONSTRAINT `gda_suivi_competences_gda_profils_FK` FOREIGN KEY (`id_profil`) REFERENCES `#__gda_profils` (`id_profil`) ON DELETE CASCADE,
  CONSTRAINT `gda_suivi_competences_gda_competences_FK` FOREIGN KEY (`id_competence`) REFERENCES `#__gda_competences` (`id_competence`),
  CONSTRAINT `gda_suivi_competences_users_FK` FOREIGN KEY (`id_moniteur`) REFERENCES `#__users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Évaluation des compétences par élève et par séance';

-- ---------------------------------------------------------------------------------------------
-- Compétences du Niveau 1. L'id du groupe « Prépa N1 » dépend de l'installation : il est retrouvé
-- par son nom. Idempotent : rien n'est inséré si le groupe porte déjà des compétences.
-- ---------------------------------------------------------------------------------------------

INSERT INTO `#__gda_competences` (`competence`, `techniques`, `id_groupe`, `ordre`)
  SELECT c.`competence`, c.`techniques`, g.`id_groupe`, c.`ordre`
  FROM `#__gda_groupes` g
  JOIN (
              SELECT 'S''équiper et se déséquiper' AS `competence`, CONCAT_WS(CHAR(10), 'Gréage et dégréage', 'Capelage et décapelage', 'Choix de son matériel personnel') AS `techniques`, 1 AS `ordre`
    UNION ALL SELECT 'Se mettre à l''eau et en sortir', CONCAT_WS(CHAR(10), 'Saut droit', 'Bascule arrière', 'Départ plage', 'Sortie de l''eau'), 2
    UNION ALL SELECT 'Évoluer dans l''eau - S''immerger', CONCAT_WS(CHAR(10), 'Canard', 'Phoque'), 3
    UNION ALL SELECT 'Évoluer dans l''eau - Se propulser', CONCAT_WS(CHAR(10), 'Palmage ventral en surface', 'Palmage dorsal', 'Palmage de sustentation', 'Palmage en immersion', 'Nage capelé'), 4
    UNION ALL SELECT 'Évoluer dans l''eau - Se ventiler', CONCAT_WS(CHAR(10), 'Ventilation en immersion', 'Ventilation sur tuba et vidage de tuba', 'Vidage de masque', 'Lâcher et reprise d''embout'), 5
    UNION ALL SELECT 'Évoluer dans l''eau - S''équilibrer', CONCAT_WS(CHAR(10), 'Gestion du gilet de stabilisation', 'Poumon ballast'), 6
    UNION ALL SELECT 'Communiquer, appliquer les conduites de sécurité', CONCAT_WS(CHAR(10), 'Exécution des signes conventionnels'), 7
    UNION ALL SELECT 'Respecter le milieu et l''environnement', CONCAT_WS(CHAR(10), 'Déplacements équilibrés'), 8
    UNION ALL SELECT 'Retourner en surface', CONCAT_WS(CHAR(10), 'Maîtrise de la vitesse de remontée', 'Tenue d''un palier', 'Tour d''horizon', 'Gonflage du gilet en surface', 'Remontée en expiration contrôlée'), 9
  ) c
  WHERE g.`groupe_name` = 'Prépa N1'
    AND NOT EXISTS (SELECT 1 FROM `#__gda_competences` x WHERE x.`id_groupe` = g.`id_groupe`);
