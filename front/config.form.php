<?php

$plugin = new Plugin();
if (!$plugin->isInstalled('sharepointinfos') || !$plugin->isActivated('sharepointinfos')) {
   throw new \Glpi\Exception\Http\NotFoundHttpException();
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

// GLPI 12 : la protection CSRF est assurée par le noyau (en-têtes Sec-Fetch-Site / Origin), sans jeton.
if (isset($_POST["update"])) {
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

// Fichier charge depuis une methode de GLPI : $CFG_GLPI n'y est visible qu'avec « global ».
global $CFG_GLPI;
Html::redirect($CFG_GLPI["root_doc"] . "/front/config.form.php?forcetab=" . urlencode('PluginSharepointinfosConfig$1'));
