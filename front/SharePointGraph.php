<?php
/**
 * Classe principale de connexion à SharePoint via Graph API
 * 
 * Elle charge automatiquement la configuration depuis PluginSharepointinfosConfig,
 * résout le site et la liste SharePoint à partir de SITE_URL et LIST_DISPLAY_NAME,
 * puis retourne les éléments filtrés (client ou code sage).
 */

if (!defined('GRAPH_BASE'))  define('GRAPH_BASE', 'https://graph.microsoft.com/v1.0');
if (!defined('GRAPH_SCOPE')) define('GRAPH_SCOPE', 'https://graph.microsoft.com/.default');

class PluginSharepointinfosSharepoint extends CommonDBTM {

    protected $tenantId;
    protected $clientId;
    protected $clientSecret;
    protected $siteUrl;
    protected $listDisplayName;

    protected array $excludeDisplays = [
        'Balise de couleur','ID de ressource de conformité','ID','Modifié','Créé','Créé par','Modifié par',
        'Version','Pièces jointes','Modifier','Type',"Nombre d'éléments enfants","Nombre d’enfants du dossier",
        "Paramètres de l’étiquette","Étiquette de rétention","Étiquette de rétention appliquée","Étiquette appliquée par",
        "L’élément est un enregistrement",'Application créée par','Application modifiée par'
    ];

    protected array $excludeInternals = [
        '_ColorTag','ComplianceAssetId','ID','Modified','Created','Author','Editor','_UIVersionString','Attachments',
        'Edit','LinkTitle','LinkTitleNoMenu','DocIcon','ItemChildCount','FolderChildCount',
        '_ComplianceFlags','_ComplianceTag','_ComplianceTagWrittenTime','_ComplianceTagUserId','_IsRecord',
        'AppAuthor','AppEditor'
    ];

    /**
     * Constructeur : récupère automatiquement la config depuis PluginSharepointinfosConfig
     */
    public function __construct() {
        parent::__construct();

        // Charge la config de ton plugin SharePointInfos
        $config = new PluginSharepointinfosConfig();
            // fallback si ta classe utilise des getters
            $this->tenantId         = $config->TenantID();
            $this->clientId         = $config->ClientID();
            $this->clientSecret     = $config->ClientSecret();
            $this->siteUrl          = 'https://'.$config->Hostname().$config->SitePath();
            $this->listDisplayName  = $config->ListDisplayName();
    }

    /* =======================================================
       PUBLIC API
       ======================================================= */

    /**
     * Récupère les éléments SharePoint depuis la liste configurée
     */
    public function getListItemsFromConfig(?string $value = null, string $by = 'both'): array {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['error' => 'AUTH_FAILED'];
        }

        $siteId = $this->resolveSiteIdFromConfig($token);
        if (is_array($siteId) && isset($siteId['error'])) {
            return $siteId;
        }

        $list = $this->resolveListByDisplayName($token, $siteId, $this->listDisplayName);
        if (isset($list['error'])) {
            return $list;
        }
        $listId = $list['id'];

        [$internalToDisplay, $allColumns] = $this->getVisibleColumns($token, $siteId, $listId);
        if (isset($internalToDisplay['error'])) {
            return $internalToDisplay;
        }

        $select = implode(',', array_keys($internalToDisplay));
        $filter = $this->buildFilter($value, $by, $allColumns);

        $itemsUrl = GRAPH_BASE.'/sites/'.rawurlencode($siteId).'/lists/'.rawurlencode($listId)
                  . '/items?$top=500&$expand='.rawurlencode("fields(\$select=$select)");
        if ($filter) {
            $itemsUrl .= '&$filter='.rawurlencode($filter);
        }

        $res = $this->http_get_json($itemsUrl, [
            'Authorization: Bearer '.$token,
            'Prefer: HonorNonIndexedQueriesWarningMayFailRandomly'
        ]);
        $js = json_decode($res['body'], true);
        if (!isset($js['value'])) {
            return ['error' => 'NO_DATA', 'detail' => $js];
        }

