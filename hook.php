<?php

function plugin_sharepointinfos_install() { // fonction installation du plugin

   PluginSharepointinfosProfile::initProfile();
   PluginSharepointinfosProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);

   return true; 
}

function plugin_sharepointinfos_uninstall() { // fonction desintallation du plugin

   //Delete rights associated with the plugin
   $profileRight = new ProfileRight();
   foreach (PluginSharepointinfosProfile::getAllRights() as $right) {
      $profileRight->deleteByCriteria(['name' => $right['field']]);
   }
   PluginSharepointinfosProfile::removeRightsFromSession();

   return true;
}



