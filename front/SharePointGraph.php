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
            curl_close($ch);
            return null;
        }

        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_status != 200) {
            curl_close($ch);
            return null;
        }

        curl_close($ch);
        $token_response = json_decode($response, true);
        return $token_response['access_token'];
    }

    /**
     * Fonction pour obtenir l'ID du site SharePoint
     * Gère différents formats d'URL SharePoint
     */
    public function getSiteId($hostname, $sitePath, $siteIdOverride = '') {
        if (!empty($siteIdOverride)) {
            return trim($siteIdOverride);
        }

        $accessToken = $this->getAccessToken();

        // Nettoyer les paramètres
        $hostname = trim($hostname);
        $sitePath = trim($sitePath);

        // S'assurer que sitePath commence par /
        if (!empty($sitePath) && $sitePath[0] !== '/') {
            $sitePath = '/' . $sitePath;
        }

        // Méthode 1 : Essayer avec hostname:sitePath (format standard)
        $url = "https://graph.microsoft.com/v1.0/sites/$hostname:$sitePath";

        $headers = array(
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseObj = json_decode($response, true);

        if (isset($responseObj['id'])) {
            return $responseObj['id'];
        }

        // Méthode 2 : Si échec, essayer sans le hostname dans le path
        // Ex: globalinfo763.sharepoint.com + /sites/clients
        if ($httpCode === 404) {
            $url2 = "https://graph.microsoft.com/v1.0/sites/$hostname:$sitePath:/";

            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $url2);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);

            $response2 = curl_exec($ch2);
            curl_close($ch2);

            $responseObj2 = json_decode($response2, true);

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
            $errorMsg .= "Réponse : " . $response;
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

        $accessToken = $this->getAccessToken();

        $url = "https://graph.microsoft.com/v1.0/sites/$siteId/lists?$filter=displayName eq '$listDisplayName'";

        $headers = array(
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $responseObj = json_decode($response, true);

        if (isset($responseObj['value']) && count($responseObj['value']) > 0) {
            return $responseObj['value'][0]['id'];
        } else {
            throw new Exception("Impossible de récupérer l'ID de la liste : " . $response);
        }
    }

    /**
     * Fonction pour récupérer les colonnes d'une liste SharePoint
     * @param string $siteId - L'ID du site SharePoint
     * @param string $listId - L'ID de la liste SharePoint
     * @return array - Les colonnes de la liste
     */
    public function getListColumns($siteId, $listId) {
        $accessToken = $this->getAccessToken();

        $url = "https://graph.microsoft.com/v1.0/sites/$siteId/lists/$listId/columns";

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

        if ($http_status != 200) {
            throw new Exception("Erreur HTTP $http_status : " . $response);
        }

        $responseObj = json_decode($response, true);

        if (isset($responseObj['value'])) {
            return $responseObj['value'];
        } else {
            throw new Exception("Impossible de récupérer les colonnes de la liste : " . $response);
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
        $accessToken = $this->getAccessToken();

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

        if ($http_status != 200) {
            throw new Exception("Erreur HTTP $http_status : " . $response);
        }

        $responseObj = json_decode($response, true);

        if (isset($responseObj['value'])) {
            return $responseObj['value'];
        } else {
            throw new Exception("Impossible de récupérer les éléments de la liste : " . $response);
        }
    }

    /**
     * Fonction pour valider la connexion SharePoint
     */
    public function validateSharePointConnection($sitePath) {
        try {
            $config = new PluginSharepointinfosConfig();

            // Test 1: Obtenir le token
            $accessToken = $this->getAccessToken();
            if (empty($accessToken)) {
                return array(
                    'status' => false,
                    'message' => 'Impossible d\'obtenir un token d\'accès. Vérifiez vos identifiants.'
                );
            }

            // Test 2: Obtenir l'ID du site
            try {
                $siteId = $this->getSiteId($config->Hostname(), $sitePath, $config->SiteID());
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
            $siteId = $this->getSiteId($config->Hostname(), $config->SitePath(), $config->SiteID());
            $results['siteID'] = array(
                'status' => !empty($siteId) ? 1 : 0,
                'message' => !empty($siteId) ? 'Site ID obtenu : ' . substr($siteId, 0, 20) . '...' : 'Impossible d\'obtenir le site ID'
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
