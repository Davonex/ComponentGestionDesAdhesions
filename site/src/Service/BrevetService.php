<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

/**
 * Service métier pour les brevets FFESSM d'un adhérent (#__gda_brevets).
 *
 * Point d'extension unique pour la lecture et l'écriture des brevets : le parcours d'adhésion
 * (AdhesionModel::saveInBrevets) et l'édition depuis la fiche Profil (ProfilController::saveBrevets)
 * passent tous deux par ce service, afin de ne pas dupliquer la règle "annule et remplace".
 */
final class BrevetService
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Brevets d'un profil, du plus récent au plus ancien.
     *
     * @return object[] objets {nom, obtention, lieu}
     */
    public function getBrevets(int $idProfil): array
    {
        if ($idProfil <= 0) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['b.nom', 'b.obtention', 'b.lieu']))
            ->from($this->db->quoteName('#__gda_brevets', 'b'))
            ->where($this->db->quoteName('b.id_profil') . ' = :id_profil')
            ->order($this->db->quoteName('b.obtention') . ' DESC')
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Annule et remplace l'intégralité des brevets d'un profil, en transaction : sans elle, un
     * échec d'insertion après le DELETE laisserait l'adhérent sans aucun brevet.
     * Les lignes sans nom (ligne de saisie laissée vide) sont ignorées.
     *
     * @param array<int, array{nom?: mixed, obtention?: mixed, lieu?: mixed}> $brevets
     *
     * @return int nombre de brevets réellement enregistrés
     */
    public function replaceBrevets(int $idProfil, array $brevets): int
    {
        if ($idProfil <= 0) {
            throw new \InvalidArgumentException('id_profil invalide');
        }

        $this->db->transactionStart();

        try {
            $this->deleteForProfil($idProfil);

            $enregistres = 0;

            foreach ($brevets as $brevet) {
                $nom = trim((string) ($brevet['nom'] ?? ''));

                if ($nom === '') {
                    continue;
                }

                // strtoupper (et non removeAccentsAndUppercase) pour rester homogène avec les
                // lignes déjà enregistrées par le parcours d'adhésion historique.
                $lieu = strtoupper(trim((string) ($brevet['lieu'] ?? '')));
                $obtention = !empty($brevet['obtention']) ? ToolsHelper::to_sqldate((string) $brevet['obtention']) : null;

                $query = $this->db->getQuery(true)
                    ->insert($this->db->quoteName('#__gda_brevets'))
                    ->columns($this->db->quoteName(['nom', 'lieu', 'obtention', 'id_profil']))
                    ->values(':nom, :lieu, :obtention, :id_profil')
                    ->bind(':nom', $nom)
                    ->bind(':lieu', $lieu)
                    ->bind(':obtention', $obtention, $obtention === null ? ParameterType::NULL : ParameterType::STRING)
                    ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

                $this->db->setQuery($query);
                $this->db->execute();

                $enregistres++;
            }

            $this->db->transactionCommit();

            return $enregistres;
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            throw new \RuntimeException('Enregistrement des brevets impossible : ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Renseigne le token FFESSM du profil (identifiant technique extrait du QR code de la carte
     * licence). N'écrase jamais un token déjà présent : le scan sert à compléter le profil, pas à
     * réécrire une donnée déjà validée.
     *
     * @return bool true si le token a été écrit
     */
    public function updateFfessmToken(int $idProfil, string $token): bool
    {
        $token = trim($token);

        if ($idProfil <= 0 || $token === '') {
            return false;
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__gda_profils'))
            ->set($this->db->quoteName('ffessm_token') . ' = :token')
            ->where($this->db->quoteName('id_profil') . ' = :id_profil')
            ->where('(' . $this->db->quoteName('ffessm_token') . ' IS NULL OR ' . $this->db->quoteName('ffessm_token') . " = '')")
            ->bind(':token', $token)
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->getAffectedRows() > 0;
    }

    private function deleteForProfil(int $idProfil): void
    {
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__gda_brevets'))
            ->where($this->db->quoteName('id_profil') . ' = :id_profil')
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }
}
