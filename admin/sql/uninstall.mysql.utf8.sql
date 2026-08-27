-- Uninstall script for com_gdadhesions component
-- Removes all component tables (order matters for foreign keys)

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `#__gda_composition_groupes`;
DROP TABLE IF EXISTS `#__gda_mapping_brevets`;
DROP TABLE IF EXISTS `#__gda_reservation_places`;
DROP TABLE IF EXISTS `#__gda_reservation`;
DROP TABLE IF EXISTS `#__gda_souscriptions`;
DROP TABLE IF EXISTS `#__gda_brevets`;
DROP TABLE IF EXISTS `#__gda_profils`;
DROP TABLE IF EXISTS `#__gda_groupes`;
DROP TABLE IF EXISTS `#__gda_cotisation`;
DROP TABLE IF EXISTS `#__gda_campagne_roles`;
DROP TABLE IF EXISTS `#__gda_role_de_campagne`;
DROP TABLE IF EXISTS `#__gda_type_de_campagne`;
DROP TABLE IF EXISTS `#__gda_campagnes`;
DROP TABLE IF EXISTS `#__gda_conf`;
SET FOREIGN_KEY_CHECKS = 1;