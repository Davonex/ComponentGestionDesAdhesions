-- ---------------------------------------------------------------------------------------------
-- Référentiel FFESSM (#__gda_mapping_brevets) : libellé d'affichage optionnel, distinct du
-- libellé officiel FFESSM (label_ffessm). Certains libellés officiels (ex: "Tuteur de stage
-- initiateur" pour le code E3 (TSI)) ne parlent pas à un public non initié — label_affichage
-- permet au Bureau de définir un texte plus clair (ex: "E3 - MF1 (TSI)"), sans jamais toucher au
-- libellé officiel ni au poids qui pilote le classement "meilleur brevet"
-- (BrevetService::getBrevetsShortListProfils()).
-- ---------------------------------------------------------------------------------------------

ALTER TABLE `#__gda_mapping_brevets`
  ADD COLUMN `label_affichage` VARCHAR(150) NULL DEFAULT NULL AFTER `label_ffessm_norm` /** CAN FAIL **/;

-- Filtre sur label_ffessm_norm plutôt que code : ce code a été renommé "E3 (TSI)" en base via
-- l'édition inline existante, alors que le seed d'install.mysql.utf8.sql porte encore "MF1(TSI)".
UPDATE `#__gda_mapping_brevets`
  SET `label_affichage` = 'E3 - MF1 (TSI)'
  WHERE `label_ffessm_norm` = 'TUTEUR DE STAGE INITIATEUR' AND `activite` = 'Technique' AND `role` = 'encadrant';
