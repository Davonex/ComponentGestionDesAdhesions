-- ---------------------------------------------------------------------------------------------
-- 0.9.18 - Nouvelle nature de campagne "Boutique" (lien vitrine vers un formulaire Shop
-- HelloAsso, sans réservation : pas de rôles/places, absente de l'onglet Suivi des inscriptions
-- et du dashboard Accueil - voir CampagnesModel::getHelloAssoFormTypeParNature()).
--
-- Idempotence : id_type laissé à l'auto-incrément (une base déjà migrée peut avoir consommé
-- d'autres ids entre-temps), la clé #__gda_conf est ensuite retrouvée par son type_name plutôt
-- qu'un id supposé. Même motif que 0.9.11.sql/0.9.12.sql pour l'ajout d'une clé #__gda_conf.
-- ---------------------------------------------------------------------------------------------

INSERT INTO `#__gda_type_de_campagne` (`type_name`, `type_image`, `type_class`)
  SELECT 'Boutique', NULL, 'campagne-boutique'
  WHERE NOT EXISTS (SELECT 1 FROM `#__gda_type_de_campagne` WHERE `type_name` = 'Boutique');

INSERT INTO `#__gda_conf` (`key`, `value`)
  SELECT 'IdTypeBoutique', CAST(t.`id_type` AS CHAR)
  FROM `#__gda_type_de_campagne` t
  WHERE t.`type_name` = 'Boutique'
    AND NOT EXISTS (SELECT 1 FROM `#__gda_conf` WHERE `key` = 'IdTypeBoutique');

-- ---------------------------------------------------------------------------------------------
-- 0.9.18 - Responsable d'une campagne Formation / Loisir : compte Joomla (Membre du Bureau ou
-- Responsable de Groupe) prévenu par e-mail à chaque nouvelle demande d'inscription. NULL = pas
-- de responsable désigné (aucun mail). Un compte supprimé remet la colonne à NULL.
--
-- Idempotence : marqueur /** CAN FAIL **/ (un ALTER par instruction, comme 0.9.17.sql).
-- ---------------------------------------------------------------------------------------------

ALTER TABLE `#__gda_campagnes` ADD COLUMN `id_responsable` int DEFAULT NULL COMMENT 'Compte Joomla prévenu des nouvelles demandes d''inscription (Formation / Loisir)' AFTER `id_groupes` /** CAN FAIL **/;
ALTER TABLE `#__gda_campagnes` ADD COLUMN `sous_type` varchar(30) DEFAULT NULL COMMENT 'Sous-type d''une campagne Formation (fosse_apnee, fosse_technique_20, fosse_technique_12, rifax)' AFTER `id_type` /** CAN FAIL **/;
ALTER TABLE `#__gda_campagnes` ADD CONSTRAINT `gda_campagnes_users_FK` FOREIGN KEY (`id_responsable`) REFERENCES `#__users` (`id`) ON DELETE SET NULL /** CAN FAIL **/;
