-- "Nombre de places réservables par inscription" devient un simple switch Oui/Non :
-- False = 1 place fixe, rien n'est demandé à la souscription.
-- True  = le nombre de places souhaité est demandé à la souscription.
--
-- Normalise d'abord les valeurs existantes (ancienne sémantique : nombre de places, 1 à 20)
-- vers un booléen (> 1 => activé), puis renomme/retype la colonne.

UPDATE `#__gda_campagnes`
SET `nbr_place_max_inscription` = CASE WHEN `nbr_place_max_inscription` > 1 THEN 1 ELSE 0 END;

ALTER TABLE `#__gda_campagnes`
  CHANGE COLUMN `nbr_place_max_inscription` `reservation_multiple` tinyint(1) unsigned NOT NULL DEFAULT 0
  COMMENT 'Active la demande du nombre de places à la souscription (0 = 1 place fixe, 1 = le nombre est demandé)';
