<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

/**
 * Service métier des réservations aux campagnes hors saison (Formation / Sortie / Soirée /
 * Boutique), stockées dans #__gda_reservation.
 *
 * À ne pas confondre avec SouscriptionService, qui reste dédié aux souscriptions de la SAISON
 * (#__gda_souscriptions, workflow CACI / cotisation / licence du secrétariat).
 *
 * Règles portées ici :
 *  - une réservation par adhérent et par campagne (contrainte UNIQUE en base) ;
 *  - les places sont accordées dans la limite de campagnes.nbr_place, le surplus part en
 *    liste d'attente (statut 'attente') ;
 *  - lors d'un ajout de places, les places déjà accordées conservent leur rang initial
 *    (date_reservation) et seul le complément est horodaté (date_demande).
 */
final class ReservationService
{
    public const STATUT_CONFIRMEE = 'confirmee';
    public const STATUT_ATTENTE   = 'attente';
    public const STATUT_ANNULEE   = 'annulee';

    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Réservation d'un adhérent pour une campagne, rôles inclus, ou null s'il n'a jamais réservé.
     * Une réservation annulée est retournée telle quelle : c'est à l'appelant de décider si elle
     * compte comme "déjà réservé" (l'adhérent peut re-réserver, la ligne est alors réactivée).
     */
    public function getReservation(int $idCampagne, int $idProfil): ?object
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__gda_reservation'))
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('id_profil') . ' = :id_profil')
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $reservation = $this->db->loadObject();

        if (!$reservation) {
            return null;
        }

        $reservation->roles = $this->getRoles((int) $reservation->id_reservation);

