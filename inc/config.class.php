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
      $discoveredSites = array();
      $discoveryError = '';
      $hasCredentials = !empty($config->TenantID()) && !empty($config->ClientID()) && !empty($config->ClientSecret());

      $config->showFormHeader(array('colspan' => 4));
      echo '</table>';

      // Test de connexion SharePoint
      $result = array();
      if($hasCredentials){
         try {
            $result = $sharepoint->validateSharePointConnection();
            if (isset($result['status']) && $result['status'] === true) {
               $checkcon = 'Connexion API : <i class="fa fa-check-circle fa-xl text-success"></i></i>' . "\n";
               try {
                  $siteId = '';
                  $siteId = !empty($config->SiteID()) ? $config->SiteID() : $sharepoint->getSiteId($config->Hostname(), $config->SitePath());
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

         try {
            $discoveredSites = $sharepoint->discoverSites();
         } catch (Exception $e) {
            $discoveryError = $e->getMessage();
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
            <p class="mb-3"><?php echo __('Renseignez vos identifiants Azure AD puis sélectionnez le site et la liste SharePoint directement depuis les menus déroulants.', 'sharepointinfos'); ?></p>

            <ol class="mb-0 ps-3">
               <li><?php echo __('Complétez le Tenant ID, le Client ID et le Client Secret fournis par Azure.', 'sharepointinfos'); ?></li>
               <li><?php echo __('Une fois la connexion établie, choisissez le site SharePoint cible dans la liste proposée.', 'sharepointinfos'); ?></li>
               <li><?php echo __('Sélectionnez enfin la liste contenant vos données (ex : Liste techno).', 'sharepointinfos'); ?></li>
            </ol>
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
               <?php echo Html::input('TenantID', array('value' => $config->TenantID(), 'class' => 'form-control', 'id' => 'TenantID', 'required' => 'required')); ?>
            </div>
            <div class="col-md-6">
               <label for="ClientID" class="form-label mb-1"><?php echo __('Client ID', 'sharepointinfos'); ?></label>
               <?php echo Html::input('ClientID', array('value' => $config->ClientID(), 'class' => 'form-control', 'id' => 'ClientID', 'required' => 'required')); ?>
            </div>

            <div class="col-md-6">
               <label for="ClientSecret" class="form-label mb-1"><?php echo __('Client Secret', 'sharepointinfos'); ?></label>
               <?php echo Html::input('ClientSecret', array('value' => $config->ClientSecret(), 'class' => 'form-control', 'id' => 'ClientSecret', 'required' => 'required', 'type' => 'password')); ?>
            </div>

            <?php
            echo Html::hidden('Hostname', array('value' => $config->Hostname(), 'id' => 'Hostname'));
            echo Html::hidden('SitePath', array('value' => $config->SitePath(), 'id' => 'SitePath'));
            echo Html::hidden('SiteID', array('value' => $config->SiteID(), 'id' => 'SiteID'));
            echo Html::hidden('ListPath', array('value' => $config->ListPath(), 'id' => 'ListPath'));
            echo Html::hidden('ListID', array('value' => $config->ListID(), 'id' => 'ListID'));
            ?>

            <?php if (!$hasCredentials) { ?>
            <div class="col-12">
               <div class="alert alert-info mb-0"><?php echo __('Renseignez vos identifiants Azure pour découvrir automatiquement vos sites SharePoint.', 'sharepointinfos'); ?></div>
            </div>
            <?php } elseif (!empty($discoveryError)) { ?>
            <div class="col-12">
               <div class="alert alert-danger mb-0"><?php echo sprintf(__('Impossible de récupérer la liste des sites : %s', 'sharepointinfos'), htmlspecialchars($discoveryError)); ?></div>
            </div>
            <?php } else { ?>
            <div class="col-md-6">
               <label for="SharePointSiteSelector" class="form-label mb-1"><?php echo __('Site SharePoint', 'sharepointinfos'); ?></label>
               <select class="form-select" id="SharePointSiteSelector" data-placeholder="<?php echo htmlspecialchars(__('Sélectionnez un site', 'sharepointinfos')); ?>">
                  <option value=""><?php echo __('Sélectionnez un site', 'sharepointinfos'); ?></option>
                  <?php foreach ($discoveredSites as $site) { ?>
                     <?php
                        $siteLabel = isset($site['displayName']) ? $site['displayName'] : $site['id'];
                        $siteLocation = isset($site['hostname']) ? $site['hostname'] : '';
                        if (isset($site['sitePath']) && $site['sitePath'] !== '') {
                           $siteLocation .= $site['sitePath'];
                        }
                        if (!empty($siteLocation)) {
                           $siteLabel .= ' (' . $siteLocation . ')';
                        }
                     ?>
                     <option value="<?php echo htmlspecialchars($site['id']); ?>" data-hostname="<?php echo htmlspecialchars($site['hostname']); ?>" data-site-path="<?php echo htmlspecialchars($site['sitePath']); ?>">
                        <?php echo htmlspecialchars($siteLabel); ?>
                     </option>
                  <?php } ?>
               </select>
               <small class="form-text text-muted"><?php echo __('Le site sélectionné définit automatiquement le nom d\'hôte et le chemin.', 'sharepointinfos'); ?></small>
            </div>

            <div class="col-md-6">
               <label for="SharePointListSelector" class="form-label mb-1"><?php echo __('Liste SharePoint', 'sharepointinfos'); ?></label>
               <select class="form-select" id="SharePointListSelector" data-placeholder="<?php echo htmlspecialchars(__('Sélectionnez une liste', 'sharepointinfos')); ?>" disabled>
                  <option value=""><?php echo __('Sélectionnez une liste', 'sharepointinfos'); ?></option>
               </select>
               <small class="form-text text-muted"><?php echo __('Seules les listes disponibles sur le site choisi sont affichées.', 'sharepointinfos'); ?></small>
            </div>
            <?php } ?>

            <?php if (!empty($config->SiteID()) && !empty($config->ListID())) { ?>
            <div class="col-12">
               <?php
                  $currentSiteLabel = $config->Hostname();
                  if (!empty($config->SitePath())) {
                     $currentSiteLabel .= $config->SitePath();
                  }
                  if (empty($currentSiteLabel)) {
                     $currentSiteLabel = $config->SiteID();
                  }
                  $currentListLabel = !empty($config->ListPath()) ? $config->ListPath() : $config->ListID();
               ?>
               <div class="alert alert-secondary mb-0 small">
                  <?php echo __('Site actuellement sélectionné : ', 'sharepointinfos'); ?>
                  <strong><?php echo htmlspecialchars($currentSiteLabel); ?></strong><br>
                  <?php echo __('Liste sélectionnée : ', 'sharepointinfos'); ?>
                  <strong><?php echo htmlspecialchars($currentListLabel); ?></strong>
               </div>
            </div>
            <?php } ?>
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

      $statusIcons = array(
         1 => '<i class="fa fa-check-circle text-success"></i>', // ✅ Succès
         0 => '<i class="fa fa-times-circle text-danger"></i>'   // ❌ Échec
      );

      $fields = array(
         'accessToken'      => __('Token d\'accès', 'sharepointinfos'),
         'siteID'           => __('Site ID', 'sharepointinfos'),
         'listAccess'       => __('Accès à la Liste SharePoint', 'sharepointinfos'),
         'graphQuery'       => __('Microsoft Graph Query', 'sharepointinfos')
      );
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
                  $status  = (int)(isset($result[$key]['status']) ? $result[$key]['status'] : 0);
                  $message = (string)(isset($result[$key]['message']) ? $result[$key]['message'] : '');
                  $icon    = isset($statusIcons[$status]) ? $statusIcons[$status] : $statusIcons[0];

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

      <?php $sitesJson = json_encode($discoveredSites, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
      <script>
         // Bootstrap 5 : init tooltips quand le DOM est prêt et initialiser les sélecteurs SharePoint
         document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
               new bootstrap.Tooltip(el);
            });

            var discoveredSites = <?php echo $sitesJson ? $sitesJson : '[]'; ?>;
            if (!Array.isArray(discoveredSites) || discoveredSites.length === 0) {
               return;
            }

            var siteSelect = document.getElementById('SharePointSiteSelector');
            var listSelect = document.getElementById('SharePointListSelector');
            if (!siteSelect || !listSelect) {
               return;
            }

            var hiddenHostname = document.getElementById('Hostname');
            var hiddenSitePath = document.getElementById('SitePath');
            var hiddenSiteId = document.getElementById('SiteID');
            var hiddenListName = document.getElementById('ListPath');
            var hiddenListId = document.getElementById('ListID');

            var listsBySite = {};
            discoveredSites.forEach(function (site) {
               listsBySite[site.id] = Array.isArray(site.lists) ? site.lists : [];
            });

            var initialSiteId = hiddenSiteId && hiddenSiteId.value ? hiddenSiteId.value : '';
            if (!initialSiteId && hiddenHostname && hiddenSitePath) {
               discoveredSites.forEach(function (site) {
                  if (initialSiteId) {
                     return;
                  }
                  if (site.hostname === hiddenHostname.value && site.sitePath === hiddenSitePath.value) {
                     initialSiteId = site.id;
                  }
               });
            }

            var initialListId = hiddenListId && hiddenListId.value ? hiddenListId.value : '';
            var initialListName = hiddenListName && hiddenListName.value ? hiddenListName.value : '';

            function updateHiddenSite(site) {
               if (!hiddenHostname || !hiddenSitePath || !hiddenSiteId) {
                  return;
               }
               if (site) {
                  hiddenHostname.value = site.hostname || '';
                  hiddenSitePath.value = site.sitePath || '';
                  hiddenSiteId.value = site.id || '';
               } else {
                  hiddenHostname.value = '';
                  hiddenSitePath.value = '';
                  hiddenSiteId.value = '';
               }
            }

            function updateHiddenList(option) {
               if (!hiddenListId || !hiddenListName) {
                  return;
               }
               if (option && option.value) {
                  hiddenListId.value = option.value;
                  hiddenListName.value = option.getAttribute('data-display-name') || option.textContent;
               } else {
                  hiddenListId.value = '';
                  hiddenListName.value = '';
               }
            }

            function populateListSelector(siteId) {
               while (listSelect.firstChild) {
                  listSelect.removeChild(listSelect.firstChild);
               }

               var placeholder = listSelect.getAttribute('data-placeholder') || '';
               var defaultOption = document.createElement('option');
               defaultOption.value = '';
               defaultOption.textContent = placeholder;
               listSelect.appendChild(defaultOption);

               if (!siteId || !listsBySite[siteId] || listsBySite[siteId].length === 0) {
                  listSelect.disabled = true;
                  updateHiddenList(null);
                  return;
               }

               listSelect.disabled = false;
               listsBySite[siteId].forEach(function (list) {
                  var option = document.createElement('option');
                  option.value = list.id;
                  option.textContent = list.displayName || list.id;
                  option.setAttribute('data-display-name', list.displayName || list.id);
                  listSelect.appendChild(option);
               });

               var targetListId = initialListId && siteId === initialSiteId ? initialListId : '';
               if (!targetListId && initialListName) {
                  listsBySite[siteId].forEach(function (list) {
                     if (!targetListId && list.displayName === initialListName) {
                        targetListId = list.id;
                     }
                  });
               }

               if (targetListId) {
                  listSelect.value = targetListId;
                  var selectedOption = listSelect.options[listSelect.selectedIndex];
                  updateHiddenList(selectedOption);
               } else {
                  updateHiddenList(null);
               }
            }

            siteSelect.addEventListener('change', function () {
               var selectedSiteId = siteSelect.value;
               var selectedSite = null;
               discoveredSites.forEach(function (site) {
                  if (site.id === selectedSiteId) {
                     selectedSite = site;
                  }
               });
               updateHiddenSite(selectedSite);
               initialSiteId = selectedSite ? selectedSite.id : '';
               populateListSelector(selectedSiteId);
            });

            listSelect.addEventListener('change', function () {
               var option = listSelect.options[listSelect.selectedIndex];
               initialListId = option && option.value ? option.value : '';
               updateHiddenList(option);
            });

            if (initialSiteId) {
               siteSelect.value = initialSiteId;
            }

            if (siteSelect.value) {
               var selected = null;
               discoveredSites.forEach(function (site) {
                  if (site.id === siteSelect.value) {
                     selected = site;
                  }
               });
               updateHiddenSite(selected);
               populateListSelector(siteSelect.value);
            } else {
               populateListSelector('');
            }
         });
      </script>

      <style>
      /* Alignement propre dans la liste du modal */
      #customModal .list-group-item .row > [class^="col-"] { display: flex; align-items: center; }
      /* Optionnel : renforce l'alignement vertical */
      #customModal .list-group-item { padding-top: .6rem; padding-bottom: .6rem; }
      </style>

      <?php

      $config->showFormButtons(array('candel' => false));
      return false;
   }

   // --- Counter Invoice ---
   // Fonctions pour les champs SharePoint (cryptés)
   function TenantID()      { return PluginSharepointinfosCrypto::decrypt(isset($this->fields['TenantID']) ? $this->fields['TenantID'] : ''); }
   function ClientID()      { return PluginSharepointinfosCrypto::decrypt(isset($this->fields['ClientID']) ? $this->fields['ClientID'] : ''); }
   function ClientSecret()  { return PluginSharepointinfosCrypto::decrypt(isset($this->fields['ClientSecret']) ? $this->fields['ClientSecret'] : ''); }
   function Hostname()      { return PluginSharepointinfosCrypto::decrypt(isset($this->fields['Hostname']) ? $this->fields['Hostname'] : ''); }
   function SitePath()      { return PluginSharepointinfosCrypto::decrypt(isset($this->fields['SitePath']) ? $this->fields['SitePath'] : ''); }
   function ListPath()      { return PluginSharepointinfosCrypto::decrypt(isset($this->fields['ListPath']) ? $this->fields['ListPath'] : ''); }
   function SiteID()        { return PluginSharepointinfosCrypto::decrypt(isset($this->fields['SiteID']) ? $this->fields['SiteID'] : ''); }
   function ListID()        { return PluginSharepointinfosCrypto::decrypt(isset($this->fields['ListID']) ? $this->fields['ListID'] : ''); }

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
                  `SiteID` TEXT NULL,
                  `ListID` TEXT NULL,
                  PRIMARY KEY (`id`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
         $DB->query($query) or die($DB->error());
         $config->add(array('id' => 1));
      }

      $migration->displayMessage("Updating $table structure");
      foreach (array('SiteID', 'ListID') as $field) {
         if (!$DB->fieldExists($table, $field)) {
            $migration->addField($table, $field, 'text');
         }
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
