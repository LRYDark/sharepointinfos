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

         // Récupérer les éléments de la liste filtrés par entityName
         // On filtre sur le champ Title qui doit contenir le nom de l'entité
         $filter = "fields/Title eq '$entityName'";
         $items = $sharepoint->getListItems($siteId, $listId, $filter);

         // Afficher le tableau
         echo '<div class="card">';
         echo '<div class="card-header">';
         echo '<h3 class="card-title">Éléments SharePoint pour : ' . htmlspecialchars($entityName) . '</h3>';
         echo '</div>';
         echo '<div class="card-body">';

         if (empty($items)) {
            echo '<div class="alert alert-info">Aucun élément trouvé pour cette entité.</div>';
         } else {
            echo '<table class="table table-striped table-hover">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>ID</th>';

            // Afficher les en-têtes dynamiquement basés sur les champs disponibles
            $firstItem = reset($items);
            if (isset($firstItem['fields'])) {
               foreach ($firstItem['fields'] as $fieldName => $fieldValue) {
                  echo '<th>' . htmlspecialchars($fieldName) . '</th>';
               }
            }

            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($items as $item) {
               echo '<tr>';
               echo '<td>' . htmlspecialchars($item['id'] ?? 'N/A') . '</td>';

               if (isset($item['fields'])) {
                  foreach ($item['fields'] as $fieldName => $fieldValue) {
                     // Gérer les différents types de valeurs
                     if (is_array($fieldValue)) {
                        echo '<td>' . htmlspecialchars(json_encode($fieldValue)) . '</td>';
                     } elseif (is_bool($fieldValue)) {
                        echo '<td>' . ($fieldValue ? 'Oui' : 'Non') . '</td>';
                     } else {
                        echo '<td>' . htmlspecialchars($fieldValue ?? '') . '</td>';
                     }
                  }
               }

               echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
         }

         echo '</div>';
         echo '</div>';

      } catch (Exception $e) {
         echo '<div class="alert alert-danger">';
         echo '<strong>Erreur : </strong>' . htmlspecialchars($e->getMessage());
         echo '</div>';
      }
   }
}
