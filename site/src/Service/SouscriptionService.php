<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

/**
 * Service réutilisable pour gérer les souscriptions (inscriptions / désinscriptions) à une campagne.
 * Peut être appelé depuis n'importe quel contrôleur ou modèle sans dépendre de la session utilisateur.
 */
final class SouscriptionService
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Inscrit un profil à une campagne.
     *
     * @param  array  $data  Tableau contenant au minimum 'id_campagne' et 'id_profil'.
     *                       Peut contenir des clés supplémentaires (ex: date_souscription, etc.)
     * @return bool
     * @throws \RuntimeException en cas d'erreur SQL
     */
    public function souscrire(array $data): bool
    {
        $idCampagne = (int) ($data['id_campagne'] ?? 0);
        $idProfil   = (int) ($data['id_profil'] ?? 0);
        $categorie = $data['categorie'] ?? null;
        $date_souscription = $data['date_souscription'] ?? ToolsHelper::now();
        $last_update = ToolsHelper::now();
        $cotisation_code = $data['cotisation_code'] ?? null;
        $id_order = $data['id_order'] ?? null;
        // $caci_check = $data['caci_check'] ?? false;



        if (! $idCampagne || ! $idProfil) {
            throw new \InvalidArgumentException("id_campagne et id_profil sont requis pour souscrire");
        }   

        // Vérifie si la souscription existe déjà
        $check = $this->db->getQuery(true);
        $check->select('COUNT(*)')
            ->from($this->db->quoteName('#__gda_souscriptions'))
            ->where($this->db->quoteName('id_campagne') . ' = :check_id_campagne')
            ->where($this->db->quoteName('id_profil') . ' = :check_id_profil')
            ->bind(':check_id_profil', $idProfil)
            ->bind(':check_id_campagne', $idCampagne);

        $this->db->setQuery($check);
        $exists = (int) $this->db->loadResult() > 0;

        $query = $this->db->getQuery(true);

        if ($exists) {
            // Update de la souscription existante
           
            $query->update($this->db->quoteName('#__gda_souscriptions'))
                ->set($this->db->quoteName('last_update') . ' = :value_last_update')
                ->set($this->db->quoteName('cotisation_code') . ' = :value_cotisation_code')
                ->set($this->db->quoteName('id_order') . ' = :value_id_order')
                ->set ($this->db->quoteName('categorie') . ' = :value_categorie')
                ->where($this->db->quoteName('id_campagne') . ' = :value_id_campagne')
                ->where($this->db->quoteName('id_profil') . ' = :value_id_profil');
            $query->bind(':value_last_update', $last_update);
            $query->bind(':value_cotisation_code', $cotisation_code);
            $query->bind(':value_id_order', $id_order);
            $query->bind(':value_id_campagne', $idCampagne);
            $query->bind(':value_id_profil', $idProfil);
            $query->bind(':value_categorie', $categorie);



        } else {
            // Insert d'une nouvelle souscription
            $query->insert($this->db->quoteName('#__gda_souscriptions'));
            $query->columns([
                $this->db->quoteName('id_campagne'),
                $this->db->quoteName('id_profil'),
                $this->db->quoteName('date_souscription'),
                $this->db->quoteName('cotisation_code'),
                 $this->db->quoteName('id_order'),
                 $this->db->quoteName('last_update'),
                 $this->db->quoteName('categorie'),
            ]);
            $query->values(':value_id_campagne, :value_id_profil, :value_date_souscription, :value_cotisation_code, :value_id_order, :value_last_update, :value_categorie');
            $query->bind(':value_id_campagne', $idCampagne);
            $query->bind(':value_id_profil', $idProfil);
            $query->bind(':value_date_souscription', $date_souscription);
            $query->bind(':value_cotisation_code', $cotisation_code);
            $query->bind(':value_id_order', $id_order);
            $query->bind(':value_last_update', $last_update);
            $query->bind(':value_categorie', $categorie);

        }

        $this->db->setQuery($query);

        try {
            $this->db->execute();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage(), 500, $e);
        }

        return true;
    }

    /**
     * Désinscrit un profil d'une campagne.
     *
     * @param  array  $data  Tableau contenant au minimum 'id_campagne', 'id_profil', 'username'.
     *                       Peut contenir des clés supplémentaires.
     * @param  string $currentUsername  Username de l'utilisateur connecté (pour contrôle)
     * @return bool
     * @throws \RuntimeException en cas d'incohérence d'identité ou d'erreur SQL
     */
    public function desouscrire(array $data, string $currentUsername): bool
    {
        $idCampagne = (int) $data['id_campagne'];
        $idProfil   = (int) $data['id_profil'];
        $username   = $data['username'];

        // Contrôle d'identité
        if ($currentUsername !== $username) {
            throw new \RuntimeException(
                "le Username " . $currentUsername . " et la licence  (" . $username . ")  sont différents /!\\ ",
                500
            );
        }

        $delete = $this->db->getQuery(true);

        $delete->delete($this->db->quoteName('#__gda_souscriptions'));
        $delete->where($this->db->quoteName('id_campagne') . ' = :value_id_campagne');
        $delete->where($this->db->quoteName('id_profil') . ' = :value_id_profil');

        $delete->bind(':value_id_campagne', $idCampagne);
        $delete->bind(':value_id_profil', $idProfil);

        $this->db->setQuery($delete);

        try {
            $this->db->execute();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage(), 500, $e);
        }

        return true;
    }


    /**
     * récupère une souscriptions d'une campagne donnée.
     */
    public function getSouscription(int $idCampagne, int $idProfil): ?object
    {
        $query = $this->db->getQuery(true);
        $query->select('*')
            ->from($this->db->quoteName('#__gda_souscriptions'))
            ->where($this->db->quoteName('id_campagne') . ' = :value_id_campagne')
            ->where($this->db->quoteName('id_profil') . ' = :value_id_profil');

        $query->bind(':value_id_campagne', $idCampagne);
        $query->bind(':value_id_profil', $idProfil);

        $this->db->setQuery($query);

        try {
            $result = $this->db->loadObject();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage(), 500, $e);
        }

        return $result ?: null;
    }

    /**
     * Détermine si un CACI est valide pour être accepté par le secrétariat, c'est-à-dire
     * daté d'au moins 3 mois à compter d'aujourd'hui.
     *
     * Point d'entrée unique pour cette règle métier : le rendu serveur (layout secretariat/step_one)
     * et la réponse AJAX (secretariat.updateDateCaci) doivent tous les deux s'appuyer sur cette
     * méthode plutôt que de recalculer la règle chacun de leur côté (source d'incohérences).
     *
     * @param string|null $dateCaci Date du CACI au format d/m/Y.
     * @return bool
     */
    public function isCaciValidable(?string $dateCaci): bool
    {
        $dateCaci = trim((string) $dateCaci);

        if ($dateCaci === '') {
            return false;
        }

        // Le préfixe '!' force à minuit tous les champs non fournis par le format (jour/mois/annee),
        // pour comparer deux dates pures sans dérive liée à l'heure courante.
        $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $dateCaci) ?: null;
        $errors = \DateTimeImmutable::getLastErrors();

        $isStrictlyValid = $date !== null
            && $date->format('d/m/Y') === $dateCaci
            && empty($errors['warning_count'])
            && empty($errors['error_count']);

        if (!$isStrictlyValid) {
            return false;
        }

        $minValidDate = new \DateTimeImmutable('today +3 months');

        return $date >= $minValidDate;
    }

    /**
     * Met à jour l'id_order d'une souscription dans la table #__gda_souscriptions.
     *
     * @param int    $idProfil   Identifiant du profil.
     * @param int    $idCampagne Identifiant de la campagne.
     * @param string $idOrder    Identifiant de la commande HelloAsso.
     * @return void
     */
    public function updateIdOrder(int $idProfil, int $idCampagne, string $idOrder): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__gda_souscriptions'))
            ->set($this->db->quoteName('id_order') . ' = :id_order')
            ->where($this->db->quoteName('id_profil') . ' = :id_profil')
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->bind(':id_order', $idOrder)
            ->bind(':id_profil', $idProfil)
            ->bind(':id_campagne', $idCampagne);

        $this->db->setQuery($query);

        try {
            $this->db->execute();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Erreur mise à jour id_order : ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Résout un id_order manquant en interrogeant HelloAsso par username, et persiste
     * le résultat dans #__gda_souscriptions si une commande est trouvée.
     *
     * Ne fait rien (retourne l'id_order tel quel) si l'id_order est déjà connu, si la
     * campagne n'a pas de formulaire HelloAsso configuré, ou si aucune commande n'est
     * trouvée pour ce username.
     *
     * @param int    $idProfil   Identifiant du profil.
     * @param int    $idCampagne Identifiant de la campagne.
     * @param string $idOrder    id_order actuel ('0' ou vide si inconnu).
     * @param string $username   Username Joomla à rechercher dans les commandes HelloAsso.
     * @return string L'id_order (inchangé si déjà connu ou introuvable, résolu sinon).
     */
    public function resolveIdOrder(int $idProfil, int $idCampagne, string $idOrder, string $username): string
    {
        if ($idOrder !== '0' && $idOrder !== '') {
            return $idOrder;
        }

        $saison = SaisonService::getSaison($idCampagne);
        if ($saison === null || empty($saison->formType) || empty($saison->formSlug) || $username === '') {
            return $idOrder;
        }

        try {
            $foundOrder = (new HelloAssoService())->findOrderByUsername($saison->formType, $saison->formSlug, $username);
        } catch (\Throwable $e) {
            // Ne doit jamais casser l'affichage du statut d'adhésion (ex: HelloAsso indisponible
            // ou mal configuré) : on se contente de journaliser et de garder l'id_order tel quel.
            GdaLogger::warning(sprintf(
                'SouscriptionService::resolveIdOrder() - Echec recherche HelloAsso pour username "%s" (campagne %d) : %s',
                $username,
                $idCampagne,
                $e->getMessage()
            ));
            return $idOrder;
        }

        if ($foundOrder === null) {
            return $idOrder;
        }

        try {
            $this->updateIdOrder($idProfil, $idCampagne, $foundOrder);
        } catch (\Throwable $e) {
            // L'id_order retrouvé reste utilisable pour cet affichage même si la
            // persistance échoue ; elle sera retentée au prochain appel.
            GdaLogger::warning(sprintf(
                'SouscriptionService::resolveIdOrder() - Echec enregistrement id_order "%s" (profil %d, campagne %d) : %s',
                $foundOrder,
                $idProfil,
                $idCampagne,
                $e->getMessage()
            ));
        }

        return $foundOrder;
    }
}
