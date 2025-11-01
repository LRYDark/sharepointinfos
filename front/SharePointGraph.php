<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginSharepointinfosSharepoint extends CommonDBTM {

    /**
     * Fonction pour obtenir un token d'accès à partir d'Azure AD
     */
    public function getAccessToken() {
        $config         = new PluginSharepointinfosConfig();
        $tenantId       = $config->TenantID();
        $clientId       = $config->ClientID();
        $clientSecret   = $config->ClientSecret();

        if (empty($tenantId) || empty($clientId) || empty($clientSecret)) {
            return null;
        }

        $token_url = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token";
        $token_data = array(
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'https://graph.microsoft.com/.default'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Erreur CURL lors de l'obtention du token : " . $error);
        }

        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status != 200) {
            throw new Exception("Réponse inattendue ($http_status) lors de l'obtention du token : " . $response);
        }

        $token_response = json_decode($response, true);
        if (!isset($token_response['access_token'])) {
            throw new Exception("Token d'accès introuvable dans la réponse : " . $response);
        }

        return $token_response['access_token'];
    }

    protected function performGraphGet($url, $accessToken = null) {
        if ($accessToken === null) {
            $accessToken = $this->getAccessToken();
        }

        if (empty($accessToken)) {
            throw new Exception("Impossible d'obtenir un token d'accès pour Microsoft Graph.");
        }

        $headers = array(
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Erreur CURL : " . $error);
        }

        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        return array(
            'status' => $http_status,
            'decoded' => $decoded,
            'raw' => $response
        );
    }

    /**
     * Fonction pour obtenir l'ID du site SharePoint
     * Gère différents formats d'URL SharePoint
     */
    public function getSiteId($hostname, $sitePath, $siteIdOverride = '') {
        if (!empty($siteIdOverride)) {
            return trim($siteIdOverride);
        }

        // Nettoyer les paramètres
        $hostname = trim($hostname);
        $sitePath = trim($sitePath);

        // S'assurer que sitePath commence par /
        if (!empty($sitePath) && $sitePath[0] !== '/') {
            $sitePath = '/' . $sitePath;
        }

        // Méthode 1 : Essayer avec hostname:sitePath (format standard)
        $url = "https://graph.microsoft.com/v1.0/sites/$hostname:$sitePath";

        $accessToken = $this->getAccessToken();
        $response = $this->performGraphGet($url, $accessToken);
        $httpCode = $response['status'];
        $responseObj = is_array($response['decoded']) ? $response['decoded'] : array();

        if (isset($responseObj['id'])) {
            return $responseObj['id'];
        }

        // Méthode 2 : Si échec, essayer sans le hostname dans le path
        // Ex: globalinfo763.sharepoint.com + /sites/clients
        if ($httpCode === 404) {
            $url2 = "https://graph.microsoft.com/v1.0/sites/$hostname:$sitePath:/";
            $response2 = $this->performGraphGet($url2, $accessToken);
            $responseObj2 = is_array($response2['decoded']) ? $response2['decoded'] : array();

            if (isset($responseObj2['id'])) {
                return $responseObj2['id'];
            }
        }

        // Si toujours en échec, afficher une erreur détaillée
        $errorMsg = "Impossible de récupérer l'ID du site.\n";
        $errorMsg .= "Hostname : $hostname\n";
        $errorMsg .= "SitePath : $sitePath\n";
        $errorMsg .= "URL testée : $url\n";

        if (isset($responseObj['error'])) {
            $errorMsg .= "Erreur API : " . $responseObj['error']['message'];
        } else {
            $errorMsg .= "Réponse : " . $response['raw'];
        }

        throw new Exception($errorMsg);
    }

    /**
     * Fonction pour obtenir l'ID d'une liste SharePoint par son nom d'affichage
     */
    public function getListId($siteId, $listDisplayName, $listIdOverride = '') {
        if (!empty($listIdOverride)) {
            return trim($listIdOverride);
        }

        $url = "https://graph.microsoft.com/v1.0/sites/$siteId/lists?$filter=displayName eq '$listDisplayName'";
        $response = $this->performGraphGet($url);
        $responseObj = is_array($response['decoded']) ? $response['decoded'] : array();

        if (isset($responseObj['value']) && count($responseObj['value']) > 0) {
            return $responseObj['value'][0]['id'];
        } else {
            throw new Exception("Impossible de récupérer l'ID de la liste : " . $response['raw']);
        }
    }

    /**
     * Fonction pour récupérer les colonnes d'une liste SharePoint
     * @param string $siteId - L'ID du site SharePoint
     * @param string $listId - L'ID de la liste SharePoint
     * @return array - Les colonnes de la liste
     */
    public function getListColumns($siteId, $listId) {
        $url = "https://graph.microsoft.com/v1.0/sites/$siteId/lists/$listId/columns";
        $response = $this->performGraphGet($url);

        if ($response['status'] != 200) {
            throw new Exception("Erreur HTTP " . $response['status'] . " : " . $response['raw']);
        }

        $responseObj = is_array($response['decoded']) ? $response['decoded'] : array();

        if (isset($responseObj['value'])) {
            return $responseObj['value'];
        } else {
            throw new Exception("Impossible de récupérer les colonnes de la liste : " . $response['raw']);
        }
    }

    /**
     * Fonction pour récupérer les éléments d'une liste SharePoint
     * @param string $siteId - L'ID du site SharePoint
     * @param string $listId - L'ID de la liste SharePoint
     * @param string $filter - Filtre OData optionnel
     * @param array $expand - Colonnes à étendre (ex: ['fields'])
     * @return array - Les éléments de la liste
     */
    public function getListItems($siteId, $listId, $filter = null, $expand = array('fields')) {
        // Construction de l'URL avec paramètres optionnels
        $url = "https://graph.microsoft.com/v1.0/sites/$siteId/lists/$listId/items";

        $params = array();
        if (!empty($expand)) {
            $params[] = '$expand=' . implode(',', $expand);
        }
        if (!empty($filter)) {
            $params[] = '$filter=' . urlencode($filter);
        }

        if (!empty($params)) {
            $url .= '?' . implode('&', $params);
        }

        $response = $this->performGraphGet($url);

        if ($response['status'] != 200) {
            throw new Exception("Erreur HTTP " . $response['status'] . " : " . $response['raw']);
        }

        $responseObj = is_array($response['decoded']) ? $response['decoded'] : array();

        if (isset($responseObj['value'])) {
            return $responseObj['value'];
        } else {
            throw new Exception("Impossible de récupérer les éléments de la liste : " . $response['raw']);
        }
    }

    public function discoverSites() {
        $sites = array();
        $accessToken = $this->getAccessToken();

        $nextUrl = 'https://graph.microsoft.com/v1.0/sites?search=*';

        while (!empty($nextUrl)) {
            $response = $this->performGraphGet($nextUrl, $accessToken);

            if ($response['status'] != 200) {
                throw new Exception("Impossible de découvrir les sites SharePoint : " . $response['raw']);
            }

            $body = is_array($response['decoded']) ? $response['decoded'] : array();

            if (isset($body['value']) && is_array($body['value'])) {
                foreach ($body['value'] as $site) {
                    if (!isset($site['id']) || !isset($site['webUrl'])) {
                        continue;
                    }

                    $parsed = parse_url($site['webUrl']);
                    if ($parsed === false || !isset($parsed['host'])) {
                        continue;
                    }

                    $sitePath = isset($parsed['path']) ? $parsed['path'] : '';

                    $sites[$site['id']] = array(
                        'id' => $site['id'],
                        'displayName' => isset($site['displayName']) ? $site['displayName'] : $site['id'],
                        'webUrl' => $site['webUrl'],
                        'hostname' => $parsed['host'],
                        'sitePath' => $sitePath,
                        'lists' => array()
                    );
                }
            }

            if (isset($body['@odata.nextLink'])) {
                $nextUrl = $body['@odata.nextLink'];
            } else {
                $nextUrl = null;
            }
        }

        if (empty($sites)) {
            throw new Exception("Aucun site SharePoint accessible n'a été détecté.");
        }

        foreach ($sites as $siteId => $siteData) {
            $sites[$siteId]['lists'] = $this->discoverSiteLists($siteId, $accessToken);
        }

        return array_values($sites);
    }

    public function discoverSiteLists($siteId, $accessToken = null) {
        if (empty($siteId)) {
            return array();
        }

        $url = "https://graph.microsoft.com/v1.0/sites/$siteId/lists?$select=id,displayName,list,webUrl";
        $response = $this->performGraphGet($url, $accessToken);

        if ($response['status'] != 200) {
            throw new Exception("Impossible de découvrir les listes du site : " . $response['raw']);
        }

        $body = is_array($response['decoded']) ? $response['decoded'] : array();
        $lists = array();

        if (isset($body['value']) && is_array($body['value'])) {
            foreach ($body['value'] as $list) {
                if (!isset($list['id'])) {
                    continue;
                }

                $isHidden = isset($list['list']['hidden']) && $list['list']['hidden'];
                $isSystem = isset($list['list']['system']) && $list['list']['system'];
                if ($isHidden) {
                    continue;
                }

                $template = isset($list['list']['template']) ? $list['list']['template'] : '';
                if ($template === 'documentLibrary') {
                    continue;
                }

                if ($isSystem) {
                    continue;
                }

                $lists[] = array(
                    'id' => $list['id'],
                    'displayName' => isset($list['displayName']) ? $list['displayName'] : $list['id'],
                    'webUrl' => isset($list['webUrl']) ? $list['webUrl'] : ''
                );
            }
        }

        return $lists;
    }

    public function getSiteDetails($siteId) {
        if (empty($siteId)) {
            throw new Exception("Identifiant de site manquant.");
        }

        $url = "https://graph.microsoft.com/v1.0/sites/$siteId";
        $response = $this->performGraphGet($url);

        if ($response['status'] != 200) {
            throw new Exception("Impossible de récupérer les informations du site : " . $response['raw']);
        }

        return $response['decoded'];
    }

    /**
     * Fonction pour valider la connexion SharePoint
     */
    public function validateSharePointConnection() {
        try {
            $config = new PluginSharepointinfosConfig();

            // Test 1: Obtenir le token
            $accessToken = $this->getAccessToken();

            // Test 2: Obtenir l'ID du site
            $siteId = $config->SiteID();
            try {
                if (empty($siteId)) {
                    $siteId = $this->getSiteId($config->Hostname(), $config->SitePath(), $config->SiteID());
                } else {
                    $this->getSiteDetails($siteId);
                }
                if (empty($siteId)) {
                    return array(
                        'status' => false,
                        'message' => 'Impossible d\'obtenir l\'ID du site. Vérifiez le hostname et le chemin du site.'
                    );
                }
            } catch (Exception $e) {
                return array(
                    'status' => false,
                    'message' => 'Erreur lors de la récupération du site : ' . $e->getMessage()
                );
            }

            // Test 3: Vérifier l'accès à la liste (si ListPath est configuré)
            $listPath = $config->ListPath();
            if (!empty($listPath) || !empty($config->ListID())) {
                try {
                    $listId = $this->getListId($siteId, $listPath, $config->ListID());
                    if (empty($listId)) {
                        return array(
                            'status' => false,
                            'message' => 'Impossible d\'accéder à la liste. Vérifiez le nom de la liste.'
                        );
                    }
                } catch (Exception $e) {
                    return array(
                        'status' => false,
                        'message' => 'Erreur lors de l\'accès à la liste : ' . $e->getMessage()
                    );
                }
            }

            return array(
                'status' => true,
                'message' => 'Connexion SharePoint réussie !',
                'siteId' => isset($siteId) ? $siteId : null
            );

        } catch (Exception $e) {
            return array(
                'status' => false,
                'message' => 'Erreur inattendue : ' . $e->getMessage()
            );
        }
    }

    /**
     * Fonction pour vérifier l'accès complet à SharePoint
     * Utilisé pour le modal de test de connexion
     */
    public function checkSharePointAccess() {
        $results = array();
        $config = new PluginSharepointinfosConfig();

        // Test 1: Token d'accès
        try {
            $accessToken = $this->getAccessToken();
            $results['accessToken'] = array(
                'status' => !empty($accessToken) ? 1 : 0,
                'message' => !empty($accessToken) ? 'Token obtenu avec succès' : 'Échec de l\'obtention du token'
            );
        } catch (Exception $e) {
            $results['accessToken'] = array(
                'status' => 0,
                'message' => 'Erreur : ' . $e->getMessage()
            );
        }

        // Test 2: Accès au site SharePoint
        try {
            $siteId = $config->SiteID();
            if (!empty($siteId)) {
                $siteDetails = $this->getSiteDetails($siteId);
            } else {
                $siteDetails = array();
                $siteId = $this->getSiteId($config->Hostname(), $config->SitePath(), $config->SiteID());
            }
            $results['siteID'] = array(
                'status' => !empty($siteId) ? 1 : 0,
                'message' => !empty($siteId)
                    ? 'Site sélectionné : ' . (isset($siteDetails['displayName']) ? $siteDetails['displayName'] : substr($siteId, 0, 20) . '...')
                    : 'Aucun site sélectionné'
            );
        } catch (Exception $e) {
            $results['siteID'] = array(
                'status' => 0,
                'message' => 'Erreur : ' . $e->getMessage()
            );
        }

        // Test 3: Accès à la liste
        if (( !empty($config->ListPath()) || !empty($config->ListID())) && isset($siteId)) {
            try {
                $listId = $this->getListId($siteId, $config->ListPath(), $config->ListID());
                $label = !empty($config->ListPath()) ? $config->ListPath() : $listId;
                $results['listAccess'] = array(
                    'status' => !empty($listId) ? 1 : 0,
                    'message' => !empty($listId) ? 'Liste accessible : ' . $label : 'Liste non accessible'
                );
            } catch (Exception $e) {
                $results['listAccess'] = array(
                    'status' => 0,
                    'message' => 'Erreur : ' . $e->getMessage()
                );
            }
        }

        // Test 4: Microsoft Graph Query
        $results['graphQuery'] = array(
            'status' => isset($results['accessToken']['status']) && $results['accessToken']['status'] == 1 ? 1 : 0,
            'message' => isset($results['accessToken']['status']) && $results['accessToken']['status'] == 1 ? 'Microsoft Graph API accessible' : 'Microsoft Graph API non accessible'
        );

        return $results;
    }
}