        return $reservation;
    }

    /**
     * Nombre de places effectivement accordées sur une campagne (hors annulées), pour calculer
     * les places restantes. Les places en attente ne sont volontairement pas comptées : elles
     * n'occupent pas de place tant qu'elles ne sont pas confirmées.
     */
    public function getPlacesOccupees(int $idCampagne): int
    {
        // Variable locale obligatoire : DatabaseQuery::bind() attend une référence, une constante
        // de classe passée directement déclencherait une erreur PHP.
        $statutAnnulee = self::STATUT_ANNULEE;

        $query = $this->db->getQuery(true)
            ->select('COALESCE(SUM(' . $this->db->quoteName('nbr_places_confirmees') . '), 0)')
            ->from($this->db->quoteName('#__gda_reservation'))
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('statut') . ' != :statut_annulee')
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':statut_annulee', $statutAnnulee);

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * Places encore disponibles sur une campagne. Retourne null si la campagne n'a pas de limite
     * (nbr_place = 0), pour distinguer "illimité" de "complet".
     */
    public function getPlacesDisponibles(int $idCampagne, int $nbrPlaceTotal): ?int
    {
        if ($nbrPlaceTotal <= 0) {
            return null;
        }

        return max(0, $nbrPlaceTotal - $this->getPlacesOccupees($idCampagne));
    }

    /**
     * Crée ou met à jour la réservation d'un adhérent.
     *
     * @param array $data id_campagne, id_profil, nbr_place_total (de la campagne), et en option
     *                    nbr_places, commentaire, id_order, roles (array de libellés).
     * @return object La réservation telle qu'enregistrée (avec statut et places accordées).
     */
    public function reserver(array $data): object
    {
        $idCampagne = (int) ($data['id_campagne'] ?? 0);
        $idProfil   = (int) ($data['id_profil'] ?? 0);

        if (!$idCampagne || !$idProfil) {
            throw new \InvalidArgumentException('id_campagne et id_profil sont requis pour réserver');
        }

        $nbrPlacesDemandees = max(1, (int) ($data['nbr_places'] ?? 1));
        $nbrPlaceTotal      = (int) ($data['nbr_place_total'] ?? 0);
        $commentaire        = $data['commentaire'] ?? null;
        $idOrder            = $data['id_order'] ?? null;
        $maintenant         = ToolsHelper::now();

        $existante = $this->getReservation($idCampagne, $idProfil);

        // Places restantes, en neutralisant ce que cette réservation occupe déjà : sans cela,
        // modifier une réservation de 2 places sur une campagne complète la verrait comme pleine
        // alors que ses 2 places lui sont déjà acquises.
        $dejaAccordees = ($existante && $existante->statut !== self::STATUT_ANNULEE)
            ? (int) $existante->nbr_places_confirmees
            : 0;

        if ($nbrPlaceTotal > 0) {
            $restantes  = max(0, $nbrPlaceTotal - $this->getPlacesOccupees($idCampagne) + $dejaAccordees);
            $confirmees = min($nbrPlacesDemandees, $restantes);
        } else {
            // Campagne sans limite de places : tout est accordé.
            $confirmees = $nbrPlacesDemandees;
        }

        $statut = $confirmees >= $nbrPlacesDemandees ? self::STATUT_CONFIRMEE : self::STATUT_ATTENTE;

        // Le rang initial ne bouge jamais ; seul un ajout de places est ré-horodaté, afin que le
        // complément prenne la file à sa date de demande sans faire perdre son rang à l'adhérent.
        // Exception : une réservation annulée puis reprise repart en fin de file, sinon annuler
        // puis re-réserver permettrait de doubler ceux qui attendent depuis plus longtemps.
        $reprise = $existante && $existante->statut === self::STATUT_ANNULEE;

        if (!$existante || $reprise) {
            $dateReservation = $maintenant;
            $dateDemande     = null;
        } else {
            $dateReservation = $existante->date_reservation;
            $dateDemande     = $nbrPlacesDemandees > (int) $existante->nbr_places
                ? $maintenant
                : $existante->date_demande;
        }

        $query = $this->db->getQuery(true);

        if ($existante) {
            $query->update($this->db->quoteName('#__gda_reservation'))
                ->set($this->db->quoteName('nbr_places') . ' = :nbr_places')
                ->set($this->db->quoteName('nbr_places_confirmees') . ' = :confirmees')
                ->set($this->db->quoteName('statut') . ' = :statut')
                ->set($this->db->quoteName('commentaire') . ' = :commentaire')
                ->set($this->db->quoteName('id_order') . ' = :id_order')
                ->set($this->db->quoteName('date_reservation') . ' = :date_reservation')
                ->set($this->db->quoteName('date_demande') . ' = :date_demande')
                ->set($this->db->quoteName('last_update') . ' = :last_update')
                ->where($this->db->quoteName('id_reservation') . ' = :id_reservation')
                ->bind(':id_reservation', $existante->id_reservation, ParameterType::INTEGER);
        } else {
            $query->insert($this->db->quoteName('#__gda_reservation'))
                ->columns($this->db->quoteName([
                    'id_campagne', 'id_profil', 'date_reservation', 'date_demande',
                    'nbr_places', 'nbr_places_confirmees', 'statut', 'commentaire',
                    'id_order', 'last_update',
                ]))
                ->values(':id_campagne, :id_profil, :date_reservation, :date_demande, :nbr_places, '
                    . ':confirmees, :statut, :commentaire, :id_order, :last_update')
                ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
                ->bind(':id_profil', $idProfil, ParameterType::INTEGER);
        }

        $query->bind(':date_reservation', $dateReservation)
            ->bind(':nbr_places', $nbrPlacesDemandees, ParameterType::INTEGER)
            ->bind(':confirmees', $confirmees, ParameterType::INTEGER)
            ->bind(':statut', $statut)
            ->bind(':commentaire', $commentaire)
            ->bind(':id_order', $idOrder)
            ->bind(':date_demande', $dateDemande)
            ->bind(':last_update', $maintenant);

        $this->db->setQuery($query);

        try {
            $this->db->execute();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage(), 500, $e);
        }

        $idReservation = $existante ? (int) $existante->id_reservation : (int) $this->db->insertid();

        // Les rôles ne sont posés que sur les places réellement accordées : attribuer un rôle à
        // une place encore en attente n'aurait pas de sens.
        if (isset($data['roles'])) {
            $this->saveRoles($idReservation, (array) $data['roles'], $confirmees);
        }

        return $this->getReservation($idCampagne, $idProfil);
    }

    /**
     * Annule la réservation d'un adhérent : passage en statut 'annulee' plutôt qu'un DELETE, pour
     * conserver l'historique et la trace du rang initial. Les places sont libérées immédiatement.
     */
    public function annuler(int $idCampagne, int $idProfil): void
    {
        $maintenant    = ToolsHelper::now();
        $statutAnnulee = self::STATUT_ANNULEE;

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__gda_reservation'))
            ->set($this->db->quoteName('statut') . ' = :statut')
            ->set($this->db->quoteName('nbr_places_confirmees') . ' = 0')
            ->set($this->db->quoteName('last_update') . ' = :last_update')
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('id_profil') . ' = :id_profil')
            ->bind(':statut', $statutAnnulee)
            ->bind(':last_update', $maintenant)
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

        $this->db->setQuery($query);

        try {
            $this->db->execute();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage(), 500, $e);
        }
    }

    /**
     * Rôles attribués aux places d'une réservation (vide si la campagne n'a pas role_actif).
     *
     * @return string[]
     */
    private function getRoles(int $idReservation): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('role'))
            ->from($this->db->quoteName('#__gda_reservation_places'))
            ->where($this->db->quoteName('id_reservation') . ' = :id_reservation')
            ->order($this->db->quoteName('tri') . ' ASC')
            ->bind(':id_reservation', $idReservation, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return $this->db->loadColumn() ?: [];
    }

    /**
     * Remplace les rôles d'une réservation. Stratégie "table rase" volontaire : le nombre de
     * places peut varier à chaque modification, un diff ligne à ligne n'apporterait rien ici.
     *
     * @param string[] $roles
     * @param int      $maxPlaces Nombre de places accordées : on n'enregistre pas de rôle au-delà.
     */
    private function saveRoles(int $idReservation, array $roles, int $maxPlaces): void
    {
        $delete = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__gda_reservation_places'))
            ->where($this->db->quoteName('id_reservation') . ' = :id_reservation')
            ->bind(':id_reservation', $idReservation, ParameterType::INTEGER);

        $this->db->setQuery($delete);
        $this->db->execute();

        $roles = array_slice(array_values($roles), 0, $maxPlaces);

        foreach ($roles as $tri => $role) {
            $role = trim((string) $role);

            if ($role === '') {
                continue;
            }

            $insert = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__gda_reservation_places'))
                ->columns($this->db->quoteName(['id_reservation', 'role', 'tri']))
                ->values(':id_reservation, :role, :tri')
                ->bind(':id_reservation', $idReservation, ParameterType::INTEGER)
                ->bind(':role', $role)
                ->bind(':tri', $tri, ParameterType::INTEGER);

            $this->db->setQuery($insert);
            $this->db->execute();
        }
    }
}
