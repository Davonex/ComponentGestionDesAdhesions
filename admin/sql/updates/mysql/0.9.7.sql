-- ---------------------------------------------------------------------------------------------
-- Correspondance entre les libellés de brevets émis par la FFESSM et le code métier du club.
--
-- Table de référence, alimentée à l'installation et non modifiée par l'application : elle sert
-- à guider l'adhérent lors de la saisie de ses brevets (#__gda_brevets) en lui proposant les
-- libellés officiels plutôt qu'une saisie libre.
--
-- Un même code porte plusieurs libellés (équivalences, passerelles, recyclages : « TIV »,
-- « TIV - Recyclage », « TIV - Réactivation »). La clé unique porte donc sur le couple
-- (code, label_ffessm_norm) et non sur le seul code.
--
-- label_ffessm_norm : libellé normalisé (MAJUSCULE, sans accents, sans ponctuation) utilisé pour
-- le rapprochement avec les libellés bruts FFESSM (BrevetService::replaceBrevets) ; label_ffessm
-- reste le libellé officiel lisible, pour l'affichage.
--
-- niveau : rang d'importance au sein du couple (activite, role) - plus haut = plus important.
-- Permet de retrouver le brevet le plus élevé d'un adhérent par activité (ex: en Plongée
-- pratiquant, Niveau 3 est plus important que Niveau 1). Cette colonne est volontairement absente
-- de #__gda_brevets : elle peut être réévaluée sans jamais toucher aux brevets déjà enregistrés.
--
-- Portée de la 0.9.7 : création de la table de référence (avec niveau et label_ffessm_norm),
-- chargement des 78 correspondances, et ajout de #__gda_brevets.id_mapping (résolu à
-- l'enregistrement par BrevetService::replaceBrevets, NULL si le libellé brut n'est pas reconnu).
-- ---------------------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__gda_mapping_brevets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL,
  `activite` VARCHAR(100) NOT NULL,
  `role` ENUM('pratiquant','encadrant') NOT NULL,
  `label_ffessm` VARCHAR(150) NOT NULL,
  `label_ffessm_norm` VARCHAR(150) NOT NULL,
  `niveau` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_code_label_norm` (`code`, `label_ffessm_norm`),
  KEY `idx_code` (`code`),
  KEY `idx_activite` (`activite`),
  KEY `idx_role` (`role`),
  KEY `idx_label_norm` (`label_ffessm_norm`),
  KEY `idx_activite_role_niveau` (`activite`, `role`, `niveau`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Correspondance code métier / libellé officiel FFESSM';

INSERT IGNORE INTO `#__gda_mapping_brevets` (`code`, `activite`, `role`, `label_ffessm`, `label_ffessm_norm`, `niveau`) VALUES
('PB', 'Technique', 'pratiquant', 'Plongeur Bronze', 'PLONGEUR BRONZE', 1),
('PA', 'Technique', 'pratiquant', 'Plongeur Argent', 'PLONGEUR ARGENT', 2),
('PO', 'Technique', 'pratiquant', 'Plongeur Or', 'PLONGEUR OR', 3),
('P1', 'Technique', 'pratiquant', 'Niveau 1', 'NIVEAU 1', 4),
('P2', 'Technique', 'pratiquant', 'Niveau 2', 'NIVEAU 2', 8),
('P3', 'Technique', 'pratiquant', 'Niveau 3', 'NIVEAU 3', 10),
('P4', 'Technique', 'pratiquant', 'Niveau IV-GP', 'NIVEAU IV-GP', 11),
('P5', 'Technique', 'pratiquant', 'Plongeur Niveau 5', 'PLONGEUR NIVEAU 5', 12),
('PA20', 'Technique', 'pratiquant', 'Plongeur en autonomie 20 metres', 'PLONGEUR EN AUTONOMIE 20 METRES', 2),
('PE40', 'Technique', 'pratiquant', 'Plongeur encadré 40 mètres', 'PLONGEUR ENCADRE 40 METRES', 4),
('PA40', 'Technique', 'pratiquant', 'Plongeur en autonomie 40 mètres', 'PLONGEUR EN AUTONOMIE 40 METRES', 7),
('PE60', 'Technique', 'pratiquant', 'Plongeur encadré 60 mètres', 'PLONGEUR ENCADRE 60 METRES', 9),
('E1', 'Technique', 'encadrant', 'E1 - Initiateur-Directeur de bassin', 'E1 - INITIATEUR-DIRECTEUR DE BASSIN', 1),
('E2', 'Technique', 'encadrant', 'E2 - Niveau IV/GP/Directeur de bassin', 'E2 - NIVEAU IV/GP/DIRECTEUR DE BASSIN', 2),
('E3', 'Technique', 'encadrant', 'E3 - M.F.1.', 'E3 - MF1', 3),
('E4', 'Technique', 'encadrant', 'E4 - M.F.2.', 'E4 - MF2', 5),
('E3 (TSI)', 'Technique', 'encadrant', 'Tuteur de stage initiateur', 'TUTEUR DE STAGE INITIATEUR', 4),
('A1', 'Apnée', 'pratiquant', 'Apnéiste Niveau 1', 'APNEISTE NIVEAU 1', 1),
('A2', 'Apnée', 'pratiquant', 'Apnéiste Niveau 2', 'APNEISTE NIVEAU 2', 2),
('A3', 'Apnée', 'pratiquant', 'Apnéiste Niveau 3', 'APNEISTE NIVEAU 3', 3),
('A4', 'Apnée', 'pratiquant', 'Apnéiste Niveau 4', 'APNEISTE NIVEAU 4', 4),
('IE1', 'Apnée', 'encadrant', 'Initiateur-Entraîneur Apnée Niveau 1', 'INITIATEUR-ENTRAINEUR APNEE NIVEAU 1', 1),
('IE2', 'Apnée', 'encadrant', 'Initiateur-Entraîneur Apnée Niveau 2', 'INITIATEUR-ENTRAINEUR APNEE NIVEAU 2', 2),
('MEF1', 'Apnée', 'encadrant', 'Moniteur entraîneur Fédéral Apnée 1er degré', 'MONITEUR ENTRAINEUR FEDERAL APNEE 1ER DEGRE', 3),
('MEF2', 'Apnée', 'encadrant', 'Moniteur entraîneur Fédéral Apnée 2ème degré', 'MONITEUR ENTRAINEUR FEDERAL APNEE 2EME DEGRE', 4),
('Bio1', 'Biologie subaquatique', 'pratiquant', 'Plongeur Biologiste Niveau 1', 'PLONGEUR BIOLOGISTE NIVEAU 1', 1),
('Bio2', 'Biologie subaquatique', 'pratiquant', 'Plongeur Biologiste Niveau 2', 'PLONGEUR BIOLOGISTE NIVEAU 2', 2),
('FBio1', 'Biologie subaquatique', 'encadrant', 'Formateur Biologie 1er degré', 'FORMATEUR BIOLOGIE 1ER DEGRE', 1),
('FBio2', 'Biologie subaquatique', 'encadrant', 'Formateur Biologie 2ème degré', 'FORMATEUR BIOLOGIE 2EME DEGRE', 2),
('F1', 'Photographie subaquatique', 'pratiquant', 'Plongeur photographe Niveau 1', 'PLONGEUR PHOTOGRAPHE NIVEAU 1', 1),
('F2', 'Photographie subaquatique', 'pratiquant', 'Plongeur photographe Niveau 2', 'PLONGEUR PHOTOGRAPHE NIVEAU 2', 2),
('FF1', 'Photographie subaquatique', 'encadrant', 'Formateur photo Niveau 1', 'FORMATEUR PHOTO NIVEAU 1', 1),
('FF2', 'Photographie subaquatique', 'encadrant', 'Formateur photo Niveau 2', 'FORMATEUR PHOTO NIVEAU 2', 2),
('V1', 'Vidéo subaquatique', 'pratiquant', 'Plongeur videaste Niveau 1', 'PLONGEUR VIDEASTE NIVEAU 1', 1),
('V2', 'Vidéo subaquatique', 'pratiquant', 'Plongeur videaste Niveau 2', 'PLONGEUR VIDEASTE NIVEAU 2', 2),
('FV1', 'Vidéo subaquatique', 'encadrant', 'Formateur videaste Niveau 1', 'FORMATEUR VIDEASTE NIVEAU 1', 1),
('FV2', 'Vidéo subaquatique', 'encadrant', 'Formateur videaste Niveau 2', 'FORMATEUR VIDEASTE NIVEAU 2', 2),
('HP6', 'Handisub', 'pratiquant', 'P.E.S.H.1 - 6 mètres', 'PESH1 - 6 METRES', 1),
('HP12', 'Handisub', 'pratiquant', 'P.E.S.H.1 - 12 mètres', 'PESH1 - 12 METRES', 2),
('EH1', 'Handisub', 'encadrant', 'EH1 - Scaphandre', 'EH1 - SCAPHANDRE', 1),
('EH2', 'Handisub', 'encadrant', 'EH2 - Scaphandre', 'EH2 - SCAPHANDRE', 3),
('RIFAP', 'Secourisme (RIFA)', 'pratiquant', 'RIFA Plongée', 'RIFA PLONGEE', 1),
('RIFAA', 'Secourisme (RIFA)', 'pratiquant', 'RIFA Apnée', 'RIFA APNEE', 1),
('RIFAN', 'Secourisme (RIFA)', 'pratiquant', 'RIFA Nage avec palmes', 'RIFA NAGE AVEC PALMES', 1),
('ANTE', 'Secourisme (RIFA)', 'encadrant', 'Anteor', 'ANTEOR', 1),
('PN', 'Nitrox', 'pratiquant', 'Plongeur Nitrox', 'PLONGEUR NITROX', 1),
('PN-C', 'Nitrox', 'pratiquant', 'Plongeur Nitrox confirmé', 'PLONGEUR NITROX CONFIRME', 2),
('MONITEUR PN-C', 'Nitrox', 'encadrant', 'Moniteur Nitrox Confirmé', 'MONITEUR NITROX CONFIRME', 1),
('IENA', 'Nage avec palmes', 'encadrant', 'Initiateur entraîneur Nage avec Palmes', 'INITIATEUR ENTRAINEUR NAGE AVEC PALMES', 1),
('TIV', 'Contrôle des blocs (TIV)', 'pratiquant', 'Technicien Inspection Visuelle', 'TECHNICIEN INSPECTION VISUELLE', 1),
('AP', 'Apnée', 'pratiquant', 'Apnéiste / Indoor Freediver 1 CMAS', 'APNEISTE / INDOOR FREEDIVER 1 CMAS', 1),
('ACP', 'Apnée', 'pratiquant', 'Apnéiste confirmé / Indoor Freediver 2 CMAS', 'APNEISTE CONFIRME / INDOOR FREEDIVER 2 CMAS', 2),
('EH1M', 'Handisub', 'encadrant', 'MHPC - Module complémentaire Handi-Psychique Cognitif', 'MHPC - MODULE COMPLEMENTAIRE HANDI-PSYCHIQUE COGNITIF', 2),
('AEL', 'Apnée', 'pratiquant', 'Apnéiste en eau Libre / Outdoor Freediver 1 CMAS', 'APNEISTE EN EAU LIBRE / OUTDOOR FREEDIVER 1 CMAS', 1),
('ACEL', 'Apnée', 'pratiquant', 'Apnéiste confirmé en eau Libre / Outdoor Freediver 2 CMAS', 'APNEISTE CONFIRME EN EAU LIBRE / OUTDOOR FREEDIVER 2 CMAS', 2),
('AEEL', 'Apnée', 'pratiquant', 'Apnéiste expert en eau Libre / Outdoor Freediver 3 CMAS', 'APNEISTE EXPERT EN EAU LIBRE / OUTDOOR FREEDIVER 3 CMAS', 3),
('E3', 'Technique', 'encadrant', 'E3 - B.E.E.S. 1er degré', 'E3 - BEES 1ER DEGRE', 3),
('FB1', 'Biologie subaquatique', 'encadrant', 'Formateur Biologie 1er degré – Aptitude Formateur PB2', 'FORMATEUR BIOLOGIE 1ER DEGRE - APTITUDE FORMATEUR PB2', 1),
('IE2', 'Apnée', 'encadrant', 'Initiateur-Entraîneur Apnée N2 / 1* Star Freediving Instructor', 'INITIATEUR-ENTRAINEUR APNEE N2 / 1* STAR FREEDIVING INSTRUCTOR', 2),
('TIV', 'Contrôle des blocs (TIV)', 'pratiquant', 'Technicien Inspection Visuelle - Réactivation', 'TECHNICIEN INSPECTION VISUELLE - REACTIVATION', 1),
('F3', 'Photographie subaquatique', 'pratiquant', 'Plongeur photographe Niveau 3', 'PLONGEUR PHOTOGRAPHE NIVEAU 3', 3),
('JFA1', 'Apnée', 'encadrant', 'Juge Fédéral Apnée 1er degré', 'JUGE FEDERAL APNEE 1ER DEGRE', 5),
('RIFATSC', 'Secourisme (RIFA)', 'pratiquant', 'RIFA Tir sur cible', 'RIFA TIR SUR CIBLE', 1),
('IETSC', 'Tir sur cible subaquatique', 'encadrant', 'Initiateur entraîneur Tir sur Cible', 'INITIATEUR ENTRAINEUR TIR SUR CIBLE', 1),
('MFEH1', 'Handisub', 'encadrant', 'Moniteur Fédéral E.H.1 Scaphandre', 'MONITEUR FEDERAL EH1 SCAPHANDRE', 4),
('EH1A', 'Handisub', 'encadrant', 'EH1 - Apnée', 'EH1 - APNEE', 1),
('P1', 'Technique', 'pratiquant', 'Niveau 1 UCPA', 'NIVEAU 1 UCPA', 1),
('P1', 'Technique', 'pratiquant', 'Niveau 1 passerelle PADI', 'NIVEAU 1 PASSERELLE PADI', 1),
('P2', 'Technique', 'pratiquant', 'Niveau 2 UCPA', 'NIVEAU 2 UCPA', 3),
('TIV', 'Contrôle des blocs (TIV)', 'pratiquant', 'Technicien Inspection Visuelle - Recyclage', 'TECHNICIEN INSPECTION VISUELLE - RECYCLAGE', 1),
('APSP', 'Plongée sportive en piscine (PSP)', 'encadrant', 'Arbitre Plongée Sportive Piscine', 'ARBITRE PLONGEE SPORTIVE PISCINE', 1),
('CE', 'Technique', 'pratiquant', 'Combinaison étanche', 'COMBINAISON ETANCHE', 1),
('JFPSP1', 'Plongée sportive en piscine (PSP)', 'encadrant', 'Juge Fédéral Plongée Sportive en Piscine - 1er degré', 'JUGE FEDERAL PLONGEE SPORTIVE EN PISCINE - 1ER DEGRE', 2),
('JRNAP', 'Nage avec palmes', 'encadrant', 'Juge Régional Nage Avec Palmes', 'JUGE REGIONAL NAGE AVEC PALMES', 2),
('MSS', 'Technique', 'pratiquant', 'Module Sport Santé', 'MODULE SPORT SANTE', 1),
('RCFAP', 'Plongée technique / Recycleur', 'pratiquant', 'Plongeur Recycleur Circuit Fermé AP-Diving Inspiration diluant Air', 'PLONGEUR RECYCLEUR CIRCUIT FERME AP-DIVING INSPIRATION DILUANT AIR', 1),
('PSOUT1', 'Plongée souterraine', 'pratiquant', 'Plongeur souterrain Niveau 1', 'PLONGEUR SOUTERRAIN NIVEAU 1', 1),
('PSOUT2', 'Plongée souterraine', 'pratiquant', 'Plongeur souterrain Niveau 2', 'PLONGEUR SOUTERRAIN NIVEAU 2', 2);


