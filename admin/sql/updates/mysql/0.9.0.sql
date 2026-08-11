-- Ajout du champ "fonction" sur #__gda_profils : permet au Bureau de préciser le rôle
-- d'un membre (ex: Trésorier, Responsable Communication, ...) depuis la vue Utilisateurs.

ALTER TABLE `#__gda_profils` ADD COLUMN `fonction` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Fonction au sein du Bureau';
