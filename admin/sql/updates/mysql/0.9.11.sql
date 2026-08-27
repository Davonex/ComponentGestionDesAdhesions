-- ---------------------------------------------------------------------------------------------
-- Capacité par rôle pour les campagnes avec role_actif = 1.
--
-- Jusqu'ici, role_actif ne servait qu'à demander un rôle informatif par place ; la capacité
-- (#__gda_campagnes.nbr_place) restait un pool global unique. Un adhérent choisissant un rôle
-- déjà complet pouvait donc être confirmé alors que ce rôle précis n'avait plus de place, et à
-- l'inverse la promotion automatique de la file d'attente (voir ReservationService::annuler())
-- pouvait faire avancer n'importe qui, pas seulement les adhérents en attente pour le même rôle.
--
-- #__gda_campagne_roles porte désormais la capacité PAR RÔLE pour une occurrence de campagne
-- donnée (ex: Formation "Fosse Lagny" -> Encadrants: 5, Participants: 15). Les rôles eux-mêmes
-- restent définis par nature dans #__gda_role_de_campagne (non touchée) : cette table ne fait
-- que répartir la capacité de CETTE campagne entre les rôles proposés par sa nature.
--
-- gda_campagnes.nbr_place reste la source de vérité pour les campagnes role_actif = 0 (inchangé) ;
-- pour role_actif = 1, la capacité totale affichée est désormais calculée à la volée comme la
-- somme des lignes ci-dessous (voir ReservationService::getCapaciteTotale() /
-- getSelectCapaciteTotale()), plutôt que dupliquée dans gda_campagnes.nbr_place : une seule
-- source de vérité, pas de risque de désynchronisation si les capacités par rôle sont modifiées.
--
-- Clé primaire composite (id_campagne, role) : une ligne par rôle et par campagne, pas de colonne
-- technique nécessaire. ON DELETE CASCADE : la répartition par rôle n'a plus de sens si la
-- campagne est supprimée.
-- ---------------------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__gda_campagne_roles` (
  `id_campagne` int NOT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Rôle, parmi #__gda_role_de_campagne pour la nature de la campagne',
  `nbr_place` int unsigned NOT NULL DEFAULT 0 COMMENT 'Capacité de ce rôle pour cette campagne',
  PRIMARY KEY (`id_campagne`, `role`),
  CONSTRAINT `gda_campagne_roles_gda_campagnes_FK` FOREIGN KEY (`id_campagne`) REFERENCES `#__gda_campagnes` (`id_campagne`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Capacité par rôle, pour les campagnes avec role_actif = 1';


-- ---------------------------------------------------------------------------------------------
-- Consolidation des natures de campagne : Formation / Loisir (Sortie + Soirée + Boutique
-- fusionnent en "Loisir"), rôles toujours actifs, capacité par rôle systématique, une réservation
-- peut désormais mélanger plusieurs rôles (ex: 2 Plongeur + 1 Non-Plongeur en une seule fois).
--
-- Conséquence architecturale : #__gda_reservation_places devient l'unité ATOMIQUE de capacité et
-- de statut (1 ligne = 1 place = 1 rôle = 1 statut), #__gda_reservation redevient une simple
-- enveloppe (qui, quand, commentaire, commande HelloAsso, annulée ou non). Avant, la contrainte
-- "role_actif = 1 => reservation_multiple = 0" empêchait une réservation de porter plus d'un
-- rôle ; cette contrainte disparaît avec role_actif, d'où la nécessité de suivre le statut par
-- PLACE plutôt que par réservation (une réservation Loisir peut être confirmée sur un rôle et en
-- attente sur un autre en même temps).
--
-- Idempotence des ALTER TABLE ci-dessous : marqueur /** CAN FAIL **/ (mécanisme standard de
-- Joomla\CMS\Installer\Installer::parseSQLFiles(), déjà utilisé par Joomla core lui-même dans
-- ses propres fichiers sql/updates) plutôt qu'un DDL conditionnel dynamique (SET @sql = ...;
-- PREPARE/EXECUTE) : le pilote mysqli de Joomla exécute les requêtes via son propre protocole de
-- requêtes préparées, qui interdit d'imbriquer un PREPARE SQL dynamique à l'intérieur ("This
-- command is not supported in the prepared statement protocol yet") - une instruction PREPARE ne
-- peut donc pas être exécutée par l'installeur réel, même si elle fonctionne en ligne de commande
-- mysql directe. Avec /** CAN FAIL **/, une colonne/contrainte déjà existante fait simplement
-- échouer silencieusement CETTE instruction (log, pas d'arrêt), sans bloc conditionnel.
-- ---------------------------------------------------------------------------------------------

-- 1. Natures : id_type=3 (Sortie, jamais utilisée) devient "Loisir", récupère les campagnes
--    Soirée(4)/Boutique(5) puis ces deux lignes sont supprimées.
UPDATE `#__gda_type_de_campagne` SET `type_name` = 'Loisir', `type_class` = 'campagne-loisir'
  WHERE `id_type` = 3;
UPDATE `#__gda_campagnes` SET `id_type` = 3 WHERE `id_type` IN (4, 5);
DELETE FROM `#__gda_type_de_campagne` WHERE `id_type` IN (4, 5);

-- 2. Rôles par défaut (gabarit de préremplissage à la création, non administrable) : Formation
--    aligné sur la terminologie brevets (pratiquant/encadrant), Loisir reprend les rôles Sortie.
--    REPLACE (pas UPDATE) : la ligne id_type=2 a dérivé manuellement en base pendant les tests
--    précédents ("Pratiquant;Encadrants"), on ne suppose pas de valeur de départ.
REPLACE INTO `#__gda_role_de_campagne` (`id_type`, `roles`) VALUES (2, 'Pratiquant;Encadrant');
REPLACE INTO `#__gda_role_de_campagne` (`id_type`, `roles`) VALUES (3, 'Plongeur;Non plongeur');

-- 3. #__gda_reservation_places : ajout des colonnes id_campagne (dénormalisée, évite une
--    jointure sur les requêtes d'occupation/rang très fréquentes), statut, date_rang.
ALTER TABLE `#__gda_reservation_places` ADD COLUMN `id_campagne` int NOT NULL DEFAULT 0 COMMENT 'Dénormalisé depuis gda_reservation.id_campagne : évite une jointure sur les requêtes d''occupation/rang, très fréquentes' AFTER `id_reservation` /** CAN FAIL **/;
ALTER TABLE `#__gda_reservation_places` ADD COLUMN `statut` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'attente' COMMENT 'confirmee | attente | annulee' AFTER `role` /** CAN FAIL **/;
ALTER TABLE `#__gda_reservation_places` ADD COLUMN `date_rang` datetime DEFAULT NULL COMMENT 'Horodatage de création de cette place : rang FIFO dans la file d''attente de (id_campagne, role)' AFTER `statut` /** CAN FAIL **/;

-- Reprise exacte des places déjà enregistrées (campagnes déjà role_actif=1, ex: 47) : le
-- formulaire imposait jusqu'ici "1 réservation = 1 rôle = 1 statut", la reprise est donc sans
-- perte ni ambiguïté. Idempotent (id_campagne=0 = pas encore repris) — nécessite que #__gda_reservation
-- porte encore sa colonne `statut` (supprimée à l'étape 6) : c'est pourquoi cette reprise est
-- placée ici, avant l'étape 6, et non après.
UPDATE `#__gda_reservation_places` rp
  INNER JOIN `#__gda_reservation` r ON r.id_reservation = rp.id_reservation
  SET rp.id_campagne = r.id_campagne, rp.statut = r.statut, rp.date_rang = r.date_reservation
  WHERE rp.id_campagne = 0 /** CAN FAIL **/;

-- Backfill des réservations actives SANS place (campagnes encore role_actif=0, ex: 40) : une
-- ligne par réservation active, rôle = 1er rôle par défaut de la nature. Générique (aucune
-- campagne codée en dur), idempotent (LEFT JOIN ... IS NULL). Dépend elle aussi de
-- #__gda_reservation.statut, voir remarque ci-dessus.
INSERT INTO `#__gda_reservation_places` (`id_reservation`, `id_campagne`, `role`, `statut`, `date_rang`, `tri`)
SELECT r.id_reservation, r.id_campagne,
       SUBSTRING_INDEX(rdc.roles, ';', 1),
       IF(r.statut = 'annulee', 'annulee', 'confirmee'),
       r.date_reservation, 0
FROM `#__gda_reservation` r
INNER JOIN `#__gda_campagnes` c ON c.id_campagne = r.id_campagne
INNER JOIN `#__gda_role_de_campagne` rdc ON rdc.id_type = c.id_type
LEFT JOIN `#__gda_reservation_places` rp ON rp.id_reservation = r.id_reservation
WHERE rp.id_place IS NULL /** CAN FAIL **/;

-- 4. #__gda_campagne_roles : capacité par défaut pour les campagnes qui n'en ont pas encore
--    (ex-role_actif=0), reprise de leur ancien nbr_place global sur le 1er rôle par défaut de
--    leur nature — le Bureau rééquilibre ensuite via le nouveau formulaire. Générique, idempotent.
INSERT INTO `#__gda_campagne_roles` (`id_campagne`, `role`, `nbr_place`)
SELECT c.id_campagne, SUBSTRING_INDEX(rdc.roles, ';', 1), c.nbr_place
FROM `#__gda_campagnes` c
INNER JOIN `#__gda_role_de_campagne` rdc ON rdc.id_type = c.id_type
LEFT JOIN `#__gda_campagne_roles` cr ON cr.id_campagne = c.id_campagne
WHERE cr.id_campagne IS NULL AND c.effacer = 0 AND c.id_type != (SELECT CAST(`value` AS UNSIGNED) FROM `#__gda_conf` WHERE `key` = 'IdTypeSaison');

-- 5. #__gda_reservation_places : rôle/date_rang deviennent obligatoires (toutes les lignes en
--    ont désormais une, cf. reprise + backfill ci-dessus), FK sur id_campagne, index de lecture.
ALTER TABLE `#__gda_reservation_places`
  MODIFY COLUMN `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  MODIFY COLUMN `date_rang` datetime NOT NULL;

ALTER TABLE `#__gda_reservation_places` ADD CONSTRAINT `gda_reservation_places_gda_campagnes_FK` FOREIGN KEY (`id_campagne`) REFERENCES `#__gda_campagnes` (`id_campagne`) ON DELETE CASCADE /** CAN FAIL **/;
ALTER TABLE `#__gda_reservation_places` ADD KEY `gda_reservation_places_occupation_IDX` (`id_campagne`, `role`, `statut`) /** CAN FAIL **/;
ALTER TABLE `#__gda_reservation_places` ADD KEY `gda_reservation_places_rang_IDX` (`id_campagne`, `role`, `date_rang`) /** CAN FAIL **/;

-- 6. #__gda_reservation : devient une enveloppe légère. annulee reprend statut='annulee', puis
--    nbr_places/nbr_places_confirmees/statut/date_demande disparaissent (déplacés au niveau
--    place ci-dessus). L'UPDATE de reprise doit s'exécuter avant le DROP COLUMN `statut`.
ALTER TABLE `#__gda_reservation` ADD COLUMN `annulee` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Réservation annulée ("Me désinscrire") : ses places restent tracées dans #__gda_reservation_places' AFTER `id_profil` /** CAN FAIL **/;

UPDATE `#__gda_reservation` SET `annulee` = 1 WHERE `statut` = 'annulee' /** CAN FAIL **/;

ALTER TABLE `#__gda_reservation` DROP COLUMN `nbr_places` /** CAN FAIL **/;
ALTER TABLE `#__gda_reservation` DROP COLUMN `nbr_places_confirmees` /** CAN FAIL **/;
ALTER TABLE `#__gda_reservation` DROP COLUMN `date_demande` /** CAN FAIL **/;
ALTER TABLE `#__gda_reservation` DROP COLUMN `statut` /** CAN FAIL **/;

-- 7. #__gda_campagnes : role_actif devient inutile (toujours vrai hors Saison, jamais utilisé
--    par Saison). nbr_place conservée (vestigiale, voir cartographie) : suppression différée.
ALTER TABLE `#__gda_campagnes` DROP COLUMN `role_actif` /** CAN FAIL **/;

-- 8. Clé de config pour filtrer les campagnes Loisir (miroir de IdTypeFormation).
INSERT INTO `#__gda_conf` (`key`, `value`)
  SELECT 'IdTypeLoisir', '3' WHERE NOT EXISTS (SELECT 1 FROM `#__gda_conf` WHERE `key` = 'IdTypeLoisir');
