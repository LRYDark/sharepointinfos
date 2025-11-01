<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginSharepointinfosTicket extends CommonDBTM {

   public static $rightname = 'sharepointinfos';

   static function getTypeName($nb = 0) {
      if(Session::haveRight("plugin_sharepointinfos_sign", READ)){
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
      if(Session::haveRight("plugin_sharepointinfos_sign", READ)){
         return '';
      }
   }

   static function showForTicket(Ticket $ticket) {
      Global $CFG_GLPI, $DB;

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

      // Vérifier la configuration
      if (empty($config->TenantID()) || empty($config->SitePath()) || empty($config->ListPath())) {
         echo '<div class="alert alert-danger">Configuration SharePoint incomplète. Veuillez configurer le plugin.</div>';
         return;
      }

      try {
         // Récupérer l'ID du site
         $siteId = $sharepoint->getSiteId($config->Hostname(), $config->SitePath());

         // Récupérer l'ID de la liste
         $listId = $sharepoint->getListId($siteId, $config->ListPath());

         // Récupérer les colonnes de la liste SharePoint
         $columns = $sharepoint->getListColumns($siteId, $listId);

         // Filtrer les colonnes à afficher (exclure les colonnes système)
         $displayColumns = [];
         $excludedColumns = ['ContentType', 'Attachments', '_UIVersionString', 'Edit', 'LinkTitle',
                             'ItemChildCount', 'FolderChildCount', 'AppAuthor', 'AppEditor',
                             '_ComplianceFlags', '_ComplianceTag', '_ComplianceTagWrittenTime',
                             '_ComplianceTagUserId', '_IsRecord', 'OData__ColorTag', 'ComplianceAssetId'];

         foreach ($columns as $column) {
            $columnName = $column['name'];
            $isHidden = isset($column['hidden']) && $column['hidden'] === true;

            // Exclure les colonnes système et cachées
            if (!in_array($columnName, $excludedColumns) && !$isHidden) {
               $displayColumns[] = [
                  'name' => $columnName,
                  'displayName' => $column['displayName'] ?? $columnName
               ];
            }
         }

         // Récupérer tous les éléments de la liste (sans filtre)
         $items = $sharepoint->getListItems($siteId, $listId, null);

         // Afficher le tableau
         echo '<div class="card">';
         echo '<div class="card-header d-flex justify-content-between align-items-center">';
         echo '<h3 class="card-title mb-0">';
         echo '<i class="fas fa-table"></i> ' . htmlspecialchars($config->ListPath());
         echo '</h3>';
         echo '<span class="badge bg-primary">' . count($items) . ' élément(s)</span>';
         echo '</div>';
         echo '<div class="card-body">';

         if (empty($items)) {
            echo '<div class="alert alert-info">Aucun élément dans la liste SharePoint.</div>';
         } else {
            echo '<div class="table-responsive">';
            echo '<table class="table table-striped table-hover table-sm">';
            echo '<thead class="table-dark">';
            echo '<tr>';

            // Afficher les en-têtes des colonnes
            foreach ($displayColumns as $column) {
               echo '<th>' . htmlspecialchars($column['displayName']) . '</th>';
            }

            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            // Afficher les lignes
            foreach ($items as $item) {
               echo '<tr>';

               if (isset($item['fields'])) {
                  foreach ($displayColumns as $column) {
                     $fieldName = $column['name'];
                     $fieldValue = $item['fields'][$fieldName] ?? '';

                     echo '<td>';

                     // Gérer les différents types de valeurs
                     if (is_array($fieldValue)) {
                        // Pour les lookups ou les personnes
                        if (isset($fieldValue['LookupValue'])) {
                           echo htmlspecialchars($fieldValue['LookupValue']);
                        } elseif (isset($fieldValue['Email'])) {
                           echo htmlspecialchars($fieldValue['Email']);
                        } else {
                           echo '<small class="text-muted">' . htmlspecialchars(json_encode($fieldValue)) . '</small>';
                        }
                     } elseif (is_bool($fieldValue)) {
                        echo '<span class="badge ' . ($fieldValue ? 'bg-success' : 'bg-secondary') . '">';
                        echo $fieldValue ? 'Oui' : 'Non';
                        echo '</span>';
                     } elseif (empty($fieldValue)) {
                        echo '<span class="text-muted">-</span>';
                     } else {
                        // Limiter la longueur pour l'affichage
                        $displayValue = htmlspecialchars($fieldValue);
                        if (strlen($displayValue) > 100) {
                           echo '<span title="' . $displayValue . '">' . substr($displayValue, 0, 100) . '...</span>';
                        } else {
                           echo $displayValue;
                        }
                     }

                     echo '</td>';
                  }
               }

               echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';

            // Légende
            echo '<div class="mt-3">';
            echo '<small class="text-muted">';
            echo '<i class="fas fa-info-circle"></i> ';
            echo 'Les données sont synchronisées en temps réel avec SharePoint. ';
            echo 'Toute modification dans SharePoint sera visible ici au prochain chargement.';
            echo '</small>';
            echo '</div>';
         }

         echo '</div>';
         echo '</div>';

      } catch (Exception $e) {
         echo '<div class="alert alert-danger">';
         echo '<strong>Erreur de connexion SharePoint : </strong><br>';
         echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
         echo '</div>';
      }
   }
}
