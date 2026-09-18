-- ---------------------------------------------------------------------------------------------
-- 0.9.17 - Tarification administrable par le Bureau (onglet "Tarification" de la vue Saisons).
--
-- #__gda_cotisation devient le référentiel unique des tarifs : cotisations club (codes A..H,
-- H = "Licence seule" sans adhésion au club, le code stocké dans
-- #__gda_souscriptions.cotisation_code étant lettre + zone, 1 = Val d'Yerres / 2 = hors agglo)
-- ET licences FFESSM (codes LIC_*), jusqu'ici stockées en clés #__gda_conf
-- (LicADULTE / LicJEUNE / LicENFANT) sous forme de chaînes à virgule ("48,50").
--
-- Les libellés quittent language/fr-FR/com_gdadhesions.ini (clés COM_GDA_COTISATION_TARIF_*)
-- pour la colonne `libelle` : un seul libellé par code, le suffixe "[Hors Agglo]" étant ajouté
-- à l'affichage par CotisationService::getLabel() lorsque tarif_hvy != tarif_vy et zone = 2.
--
-- `reduction` lie une ligne à une option de la liste "Tarification" du formulaire d'adhésion et
-- `option_libelle` porte le libellé de cette option (ex clés COM_GDA_REDUCTION_CHOIX_*, elles
-- aussi retirées du .ini) ; `cible` lève l'ambiguïté des couples qui partagent une option
-- (A/D sur 0, B/E sur 1). Deux lignes de même `reduction` portent donc le MÊME `option_libelle` :
-- l'invariant est tenu par CotisationService::updateLigne(), qui propage toute modification.
--
-- Idempotence : marqueur /** CAN FAIL **/ (mécanisme standard de l'installeur Joomla, déjà
-- utilisé par 0.9.11.sql), INSERT IGNORE et clauses WHERE ... IS NULL. Un ALTER par instruction,
-- sinon un seul échec annulerait les autres.
-- ---------------------------------------------------------------------------------------------

-- 1) Clé primaire technique (la table n'en avait aucune) : l'édition inline a besoin d'un
--    identifiant stable côté client, et `code` reste réservé à l'identité métier.
ALTER TABLE `#__gda_cotisation` ADD COLUMN `id_tarif` INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id_tarif`) /** CAN FAIL **/;

-- 2) `code` doit loger les codes licence (LIC_ADULTE...) ; les montants passent en décimal pour
--    accueillir les tarifs de licence FFESSM, qui ont des centimes (48,50 €).
ALTER TABLE `#__gda_cotisation` MODIFY COLUMN `code` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' /** CAN FAIL **/;
ALTER TABLE `#__gda_cotisation` MODIFY COLUMN `tarif_vy` DECIMAL(6,2) NOT NULL DEFAULT 0.00 /** CAN FAIL **/;
ALTER TABLE `#__gda_cotisation` MODIFY COLUMN `tarif_hvy` DECIMAL(6,2) NOT NULL DEFAULT 0.00 /** CAN FAIL **/;

-- 3) Nouvelles colonnes administrables.
ALTER TABLE `#__gda_cotisation` ADD COLUMN `nature` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'COTISATION' COMMENT 'COTISATION (cotisation club) | LICENCE (licence FFESSM)' AFTER `code` /** CAN FAIL **/;
ALTER TABLE `#__gda_cotisation` ADD COLUMN `cible` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TOUS' COMMENT 'ADULTE | ENFANT | TOUS pour une cotisation ; categorie (ADULTE|JEUNE|ENFANT) pour une licence' AFTER `nature` /** CAN FAIL **/;
ALTER TABLE `#__gda_cotisation` ADD COLUMN `libelle` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Libellé unique du tarif ; le suffixe "[Hors Agglo]" est ajouté à l''affichage' AFTER `cible` /** CAN FAIL **/;
ALTER TABLE `#__gda_cotisation` ADD COLUMN `commentaire` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Note libre du Bureau, jamais affichée côté adhérent' AFTER `libelle` /** CAN FAIL **/;
ALTER TABLE `#__gda_cotisation` ADD COLUMN `reduction` TINYINT NULL DEFAULT NULL COMMENT 'Option de la liste "Tarification" du formulaire (CotisationService::REDUCTION_*), NULL = jamais proposée' AFTER `tarif_hvy` /** CAN FAIL **/;
ALTER TABLE `#__gda_cotisation` ADD COLUMN `option_libelle` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Libellé de l''option dans la liste "Tarification" du formulaire d''adhésion ; partagé par toutes les lignes de même `reduction`' AFTER `reduction` /** CAN FAIL **/;
ALTER TABLE `#__gda_cotisation` ADD COLUMN `actif` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Pilote la liste du formulaire d''adhésion ; ne filtre jamais le calcul d''un montant déjà souscrit' AFTER `option_libelle` /** CAN FAIL **/;
ALTER TABLE `#__gda_cotisation` ADD COLUMN `ordre` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Tri d''affichage, par pas de 10' AFTER `actif` /** CAN FAIL **/;