        $rows = [];
        foreach ($js['value'] as $it) {
            $f = $it['fields'] ?? [];
            $line = [];
            foreach ($internalToDisplay as $internal => $display) {
                $line[$display] = $f[$internal] ?? null;
            }
            $rows[] = $line;
        }
        return $rows;
    }

    /* =======================================================
       INTERNE : API GRAPH
       ======================================================= */

    protected function getAccessToken(): ?string {
        $tokenUrl = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        $body = $this->http_post_form($tokenUrl, [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'client_credentials',
            'scope'         => GRAPH_SCOPE
        ]);
        $json = json_decode($body, true);
        return $json['access_token'] ?? null;
    }

    protected function resolveSiteIdFromConfig(string $token) {
        if (!preg_match('~^https?://([^/]+)(/.*)$~i', $this->siteUrl, $m)) {
            return ['error' => 'SITE_URL_INVALID'];
        }
        $host = $m[1];
        $path = $m[2];
        $url  = GRAPH_BASE.'/sites/'.rawurlencode($host).':'.$path;

        $res  = $this->http_get_json($url, ['Authorization: Bearer '.$token]);
        $js   = json_decode($res['body'], true);
        if (!empty($js['id'])) return $js['id'];
        return ['error'=>'SITE_NOT_FOUND', 'detail'=>$js];
    }

    protected function resolveListByDisplayName(string $token, string $siteId, string $listDisplayName) {
        $url = GRAPH_BASE.'/sites/'.rawurlencode($siteId).'/lists?$filter='
             . rawurlencode("displayName eq '$listDisplayName'");
        $res = $this->http_get_json($url, ['Authorization: Bearer '.$token]);
        $js  = json_decode($res['body'], true);
        if (!empty($js['value'][0])) return $js['value'][0];

        // fallback "contains"
        $res = $this->http_get_json(
            GRAPH_BASE.'/sites/'.rawurlencode($siteId).'/lists?$top=500&$select=id,displayName,webUrl',
            ['Authorization: Bearer '.$token]
        );
        $js  = json_decode($res['body'], true);
        foreach ($js['value'] ?? [] as $li) {
            if (!empty($li['displayName']) && mb_stripos($li['displayName'], $listDisplayName) !== false) {
                return $li;
            }
        }
        return ['error'=>'LIST_NOT_FOUND', 'detail'=>$js];
    }

    protected function getVisibleColumns(string $token, string $siteId, string $listId): array {
        $colsUrl = GRAPH_BASE.'/sites/'.rawurlencode($siteId).'/lists/'.rawurlencode($listId).'/columns?$top=500';
        $res     = $this->http_get_json($colsUrl, ['Authorization: Bearer '.$token]);
        $js      = json_decode($res['body'], true);
        $cols    = $js['value'] ?? null;
        if (!is_array($cols)) {
            return [ ['error'=>'COLUMNS_ERROR', 'detail'=>$js], [] ];
        }

        $internalToDisplay = [];
        foreach ($cols as $c) {
            $hidden = $c['hidden'] ?? false;
            $group  = $c['columnGroup'] ?? '';
            if ($hidden || $group === '_Hidden') continue;

            $internal = $c['name'] ?? '';
            $display  = $c['displayName'] ?? $internal;
            if (!$internal) continue;

            if (in_array($display,  $this->excludeDisplays,  true)) continue;
            if (in_array($internal, $this->excludeInternals, true)) continue;

            $internalToDisplay[$internal] = $display;
        }

        if (empty($internalToDisplay)) {
            return [ ['error'=>'NO_COLUMNS_AFTER_EXCLUDE'], $cols ];
        }
        return [ $internalToDisplay, $cols ];
    }

    protected function buildFilter(?string $value, string $by, array $allColumns): ?string {
        if ($value === null || $value === '') return null;

        $codeSageInternal = 'field_1';
        foreach ($allColumns as $c) {
            if (isset($c['displayName']) && mb_strtolower($c['displayName']) === 'code sage') {
                $codeSageInternal = $c['name'] ?? 'field_1';
                break;
            }
        }

        $safe = str_replace("'", "''", $value);
        $clauses = [];
        if ($by === 'client' || $by === 'both')    $clauses[] = "fields/Title eq '$safe'";
        if ($by === 'code_sage' || $by === 'both') $clauses[] = "fields/$codeSageInternal eq '$safe'";
        if (empty($clauses))                        $clauses[] = "fields/Title eq '$safe'";

        return implode(' or ', $clauses);
    }

    /* =======================================================
       HTTP Helper
       ======================================================= */

    protected function http_post_form(string $url, array $data): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return $body;
    }

    protected function http_get_json(string $url, array $headers = []): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        return ['body' => $body, 'info' => $info];
    }

    public function checkSharePointAccess(): array {
        $out = [
            'accessToken' => ['status' => 0, 'message' => 'Non vérifié'],
            'siteID'      => ['status' => 0, 'message' => 'Non vérifié'],
            'listAccess'  => ['status' => 0, 'message' => 'Non vérifié'],
            'graphQuery'  => ['status' => 0, 'message' => 'Non vérifié'],
        ];

        // 1) Token
        $token = $this->getAccessToken();
        if (!$token) {
            $out['accessToken'] = [
                'status'  => 0,
                'message' => "Échec de l'obtention du token (vérifie TenantID/ClientID/Secret et droits app Graph)."
            ];
            // On continue quand même pour remonter un max d’infos
        } else {
            $shown = substr($token, 0, 6).'…';
            $out['accessToken'] = [
                'status'  => 1,
                'message' => "OK (préfixe: {$shown})"
            ];
        }

        // 2) Site ID
        $siteId = null;
        if ($token) {
            $siteId = $this->resolveSiteIdFromConfig($token);
            if (is_array($siteId) && isset($siteId['error'])) {
                $out['siteID'] = [
                    'status'  => 0,
                    'message' => "Échec résolution site: ".($siteId['error'] ?? 'inconnu')
                ];
            } else {
                $out['siteID'] = [
                    'status'  => 1,
                    'message' => "OK (siteId: {$siteId})"
                ];
            }
        } else {
            $out['siteID'] = [
                'status'  => 0,
                'message' => "Non testé (pas de token)."
            ];
        }

        // 3) Accès à la liste
        $list = null;
        $listId = null;
        if ($token && is_string($siteId) && $siteId !== '') {
            $list = $this->resolveListByDisplayName($token, $siteId, $this->listDisplayName);
            if (isset($list['error'])) {
                $out['listAccess'] = [
                    'status'  => 0,
                    'message' => "Échec accès liste '{$this->listDisplayName}' (".$list['error'].")"
                ];
            } else {
                $listId = $list['id'] ?? null;
                if (!$listId) {
                    $out['listAccess'] = [
                        'status'  => 0,
                        'message' => "Liste trouvée mais id manquant."
                    ];
                } else {
                    // On vérifie l’endpoint lists/{id}
                    $url  = GRAPH_BASE.'/sites/'.rawurlencode($siteId).'/lists/'.rawurlencode($listId).'?$select=id,displayName';
                    $resp = $this->http_get_json($url, ['Authorization: Bearer '.$token]);
                    $http = (int)($resp['info']['http_code'] ?? 0);
                    if ($http === 200) {
                        $out['listAccess'] = [
                            'status'  => 1,
                            'message' => "OK (listId: {$listId})"
                        ];
                    } else {
                        $out['listAccess'] = [
                            'status'  => 0,
                            'message' => "HTTP {$http} sur /lists/{$listId}"
                        ];
                    }
                }
            }
        } else {
            $out['listAccess'] = [
                'status'  => 0,
                'message' => "Non testé (siteId/token manquant)."
            ];
        }

        // 4) Requête simple items?$top=1
        if ($token && is_string($siteId) && $siteId !== '' && $listId) {
            $url  = GRAPH_BASE.'/sites/'.rawurlencode($siteId).'/lists/'.rawurlencode($listId).'/items?$top=1&$expand='.rawurlencode('fields($select=Title)');
            $resp = $this->http_get_json($url, [
                'Authorization: Bearer '.$token,
                'Prefer: HonorNonIndexedQueriesWarningMayFailRandomly'
            ]);
            $http = (int)($resp['info']['http_code'] ?? 0);
            if ($http === 200) {
                $js = json_decode($resp['body'], true);
                $count = is_array($js['value'] ?? null) ? count($js['value']) : 0;
                $out['graphQuery'] = [
                    'status'  => 1,
                    'message' => "OK (items retournés: {$count})"
                ];
            } else {
                $out['graphQuery'] = [
                    'status'  => 0,
                    'message' => "HTTP {$http} sur /items?\$top=1"
                ];
            }
        } else {
            $out['graphQuery'] = [
                'status'  => 0,
                'message' => "Non testé (liste/site/token manquant)."
            ];
        }

        return $out;
    }
}
