<?php

function plugin_sharepointinfos_install() { // fonction installation du plugin
   global $DB;

   PluginSharepointinfosProfile::initProfile();
   PluginSharepointinfosProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);

   // Créer les tables de configuration
   $migration = new Migration(PLUGIN_SHAREPOINTINFOS_VERSION);
   PluginSharepointinfosConfig::install($migration);

   return true;
}

function plugin_sharepointinfos_uninstall() { // fonction desintallation du plugin

   //Delete rights associated with the plugin
   $profileRight = new ProfileRight();
   foreach (PluginSharepointinfosProfile::getAllRights() as $right) {
      $profileRight->deleteByCriteria(array('name' => $right['field']));
   }
   PluginSharepointinfosProfile::removeRightsFromSession();
   PluginSharepointinfosMenu::removeRightsFromSession();

   // Supprimer les tables de configuration
   $migration = new Migration(PLUGIN_SHAREPOINTINFOS_VERSION);
   PluginSharepointinfosConfig::uninstall($migration);

   return true;
}



