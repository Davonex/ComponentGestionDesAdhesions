-- Ajout de la notion de saison courante, distincte de la saison ouverte (active)
-- `active`   = inscriptions possibles (saison ouverte)
-- `courante` = saison de suivi (CACI, licence, groupes) pour l'année en cours


ALTER TABLE `#__gda_campagnes` ADD COLUMN `courante` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Saison de suivi courante (distincte de active = ouverte)';

-- Reprise de continuité : la saison actuellement ouverte devient la saison courante
UPDATE `#__gda_campagnes` c
INNER JOIN `#__gda_conf` conf ON conf.`key` = 'IdTypeSaison'
SET c.`courante` = 1
WHERE c.`id_type` = conf.`value`
  AND c.`active` = 1
  AND c.`effacer` = 0;

-- Icones Font Awesome (fa-solid) pour les groupes affiches dans la vue site "Groupes"

UPDATE `#__gda_groupes` SET `icon` = 'fa-person-swimming' WHERE `groupe_name` = 'Prépa N1';
UPDATE `#__gda_groupes` SET `icon` = 'fa-water' WHERE `groupe_name` = 'Prépa N2';
UPDATE `#__gda_groupes` SET `icon` = 'fa-compass' WHERE `groupe_name` = 'Prépa N3';
UPDATE `#__gda_groupes` SET `icon` = 'fa-chalkboard-user' WHERE `groupe_name` = 'Prépa (E1/GP)';
UPDATE `#__gda_groupes` SET `icon` = 'fa-lungs' WHERE `groupe_name` = 'Apnéiste débutant';
UPDATE `#__gda_groupes` SET `icon` = 'fa-stopwatch' WHERE `groupe_name` = 'Apnéiste confirmé';
UPDATE `#__gda_groupes` SET `icon` = 'fa-camera-retro' WHERE `groupe_name` = 'Photo/Video';
UPDATE `#__gda_groupes` SET `icon` = 'fa-fish' WHERE `groupe_name` = 'Biologie Subaqua';
UPDATE `#__gda_groupes` SET `icon` = 'fa-arrows-rotate' WHERE `groupe_name` = 'Maintien des Acquis';
UPDATE `#__gda_groupes` SET `icon` = 'fa-universal-access' WHERE `groupe_name` = 'Plongée Handi';
UPDATE `#__gda_groupes` SET `icon` = 'fa-medal' WHERE `groupe_name` = 'Encadrant Impliqué';
UPDATE `#__gda_groupes` SET `icon` = 'fa-child' WHERE `groupe_name` = 'Section jeune';
