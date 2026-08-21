-- ---------------------------------------------------------------------------------------------
-- Activité rattachée à un groupe du club (#__gda_groupes.activite).
--
-- Permet de rapprocher un groupe du club du référentiel FFESSM : les valeurs proposées à la
-- saisie sont les activités distinctes de #__gda_mapping_brevets (Technique, Apnée, Handisub,
-- Nitrox, ...), plus la valeur « Toutes » pour un groupe transverse à toutes les activités.
--
-- Volontairement SANS clé étrangère vers #__gda_mapping_brevets : cette table de référence n'a
-- pas de clé sur `activite` (plusieurs lignes par activité), et « Toutes » n'y figure pas. La
-- cohérence est portée par la liste proposée à la saisie (GroupesService::getActivitesDisponibles).
--
-- Colonne obligatoire : DEFAULT 'Toutes' vaut aussi valeur d'initialisation pour les groupes déjà
-- enregistrés, l'affectation fine se faisant ensuite depuis la vue Saisons (onglet « Saison
-- courante », panneau « Groupes du club »).
-- ---------------------------------------------------------------------------------------------

ALTER TABLE `#__gda_groupes`
  ADD COLUMN `activite` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Toutes'
  COMMENT 'Activité FFESSM du groupe (valeurs de #__gda_mapping_brevets.activite, ou « Toutes »)'
  AFTER `groupe_name`;


-- ---------------------------------------------------------------------------------------------
-- Vue « Brevets » (gestion du référentiel FFESSM et du rattachement des brevets adhérents).
--
-- 1. Clé primaire sur #__gda_brevets.
--    La table n'en avait aucune : les brevets n'étaient manipulés qu'en lot par profil
--    (BrevetService::replaceBrevets, annule et remplace), ce qui ne demandait pas d'identifiant
--    de ligne. La nouvelle vue édite en revanche UNE ligne précise (correction du libellé,
--    rattachement au référentiel) : sans clé, deux brevets homonymes d'un même adhérent seraient
--    modifiés ensemble. L'AUTO_INCREMENT se remplit seul, replaceBrevets() reste inchangé.
--
-- 2. #__gda_mapping_brevets.niveau renommé en `poids`.
--    « niveau » entrait en collision sémantique avec les niveaux de plongée (N1/N2/N3), que la
--    table stocke justement dans `code`. Il s'agit d'un rang d'importance au sein du couple
--    (activite, role), servant à retrouver le brevet le plus élevé d'un adhérent par activité :
--    « poids » le décrit sans ambiguïté. Colonne introduite en 0.9.7, renommée tant que le coût
--    reste faible.
-- ---------------------------------------------------------------------------------------------

ALTER TABLE `#__gda_brevets`
  ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT
  COMMENT 'Clé technique : permet de cibler une ligne précise depuis la vue Brevets'
  FIRST,
  ADD PRIMARY KEY (`id`);

ALTER TABLE `#__gda_mapping_brevets`
  CHANGE COLUMN `niveau` `poids` TINYINT UNSIGNED NOT NULL DEFAULT 1
  COMMENT 'Rang d''importance au sein du couple (activite, role) - plus haut = plus important';

ALTER TABLE `#__gda_mapping_brevets`
  DROP INDEX `idx_activite_role_niveau`,
  ADD KEY `idx_activite_role_poids` (`activite`, `role`, `poids`);
