<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Http\HttpFactory;
use NCB\Component\Gda\Site\Helper\CryptoHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use RuntimeException;

final class HelloAssoService
{
    private string $clientId;
    private string $clientSecret;
    private string $oauthBaseUrl; // ex: https://api.helloasso-sandbox.com/oauth2
    private string $apiBaseUrl;   // ex: https://api.helloasso-sandbox.com/v5
    private string $accessToken = '';
    private int $tokenExpiresAt = 0;
    private string $organizationSlug = '';

    /**
     * HelloAssoService constructor.
     */
    public function __construct( ) {

        $this->oauthBaseUrl =  (string) ConfHelper::getValue('HelloAssoBaseUrl') . '/oauth2';
        $this->apiBaseUrl =  (string) ConfHelper::getValue('HelloAssoBaseUrl') . '/v5';

        $this->clientId = $this->decryptIfNeeded((string) ConfHelper::getValue('HelloAssoClientId'));
        $this->clientSecret = $this->decryptIfNeeded((string) ConfHelper::getValue('HelloAssoClientSecret'));

        $this->organizationSlug = (string) ConfHelper::getValue('HelloAssoOrganizationSlug');

        $this->clientId = trim($this->clientId);
        $this->clientSecret = trim($this->clientSecret);
        $this->oauthBaseUrl = rtrim($this->oauthBaseUrl, '/');
        $this->apiBaseUrl = rtrim($this->apiBaseUrl, '/');

        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new RuntimeException('HelloAsso credentials are empty.');
        }
    }

    /**
     * Dechiffre une valeur si elle est au format chiffre, sinon la renvoie telle quelle.
     */
    private function decryptIfNeeded(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (!$this->looksEncryptedPayload($value)) {
            return $value;
        }

        try {
            return CryptoHelper::decrypt($value);
        } catch (\Throwable $e) {
            throw new RuntimeException('Unable to decrypt HelloAsso credential.', 0, $e);
        }
    }

    /**
     * Detecte le format base64(json({iv,tag,value})) de CryptoHelper.
     */
    private function looksEncryptedPayload(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        $data = json_decode($decoded, true);

        return is_array($data)
            && isset($data['iv'], $data['tag'], $data['value'])
            && is_string($data['iv'])
            && is_string($data['tag'])
            && is_string($data['value']);
    }


    /**
     * Get an access token from HelloAsso OAuth2 API.
     *
     * @return string The access token.
     * @throws RuntimeException If the request fails or the response is invalid.
     * 
     * documentation 
     */
    public function getAccessToken(): string
    {      

        if ($this->accessToken !== '' && $this->tokenExpiresAt > time() + 60) {
            return $this->accessToken;
        }

        // Jeton partagé entre requêtes : sans lui, chaque requête HTTP du site qui touche HelloAsso
        // (chaque affichage du dashboard après expiration d'un cache, chaque clic sur Rafraîchir)
        // paierait un aller-retour OAuth supplémentaire avant son appel utile.
        if ($this->loadCachedToken()) {
            return $this->accessToken;
        }

        $http = (new HttpFactory())->getHttp();
        $response = $http->post(
            $this->oauthBaseUrl . '/token',
            [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );

        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();
        $body = (string) $response->getBody();

        if ($statusCode !== 200) {
            throw new RuntimeException('OAuth error HTTP (' . $statusCode . '): ' . $reasonPhrase . '): ');
        }

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('OAuth error: invalid token payload');
        }

        // Retourne aussi refresh_token/expires_in pour votre stockage
                $this->accessToken    = (string) $data['access_token'];
        $this->tokenExpiresAt = time() + (int) ($data['expires_in'] ?? 1800);
        $this->storeCachedToken();

        return $this->accessToken;
    }

    /**
     * GET authentifié vers l'API HelloAsso, avec un unique nouvel essai si le jeton est refusé (401) :
     * un jeton peut être invalidé côté HelloAsso avant son expiration annoncée, et depuis sa mise en
     * cache entre requêtes (getAccessToken()) un jeton périmé serait sinon réutilisé jusqu'à
     * l'expiration locale.
     *
     * @param  string $url URL complète de l'endpoint.
     * @return \Joomla\Http\Response Réponse HTTP brute (le contrôle du statut reste à l'appelant).
     * @throws RuntimeException Si l'obtention du jeton échoue.
     */
    private function apiGet(string $url): \Joomla\Http\Response
    {
        $http = (new HttpFactory())->getHttp();

        for ($tentative = 1; ; $tentative++) {
            if ($this->accessToken === '' || $this->tokenExpiresAt <= time() + 60) {
                $this->getAccessToken();
            }

            $response = $http->get($url, [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
            ]);

            if ($response->getStatusCode() === 401 && $tentative === 1) {
                $this->accessToken    = '';
                $this->tokenExpiresAt = 0;
                @unlink($this->getTokenCacheFile());

                continue;
            }

            return $response;
        }
    }

    /**
     * Chemin du fichier de cache du jeton OAuth. Le nom dépend de l'environnement (URL OAuth) et du
     * client : un site qui bascule du sandbox à la production ne réutilise jamais le jeton de l'autre.
     */
    private function getTokenCacheFile(): string
    {
        return $this->getCacheDir() . '/oauth_' . md5($this->oauthBaseUrl . '|' . $this->clientId) . '.bin';
    }

    /**
     * Recharge un jeton encore valide depuis le cache fichier (voir getAccessToken()). Le fichier
     * est chiffré (CryptoHelper) : le répertoire de cache est sous la racine web, un jeton en clair
     * y donnerait accès aux données HelloAsso de l'association à quiconque en devinerait le nom.
     *
     * @return bool True si un jeton valide (au moins 60 s de marge) a été chargé.
     */
    private function loadCachedToken(): bool
    {
        $file = $this->getTokenCacheFile();

        if (!is_file($file)) {
            return false;
        }

        try {
            $data = json_decode(CryptoHelper::decrypt((string) file_get_contents($file)), true);
        } catch (\Throwable $e) {
            return false;
        }

        if (!is_array($data) || empty($data['token']) || (int) ($data['expires_at'] ?? 0) <= time() + 60) {
            return false;
        }

        $this->accessToken    = (string) $data['token'];
        $this->tokenExpiresAt = (int) $data['expires_at'];

        return true;
    }

    /**
     * Persiste le jeton courant (chiffré) pour les requêtes suivantes. Un échec d'écriture n'est
     * pas bloquant : le jeton reste utilisable pour la requête en cours.
     */
    private function storeCachedToken(): void
    {
        try {
            file_put_contents(
                $this->getTokenCacheFile(),
                CryptoHelper::encrypt((string) json_encode(['token' => $this->accessToken, 'expires_at' => $this->tokenExpiresAt])),
                LOCK_EX
            );
        } catch (\Throwable $e) {
            GdaLogger::warning('HelloAssoService : jeton OAuth non mis en cache - ' . $e->getMessage());
        }
    }


    /**
     * Obtenir les formulaires d'une organisation
     * @return array Les données des formulaires.
     * @throws RuntimeException Si la requête échoue ou si la réponse est invalide.
     * URL de l'API : https://api.helloasso.com/v5/organizations/{organizationSlug}/forms
     * documentation de l'API : https://dev.helloasso.com/reference/get_organizations-organizationslug-forms
     */
     public function getForms(): array
     {
        //$this->apiBaseUrl . '/organizations/' . rawurlencode($this->organizationSlug) . '/forms'
        $url = $this->getAPIWithPagination($this->apiBaseUrl . '/organizations/' . rawurlencode($this->organizationSlug) . '/forms');

        return $url;
     }



   

     /**
     * Obtenir une liste des types de formulaires pour une organisation
     *
     * @return array Les données des formulaires.
     * @throws RuntimeException Si la requête échoue ou si la réponse est invalide.
     * URL de l'API : https://api.helloasso.com/v5/organizations/{organizationSlug}/formTypes
     * documentation de l'API : https://dev.helloasso.com/reference/get_organizations-organizationslug-formtypes
     */
    public function getFormsTypes(): array
    {
        if (empty($this->accessToken)) {
            throw new RuntimeException('Access token is required to get forms.');
        }
        $http = (new HttpFactory())->getHttp();
        $url = $this->apiBaseUrl . '/organizations/' . rawurlencode($this->organizationSlug) . '/formTypes';

        $response = $http->get($url, [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept' => 'application/json',
        ]);

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode !== 200) {
            throw new RuntimeException('FormTypes API error HTTP ' . $statusCode . ': ' . $body);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('FormTypes API error: invalid JSON');
        }

        return $data;
    }

     /**
     * Get forms from HelloAsso API.
     *
     * @param string $formType The form type.
     * @param string $formSlug The form slug.
     * @return array The forms data.
     * @throws RuntimeException If the request fails or the response is invalid.
     */
    public function getFormsPublic( string $formType, string $formSlug): array
    {

        $url = $this->apiBaseUrl . '/organizations/' . rawurlencode($this->organizationSlug) . '/forms/' . rawurlencode($formType) . '/' . rawurlencode($formSlug) . '/public'    ;

        return $this->getAPIWithoutPagination($url);

    }

    /**
     * Variante mise en cache (fichier, 30 min) de getFormsPublic(), même motif que
     * getFormsOrders(). Les données publiques d'un formulaire (titre, dates, tiers/prix d'une
     * Boutique) ne changent pas assez souvent pour justifier un appel à l'API HelloAsso à chaque
     * affichage du dashboard Accueil par chaque adhérent - contrairement à getFormsPublic(), qui
     * reste appelée sans cache par CampagnesController::getformDetailHelloAsso() (action ponctuelle
     * du Bureau, pas un affichage répété).
     *
     * @param string $formType     Le type de formulaire.
     * @param string $formSlug     Le slug du formulaire.
     * @param bool   $forceRefresh Forcer l'appel à l'API en ignorant le cache.
     * @return array Les données publiques du formulaire.
     * @throws RuntimeException Si la requête échoue ou si la réponse est invalide.
     */
    public function getFormsPublicCached(string $formType, string $formSlug, bool $forceRefresh = false): array
    {
        return $this->getCachedOrFetch(
            'public_' . md5($formType . '_' . $formSlug),
            fn(): array => $this->getFormsPublic($formType, $formSlug),
            $forceRefresh
        );
    }

    /**
     * Obtenir les statistiques de vente d'un formulaire HelloAsso (FormStatsModel : quantité
     * vendue/max par tarif, entre autres). Utilisée pour calculer le stock restant d'un article de
     * Boutique (maxEntries - entriesTaken), absent du formulaire public (getFormsPublic()).
     *
     * @param string $formType Le type de formulaire.
     * @param string $formSlug Le slug du formulaire.
     * @return array Les statistiques du formulaire (unGroupedTiers, additionalOptions, totalParticipant).
     * @throws RuntimeException Si la requête échoue ou si la réponse est invalide.
     * URL de l'API : https://api.helloasso.com/v5/organizations/{organizationSlug}/forms/{formType}/{formSlug}/stats
     * Documentation de l'API : https://dev.helloasso.com/reference/get_organizations-organizationslug-forms-formtype-formslug-stats
     */
    public function getFormsStats(string $formType, string $formSlug): array
    {
        $url = $this->apiBaseUrl . '/organizations/' . rawurlencode($this->organizationSlug) . '/forms/' . rawurlencode($formType) . '/' . rawurlencode($formSlug) . '/stats';

        return $this->getAPIWithoutPagination($url);
    }

    /**
     * Variante mise en cache (fichier, 30 min) de getFormsStats(), même motif que
     * getFormsPublicCached().
     *
     * @param string $formType     Le type de formulaire.
     * @param string $formSlug     Le slug du formulaire.
     * @param bool   $forceRefresh Forcer l'appel à l'API en ignorant le cache.
     * @return array Les statistiques du formulaire.
     * @throws RuntimeException Si la requête échoue ou si la réponse est invalide.
     */
    public function getFormsStatsCached(string $formType, string $formSlug, bool $forceRefresh = false): array
    {
        return $this->getCachedOrFetch(
            'stats_' . md5($formType . '_' . $formSlug),
            fn(): array => $this->getFormsStats($formType, $formSlug),
            $forceRefresh
        );
    }

    /**
     * Lecture avec cache fichier des données publiques d'un formulaire (getFormsPublicCached(),
     * getFormsStatsCached()), pensée pour un affichage répété (dashboard de chaque adhérent) :
     *  - cache de 30 min ; un rafraîchissement forcé est, lui, limité à un appel HelloAsso par
     *    minute et par formulaire (chaque adhérent connecté peut cliquer sur "Rafraîchir") : en deçà,
     *    la donnée déjà en cache est renvoyée ;
     *  - un échec est mémorisé 5 min (1 min après un rafraîchissement forcé) pour ne pas refaire
     *    attendre un timeout à chaque affichage tant que HelloAsso ou le lien du formulaire est en
     *    panne ;
     *  - en cas d'échec, la dernière donnée connue - même expirée - est servie plutôt que rien.
     *
     * @param  string   $cacheKey     Clé du cache (préfixe + md5 formType/formSlug).
     * @param  callable $fetch        Appel API à effectuer, retourne le tableau à mettre en cache.
     * @param  bool     $forceRefresh True si l'appel vient du bouton "Rafraîchir".
     * @return array Données du formulaire.
     * @throws RuntimeException Si l'appel échoue et qu'aucune donnée n'est disponible en cache.
     */
    private function getCachedOrFetch(string $cacheKey, callable $fetch, bool $forceRefresh): array
    {
        $ttl            = 30 * 60;
        $ttlRefresh     = 60;
        $ttlErreur      = $forceRefresh ? $ttlRefresh : 5 * 60;
        $cached         = $this->getCache($cacheKey, $forceRefresh ? $ttlRefresh : $ttl);

        if ($cached !== null) {
            return $cached;
        }

        $erreurRecente = $this->getCache($cacheKey . '_err', $ttlErreur);
        $derniereValeur = $this->getCache($cacheKey, PHP_INT_MAX);

        if ($erreurRecente !== null) {
            if ($derniereValeur !== null) {
                return $derniereValeur;
            }

            throw new RuntimeException((string) ($erreurRecente['message'] ?? 'HelloAsso indisponible (échec récent)'));
        }

        try {
            $data = $fetch();
        } catch (\Throwable $e) {
            $this->setCache($cacheKey . '_err', ['message' => $e->getMessage()]);
            GdaLogger::warning('HelloAssoService : ' . $cacheKey . ' indisponible - ' . $e->getMessage());

            if ($derniereValeur !== null) {
                return $derniereValeur;
            }

            throw $e;
        }

        $this->setCache($cacheKey, $data);
        @unlink($this->getCacheDir() . '/' . $cacheKey . '_err.json');

        return $data;
    }


    /**
     * Obtenir les commandes d'un formulaire HelloAsso (avec cache fichier).
     *
     * @param string $formType Le type de formulaire.
     * @param string $formSlug Le slug du formulaire.
     * @param string $withDetails Inclure les détails.
     * @param bool   $forceRefresh Forcer l'appel à l'API en ignorant le cache.
     * @return array Les données des formulaires.
     * @throws RuntimeException Si la requête échoue ou si la réponse est invalide.
     * URL de l'API : https://api.helloasso.com/v5/organizations/{organizationSlug}/forms/{formType}/{formSlug}/orders
     * documentation de l'API : https://dev.helloasso.com/reference/get_organizations-organizationslug-forms-formtype-formslug-orders
     */
    public function getFormsOrders( string $formType, string $formSlug, string $withDetails = "false", bool $forceRefresh = false): array
    {
        $cacheKey = 'orders_' . md5($formType . '_' . $formSlug . '_' . $withDetails);
        $ttl = 30 * 60; // 30 minutes

        if (!$forceRefresh) {
            $cached = $this->getCache($cacheKey, $ttl);
            if ($cached !== null) {
                return $cached;
            }
        }

        $url = $this->apiBaseUrl . '/organizations/' . rawurlencode($this->organizationSlug) . '/forms/' . rawurlencode($formType) . '/' . rawurlencode($formSlug) . '/orders';

        $data = $this->getAPIWithPagination($url, ['withDetails' => $withDetails]);

        $this->setCache($cacheKey, $data);

        return $data;
    }


    /**
     * Cherche un order HelloAsso correspondant à un username dans les customFields.
     *
     * Utilise le cache de getFormsOrders() par défaut (secrétariat).
     * Passer $forceRefresh = true pour forcer l'appel API (adhésion en cours).
     *
     * @param string $formType     Type du formulaire HelloAsso.
     * @param string $formSlug     Slug du formulaire HelloAsso.
     * @param string $username     Username Joomla à rechercher dans les customFields.
     * @param bool   $forceRefresh true = ignore le cache fichier (défaut: false).
     * @return string|null         L'id de la commande trouvée, ou null si introuvable.
     */
    public function findOrderByUsername(string $formType, string $formSlug, string $username, bool $forceRefresh = false): ?string
    {
        $orders = $this->getFormsOrders($formType, $formSlug, 'true', $forceRefresh);

        foreach ($orders as $order) {
            // Une commande peut regrouper plusieurs adhérents (items[]) : chercher dans tous les
            // items, pas seulement le premier, sous peine de ne jamais retrouver la commande d'un
            // adhérent qui ne serait pas en première position.
            foreach (($order['items'] ?? []) as $item) {
                foreach (($item['customFields'] ?? []) as $field) {
                    if (($field['answer'] ?? '') === $username) {
                        return (string) $order['id'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Retrouve, parmi les items d'une commande HelloAsso, celui qui correspond à l'adhérent
     * donné. Une commande peut regrouper plusieurs adhérents (ex : un parent réglant en une fois
     * l'adhésion de plusieurs enfants) : chaque item porte son propre bénéficiaire (item.user) et
     * ses propres customFields (dont la licence, dans un champ dont l'id varie d'une campagne à
     * l'autre - la recherche se fait donc sur la VALEUR de la réponse, jamais sur l'id du champ).
     *
     * Cascade : licence exacte -> nom/prénom -> premier item (dernier recours, log un avertissement
     * pour rester repérable plutôt que d'échouer silencieusement).
     *
     * @param array<int, array> $items         Tableau 'items' brut d'une commande HelloAsso (getOrderDetails()).
     * @param string            $licenceAttendue Licence (username Joomla) de l'adhérent recherché.
     * @param string            $nomAttendu      Nom de famille de l'adhérent recherché.
     * @param string            $prenomAttendu   Prénom de l'adhérent recherché.
     * @return array|null L'item correspondant, ou null si $items est vide.
     */
    public function findItemForAdherent(array $items, string $licenceAttendue, string $nomAttendu, string $prenomAttendu): ?array
    {
        if ($items === []) {
            return null;
        }

        $licenceAttendue = trim($licenceAttendue);

        if ($licenceAttendue !== '') {
            foreach ($items as $item) {
                foreach (($item['customFields'] ?? []) as $field) {
                    if (trim((string) ($field['answer'] ?? '')) === $licenceAttendue) {
                        return $item;
                    }
                }
            }
        }

        $nomNorm = ToolsHelper::removeAccentsAndUppercase($nomAttendu);
        $prenomNorm = ToolsHelper::removeAccentsAndUppercase($prenomAttendu);

        if ($nomNorm !== '' || $prenomNorm !== '') {
            foreach ($items as $item) {
                $itemNom = ToolsHelper::removeAccentsAndUppercase((string) ($item['user']['lastName'] ?? ''));
                $itemPrenom = ToolsHelper::removeAccentsAndUppercase((string) ($item['user']['firstName'] ?? ''));

                if ($itemNom === $nomNorm && $itemPrenom === $prenomNorm) {
                    return $item;
                }
            }
        }

        GdaLogger::warning(sprintf(
            'HelloAssoService::findItemForAdherent() - Aucune correspondance (licence="%s", nom="%s %s") parmi %d item(s) : repli sur le premier item.',
            $licenceAttendue,
            $nomAttendu,
            $prenomAttendu,
            count($items)
        ));

        return $items[0];
    }

    /**
     * Extrait la réponse du champ personnalisé "Licence" d'un item de commande HelloAsso. Le nom
     * exact de ce champ n'est pas garanti (paramétrable par formulaire côté back-office HelloAsso) :
     * la recherche se fait sur le nom du champ (customFields[].name) contenant "licence", insensible
     * à la casse et aux accents (ToolsHelper::removeAccentsAndUppercase()).
     *
     * @param array $item Item HelloAsso ('items[]' d'une commande, voir getFormsOrders()/getOrderDetails()).
     * @return string|null Réponse trouvée (trim), ou null si aucun champ ne correspond.
     */
    public function extractLicenceAnswer(array $item): ?string
    {
        foreach (($item['customFields'] ?? []) as $field) {
            $fieldName = ToolsHelper::removeAccentsAndUppercase((string) ($field['name'] ?? ''));

            if (str_contains($fieldName, 'LICENCE')) {
                $answer = trim((string) ($field['answer'] ?? ''));

                return $answer !== '' ? $answer : null;
            }
        }

        return null;
    }


    /**
     *  Obtenir des informations détaillées sur une commande
     * 
     * @param string $orderId L'identifiant de la commande HelloAsso.
     * @return array Les données de la commande.
     * 
     * URL de l'API : https://api.helloasso.com/v5/orders/{orderId}
     * https://dev.helloasso.com/reference/get_orders-orderid
     */

    public function getOrderDetails(string $orderId): array
    {
        $url = $this->apiBaseUrl . '/orders/' . rawurlencode($orderId);

        return $this->getAPIWithoutPagination($url);
    }


    /**
     * Obtenir une liste d'articles vendus dans un formulaire
     *
     * @param string $formType Le type de formulaire.
     * @param string $formSlug Le slug du formulaire.
     * @return array Les données des formulaires.
     * @throws RuntimeException Si la requête échoue ou si la réponse est invalide.
     * 
     * URL de l'API : https://api.helloasso.com/v5/organizations/{organizationSlug}/forms/{formType}/{formSlug}/items
     * Documentation de l'API : https://dev.helloasso.com/reference/get_organizations-organizationslug-forms-formtype-formslug-items   
     */
    public function getFormsItems( string $formType, string $formSlug): array
    {
        
        $url = $this->apiBaseUrl . '/organizations/' . rawurlencode($this->organizationSlug) . '/forms/' . rawurlencode($formType) . '/' . rawurlencode($formSlug) . '/items'    ;

        return $this->getAPIWithPagination($url);

        // return $data;
    }




     /**
     * Get endpoint data from HelloAsso API.
     *
     * @param string $endpoint The API endpoint URL.
     * @return array The forms data.
     * @throws RuntimeException If the request fails or the response is invalid.
     */
    private function getAPIWithPagination($endpoint, $options = []): array
    {
        $allData = [];
        $continuationToken = "";
        if (isset($options['withDetails']) && $options['withDetails']) {
            $endpoint .= (str_contains($endpoint, '?') ? '&' : '?') . 'withDetails='.$options['withDetails'];
        }
        do {

            $url = $endpoint . ($continuationToken ? (str_contains($endpoint, '?') ? '&' : '?') . 'continuationToken=' . rawurlencode($continuationToken) : '');

            $response = $this->apiGet($url);

            $statusCode = $response->getStatusCode();
            $reasonPhrase = $response->getReasonPhrase();
            $body = (string) $response->getBody();

            if ($statusCode !== 200) {
                throw new RuntimeException('HelloAsso API error HTTP (' . $statusCode . '): ' . $reasonPhrase);
            }

            $response_body  = json_decode($body, true);
            if (!is_array($response_body)) {
                throw new RuntimeException('API error: invalid JSON');
            }

            // Fusionner les résultats
            if (!empty($response_body['data'])) {
                $allData = array_merge($allData, $response_body['data']);
            }

            // Préparer la requête suivante si continuationToken est présent
            $continuationToken = $response_body['pagination']['continuationToken'] ?? '';

                    $totalPages = (int) ($response_body['pagination']['totalPages'] ?? 1);
                    $pageIndex = (int) ($response_body['pagination']['pageIndex'] ?? 1);

        } while (! empty($response_body['data']) OR $pageIndex <= $totalPages);


         return $allData;
    }


         /**
     * Get endpoint data from HelloAsso API without pagination.
     *
     * @param string $endpoint The API endpoint URL.
     * @return array The forms data.
     * @throws RuntimeException If the request fails or the response is invalid.
     */
    private function getAPIWithoutPagination($endpoint): array
    {
        $response = $this->apiGet($endpoint);

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode !== 200) {
            throw new RuntimeException('API error HTTP ' . $statusCode . ': ' . $body);
        }

        $response_body  = json_decode($body, true);
        if (!is_array($response_body)) {
            throw new RuntimeException('API error: invalid JSON');
        }

        return $response_body;


    }


    /**
     * Récupère le chemin du répertoire de cache, en le créant si nécessaire.
     *
     * @return string
     */
    private function getCacheDir(): string
    {
        $dir = JPATH_CACHE . '/com_gdadhesions';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Lit les données depuis le cache fichier.
     *
     * @param string $key Clé du cache.
     * @param int    $ttl Durée de vie en secondes.
     * @return array|null Données mises en cache ou null si expiré/manquant.
     */
    private function getCache(string $key, int $ttl): ?array
    {
        $file = $this->getCacheDir() . '/' . $key . '.json';
        if (!file_exists($file)) {
            return null;
        }
        if ((time() - filemtime($file)) >= $ttl) {
            return null;
        }
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Écrit des données dans le cache fichier.
     *
     * @param string $key  Clé du cache.
     * @param array  $data Données à mettre en cache.
     */
    private function setCache(string $key, array $data): void
    {
        $file = $this->getCacheDir() . '/' . $key . '.json';
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
}