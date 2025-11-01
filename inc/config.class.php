<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginSharepointinfosConfig extends CommonDBTM
{
   static private $_instance = null;

   function __construct()
   {
      global $DB;

      if ($DB->tableExists($this->getTable())) {
         $this->getFromDB(1);
      }
   }

   static function canCreate()
   {
      return Session::haveRight('config', UPDATE);
   }

   static function canView()
   {
      return Session::haveRight('config', READ);
   }

   static function canUpdate()
   {
      return Session::haveRight('config', UPDATE);
   }

   static function getTypeName($nb = 0)
   {
      return __("Sharepointinfos Bl ", "sharepointinfos");
   }

   static function getInstance()
   {
      if (!isset(self::$_instance)) {
         self::$_instance = new self();
         if (!self::$_instance->getFromDB(1)) {
            self::$_instance->getEmpty();
         }
      }
      return self::$_instance;
   }

   static function showConfigForm(){ //formulaire de configuration du plugin
      global $DB;
      $config = new self();
      $config->getFromDB(1);
      require_once PLUGIN_SHAREPOINTINFOS_DIR.'/front/SharePointGraph.php';

      $sharepoint = new PluginSharepointinfosSharepoint();
      $errorcon = "";
      $checkcon ="";

      $config->showFormHeader(['colspan' => 4]);
      echo '</table>';

      // Test de connexion SharePoint
      $result = [];
      if(!empty($config->TenantID())){
         try {
            $result = $sharepoint->validateSharePointConnection($config->SitePath());
            if (isset($result['status']) && $result['status'] === true) {
               $checkcon = 'Connexion API : <i class="fa fa-check-circle fa-xl text-success"></i></i>' . "\n";
               try {
                  $siteId = '';
                  $siteId = $sharepoint->getSiteId($config->Hostname(), $config->SitePath());
               } catch (Exception $e) {
                  $errorcon = '  <i class="fa-solid fa-circle-info fa-xl text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Erreur : '.$e->getMessage().'"></i>';
               }
            } else {
               $checkcon = 'Connexion API : <i class="fa fa-times-circle fa-xl text-danger"></i>' . "\n";
               $errorcon = '  <i class="fa-solid fa-circle-info fa-xl text-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$result['message'].'"></i>';
            }
         } catch (Exception $e) {
            $errorcon = '  <i class="fa-solid fa-circle-info fa-xl text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Erreur inattendue : '.$e->getMessage().'"></i>';
         }
      }
      ?>

      <!-- CARD : Aide configuration -->
      <div class="card mb-3 bg-light">
         <div class="card-header bg-info text-white">
            <h5 class="card-title mb-0">
               <i class="fas fa-info-circle"></i> <?php echo __('Comment configurer ?', 'sharepointinfos'); ?>
            </h5>
         </div>
         <div class="card-body">
            <p class="mb-2"><strong>À partir de votre URL SharePoint :</strong></p>
            <code class="d-block mb-3">https://<span class="text-danger">globalinfo763.sharepoint.com</span><span class="text-success">/sites/clients</span>/Lists/<span class="text-primary">Liste%20techno</span>/AllItems.aspx</code>

            <ul class="mb-0">
               <li><strong>Hostname :</strong> <span class="text-danger">globalinfo763.sharepoint.com</span> (partie avant /sites/...)</li>
               <li><strong>Chemin du Site :</strong> <span class="text-success">/sites/clients</span> (de /sites jusqu'au nom du site)</li>
               <li><strong>Nom de la Liste :</strong> <span class="text-primary">Liste techno</span> (nom exact sans %20, avec espaces)</li>
            </ul>
         </div>
      </div>

      <!-- CARD : Connexion SharePoint -->
      <div class="card mb-3">
         <div class="card-header">
            <h3 class="card-title mb-0">
            <?php echo __('Connexion SharePoint (API Graph) | '.$checkcon.$errorcon, 'sharepointinfos'); ?>
            </h3>
         </div>
         <div class="card-body">
            <div class="row g-3">
            <div class="col-md-6">
               <label for="TenantID" class="form-label mb-1"><?php echo __('Tenant ID', 'sharepointinfos'); ?></label>
               <?php echo Html::input('TenantID', ['value' => $config->TenantID(), 'class' => 'form-control', 'id' => 'TenantID']); ?>
            </div>
            <div class="col-md-6">
               <label for="ClientID" class="form-label mb-1"><?php echo __('Client ID', 'sharepointinfos'); ?></label>
               <?php echo Html::input('ClientID', ['value' => $config->ClientID(), 'class' => 'form-control', 'id' => 'ClientID']); ?>
            </div>

            <div class="col-md-6">
               <label for="ClientSecret" class="form-label mb-1"><?php echo __('Client Secret', 'sharepointinfos'); ?></label>
               <?php echo Html::input('ClientSecret', ['value' => $config->ClientSecret(), 'class' => 'form-control', 'id' => 'ClientSecret']); ?>
            </div>
            <div class="col-md-6">
               <label for="Hostname" class="form-label mb-1">
                  <?php echo __('Nom d\'hôte SharePoint', 'sharepointinfos'); ?>
                  <small class="text-muted">(ex: votreentreprise.sharepoint.com)</small>
               </label>
               <?php echo Html::input('Hostname', ['value' => $config->Hostname(), 'class' => 'form-control', 'id' => 'Hostname', 'placeholder' => 'globalinfo763.sharepoint.com']); ?>
            </div>

            <div class="col-md-6">
               <label for="SitePath" class="form-label mb-1">
                  <?php echo __('Chemin du Site', 'sharepointinfos'); ?>
                  <small class="text-muted">(ex: /sites/clients)</small>
               </label>
               <?php echo Html::input('SitePath', ['value' => $config->SitePath(), 'class' => 'form-control', 'id' => 'SitePath', 'placeholder' => '/sites/clients']); ?>
               <small class="form-text text-muted">Copier depuis l'URL : https://votreentreprise.sharepoint.com<strong>/sites/clients</strong>/...</small>
            </div>

            <div class="col-md-6">
               <label for="ListPath" class="form-label mb-1">
                  <?php echo __('Nom de la Liste SharePoint', 'sharepointinfos'); ?>
                  <small class="text-muted">(nom exact affiché dans SharePoint)</small>
               </label>
               <?php echo Html::input('ListPath', ['value' => $config->ListPath(), 'class' => 'form-control', 'id' => 'ListPath', 'placeholder' => 'Liste techno']); ?>
               <small class="form-text text-muted">Le nom exact tel qu'affiché dans SharePoint (sensible à la casse)</small>
            </div>
            </div>
         </div>
      </div>

      <!-- CARD : Bouton de test de connexion -->
      <div class="card mb-3">
         <div class="card-header d-flex align-items-center justify-content-between">
            <h3 class="card-title mb-0"><?php echo __('Connexion SharePoint', 'sharepointinfos'); ?></h3>
            <button type="button"
                     class="btn btn-outline-primary btn-sm"
                     data-bs-toggle="modal"
                     data-bs-target="#customModal">
               <?php echo __('Statut de connexion SharePoint', 'sharepointinfos'); ?>
            </button>
         </div>

         <div class="card-body">
            <p class="text-muted mb-0">
               <?php echo __('Cliquez sur "Statut de connexion SharePoint" pour afficher le détail des vérifications.', 'sharepointinfos'); ?>
            </p>
         </div>
      </div>

      <?php
      // ---------- MODAL DE TEST DE CONNEXION ----------
      $result = $sharepoint->checkSharePointAccess();

      $statusIcons = [
      1 => '<i class="fa fa-check-circle text-success"></i>', // ✅ Succès
      0 => '<i class="fa fa-times-circle text-danger"></i>'   // ❌ Échec
      ];

      $fields = [
      'accessToken'      => __('Token d\'accès', 'sharepointinfos'),
      'siteID'           => __('Site ID', 'sharepointinfos'),
      'listAccess'       => __('Accès à la Liste SharePoint', 'sharepointinfos'),
      'graphQuery'       => __('Microsoft Graph Query', 'sharepointinfos')
      ];
      ?>

      <div class="modal fade" id="customModal" tabindex="-1" aria-labelledby="AddSharepointinfosModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
         <div class="modal-content">

            <div class="modal-header">
            <h5 class="modal-title" id="AddSharepointinfosModalLabel">
               <?php echo __('Statut de connexion SharePoint', 'sharepointinfos'); ?>
               <i class="fa-solid fa-circle-info text-secondary ms-1"
                  data-bs-toggle="tooltip"
                  data-bs-placement="top"
                  title="<?php echo __(
                     "Pensez à vérifier les droits de suppression, de lecture et d'écriture sur le site SharePoint afin d'assurer son bon fonctionnement et une récupération optimale des métadonnées.",
                     'sharepointinfos'
                  ); ?>"></i>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo __('Fermer', 'sharepointinfos'); ?>"></button>
            </div>

            <div class="modal-body">
            <ul class="list-group">

               <!-- En-tête -->
               <li class="list-group-item">
                  <div class="row fw-bold">
                  <div class="col-4"><?php echo __('Champ', 'sharepointinfos'); ?></div>
                  <div class="col-6"><?php echo __('Statut', 'sharepointinfos'); ?></div>
                  <div class="col-2 text-center"><?php echo __('Validation', 'sharepointinfos'); ?></div>
                  </div>
               </li>

               <?php
               foreach ($fields as $key => $label) {
                  if (!isset($result[$key])) {
                  continue;
                  }
                  $status  = (int)($result[$key]['status'] ?? 0);
                  $message = (string)($result[$key]['message'] ?? '');
                  $icon    = $statusIcons[$status] ?? $statusIcons[0];

                  echo "<li class='list-group-item'>";
                  echo "<div class='row align-items-center gy-1'>";

                     // Colonne Champ
                     echo "<div class='col-4'><strong>$label</strong></div>";

                     // Colonne Statut
                     echo "<div class='col-6'>";
                     echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
                     echo "</div>";

                     // Colonne Validation (icône)
                     echo "<div class='col-2 text-center'>$icon</div>";

                  echo "</div>";
                  echo "</li>";
               }
               ?>

            </ul>
            </div>

            <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('Fermer', 'sharepointinfos'); ?></button>
            </div>

         </div>
      </div>
      </div>

      <script>
         // Bootstrap 5 : init tooltips quand le DOM est prêt
         document.addEventListener('DOMContentLoaded', function () {
         document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el);
         });
         });
      </script>

      <style>
      /* Alignement propre dans la liste du modal */
      #customModal .list-group-item .row > [class^="col-"] { display: flex; align-items: center; }
      /* Optionnel : renforce l'alignement vertical */
      #customModal .list-group-item { padding-top: .6rem; padding-bottom: .6rem; }
      </style>

      <?php

      $config->showFormButtons(['candel' => false]);
      return false;
   }

   // --- Counter Invoice ---
   // Fonctions pour les champs SharePoint (cryptés)
   function TenantID()      { return PluginSharepointinfosCrypto::decrypt($this->fields['TenantID']      ?? ''); }
   function ClientID()      { return PluginSharepointinfosCrypto::decrypt($this->fields['ClientID']      ?? ''); }
   function ClientSecret()  { return PluginSharepointinfosCrypto::decrypt($this->fields['ClientSecret']  ?? ''); }
   function Hostname()      { return PluginSharepointinfosCrypto::decrypt($this->fields['Hostname']      ?? ''); }
   function SitePath()      { return PluginSharepointinfosCrypto::decrypt($this->fields['SitePath']      ?? ''); }
   function ListPath()      { return PluginSharepointinfosCrypto::decrypt($this->fields['ListPath']      ?? ''); }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
   {

      if ($item->getType() == 'Config') {
         return __("Sharepointinfos BL", "sharepointinfos");
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
   {

      if ($item->getType() == 'Config') {
         self::showConfigForm();
      }
      return true;
   }

   function decryptData($data) {
      // Clé de cryptage - Doit correspondre à la clé utilisée pour le cryptage
      $encryption_key = 'votre_clé_de_cryptage';
      return openssl_decrypt(base64_decode($data), 'aes-256-cbc', $encryption_key, 0, '1234567890123456');
   }
  
   static function install(Migration $migration)
   {
      global $DB;

      $default_charset = DBConnection::getDefaultCharset();
      $default_collation = DBConnection::getDefaultCollation();
      $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

      $table = self::getTable();
      $config = new self();

      if (!$DB->tableExists($table)) {

         $migration->displayMessage("Installing $table");

         // Créer la table de configuration avec uniquement les champs SharePoint
         $query = "CREATE TABLE IF NOT EXISTS $table (
                  `id` int {$default_key_sign} NOT NULL auto_increment,
                  `TenantID` TEXT NULL,
                  `ClientID` TEXT NULL,
                  `ClientSecret` TEXT NULL,
                  `Hostname` TEXT NULL,
                  `SitePath` TEXT NULL,
                  `ListPath` TEXT NULL,
                  PRIMARY KEY (`id`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
         $DB->query($query) or die($DB->error());
         $config->add(['id' => 1,]);
      }
   }

   static function uninstall(Migration $migration)
   {
      global $DB;

      $table = self::getTable();
      if ($DB->TableExists($table)) {
         $migration->displayMessage("Uninstalling $table");
         $migration->dropTable($table);
      }
   }
}
