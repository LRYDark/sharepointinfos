<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginSharepointinfosTicket extends CommonDBTM {

   public static $rightname = 'sharepointinfos';

   static function getTypeName($nb = 0) {
      if(Session::haveRight("plugin_sharepointinfos", READ)){
         return _n('Infos Clients', 'Infos Clients', $nb, 'sharepointinfos');
      }
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      $nb = 0;
      switch ($item->getType()) {
         case 'Ticket' :
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
         $result = $sp->getListItemsFromConfig($entityName, 'both');
         $config = new PluginSharepointinfosConfig();

         if (!empty($result)) {
            foreach ($result as $item) {
                  echo '<div style="background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin: 20px 0; overflow: hidden;">';
                  echo '<table style="width: 100%; border-collapse: collapse;">';
                  echo '<tbody>';
                  
                  foreach ($item as $field => $value) {
                     echo '<tr style="border-bottom: 1px solid #f0f0f0; transition: background 0.2s;">';
                     echo '<td style="width: 30%; padding: 18px 24px; font-weight: 600; color: #2c3e50; background: #fafafa;">' . $field . '</td>';
                     echo '<td style="padding: 18px 24px; color: #34495e;">' . ($value ?? '<span style="color: #95a5a6;">—</span>') . '</td>';
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
