-- ---------------------------------------------------------------------------------------------
-- Centralisation du mois de début de saison fédérale FFESSM (jusqu'ici dupliqué en dur dans deux
-- constantes PHP : AdhesionStatusHelper::MOIS_DEBUT_SAISON_LICENCE, pour le calcul de fin de
-- validité de la licence, et SouscriptionService::MOIS_DEBUT_SAISON_FEDERALE, pour le seuil de
-- validité minimale du CACI accepté par le secrétariat). Les deux constantes sont remplacées par
-- une lecture de cette clé de config (ConfHelper::getValue('MoisDebutSaisonFederale')), même
-- motif que les autres clés techniques déjà en base (IdTypeSaison, IdTypeFormation, IdTypeLoisir).
-- ---------------------------------------------------------------------------------------------

INSERT INTO `#__gda_conf` (`key`, `value`)
  SELECT 'MoisDebutSaisonFederale', '9' WHERE NOT EXISTS (SELECT 1 FROM `#__gda_conf` WHERE `key` = 'MoisDebutSaisonFederale');
