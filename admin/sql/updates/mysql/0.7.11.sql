-- Ajouter d'un nouveau groupes

INSERT INTO `#__gda_groupes` (groupe_name,groupe_tri,published,icon)
SELECT 'Encadrant Impliqué', 60, 1, NULL
WHERE NOT EXISTS (SELECT 1 FROM `#__gda_groupes` WHERE groupe_name = 'Encadrant Impliqué');