-- 4) Unicité du code : garantit que CotisationService peut indexer son cache par code.
ALTER TABLE `#__gda_cotisation` ADD UNIQUE KEY `uniq_gda_cotisation_code` (`code`) /** CAN FAIL **/;

-- 5) Reprise des libellés depuis language/fr-FR/com_gdadhesions.ini (clés COM_GDA_COTISATION_TARIF_x1),
--    sans le suffixe "[Hors Agglo]" qui est désormais calculé à l'affichage.
--    `reduction`/`cible` reproduisent exactement la cascade de CotisationService::getCode().
UPDATE `#__gda_cotisation` SET
  `nature` = 'COTISATION', `cible` = 'ADULTE', `reduction` = 0, `ordre` = 10,
  `libelle` = 'ADULTES ou ADOS (17 et plus)',
  `option_libelle` = 'Normal'
  WHERE `code` = 'A';

UPDATE `#__gda_cotisation` SET
  `nature` = 'COTISATION', `cible` = 'ADULTE', `reduction` = 1, `ordre` = 20,
  `libelle` = '2nd ADULTES membres du même foyer',
  `option_libelle` = 'Reduction Famille'
  WHERE `code` = 'B';

-- Code C : l'option "Étudiant" (reduction = 3) est commentée dans models/forms/adhesion.xml
-- depuis la 0.9.14 ; `actif = 0` reproduit cet état, désormais pilotable par le Bureau.
UPDATE `#__gda_cotisation` SET
  `nature` = 'COTISATION', `cible` = 'ADULTE', `reduction` = 3, `ordre` = 30, `actif` = 0,
  `libelle` = 'ETUDIANTS (de 16 a 25 ans)',
  `commentaire` = 'Ne bénéficient pas de la réduction familiale',
  `option_libelle` = 'Etudiants'
  WHERE `code` = 'C';

UPDATE `#__gda_cotisation` SET
  `nature` = 'COTISATION', `cible` = 'ENFANT', `reduction` = 0, `ordre` = 40,
  `libelle` = 'ENFANTS (moins de 17ans) ne participant qu''aux entraînements du vendredi',
  `option_libelle` = 'Normal'
  WHERE `code` = 'D';

UPDATE `#__gda_cotisation` SET
  `nature` = 'COTISATION', `cible` = 'ENFANT', `reduction` = 1, `ordre` = 50,
  `libelle` = 'ENFANTS (moins de 17ans) membres du même foyer',
  `option_libelle` = 'Reduction Famille'
  WHERE `code` = 'E';

UPDATE `#__gda_cotisation` SET
  `nature` = 'COTISATION', `cible` = 'TOUS', `reduction` = 2, `ordre` = 60,
  `libelle` = 'Encadrant Actif, Membre du bureau ou TIV',
  `option_libelle` = 'Membre du bureau, encadrant ou TIV'
  WHERE `code` = 'F';

UPDATE `#__gda_cotisation` SET
  `nature` = 'COTISATION', `cible` = 'TOUS', `reduction` = 4, `ordre` = 70,
  `libelle` = 'Plongeur en situation de handicap',
  `option_libelle` = 'Plongeur Handisub'
  WHERE `code` = 'G';

-- 5bis) Option "Licence seule" (achat de la licence FFESSM sans adhésion au club) : contrairement
--       à A..G, le code H n'existe pas avant cette migration - INSERT IGNORE plutôt que UPDATE.
--       Aucune cotisation club due (tarif_vy = tarif_hvy = 0) : la licence FFESSM reste facturée
--       séparément selon l'âge, par le mécanisme existant (secrétariat, GetCategorie()/getMontantLicence()).
INSERT IGNORE INTO `#__gda_cotisation`
  (`code`, `nature`, `cible`, `libelle`, `tarif_vy`, `tarif_hvy`, `reduction`, `option_libelle`, `actif`, `ordre`) VALUES
  ('H', 'COTISATION', 'TOUS', 'Licence FFESSM seule (sans adhésion au club)', 0.00, 0.00, 5, 'Licence FFESSM seule', 1, 80);

-- 6) Reprise des 3 tarifs de licence FFESSM depuis #__gda_conf (valeurs stockées "48,50" -> 48.50).
--    tarif_vy = tarif_hvy : une licence FFESSM ne dépend pas du lieu de résidence.
--    INSERT IGNORE : rejouable grâce à uniq_gda_cotisation_code.
INSERT IGNORE INTO `#__gda_cotisation`
  (`code`, `nature`, `cible`, `libelle`, `commentaire`, `tarif_vy`, `tarif_hvy`, `reduction`, `actif`, `ordre`)
