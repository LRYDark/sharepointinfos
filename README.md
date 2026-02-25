# Sharepoint Infos

Plugin GLPI qui ajoute un onglet Ticket pour afficher des informations client stockées dans une liste SharePoint (via Microsoft Graph), en s'appuyant sur les données d'entité GLPI pour retrouver l'enregistrement concerné.

Le plugin sert surtout à éviter de sortir de GLPI pour consulter des informations de référence client (coordonnées, lien SharePoint, informations techniques, etc. selon votre liste).

## Ce que voit l'utilisateur

- Un onglet Ticket (ex: `Infos Clients`) sur les tickets autorisés.
- Un affichage de données récupérées depuis SharePoint.
- Un bouton de redirection vers l'élément SharePoint (si le lien est configuré).

## Fonctionnement (parcours type)

1. Un administrateur configure la connexion Microsoft Graph + le site/liste SharePoint.
2. L'administrateur vérifie la connexion via le test intégré (modal de statut).
3. Un technicien ouvre un ticket.
4. Le plugin utilise l'entité GLPI du ticket pour rechercher la ligne correspondante dans la liste SharePoint.
5. Les informations trouvées sont affichées dans l'onglet Ticket.
6. Si nécessaire, l'utilisateur ouvre la source SharePoint via le bouton dédié.

## Configuration plugin (ce que chaque zone active)

### Connexion Microsoft Graph / SharePoint

- `TenantID`: identifie votre tenant Microsoft Entra (Azure AD). Sans cette valeur, aucun jeton Graph ne peut être obtenu.
- `ClientID`: identifiant de l'application enregistrée dans Entra.
- `ClientSecret`: secret de l'application. Le plugin s'en sert pour récupérer un token Graph et appeler SharePoint.
- `Hostname`: nom d'hôte SharePoint (ex: `contoso.sharepoint.com`).
- `SitePath`: chemin du site (ex: `/sites/Support`).
- `ListDisplayName`: nom de la liste à interroger.
- `Link`: URL/lien utilisé pour le bouton d'ouverture SharePoint.

### Test de connexion (modal)

Le bouton de test permet de valider rapidement:
- l'obtention du token Graph
- l'accès au site SharePoint
- l'existence de la liste configurée
- les droits du compte applicatif sur la liste

## Prérequis

- GLPI 11.x
- PHP compatible GLPI
- Une application Microsoft Entra (Azure AD) avec accès Graph/SharePoint
- Permissions SharePoint adaptées (site + liste)
- Extension `sodium` pour le chiffrement des secrets en base (recommandée/attendue)

## Droits / profils

- La configuration du plugin est réservée aux profils d'administration autorisés.
- L'onglet Ticket est visible selon le droit plugin `sharepointinfos` (lecture) et les règles GLPI.

## Architecture (résumé court)

- Une configuration centrale stocke les paramètres Graph/SharePoint.
- Les secrets sensibles (ex: `ClientSecret`) sont chiffrés en base.
- Une couche d'accès Graph interroge la liste SharePoint.
- Un onglet Ticket affiche les données trouvées avec échappement des sorties HTML/URL.

## Vérifications rapides après mise à jour

- Ouvrir la configuration plugin et enregistrer les paramètres.
- Lancer le test de connexion et vérifier un statut OK.
- Ouvrir un ticket dans une entité connue et vérifier l'onglet `Infos Clients`.
- Vérifier le bouton de redirection SharePoint.
