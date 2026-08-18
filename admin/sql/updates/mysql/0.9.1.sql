-- Refonte de la vue Campagnes : consolidation de #__gda_type_de_campagne sur exactement
-- 5 natures (Saison, Formation, Sortie, Soirée, Boutique) et ajout du nombre de places
-- réservables par inscription sur #__gda_campagnes.
--
-- Cible :
--   1 = Saison    (inchangé)
--   2 = Formation (ex "Fosse", garde ses campagnes actuelles)
--   3 = Sortie    (ex "Sortie technique", reçoit les campagnes ex-"Sortie Club")
--   4 = Soirée    (ex "Sortie Club", reçoit les campagnes ex-"Fête du club")
--   5 = Boutique  (nouvelle ligne, sur l'id libéré par la suppression de l'ancienne ligne "Formation")
--
-- ATTENTION : script à relire et exécuter manuellement sur la base joomla_ncb5, pas exécuté
-- automatiquement par Claude Code.

-- 1) Remap des campagnes existantes AVANT de renommer/supprimer les types (ordre important)
UPDATE `#__gda_campagnes` SET id_type = 3 WHERE id_type = 4; -- Sortie Club -> Sortie (id 3)
UPDATE `#__gda_campagnes` SET id_type = 2 WHERE id_type = 5; -- Formation -> Formation (id 2)
UPDATE `#__gda_campagnes` SET id_type = 4 WHERE id_type = 6; -- Fête du club -> Soirée (id 4)

-- 2) Renommage / repurpose des lignes conservées
UPDATE `#__gda_type_de_campagne` SET type_name = 'Formation', type_class = 'campagne-formation' WHERE id_type = 2;
UPDATE `#__gda_type_de_campagne` SET type_name = 'Sortie',    type_class = 'campagne-sortie'    WHERE id_type = 3;
UPDATE `#__gda_type_de_campagne` SET type_name = 'Soirée',    type_class = 'campagne-soiree'    WHERE id_type = 4;

-- 3) Suppression des lignes devenues orphelines
DELETE FROM `#__gda_type_de_campagne` WHERE id_type IN (5, 6);

-- 4) Réinsertion de Boutique sur l'id libéré (5), pour garder les 5 types sur des ids contigus 1-5
INSERT INTO `#__gda_type_de_campagne` (`id_type`, `type_name`, `type_image`, `type_class`)
VALUES (5, 'Boutique', '', 'campagne-boutique');

-- 5) Nombre de places réservables en une seule souscription (Formation = 1 fixe, Sortie/Soirée
--    configurable, ignoré pour Boutique qui n'a pas de réservation)
ALTER TABLE `#__gda_campagnes`
  ADD COLUMN `nbr_place_max_inscription` int unsigned DEFAULT 1
  COMMENT 'Nombre de places réservables en une seule souscription'
  AFTER `nbr_place`;

-- 6) Id du type "Formation" (cf. tableau ci-dessus), utilisé par CampagnesController::suivi()
--    pour savoir quel layout de suivi des inscriptions afficher.
INSERT INTO `#__gda_conf` (`key`, `value`) VALUES ('IdTypeFormation', '2');
