<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginSharepointinfosTicket extends CommonDBTM {

   public static $rightname = 'plugin_sharepointinfos';

   static function getTypeName($nb = 0) {
      if(Session::haveRight(self::$rightname, READ)){
         return _n('Sharepointinfos', 'Sharepointinfos', $nb, 'sharepointinfos');
      }
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      $nb = self::countForItem($item);
      switch ($item->getType()) {
         case 'Ticket' :
            return self::createTabEntry(self::getTypeName($nb), $nb);
         default :
            return self::getTypeName($nb);
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      switch ($item->getType()) {
         case 'Ticket' :
            self::showForTicket($item);
            break;
      }
      return true;
   }

   public static function countForItem(CommonDBTM $item) {
      if(Session::haveRight(self::$rightname, READ)){
         return '';
      }
   }

   static function showForTicket(Ticket $ticket) {
      if (!Session::haveRight(self::$rightname, READ)) {
         return;
      }

      $entityID = $ticket->getField('entities_id');

      // Récupérer le nom de l'entité
      $entity = new Entity();
      if ($entity->getFromDB($entityID)) {
         $entityName = $entity->getField('name');
      } else {
         echo '<div class="alert alert-warning">Entité non trouvée</div>';
         return;
      }

      // Charger la configuration et SharePoint
      require_once PLUGIN_SHAREPOINTINFOS_DIR.'/front/SharePointGraph.php';
      $config = new PluginSharepointinfosConfig();
      $sharepoint = new PluginSharepointinfosSharepoint();

      $hasSiteIdentifier = !empty($config->SiteID()) || (!empty($config->Hostname()) && !empty($config->SitePath()));
      $hasListIdentifier = !empty($config->ListID()) || !empty($config->ListPath());

      if (empty($config->TenantID()) || empty($config->ClientID()) || empty($config->ClientSecret()) || !$hasSiteIdentifier || !$hasListIdentifier) {
         echo '<div class="alert alert-danger">Configuration SharePoint incomplète. Veuillez vérifier les identifiants, le site et la liste.</div>';
         return;
      }

      try {
         // Récupérer l'ID du site
         $siteId = $sharepoint->getSiteId($config->Hostname(), $config->SitePath(), $config->SiteID());

         // Récupérer l'ID de la liste
         $listId = $sharepoint->getListId($siteId, $config->ListPath(), $config->ListID());

         // Récupérer les colonnes de la liste SharePoint
         $columns = $sharepoint->getListColumns($siteId, $listId);

         // Filtrer les colonnes à afficher (exclure les colonnes système)
         $displayColumns = array();
         $excludedColumns = array(
            'ContentType',
            'Attachments',
            '_UIVersionString',
            'Edit',
            'LinkTitle',
            'ItemChildCount',
            'FolderChildCount',
            'AppAuthor',
            'AppEditor',
            '_ComplianceFlags',
            '_ComplianceTag',
            '_ComplianceTagWrittenTime',
            '_ComplianceTagUserId',
            '_IsRecord',
            'OData__ColorTag',
            'ComplianceAssetId'
         );

         foreach ($columns as $column) {
            $columnName = isset($column['name']) ? $column['name'] : '';
            $isHidden = isset($column['hidden']) && $column['hidden'] === true;

            if (empty($columnName)) {
               continue;
            }

            // Exclure les colonnes système et cachées
            if (!in_array($columnName, $excludedColumns) && !$isHidden) {
               $displayColumns[] = array(
                  'name' => $columnName,
                  'displayName' => isset($column['displayName']) && !empty($column['displayName'])
                     ? $column['displayName']
                     : $columnName
               );
            }
         }

         // Récupérer uniquement les éléments correspondant à l'entité courante
         $filterEntityName = str_replace("'", "''", $entityName);
         $items = $sharepoint->getListItems($siteId, $listId, "fields/Title eq '$filterEntityName'");

         if (!empty($items)) {
            $filteredColumns = array();
            foreach ($displayColumns as $column) {
               $fieldName = isset($column['name']) ? $column['name'] : '';
               if (empty($fieldName)) {
                  continue;
               }

               foreach ($items as $item) {
                  if (isset($item['fields'][$fieldName]) && $item['fields'][$fieldName] !== '' && $item['fields'][$fieldName] !== null) {
                     $filteredColumns[] = $column;
                     break;
                  }
               }
            }

            if (!empty($filteredColumns)) {
               $displayColumns = $filteredColumns;
            }
         }

         if (empty($displayColumns)) {
            $displayColumns[] = array(
               'name' => 'Title',
               'displayName' => __('Titre', 'sharepointinfos')
            );
         }

         // Afficher le tableau
         if (empty($items)) {
            echo '<div class="alert alert-info">';
            echo 'Aucun élément SharePoint ne correspond à l\'entité &laquo; ' . htmlspecialchars($entityName) . ' &raquo;.';
            echo '</div>';
         } else {
            echo '<div class="table-responsive">';
            echo '<table class="table table-striped table-hover table-sm">';
            echo '<thead class="table-light">';
            echo '<tr>';

            foreach ($displayColumns as $column) {
               $headerLabel = isset($column['displayName']) ? $column['displayName'] : '';
               echo '<th>' . htmlspecialchars($headerLabel) . '</th>';
            }

            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($items as $item) {
               if (!isset($item['fields']) || !is_array($item['fields'])) {
                  continue;
               }

               echo '<tr>';
               foreach ($displayColumns as $column) {
                  $fieldName = isset($column['name']) ? $column['name'] : '';
                  $fieldValue = ($fieldName !== '' && isset($item['fields'][$fieldName])) ? $item['fields'][$fieldName] : '';

                  echo '<td>';

                  if (is_array($fieldValue)) {
                     if (isset($fieldValue['LookupValue'])) {
                        echo htmlspecialchars($fieldValue['LookupValue']);
                     } elseif (isset($fieldValue['Email'])) {
                        echo htmlspecialchars($fieldValue['Email']);
                     } else {
                        echo '<small class="text-muted">' . htmlspecialchars(json_encode($fieldValue)) . '</small>';
                     }
                  } elseif (is_bool($fieldValue)) {
                     echo $fieldValue ? __('Oui', 'sharepointinfos') : __('Non', 'sharepointinfos');
                  } elseif ($fieldValue === '' || $fieldValue === null) {
                     echo '<span class="text-muted">-</span>';
                  } else {
                     $displayValue = htmlspecialchars($fieldValue);
                     if (strlen($displayValue) > 100) {
                        echo '<span title="' . $displayValue . '">' . substr($displayValue, 0, 100) . '...</span>';
                     } else {
                        echo $displayValue;
                     }
                  }

                  echo '</td>';
               }
               echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
         }

      } catch (Exception $e) {
         echo '<div class="alert alert-danger">';
         echo '<strong>Erreur de connexion SharePoint : </strong><br>';
         echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
         echo '</div>';
      }
   }
}