-- ---------------------------------------------------------------------------------------------
-- #__gda_brevets.id_mapping : correspondance résolue à l'écriture (BrevetService::replaceBrevets)
-- vers #__gda_mapping_brevets.id. NULL si le libellé brut ne correspond à aucune ligne connue du
-- référentiel (nouveau brevet FFESSM pas encore ajouté au mapping, variante d'orthographe...) :
-- ce n'est jamais bloquant, le brevet reste enregistré normalement avec son nom brut, un WARNING
-- est simplement tracé (GdaLogger) pour repérer les libellés à ajouter au référentiel.
-- ---------------------------------------------------------------------------------------------

ALTER TABLE `#__gda_brevets`
  ADD COLUMN `id_mapping` INT UNSIGNED DEFAULT NULL COMMENT 'Correspondance résolue à l''enregistrement vers #__gda_mapping_brevets.id (NULL = libellé non reconnu)' AFTER `obtention`,
  ADD KEY `gda_brevets_id_mapping_IDX` (`id_mapping`),
  ADD CONSTRAINT `gda_brevets_gda_mapping_brevets_FK` FOREIGN KEY (`id_mapping`) REFERENCES `#__gda_mapping_brevets` (`id`) ON DELETE SET NULL;


-- ---------------------------------------------------------------------------------------------
-- Trombinoscope du Bureau (vue site `Trombinoscope`).
--
-- La donnée d'identité (photo, fonction) et le groupe Joomla "Membre du Bureau" existent déjà :
-- seul manquait un ordre d'affichage explicite (Présidente en premier, etc.), non déductible des
-- colonnes existantes. NULL = pas d'ordre défini, l'affichage retombe sur un tri alphabétique
-- (nom, prenom).
-- ---------------------------------------------------------------------------------------------

ALTER TABLE `#__gda_profils`
  ADD COLUMN `ordre_bureau` smallint unsigned DEFAULT NULL
  COMMENT 'Ordre d''affichage dans le trombinoscope du Bureau (NULL = tri alphabétique en repli)'
  AFTER `fonction`;