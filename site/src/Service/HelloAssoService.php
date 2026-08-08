<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Http\HttpFactory;
use NCB\Component\Gda\Site\Helper\CryptoHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
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
        return $this->accessToken;
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
            $customFields = $order['items'][0]['customFields'] ?? [];
            foreach ($customFields as $field) {
                if (($field['answer'] ?? '') === $username) {
                    return (string) $order['id'];
                }
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
        if ($this->accessToken === '' || $this->tokenExpiresAt <= time() + 60) {
                        // throw new RuntimeException('Access token is required to get.');
            $this->getAccessToken(); // Tenter de récupérer un nouveau token
        }
        $http = (new HttpFactory())->getHttp();
        $allData = [];
        $continuationToken = "";
        if (isset($options['withDetails']) && $options['withDetails']) {
            $endpoint .= (str_contains($endpoint, '?') ? '&' : '?') . 'withDetails='.$options['withDetails'];
        }
        do {

            $url = $endpoint . ($continuationToken ? (str_contains($endpoint, '?') ? '&' : '?') . 'continuationToken=' . rawurlencode($continuationToken) : '');

            $response = $http->get($url, [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
            ]);

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
        if ($this->accessToken === '' || $this->tokenExpiresAt <= time() + 60) {
            // throw new RuntimeException('Access token is required to get.');
            $this->getAccessToken(); // Tenter de récupérer un nouveau token
        }
        $http = (new HttpFactory())->getHttp();

        $response = $http->get($endpoint, [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept' => 'application/json',
        ]);

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