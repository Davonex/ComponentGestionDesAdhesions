-- Rôle par place à la souscription, déterminé par la nature de la campagne (pas configurable
-- par campagne individuelle) :
--   - Formation : rôles fixes "Encadrants" / "Participants"
--   - Sortie    : rôles fixes "Plongeur" / "Non Plongeur"
--   - Soirée / Boutique : pas de rôle
--
-- Portée : uniquement le formulaire de gestion de campagne (switch "rôle par place" + affichage
-- de la liste fixe correspondante). L'attribution effective d'un rôle par place à la souscription
-- (#__gda_souscriptions), le rapport et l'intégration HelloAsso sont traités plus tard.

CREATE TABLE IF NOT EXISTS `#__gda_role_de_campagne` (
  `id_type` int unsigned NOT NULL,
  `roles` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Rôles proposés pour cette nature, séparés par ;',
  PRIMARY KEY (`id_type`),
  CONSTRAINT `gda_role_de_campagne_gda_type_de_campagne_FK` FOREIGN KEY (`id_type`) REFERENCES `#__gda_type_de_campagne` (`id_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liste fixe des rôles par nature de campagne (Formation, Sortie, ...)';

INSERT INTO `#__gda_role_de_campagne` (`id_type`, `roles`) VALUES
(2, 'Encadrants;Participants'),
(3, 'Plongeur;Non Plongeur');

ALTER TABLE `#__gda_campagnes`
  ADD COLUMN `role_actif` tinyint(1) unsigned NOT NULL DEFAULT 0
  COMMENT 'Un rôle est demandé par place à la souscription (liste fixe déterminée par la nature)'
  AFTER `reservation_multiple`;

-- Date et heure de l'événement lui-même, à ne pas confondre avec date_debut / date_fin qui
-- délimitent la période d'ouverture des souscriptions. Nullable : une campagne de type Boutique
-- n'a pas de date d'événement.
ALTER TABLE `#__gda_campagnes`
  ADD COLUMN `date_evenement` datetime DEFAULT NULL
  COMMENT 'Date et heure de l''événement (distinct de la période de souscription)'
  AFTER `date_fin`;


-- ---------------------------------------------------------------------------------------------
-- Réservations aux campagnes (Formation / Sortie / Soirée / Boutique).
--
-- #__gda_souscriptions reste réservée aux souscriptions de la SAISON (workflow CACI / cotisation
-- / licence géré par le secrétariat). Les réservations aux autres campagnes vivent ici : pas de
-- colonnes de validation saison, mais une notion de places, de file d'attente et de rôle.
-- ---------------------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__gda_reservation` (
  `id_reservation` int unsigned NOT NULL AUTO_INCREMENT,
  `id_campagne` int NOT NULL COMMENT 'Campagne concernée (jamais une campagne de type Saison)',
  `id_profil` int NOT NULL,
  `date_reservation` datetime DEFAULT NULL COMMENT 'Horodatage de la réservation initiale : rang dans la file d''attente',
  `date_demande` datetime DEFAULT NULL COMMENT 'Horodatage de la dernière demande de places supplémentaires : rang du complément dans la file',
  `nbr_places` int unsigned NOT NULL DEFAULT 1 COMMENT 'Places demandées',
  `nbr_places_confirmees` int unsigned NOT NULL DEFAULT 0 COMMENT 'Places effectivement accordées (< nbr_places = complément en attente)',
  `statut` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'attente' COMMENT 'confirmee | attente | annulee',
  `commentaire` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_order` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Commande HelloAsso (paiement)',
  `last_update` datetime DEFAULT NULL,
  PRIMARY KEY (`id_reservation`),
  UNIQUE KEY `uniq_campagne_profil` (`id_campagne`,`id_profil`),
  KEY `gda_reservation_gda_profils_FK` (`id_profil`),
  CONSTRAINT `gda_reservation_gda_campagnes_FK` FOREIGN KEY (`id_campagne`) REFERENCES `#__gda_campagnes` (`id_campagne`),
  CONSTRAINT `gda_reservation_gda_profils_FK` FOREIGN KEY (`id_profil`) REFERENCES `#__gda_profils` (`id_profil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Réservations aux campagnes hors saison';

-- Une ligne par place réservée, uniquement quand la campagne a role_actif = 1.
-- Sinon la table reste vide : nbr_places de la réservation parente fait foi.
CREATE TABLE IF NOT EXISTS `#__gda_reservation_places` (
  `id_place` int unsigned NOT NULL AUTO_INCREMENT,
  `id_reservation` int unsigned NOT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rôle choisi pour cette place, parmi #__gda_role_de_campagne',
  `tri` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_place`),
  KEY `gda_reservation_places_gda_reservation_FK` (`id_reservation`),
  CONSTRAINT `gda_reservation_places_gda_reservation_FK` FOREIGN KEY (`id_reservation`) REFERENCES `#__gda_reservation` (`id_reservation`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rôle par place réservée (campagnes avec role_actif = 1)';
