<?php
include('../../../inc/includes.php');

$plugin = new Plugin();
if (!$plugin->isInstalled('sharepointinfos') || !$plugin->isActivated('sharepointinfos')) {
   Html::displayNotFoundError();
}

Session::checkRight('config', UPDATE);

$config = new PluginSharepointinfosConfig();

function encryptArray($array) {
   static $include_keys_map = null;
   if ($include_keys_map === null) {
      $include_keys_map = array_flip(['TenantID', 'ClientID', 'ClientSecret', 'Hostname', 'SitePath', 'ListDisplayName', 'Link']);
   }
   $encrypted_array = [];

   foreach ($array as $key => $value) {
       // Crypter uniquement les clés définies dans $include_keys
       if (isset($include_keys_map[$key]) && is_scalar($value) && (string)$value !== '') {
           $encrypted_array[$key] = PluginSharepointinfosCrypto::encrypt((string)$value);
       } else {
           $encrypted_array[$key] = $value;
       }
   }
   return $encrypted_array;
}

function pluginSharepointinfosCheckCSRF(array $data): void {
   if (!empty($data['plugin_sharepointinfos_csrf_token'])) {
      Session::checkCSRF([
         '_glpi_csrf_token' => (string)$data['plugin_sharepointinfos_csrf_token']
      ], true);
      return;
   }

   Session::checkCSRF($data, true);
}

if (isset($_POST["update"])) {
   pluginSharepointinfosCheckCSRF($_POST);
   $encrypted_post = encryptArray($_POST);

   if(!$config->update($encrypted_post)){
      Session::addMessageAfterRedirect(
         __('Erreur lors de la modification', 'sharepointinfos'),
         true,
         ERROR
      );
   }
   Html::back();
}

Html::redirect($CFG_GLPI["root_doc"] . "/front/config.form.php?forcetab=" . urlencode('PluginSharepointinfosConfig$1'));
