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
      if(!empty($config->TenantID()) && $config->SharePointOn() == 1){
         try {
            $result = $sharepoint->validateSharePointConnection($config->Hostname().':'.$config->SitePath());
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

      <!-- CARD : État de connexion SharePoint -->
      <div class="card mb-3">
      <div class="card-header">
         <h3 class="card-title mb-0"><?php echo __('Configuration SharePoint', 'sharepointinfos'); ?></h3>
      </div>
      <div class="card-body">
         <div class="row g-3">
            <div class="col-md-6">
               <label class="form-label mb-1"><?php echo __('Autoriser la connexion à SharePoint', 'sharepointinfos'); ?></label>
               <?php Dropdown::showYesNo('SharePointOn', $config->SharePointOn(), -1); ?>
            </div>
         </div>
      </div>
      </div>

      <?php if ($config->SharePointOn() == 1): ?>
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
               <label for="Hostname" class="form-label mb-1"><?php echo __('Nom d\'hôte', 'sharepointinfos'); ?></label>
               <?php echo Html::input('Hostname', ['value' => $config->Hostname(), 'class' => 'form-control', 'id' => 'Hostname']); ?>
            </div>

            <div class="col-md-6">
               <label for="SitePath" class="form-label mb-1"><?php echo __('Chemin du Site (/sites/XXXX)', 'sharepointinfos'); ?></label>
               <?php echo Html::input('SitePath', ['value' => $config->SitePath(), 'class' => 'form-control', 'id' => 'SitePath']); ?>
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
      'sharePointAccess' => __('Accès SharePoint', 'sharepointinfos'),
      'siteID'           => __('Site ID', 'sharepointinfos'),
      'graphQuery'       => __('Microsoft Graph Query', 'sharepointinfos'),
      'driveAccess'      => sprintf(__('Accès au Drive : <br> - %s', 'sharepointinfos'), Html::entities_deep($config->Global())),
      'permissions'      => sprintf(__('Permissions SharePoint : <br> - %s', 'sharepointinfos'), Html::entities_deep($config->Global()))
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
                  $icon    = ($key !== 'permissions') ? ($statusIcons[$status] ?? $statusIcons[0]) : '';

                  echo "<li class='list-group-item'>";
                  echo "<div class='row align-items-center gy-1'>";

                     // Colonne Champ
                     echo "<div class='col-4'><strong>$label</strong></div>";

                     // Colonne Statut (message + cas particuliers)
                     echo "<div class='col-6'>";
                        if ($key === 'permissions' && !empty($result[$key]['roles'])) {
                        echo "<ul class='list-unstyled mb-0'>";
                        foreach ($result[$key]['roles'] as $group => $roles) {
                           $roleList = implode(', ', array_map('Html::entities_deep', $roles));
                           echo "<li><strong>".Html::entities_deep($group)." :</strong> $roleList</li>";
                        }
                        echo "</ul>";
                        } else {
                        $safeMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

                        // Ajout d'une icône d'information uniquement pour driveAccess
                        if ($key === 'driveAccess') {
                           if (strpos($message, 'modifier des fichiers') !== false) {
                              $safeMsg .= " <i class='fa-solid fa-circle-exclamation text-warning' data-bs-toggle='tooltip'
                                             data-bs-placement='top'
                                             title='".__(
                                                "Le plugin ne pourra pas supprimer ou télécharger automatiquement les documents après signature, il ne sera pas fonctionnel à 100%",
                                                'sharepointinfos'
                                             )."'></i>";
                           } elseif (strpos($message, 'uniquement lire les fichiers') !== false) {
                              $safeMsg .= " <i class='fa-solid fa-circle-info text-secondary' data-bs-toggle='tooltip'
                                             data-bs-placement='top'
                                             title='".__(
                                                "Le plugin ne pourra pas supprimer, télécharger ou modifier automatiquement les documents après signature",
                                                'sharepointinfos'
                                             )."'></i>";
                           }
                        }

                        echo $safeMsg;
                        }
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
      <?php endif; ?>

      <?php

      $config->showFormButtons(['candel' => false]);
      return false;
   }

   // --- Counter Invoice ---
   function CounterInvoice() { // NEW
      return isset($this->fields['CounterInvoice']) ? (int)$this->fields['CounterInvoice'] : 0;
   }
   function CounterInvoiceUsers() {
      $raw = $this->fields['CounterInvoiceUsers'] ?? '[]';
      $arr = json_decode($raw, true);
      return is_array($arr) ? $arr : [];
   }
   function CounterInvoicePdf() {
      return isset($this->fields['CounterInvoicePdf']) ? (int)$this->fields['CounterInvoicePdf'] : 0;
   }
   function CounterInvoiceMail(){
      return isset($this->fields['CounterInvoiceMail']) ? (string)$this->fields['CounterInvoiceMail'] : '';
   } 
   function CounterInvoiceText(){
      return isset($this->fields['CounterInvoiceText']) ? (string)$this->fields['CounterInvoiceText'] : '';
   }

   // old
   function formulaire(){
      return ($this->fields['formulaire']);
   }
   function AddFileSite(){
      if (isset($this->fields['AddFileSite'])) return ($this->fields['AddFileSite']);
   }
   function Global(){
      return ($this->fields['Global']);
   }
   function LastCronTask(){
      return ($this->fields['LastCronTask']);
   }
   function SignatureX(){
      return ($this->fields['SignatureX']);
   } 
   function SignatureY(){
      return ($this->fields['SignatureY']);
   } 
   function SignatureSize(){
      return ($this->fields['SignatureSize']);
   } 
   function ExtractYesNo(){
      return ($this->fields['ExtractYesNo']);
   } 
   function extract(){
      return ($this->fields['extract']);
   } 
   function MailTrackerYesNo(){
      return ($this->fields['MailTrackerYesNo']);
   } 
   function MailTracker(){
      return ($this->fields['MailTracker']);
   } 
   function EntitiesExtract(){
      return ($this->fields['EntitiesExtract']);
   } 
   function EntitiesExtractValue(){
      return ($this->fields['EntitiesExtractValue']);
   } 
   function SignataireX(){
      return ($this->fields['SignataireX']);
   } 
   function SignataireY(){
      return ($this->fields['SignataireY']);
   } 
   function DateX(){
      return ($this->fields['DateX']);
   } 
   function DateY(){
      return ($this->fields['DateY']);
   } 
   function TechX(){
      return ($this->fields['TechX']);
   } 
   function TechY(){
      return ($this->fields['TechY']);
   }
   function NumberViews(){
      return ($this->fields['NumberViews']);
   }
   function ZenDocMail(){
      return ($this->fields['ZenDocMail']);
   }
   function SharePointLinkDisplay(){
      return ($this->fields['SharePointLinkDisplay']);
   }
   function DisplayPdfEnd(){
      return ($this->fields['DisplayPdfEnd']);
   }
   function MailTo(){
      return ($this->fields['MailTo']);
   }
   function gabarit(){
      return ($this->fields['gabarit']);
   }
   function ConfigModes(){
      return ($this->fields['ConfigModes']);
   }
   function gabarit_tracker(){
      return ($this->fields['gabarit_tracker']);
   }
   function mode(){
      return ($this->fields['mode']);
   }
   function SharePointOn(){
      return ($this->fields['SharePointOn']);
   }
   function SageOn(){
      return ($this->fields['SageOn']);
   }
   function SageSearch(){
      return ($this->fields['SageSearch']);
   }
   function SharePointSearch(){
      return ($this->fields['SharePointSearch']);
   }
   function LocalSearch(){
      return ($this->fields['LocalSearch']);
   }

   // --- Remote signature getters ---
   function RemoteSignatureOn() {
      return isset($this->fields['RemoteSignatureOn']) ? (int)$this->fields['RemoteSignatureOn'] : 0;
   }
   function RemoteSignatureUsers() {
      $raw = $this->fields['RemoteSignatureUsers'] ?? '[]';
      $arr = json_decode($raw, true);
      return is_array($arr) ? $arr : [];
   }
   function SageUrlApi(){
      return ($this->fields['SageUrlApi']);
   }

   function SageToken()     { return PluginSharepointinfosCrypto::decrypt($this->fields['SageToken']     ?? ''); }
   function TenantID()      { return PluginSharepointinfosCrypto::decrypt($this->fields['TenantID']      ?? ''); }
   function ClientID()      { return PluginSharepointinfosCrypto::decrypt($this->fields['ClientID']      ?? ''); }
   function ClientSecret()  { return PluginSharepointinfosCrypto::decrypt($this->fields['ClientSecret']  ?? ''); }
   function Hostname()      { return PluginSharepointinfosCrypto::decrypt($this->fields['Hostname']      ?? ''); }
   function SitePath()      { return PluginSharepointinfosCrypto::decrypt($this->fields['SitePath']      ?? ''); }
   // return fonction

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
                  `SharePointOn` TINYINT NOT NULL DEFAULT '0',
                  `Global` VARCHAR(255) NULL,
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
