<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginSharepointinfosTicket extends CommonDBTM {

   public static $rightname = 'sharepointinfos';

   static function getIcon() {
      return "fa-solid fa-file-contract";
   }

   static function getTypeName($nb = 0) {
      //return _n('Infos Clients', 'Infos Clients', $nb, 'sharepointinfos');
      return __('<span class="d-flex align-items-center"><i class="fa-solid fa-share-alt me-2"></i>Infos Clients</span>', "sharepointinfos");
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if(Session::haveRight("plugin_sharepointinfos", READ)){
         $nb = 0;
         switch ($item->getType()) {
            case 'Ticket' :
               return self::getTypeName($nb);
         }
         return '';
      }
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      switch ($item->getType()) {
         case 'Ticket' :
            self::showForTicket($item);
            break;
      }
      return true;
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
         $entityName = "";
         return;
      }

      require_once PLUGIN_SHAREPOINTINFOS_DIR.'/front/SharePointGraph.php';

      if (!empty($entityName)) {
         $sp = new PluginSharepointinfosSharepoint();
         $result = $sp->getListItemsFromConfig($entityName, 'any');
         $config = new PluginSharepointinfosConfig();
         $escape = function ($str) {
            return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
         };
         $isUrl = function ($str) {
            return is_string($str) && filter_var($str, FILTER_VALIDATE_URL);
         };
         $formatValue = function ($val) use ($escape, $isUrl, &$formatValue) {
            if (is_array($val)) {
               // Si tous les éléments du tableau sont des URLs, on affiche la liste de liens cliquables
               $urlValues = [];
               foreach ($val as $v) {
                  if ($isUrl($v)) {
                     $urlValues[$v] = true;
                  } else {
                     $urlValues = [];
                     break;
                  }
               }
               if (!empty($urlValues)) {
                  return implode('<br>', array_map(function ($url) use ($escape) {
                     return '<a href="' . $escape($url) . '" target="_blank" style="color: #0b71d9; text-decoration: none;">' . $escape($url) . '</a>';
                  }, array_keys($urlValues)));
               }

               $items = [];
               foreach ($val as $k => $v) {
                  $label = is_string($k) ? '<strong>' . $escape($k) . ':</strong> ' : '';
                  $items[] = '<li style="margin:4px 0;">' . $label . $formatValue($v) . '</li>';
               }
               return '<ul style="margin:0; padding-left:18px;">' . implode('', $items) . '</ul>';
            }

            if ($val === null || $val === '') {
               return '<span style="color: #95a5a6;">-</span>';
            }

            // Badges de statut
            $normalized = strtolower(trim((string)$val));
            $style = null;
            if (strpos($normalized, 'configur') !== false) {
               $style = ['bg' => '#dff2e1', 'border' => '#a4d8ac', 'text' => '#2f7d35'];
            } else if (strpos($normalized, 'compatib') !== false) {
               $style = ['bg' => '#e6f1fb', 'border' => '#b6d2f4', 'text' => '#2d6db5'];
            } else if (strpos($normalized, 'inconn') !== false) {
               $style = ['bg' => '#f3f4f6', 'border' => '#d8dbe0', 'text' => '#6b7280'];
            }
            if ($style !== null) {
               $st = $style;
               return '<span style="display:inline-block;padding:4px 10px;border-radius:16px;border:1px solid ' 
                     . $st['border'] . ';background:' . $st['bg'] . ';color:' . $st['text']
                     . ';font-weight:600;font-size:13px;">' . $escape($val) . '</span>';
            }

            // URLs cliquables
            if ($isUrl($val)) {
               return '<a href="' . $escape($val) . '" target="_blank" style="color: #0b71d9; text-decoration: none;">' . $escape($val) . '</a>';
            }

            return $escape($val);
         };

         if (!empty($result)) {
            foreach ($result as $item) {
                  echo '<div style="background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin: 20px 0; overflow: hidden;">';
                  echo '<table style="width: 100%; border-collapse: collapse;">';
                  echo '<tbody>';
                  
                  foreach ($item as $field => $value) {
                     echo '<tr style="border-bottom: 1px solid #f0f0f0; transition: background 0.2s;">';
                     echo '<td style="width: 30%; padding: 18px 24px; font-weight: 600; color: #2c3e50; background: #fafafa;">' . $escape($field) . '</td>';
                     echo '<td style="padding: 18px 24px; color: #34495e;">' . $formatValue($value) . '</td>';
                     echo '</tr>';
                  }
                  
                  echo '</tbody>';
                  echo '</table>';
                  echo '</div>';
            }
            
            // Bouton de redirection vers SharePoint en bas
            echo '<div style="margin: 20px 0; text-align: right;">';
            echo '<a href="' . $config->Link() . '?q=' . $entityName . '" target="_blank" style="display: inline-block; padding: 10px 20px; background: #0078d4; color: #fff; text-decoration: none; border-radius: 4px; font-weight: 500; transition: background 0.3s;">';        echo '<i class="fas fa-external-link-alt" style="margin-right: 8px;"></i>';
            echo 'Voir sur SharePoint';
            echo '</a>';
            echo '</div>';
         } else {
            echo '<div class="alert alert-info">Aucune information trouvée pour cette entité</div>';
         }
      } else {
         echo '<div class="alert alert-info">Aucune information trouvée ou entité vide</div>';   
      }
   }
}




