<?php
include('../../../inc/includes.php');

$plugin = new Plugin();
if (!$plugin->isInstalled('sharepointinfos') || !$plugin->isActivated('sharepointinfos')) {
   Html::displayNotFoundError();
}

Session::checkRight('config', UPDATE);

$config = new PluginSharepointinfosConfig();

function encryptArray($array) {
   $include_keys = ['TenantID', 'ClientID', 'ClientSecret', 'Hostname', 'SitePath', 'ListPath'];
   $encrypted_array = [];

   foreach ($array as $key => $value) {
       // Crypter uniquement les clés définies dans $include_keys
       if (in_array($key, $include_keys) && !empty($value)) {
           $encrypted_array[$key] = PluginSharepointinfosCrypto::encrypt($value);
       } else {
           $encrypted_array[$key] = $value;
       }
   }
   return $encrypted_array;
}

if (isset($_POST["update"])) {
   $config->check($_POST['id'], UPDATE);
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