SELECT 'LIC_ADULTE', 'LICENCE', 'ADULTE', 'Licence FFESSM ADULTE', NULL,
       CAST(REPLACE(c.`value`, ',', '.') AS DECIMAL(6,2)),
       CAST(REPLACE(c.`value`, ',', '.') AS DECIMAL(6,2)),
       NULL, 1, 100
  FROM `#__gda_conf` c WHERE c.`key` = 'LicADULTE';

INSERT IGNORE INTO `#__gda_cotisation`
  (`code`, `nature`, `cible`, `libelle`, `commentaire`, `tarif_vy`, `tarif_hvy`, `reduction`, `actif`, `ordre`)
SELECT 'LIC_JEUNE', 'LICENCE', 'JEUNE', 'Licence FFESSM JEUNE', NULL,
       CAST(REPLACE(c.`value`, ',', '.') AS DECIMAL(6,2)),
       CAST(REPLACE(c.`value`, ',', '.') AS DECIMAL(6,2)),
       NULL, 1, 110
  FROM `#__gda_conf` c WHERE c.`key` = 'LicJEUNE';

INSERT IGNORE INTO `#__gda_cotisation`
  (`code`, `nature`, `cible`, `libelle`, `commentaire`, `tarif_vy`, `tarif_hvy`, `reduction`, `actif`, `ordre`)
SELECT 'LIC_ENFANT', 'LICENCE', 'ENFANT', 'Licence FFESSM ENFANT', NULL,
       CAST(REPLACE(c.`value`, ',', '.') AS DECIMAL(6,2)),
       CAST(REPLACE(c.`value`, ',', '.') AS DECIMAL(6,2)),
       NULL, 1, 120
  FROM `#__gda_conf` c WHERE c.`key` = 'LicENFANT';

-- Filet de sécurité : si les clés #__gda_conf avaient déjà été supprimées (script rejoué sur une
-- base partiellement migrée), on garantit malgré tout l'existence des 3 lignes licence.
INSERT IGNORE INTO `#__gda_cotisation`
  (`code`, `nature`, `cible`, `libelle`, `tarif_vy`, `tarif_hvy`, `reduction`, `actif`, `ordre`) VALUES
  ('LIC_ADULTE', 'LICENCE', 'ADULTE', 'Licence FFESSM ADULTE', 48.50, 48.50, NULL, 1, 100),
  ('LIC_JEUNE',  'LICENCE', 'JEUNE',  'Licence FFESSM JEUNE',  30.50, 30.50, NULL, 1, 110),
  ('LIC_ENFANT', 'LICENCE', 'ENFANT', 'Licence FFESSM ENFANT', 14.00, 14.00, NULL, 1, 120);

-- 7) Les 3 clés de configuration n'ont plus de lecteur : CotisationService::getMontantLicence()
--    lit désormais #__gda_cotisation.
DELETE FROM `#__gda_conf` WHERE `key` IN ('LicADULTE', 'LicJEUNE', 'LicENFANT');

-- 8) Montant figé à la souscription : les tarifs restent globaux (pas de id_campagne), c'est la
--    souscription qui mémorise ce qui a réellement été facturé à l'adhérent. Sans cela, une
--    correction de tarif par le Bureau réécrirait rétroactivement l'historique des saisons
--    passées (#__gda_souscriptions ne stockait que le code, jamais le montant).
--    NULL autorisé : marqueur "souscription antérieure au figeage", qui déclenche le repli sur le
--    tarif courant via CotisationService::getMontantFige().
ALTER TABLE `#__gda_souscriptions` ADD COLUMN `cotisation_montant` DECIMAL(6,2) NULL DEFAULT NULL COMMENT 'Montant de cotisation figé au moment de la souscription' AFTER `cotisation_code` /** CAN FAIL **/;

-- 9) Reprise des souscriptions existantes : on fige le tarif COURANT, une seule fois.
UPDATE `#__gda_souscriptions` s
  INNER JOIN `#__gda_cotisation` t
     ON t.`code` = LEFT(s.`cotisation_code`, 1) AND t.`nature` = 'COTISATION'
  SET s.`cotisation_montant` = CASE WHEN RIGHT(s.`cotisation_code`, 1) = '1'
                                    THEN t.`tarif_vy` ELSE t.`tarif_hvy` END
  WHERE s.`cotisation_montant` IS NULL
    AND s.`cotisation_code` IS NOT NULL
    AND s.`cotisation_code` <> '';
