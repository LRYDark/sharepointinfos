<?php
define('PLUGIN_SHAREPOINTINFOS_VERSION', '1.0.0'); // version du plugin
$_SESSION['PLUGIN_SHAREPOINTINFOS_VERSION'] = PLUGIN_SHAREPOINTINFOS_VERSION;

// Minimal GLPI version,
define("PLUGIN_SHAREPOINTINFOS_MIN_GLPI", "10.0.3");
// Maximum GLPI version,
define("PLUGIN_SHAREPOINTINFOS_MAX_GLPI", "10.2.0");

define("PLUGIN_SHAREPOINTINFOS_WEBDIR", Plugin::getWebDir("sharepointinfos"));
define("PLUGIN_SHAREPOINTINFOS_DIR", Plugin::getPhpDir("sharepointinfos"));
define("PLUGIN_SHAREPOINTINFOS_NOTFULL_DIR", Plugin::getPhpDir("sharepointinfos",false));
define("PLUGIN_SHAREPOINTINFOS_NOTFULL_WEBDIR", Plugin::getWebDir("sharepointinfos",false));

function plugin_init_sharepointinfos() { // fonction glpi d'initialisation du plugin
   global $PLUGIN_HOOKS, $CFG_GLPI;

   $PLUGIN_HOOKS['csrf_compliant']['sharepointinfos'] = true;
   $PLUGIN_HOOKS['change_profile']['sharepointinfos'] = [PluginSharepointinfosProfile::class, 'initProfile'];

   $plugin = new Plugin();
   if ($plugin->isActivated('sharepointinfos')){ // verification si le plugin sharepointinfos est installé et activé

      if (Session::getLoginUserID()) {
         Plugin::registerClass('PluginSharepointinfosProfile', ['addtabon' => 'Profile']);
      }

      Plugin::registerClass('PluginSharepointinfosTicket', ['addtabon' => 'Ticket']);

      $PLUGIN_HOOKS['config_page']['sharepointinfos'] = 'front/config.form.php'; // initialisation de la page config
      Plugin::registerClass('PluginSharepointinfosConfig', ['addtabon' => 'Config']); // ajout de la de la class config dans glpi

      $PLUGIN_HOOKS['post_show_item']['sharepointinfos'] = ['PluginSharepointinfosTicket', 'postShowItemNewTicketSHAREPOINTINFOS']; // initialisation de la class
      //$PLUGIN_HOOKS['pre_show_item']['sharepointinfos'] = ['PluginSharepointinfosTicket', 'postShowItemNewTaskSHAREPOINTINFOS']; // initialisation de la class
   }
}

function plugin_version_sharepointinfos() { // fonction version du plugin (verification et affichage des infos de la version)
   return [
      'name'           => _n('sharepoint infos', 'sharepoint infos', 2, 'sharepointinfos'),
      'version'        => PLUGIN_SHAREPOINTINFOS_VERSION,
      'author'         => 'REINERT Joris',
      'homepage'       => 'https://www.jcd-groupe.fr',
      'requirements'   => [
         'glpi' => [
            'min' => PLUGIN_SHAREPOINTINFOS_MIN_GLPI,
            'max' => PLUGIN_SHAREPOINTINFOS_MAX_GLPI,
         ]
      ]
   ];
}

/**
 * @return bool
 */
function plugin_sharepointinfos_check_prerequisites() {
   return true;
}
