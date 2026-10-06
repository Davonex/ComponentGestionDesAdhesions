-- ---------------------------------------------------------------------------------------------
-- 1.0.6 - Compétences des Niveaux 2 et 3, et nouvelle échelle d'appréciation (vue Suivi).
--
-- Compétences préfixées par leur domaine : « Commun » (aptitudes PA20 et PE40), « PA20 » (plongeur
-- autonome 20 m) et « PE40 » (plongeur encadré 40 m). L'id du groupe « Prépa N2 » dépend de
-- l'installation : il est retrouvé par son nom. Idempotent : rien n'est inséré si le groupe porte
-- déjà des compétences.
-- ---------------------------------------------------------------------------------------------

INSERT INTO `#__gda_competences` (`competence`, `techniques`, `id_groupe`, `ordre`)
  SELECT c.`competence`, c.`techniques`, g.`id_groupe`, c.`ordre`
  FROM `#__gda_groupes` g
  JOIN (
              SELECT 'Commun - S''équiper et se déséquiper, se mettre à l''eau et en sortir' AS `competence`, CONCAT_WS(CHAR(10), 'Gréage et dégréage', 'Capelage et décapelage', 'Saut droit et bascule arrière, remontée à l''échelle') AS `techniques`, 1 AS `ordre`
    UNION ALL SELECT 'Commun - S''immerger, se propulser, se ventiler', CONCAT_WS(CHAR(10), 'Canard et phoque', 'Palmages', 'Remontée en expiration contrôlée (REC)', 'Descente et remontée'), 2
    UNION ALL SELECT 'Commun - Respecter le milieu et l''environnement', CONCAT_WS(CHAR(10), 'Aisance aquatique'), 3
    UNION ALL SELECT 'PA20 - Être attentif au matériel de ses équipiers', CONCAT_WS(CHAR(10), 'Mise en œuvre de son propre matériel', 'Connaissance du matériel des équipiers'), 4
    UNION ALL SELECT 'PA20 - Évoluer en autonomie', CONCAT_WS(CHAR(10), 'Sécurité de la palanquée'), 5
    UNION ALL SELECT 'PA20 - Planifier la plongée en fonction des consignes du DP', CONCAT_WS(CHAR(10), 'Compréhension des directives du DP', 'Compréhension de la topologie du site de plongée, orientation', 'Détermination du profil de la plongée et des différentes procédures en immersion'), 6
    UNION ALL SELECT 'PA20 - Intervenir et porter assistance à un plongeur en difficulté', CONCAT_WS(CHAR(10), 'Observation, compréhension et réaction face à un incident'), 7
    UNION ALL SELECT 'PE40 - Se ventiler, s''équilibrer', CONCAT_WS(CHAR(10), 'Ventilation en surface et en immersion', 'Vidage du masque', 'Stabilisation'), 8
    UNION ALL SELECT 'PE40 - Communiquer avec le guide de palanquée', CONCAT_WS(CHAR(10), 'Connaissance de tous les signes et codes'), 9
    UNION ALL SELECT 'PE40 - Retourner en surface', CONCAT_WS(CHAR(10), 'Gestion de la désaturation', 'Gestion d''une remontée isolée'), 10
    UNION ALL SELECT 'PE40 - Intervenir en relais sur un équipier en difficulté', CONCAT_WS(CHAR(10), 'Intervention en relais'), 11
  ) c
  WHERE g.`groupe_name` = 'Prépa N2'
    AND NOT EXISTS (SELECT 1 FROM `#__gda_competences` x WHERE x.`id_groupe` = g.`id_groupe`);

-- ---------------------------------------------------------------------------------------------
-- Compétences du Niveau 3, préfixées par leur domaine : « PA40 » (plongeur autonome 40 m),
-- « PE60 » (plongeur encadré 60 m) et « PA60 » (plongeur autonome 60 m). Groupe « Prépa N3 »
-- retrouvé par son nom.
-- Idempotent : rien n'est inséré si le groupe porte déjà des compétences.
-- ---------------------------------------------------------------------------------------------

INSERT INTO `#__gda_competences` (`competence`, `techniques`, `id_groupe`, `ordre`)
  SELECT c.`competence`, c.`techniques`, g.`id_groupe`, c.`ordre`
  FROM `#__gda_groupes` g
  JOIN (
              SELECT 'PA40 - Planifier la plongée' AS `competence`, CONCAT_WS(CHAR(10), 'Prise en compte des directives du DP', 'Compréhension de la topologie du site, orientation', 'Détermination du profil de la plongée et des différentes procédures en immersion') AS `techniques`, 1 AS `ordre`
    UNION ALL SELECT 'PA40 - Évoluer en autonomie', CONCAT_WS(CHAR(10), 'Orientation', 'Évolution subaquatique', 'Désaturation'), 2
    UNION ALL SELECT 'PA40 - Intervenir et porter assistance à un plongeur en difficulté', CONCAT_WS(CHAR(10), 'Observation, compréhension et réaction face à un incident'), 3
    UNION ALL SELECT 'PE60 - S''adapter à la profondeur', CONCAT_WS(CHAR(10), 'Stabilisation', 'Mise en œuvre de l''ensemble des autres techniques'), 4
    UNION ALL SELECT 'PA60 - Organiser la plongée', CONCAT_WS(CHAR(10), 'Choix du site', 'Organisation des conditions de la plongée', 'Sécurisation de l''activité'), 5
    UNION ALL SELECT 'PA60 - Évoluer en autonomie', CONCAT_WS(CHAR(10), 'Orientation', 'Évolution subaquatique', 'Désaturation'), 6
  ) c
  WHERE g.`groupe_name` = 'Prépa N3'
    AND NOT EXISTS (SELECT 1 FROM `#__gda_competences` x WHERE x.`id_groupe` = g.`id_groupe`);

-- ---------------------------------------------------------------------------------------------
-- Échelle d'appréciation : « En cours d'acquisition / Acquis / Maîtrisé » devient
-- « Non acquis / En cours d'acquisition / Acquis » (SuiviService). « Maîtrisé » n'existe plus :
-- les évaluations qui le portaient passent à « Acquis ». Idempotent.
-- ---------------------------------------------------------------------------------------------

UPDATE `#__gda_suivi_competences` SET `appreciation` = 'acquis' WHERE `appreciation` = 'maitrise';

ALTER TABLE `#__gda_suivi_competences`
  MODIFY `appreciation` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'non_acquis | en_cours | acquis (SuiviService)';