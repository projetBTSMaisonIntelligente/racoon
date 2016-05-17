<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
if(!class_exists("ParticleAPI"))
{
    require_once dirname(__FILE__) . '/../../3rdparty/phpParticle/phpSpark.class.php';
}

/**
     * Class racoon : eqLogic Racoon
     *
     * @author Simon Desnoë <sdesnoe@gmail.com>
     *
     * @see https://www.jeedom.com/doc/documentation/code/classes/eqLogic.html Documentation de la Classe eqLogic
     *
     * @version 1.1
     */
class racoon extends eqLogic {
/*     * *************************Attributs****************************** */
    const CONFORT = 'C';
    const ECONOMIQUE = 'E';
    const HORSGEL = 'H';
    const ARRET = 'A';
    const DELESTAGE = 'D';
    const NOMBRE_MINI_FILPILOTE = 1;
    const NOMBRE_MAX_FILPILOTE = 7;
    const CHEMIN_FICHIERJSON = '/usr/share/nginx/www/jeedom/plugins/racoon/core/config/';


    /*     * ***********************Methode static*************************** */

  /**
    *
    * Méthode appelée par le système toutes les minutes
    *
    */
public static function cron() {
  $racoonSparkCore = self::byLogicalId('sparkCore','racoon');
  if(is_object($racoonSparkCore)) {
    $resultatRequete = self::requeteInformationSparkCore();
    if(isset($resultatRequete)) {
      self::traitementInformationSparkCore($resultatRequete);
      $cmdSparkCore = $racoonSparkCore->searchCmdByConfiguration('aRecuperer');
      if(isset($cmdSparkCore)) {
        foreach ($cmdSparkCore as $cmdVariable) {
          if($cmdVariable->getConfiguration('aRecuperer') == true) {
            $resultatRequete = self::requeteVariableSparkCore($cmdVariable->getName());
            if(isset($resultatRequete)) {
              $cmdVariable->setConfiguration('value',$resultatRequete);
              $cmdVariable->save();
              $cmdVariable->event($resultatRequete);
              switch ($cmdVariable->getConfiguration('utiliserPar')) {
                case 'filPilote':
                  self::traitementFilPilote($resultatRequete);
                  break;
              
                case 'teleinfo':
                  self::traitementTeleinfo($resultatRequete);
                  break;

                case 'temperature':
                  self::traitementTemperature($resultatRequete);
                  break;
              }
            }
          }
        }
      }
    }
  }
}

 ////////////////////////////////////////////////////////////////////////
//  Méthodes des créations des eqLogics et commandes
/////////////////////////////////////////////////////////////////////////
  /**
     * Ajout de tous les objets du plugin appelé après la sauvegarde de la configuration
     *
     * @param string $typeEquipement Correspond au type de d'équipement à créer.
     *
     * @param int $nombreEquipement correspond au nombre d'équipement d'un type à créer
     *
     * @return boolean $resultat retourne TRUE si ça marche / FALSE si ça ne marche pas + messages log. 
     *
     */
public static function creationEquipement($typeEquipement,$nombreEquipement) {
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      log::add('racoon','DEBUG','[Appel] creationEquipement avec les paramètres, typeEquipement :' . $typeEquipement . ' & nombreEquipement à créer' . $nombreEquipement);
      if($equipement = self::getConfigJSON('equipement')) {
        for($iNbEqui = 1 ; $iNbEqui <= $nombreEquipement ; $iNbEqui ++) {
          $keyEquipement = $equipement[$typeEquipement];
          $configuration = $keyEquipement['configuration'];
          if($nombreEquipement == 1 && $keyEquipement['isUnique'] == 1)
            $racoon = self::byLogicalId($keyEquipement['logicalId'], 'racoon');
          else
            $racoon = self::byLogicalId($keyEquipement['logicalId'] . $iNbEqui,'racoon');
          if(!is_object($racoon)) {
            $racoon = new racoon();
            $racoon->setEqType_name($keyEquipement['eqType_name']);
            if($nombreEquipement == 1 && $keyEquipement['isUnique'] == 1) {
            $racoon->setName($keyEquipement['name']);
            $racoon->setLogicalId($keyEquipement['logicalId']);
            } else {
            $racoon->setName($keyEquipement['name'] . ' ' . $iNbEqui);
            $racoon->setLogicalId($keyEquipement['logicalId'] . $iNbEqui);
            }
            $racoon->setIsEnable($keyEquipement['isEnable']);
            foreach ($configuration as $keyConfiguration) {
              if($keyConfiguration['value'] == "variableINbEqui")
              $racoon->setConfiguration($keyConfiguration['name'],$iNbEqui);
              else
              $racoon->setConfiguration($keyConfiguration['name'],$keyConfiguration['value']);
            }
            $racoon->save();
              log::add('racoon','DEBUG','[Data] Equipement : ' . print_r($racoon,true));
            } else {
              log::add('racoon','DEBUG','[Equipement] ' . $racoon->getName() .' déjà crée');
            }
            if(count($keyEquipement['commande']) != 0)
              self::creationCommande($racoon,$typeEquipement);
        }
        log::add('racoon','INFO','[Succès] Création de l\'équipement ' . $racoon->getName() . ' réussie');
        $resultat = true;
      } else {
        log::add('racoon','ERROR','[Config] Fichier JSON');
        $resultat = false;
      }
      if(empty($resultat))
        $resultatLog = "ERROR";
      else 
        $resultatLog = print_r($resultat,true);
      log::add('racoon','DEBUG','Valeur de retour de la méthode creationEquipement : ' . $resultatLog);
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      return $resultat;
} 

  /**
     * Création des commandes par rapport aux objets
     *
     * @param racoon $equipRacoon correspond au racoon sur lequel les commandes vont être créer.
     *
     * @param string $typeEquipement correspond au type de l'équipement pour récupérer les commandes par rapport à cet type.
     *
     * @param string $nomCommande correspond au nom de la commande lorsqu'elle est appelé après un traitement d'une requête
     *
     * @return boolean $resultat retourne TRUE si ça marche / FALSE si ça ne marche pas + messages log. 
     *
     */
    public static function creationCommande($equipRacoon,$typeEquipement,$nomCommande = null) {
        log::add('racoon','DEBUG','-----------------------------------------------------------------');
        log::add('racoon','DEBUG','[Appel] creationCommande avec les paramètres, Object Racoon : ' . print_r($equipRacoon,true) . ' &  type d\'équipement : ' . $typeEquipement . ' & nomCommande : ' . $nomCommande);
        if ($commande = self::getConfigJSON('commande')) {
          //$typeCommande = $commande[$typeEquipement];
        foreach ($commande[$typeEquipement] as $typeCommande) {
          $racoonCmd = racoonCmd::byEqLogicIdCmdName($equipRacoon->getId(),$typeCommande['name']);
           if(!is_object($racoonCmd)) {
                $racoonCmd = new racoonCmd();
                if(isset($nomCommande)) {
                  $racoonCmd->setName($nomCommande);
                  $racoonCmd->setLogicalId($nomCommande);
                } else {
                  $racoonCmd->setName($typeCommande['name']);
                  $racoonCmd->setLogicalId($typeCommande['logicalId']);
                }
                $racoonCmd->setEqLogic_id($equipRacoon->getId());
                $racoonCmd->setEqType($typeCommande['eqType']);
                $racoonCmd->setType($typeCommande['type']);
                foreach ($typeCommande['configuration'] as $keyConfiguration) {
                   $racoonCmd->setConfiguration($keyConfiguration['name'],$keyConfiguration['value']);    
                }
                foreach ($typeCommande['template'] as $keyTemplate) {
                  if(isset($keyTemplate))
                    $racoonCmd->setTemplate($keyTemplate['key'],$keyTemplate['value']);
                }
                $racoonCmd->setSubType($typeCommande['subType']);
                $racoonCmd->setDisplay('generic_type',$typeCommande['display']);
                $racoonCmd->setUnite($typeCommande['unite']);
                $racoonCmd->setOrder($typeCommande['order']);
                $racoonCmd->save();
                log::add('racoon','DEBUG','[Data] Commande : '  . print_r($racoonCmd,true));
                log::add('racoon','INFO','[Succès] Création de la commande ' . $racoonCmd->getName() . ' du racoon ' . $equipRacoon->getName());
            } else {
              log::add('racoon','DEBUG','[Commande] ' . $racoonCmd->getName() . ' déjà crée');
            }
        }
        log::add('racoon','INFO','[Succès] Création des commandes pour l\'équipement Racoon ' . $equipRacoon->getName() . ' réussie');
        $resultat = true;
      } else {
        log::add('racoon','ERROR','[Config] Fichier JSON');
        $resultat = false;
       }
        if(empty($resultat))
        $resultatLog = "ERROR";
      else 
        $resultatLog = print_r($resultat,true);
       log::add('racoon','DEBUG','Valeur de retour de la méthode  : ' . $resultatLog);
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
       return $resultat;
    }

 ////////////////////////////////////////////////////////////////////////
//  Méthodes des get de config (JSON dans le dossier config et les données sur la page de config)
/////////////////////////////////////////////////////////////////////////

  /**
     * Récupération depuis les fichiers JSON, des informations correspondant soit aux équipements, aux commandes et aux nombre de fil pilotes.
     *
     * @see https://secure.php.net/manual/fr/function.json-last-ERROR.php Pour les codes d'erreur JSON
     *
     * @param string $element le nom du fichier où il faut récupérer les informations.
     *
     * @return array $resultat résultat de la récupération des données de la config JSON selon l'élèment
     *
     */
  public static function getConfigJSON($element) {
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      log::add('racoon','DEBUG','[Appel] getConfigJSON avec le paramètre, fichier : ' . $element);
        if(file_get_contents(self::CHEMIN_FICHIERJSON . $element . '.json') == FALSE) {
            log::add('racoon','ERROR','[Fichier] ' . $element . '.json introuvable');
            $resultat = false;
        } else {
            $json_source = file_get_contents(self::CHEMIN_FICHIERJSON . $element . '.json');
            if(json_decode($json_source,TRUE) == FALSE) {
              log::add('racoon','ERROR','[JSON] [CODE] ' . json_last_error()); 
              $resultat = false;        
            } else {
              $data = json_decode($json_source,TRUE);
              switch ($element) {
                case 'equipement':
                    $equipement = $data['equipement'];
                    log::add('racoon','DEBUG','[Data] Array équipement ' . print_r($equipement,true));
                    log::add('racoon','INFO','[Succès] Récupération des données du fichierJSON ' . $element . ' réussie');
                    $resultat = $equipement;
                    break;
                case 'commande':
                    $commande = $data['commande'];
                    log::add('racoon','DEBUG','[Data] Array commande ' . print_r($commande,true));
                    log::add('racoon','INFO','[Succès] Récupération des données du fichierJSON ' . $element . ' réussie');
                    $resultat = $commande;
                    break;
              }
            }
        }
        if(empty($resultat))
          $resultatLog = "ERROR";
        else 
          $resultatLog = print_r($resultat,true);
        log::add('racoon','DEBUG','Valeur de retour de la méthode getConfigJSON : ' . $resultatLog);
        log::add('racoon','DEBUG','-----------------------------------------------------------------');
        return $resultat;
  }
 /**
     * Récupération à partir de la page de configuration, des informations sur le Spark Core.
     *
     * @return boolean $resultat retourne TRUE si ça marche / FALSE si ça ne marche pas + messages log. 
     *
     */
public static function getConfigSparkCore() {
    log::add('racoon','DEBUG','-----------------------------------------------------------------');
    log::add('racoon','DEBUG','[Appel] getConfigSparkCore sans paramètre');
    $deviceId = config::byKey('deviceid','racoon');
    $accessToken = config::byKey('accessToken','racoon');
    log::add('racoon','DEBUG','[Data] DeviceID du spark core : ' . $deviceId . ', accessToken : ' . $accessToken);
    if(isset($deviceId) && isset($accessToken)) {
      $racoonSparkCore = self::byLogicalId('sparkCore','racoon');
      if(is_object($racoonSparkCore)) {
        $racoonSparkCore->setConfiguration('deviceId',$deviceId);
        $racoonSparkCore->setConfiguration('accessToken',$accessToken);
        $racoonSparkCore->save();
        log::add('racoon','INFO','[Succès] Récupération des données de configuration du Spark Core réussie');
        //message::add('');
        $resultat = true;
      } else {
        log::add('racoon','ERROR','[Equipement] Racoon SparkCore n\'existe pas');
        $resultat = false;
      }
    } else {
      log::add('racoon','ERROR','[Config] Données page de configuration non-conformes');
      $resultat = false;
    }
       if(empty($resultat))
        $resultatLog = "ERROR";
      else 
        $resultatLog = print_r($resultat,true);
      log::add('racoon','DEBUG','Valeur de retour de la méthode getConfigSparkCore : ' . $resultatLog);
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      return $resultat;
    }

////////////////////////////////////////////////////////////////////////
//  Méthode de la configuration d'un objet phpSpark pour la communication avec le Spark Core
/////////////////////////////////////////////////////////////////////////

  /**
     * Configuration de l'objet Spark Core (utilisé dans les méthodes call et getStatut)
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @param string $accessToken Variable correspondant à la clé d'accès du Spark Core reçu sur la page de configuration
     *
     * @return phpSpark $spark Objet de la librairie phpParticle
     *
     */
    public static function configurationPhpParticle($accessToken) {
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      log::add('racoon','DEBUG','[Appel] ConfigurationSparkCore avec le paramètre, accessToken : ' .$accessToken);
      $spark = new phpSpark();
      $spark->setDebug(false);
      $spark->setDebugType("TXT");
      $spark->setAccessToken($accessToken);
      $spark->setTimeout("5");
      return $spark;
      log::add('racoon','DEBUG','Valeur de retour de la méthode configurationPhpParticle : ' . $spark);
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
    }

 ////////////////////////////////////////////////////////////////////////
//  Méthodes des requêtes vers le Spark Core
/////////////////////////////////////////////////////////////////////////
    /**
    *
    * Requête effectué sur le Spark Core pour récupérer ses informations getDeviceInfo()
    *
    * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
    *
    * @return array $data Renvoit les informations récupérées de la requête SINON false si il y a une erreur + messages LOG
    *
    */
  public static function requeteInformationSparkCore() {
    log::add('racoon','DEBUG','-----------------------------------------------------------------');
      log::add('racoon','DEBUG','[Appel] requeteInformationSparkCore() sans paramètre');
      $racoonSparkCore = self::byLogicalId('sparkCore','racoon');
      $spark = self::configurationPhpParticle($racoonSparkCore->getConfiguration('accessToken'));
      if($spark->getAttributes($racoonSparkCore->getConfiguration('deviceId')) == true) {
        $data = $spark->getResult();
        log::add('racoon','INFO','[Succès] Requête HTTP sur le Spark Core pour récupérer ses informations réussie');
        log::add('racoon','DEBUG','[Data] Données reçues du Spark Core : ' . print_r($data,true));
        $resultat = $data;
        } else {
          log::add('racoon','ERROR','[Requête] Erreur d\'appel des informations du Spark Core [Code] : ' . $spark->getError() . ' [Source] : ' . $spark->getErrorSource());
          $resultat = false;
        }
       if(empty($resultat))
        $resultatLog = "ERROR";
      else 
        $resultatLog = print_r($resultat,true);
      log::add('racoon','DEBUG','Valeur de retour de la méthode requeteInformationSparkCore : ' . $resultatLog);
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      return $resultat;
  }
  /**
     * Requête envoyé au Spark Core pour une variable selon un paramètre, le nom de la variable.
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @param string $variableSparkCore correspond au nom de la fonction appelé sur le Spark Core
     *
     * @return array $resultat récupère les résultats de la requête de la variable SINON false si il y a erreur + messages LOG
     *
     */
    public static function requeteVariableSparkCore($variableSparkCore) {
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      log::add('racoon','DEBUG','[Appel] requeteVariableSparkCore avec le paramètre, variableSparkCore : ' . $variableSparkCore);
      if(is_string($variableSparkCore)){
        $racoonSparkCore = self::byLogicalId('sparkCore','racoon');
        $spark = self::configurationPhpParticle($racoonSparkCore->getConfiguration('accessToken'));
        if($spark->getVariable($racoonSparkCore->getConfiguration('deviceId'),$variableSparkCore) == true) {
          log::add('racoon','INFO','[Succès] Requête HTTP sur le Spark Core pour récupérer la valeur de la variable ' . $variableSparkCore . ' sur le Spark Core réussie');
          $data = $spark->getResult();
          $resultat = $data['result'];
          log::add('racoon','DEBUG','[Data] Valeur de la variable ' . $variableSparkCore . ' : ' . $resultat);
        } else {
            log::add('racoon','ERROR','[Requête] Erreur d\'appel de la variable ' . $variableSparkCore. ' [Code] : ' . $spark->getError() . ' [Source] : ' . $spark->getErrorSource());
            $resultat = false;
        }
      } else {
        log::add('racoon','ERROR','[Paramètres] Non-conformes');
        $resultat = false;
      }
      if(empty($resultat))
        $resultatLog = "ERROR";
      else 
        $resultatLog = print_r($resultat,true);
      log::add('racoon','DEBUG','Valeur de retour de la méthode requeteVariableSparkCore : ' . $resultatLog);
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      return $resultat;
    }
 /**
     * Requête envoyé au Spark Core pour une fonction selon deux paramètres, le nom et les paramètres de la fonction.
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @param string $fonctionSparkCore correspond au nom de la fonction appelé sur le Spark Core
     *
     * @param string $parametre correspond aux paramètres à envoyer à la fonction sur le Spark Core
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log.
     *
     */
    public static function requeteFonctionSparkCore($fonctionSparkCore,$parametre) {
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      log::add('racoon','DEBUG','[Appel] requeteFonctionSparkCore avec les paramètres, fonctionSparkCore ' . $fonctionSparkCore . ' & les paramètres ' . $parametre);
      if(is_string($fonctionSparkCore) && is_string($parametre)) {
        $racoonSparkCore = self::byLogicalId('sparkCore','racoon');
        $spark = self::configurationPhpParticle($racoonSparkCore->getConfiguration('accessToken'));
        if($spark->callFunction($racoonSparkCore->getConfiguration('deviceId'),$fonctionSparkCore,$parametre) == true) {
          log::add('racoon','INFO','[Succès] Requête HTTP sur le Spark Core pour faire appel à la fonction ' . $fonctionSparkCore . ' avec les paramètres : ' . $parametre . ' réussie');
          $resultat = true;
        } else {
          log::add('racoon','ERROR','[Requête] Erreur d\'appel de la fonction '. $fonctionSparkCore . ' [Code] :' . $spark->getError() . ' [Source] : ' . $spark->getErrorSource());
          $resultat = false;
        }
      } else {
        log::add('racoon','ERROR','[Paramètres] Non-conformes');
        $resultat = false;
      }
      if(empty($resultat))
        $resultatLog = "ERROR";
      else 
        $resultatLog = print_r($resultat,true);
      log::add('racoon','DEBUG','Valeur de retour de la méthode requeteFonctionSparkCore : ' . $resultatLog);
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      return $resultat;
    }
/**
public static function supprimerCommandeSparkCore($variables,$fonctions) {
  log::add('racoon','DEBUG','Appel de supprimerCommandeSparkCore avec les paramètres : ' . print_r($variables) . ' & ' . print_r($fonctions));
  $racoonSparkCore = self::getRacoonSparkCore();
  $racoonCmdSparkCore = self::getCmd();
  //array_intersect(array1, array2) A REGARDER
  foreach ($racoonCmdSparkCore as $keyRacoonCmdSparkCore) {
    foreach ($variables as $keyVariable) {
      if($keyRacoonCmdSparkCore)
    }
  }

}
**/
////////////////////////////////////////////////////////////////////////
//  Méthodes des traitements des résultats des requêtes effectuées au Spark Core
/////////////////////////////////////////////////////////////////////////
/**
*
* Traitement de la requête pour récupérer les informations du Spark Core, les fonctions et variables sont enregistrées en tant que commande de l'eqLogic sparkCore
*
* @param array $resultatRequete correspond au résultat de la requête pour récupérer les informations du SPark Core
*
* @param string $typeEquipement correspond au type de racoon qui demande les informations du Spark Core
*
* @param string $variable correspond à la variable qu'un type de racoon demande à récupérer toutes les minutes
*
* @param string $fonction correspond à la fonction qu'un type de racoon demande à utiliser pour la fonctionnalité correspondant au type d'équipement
*
* @return boolean $resultat retourne TRUE si ça marche / FALSE si ça ne marche pas + messages log. 
*
*/
    public static function traitementInformationSparkCore($resultatRequete) {
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      log::add('racoon','DEBUG','[Appel] traitementInformationSparkCore() avec es paramètre, résultat de la requête : ' . print_r($resultatRequete,true));
      $typeEquipement = 'sparkCore';
      $racoonSparkCore = self::byLogicalId('sparkCore','racoon');
      if($resultatRequete != null && is_array($resultatRequete) && is_array($resultatRequete['variables']) && is_array($resultatRequete['variables'])) {
        if(is_object($racoonSparkCore)) {
        $variables = $resultatRequete['variables'];
        if (count($variables) != 0) {
          foreach ($variables as $keyVariable => $type) {
            log::add('racoon','DEBUG','Retour informations variables ' . $keyVariable);
            $commandeVariable = racoonCmd::byEqLogicIdCmdName($racoonSparkCore->getId(),$keyVariable);
            if(!is_object($commandeVariable))
              self::creationCommande($racoonSparkCore,$typeEquipement . 'Variable',$keyVariable);
              message::add('racoon','Ajout d\'une variable Particle ' . $keyVariable);
          }
        }
        $fonctions = $resultatRequete['functions'];
        if(count($fonctions) != 0) {
            foreach ($fonctions as $keyFonction){
            log::add('racoon','DEBUG','Retour informations fonctions ' . $keyFonction);
            $commandeFonction = racoonCmd::byEqLogicIdCmdName($racoonSparkCore->getId(),$keyFonction);
            if(!is_object($commandeFonction)){
              self::creationCommande($racoonSparkCore,$typeEquipement . 'Fonction',$keyFonction);
              message::add('racoon','Ajout d\'une fonction Particle ' . $keyFonction);
            }  
          }
        }  
        log::add('racoon','INFO','[Succès] Traitement des informations du Spark Core réussi');
        //self::supprimerCommandeSparkCore($variables,$fonctions);
        $resultat = true;
      } else {
        log::add('racoon','ERROR','[Equipement] Racoon SparkCore n\'existe pas');
        $resultat = false;
      }
    } else {
      log::add('racoon','ERROR','[Traitement] Informations du Spark Core non-conforme');
      $resultat = false;
    }
    if(empty($resultat))
      $resultatLog = "ERROR";
    else 
      $resultatLog = print_r($resultat,true);
    log::add('racoon','DEBUG','Valeur de retour de la méthode traitementInformationSparkCore : ' . $resultatLog);
    log::add('racoon','DEBUG','-----------------------------------------------------------------');
    return $resultat;
  }
   /**
     * Traitement de la requête sur la variable correspondant aux statuts des fil pilotes
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @param string $resultatRequete correspond au traitement de la requête pour récupérer la variable correspondant à la fonctionnalité FilPilote c'est à dire les status des fil pilotes
     *
     * @return boolean $resultat retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   public static function traitementFilPilote($resultatRequete) {
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      log::add('racoon','DEBUG','[Appel] traitementStatut() avec les paramètres, résultat de la requête :' . print_r($resultatRequete,true));
      if(is_string($resultatRequete)) {
        for($izone= self::NOMBRE_MINI_FILPILOTE; $izone <= self::NOMBRE_MAX_FILPILOTE ; $izone++) { 
          $sparkZone = $izone-1;
          $valeur = $resultatRequete[$sparkZone];
          $logical = 'zone' . $izone;
          $racoon = self::byLogicalId($logical,'racoon');
          log::add('racoon','DEBUG','[Data] Retour statut zone ' . $izone . ' valeur ' . $valeur);
            if(is_object($racoon)) {
              $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),'statut');
              //$racoonCmd->setValue($valeur);
              $ordre = self::nomStatutFilPilote($valeur);
              $racoonCmd->setConfiguration('value',$ordre);
              $racoonCmd->save();
              $racoonCmd->event($ordre);
              log::add('racoon','INFO','[Succès] Traitement Fil Pilote réussi');
              $resultat = true;
            } else {
              log::add('racoon','ERROR','[Equipement] Zone '. $izone . ' n\'existe pas');
              $resultat = false;
              break;
            }
        }
      } else {
        log::add('racoon','ERROR','[Traitement] Informations \'Fil Pilote\' du Spark Core récupérés non-conformes');
        $resultat = false;
      }
      if(empty($resultat))
        $resultatLog = "ERROR";
      else 
        $resultatLog = print_r($resultat,true);
      log::add('racoon','DEBUG','Valeur de retour de la méthode traitementFilPilote : ' . $resultatLog);
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      return $resultat;
   }
   /**
     * Récupération de la température enregistrée sur le Spark Core grâve au module 433MHz
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @param int $resultatRequete correspond au traitement de la requête pour récupérer la variable correspondant à la fonctionnalité Temperature c'est à dire les status des fil pilotes
     *
     * @return boolean $resultat retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   public static function traitementTemperature($resultatRequete) {
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      log::add('racoon','DEBUG','[Appel] traitementTemperature() avec le paramètre, résultat de la requête :' . print_r($resultatRequete,true));
      if(is_int($resultatRequete)) {
        $racoon = self::byLogicalId('temperature','racoon');
        if(is_object($racoon)) {
          $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),'temperature');
          //$racoonCmd->setValue($resultatRequete);
          $racoonCmd->setConfiguration('value',$resultatRequete);
          $racoonCmd->save();
          $racoonCmd->event($resultatRequete);
          log::add('racoon','INFO','[Succès] Traitement température réussi');
          $resultat = true;
        } else {
            log::add('racoon','ERROR','[Equipement] Temperature n\'existe pas');
            $resultat = false;
        }
      } else {
          log::add('racoon','ERROR','[Traitement] Informations \'Température\' du Spark Core récupérés non-conformes');
          $resultat = false;
      }
      if(empty($resultat))
        $resultatLog = "ERROR";
      else 
        $resultatLog = print_r($resultat,true);
      log::add('racoon','DEBUG','Valeur de retour de la méthode traitementTemperature : ' . $resultatLog);
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      return $resultat;
    }
     /**
     * Récupération des téléinfos enregistrées sur le Spark Core
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @param json $resultatRequete correspond au traitement de la requête pour récupérer la variable correspondant à la fonctionnalité Teleinfo c'est à dire les status des fil pilotes
     *
     * @return boolean $resultat retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   public static function traitementTeleinfo($resultatRequete) {
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      log::add('racoon','DEBUG','[Appel] traitementTeleinfo() avec le paramètre, résultat de la requête :' . print_r($resultatRequete,true));
      if(json_decode($resultatRequete,true) == TRUE) {
        $teleinfo = json_decode($resultatRequete,true); 
        $typeEquipement = 'teleinfo';
        $racoon = self::byLogicalId('teleinfo','racoon');
        if(is_object($racoon)) {
          foreach($teleinfo as $keyEtiquette => $value) {
            log::add('racoon','DEBUG','[Data] Retour teleinfo ' . $keyEtiquette . ' valeur ' . $value);
            $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),$keyEtiquette);
            if(!is_object($racoonCmd)) {
              self::creationCommande($racoon,$typeEquipement,$keyEtiquette);
            }
            $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),$keyEtiquette);
            //$racoonCmd->setValue($value);
            $racoonCmd->setConfiguration('value',$value);
            $racoonCmd->save();
            $racoonCmd->event($value);
          }
          log::add('racoon','INFO','[Succès] Traitement téléinfo réussi');
          $resultat = true;
        } else {
          log::add('racoon','ERROR','[Equipement] Teleinfo n\'existe pas');
          $resultat = false;
        }
      } else {
        log::add('racoon','ERROR','[Traitement] Informations \'Téléinfo\' du Spark Core récupérés non-conformes');
        $resultat = false;
      }
      if(empty($resultat))
        $resultatLog = "ERROR";
      else 
        $resultatLog = print_r($resultat,true);
      log::add('racoon','DEBUG','Valeur de retour de la méthode traitementTeleinfo : ' . $resultatLog);
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      return $resultat;
    }

////////////////////////////////////////////////////////////////////////
//  Méthodes des récupération des paramètres pour envoyer au SparkCore
/////////////////////////////////////////////////////////////////////////

   /**
     * Envoi d'ordre $request à la zone $zone via requête HTTP par méthode POST pour les radiateurs.  
     *
     * @param int $zone Variable correspondant au numéro de la zone sélectionnée.
     *
     * @param string $request Variable correspondant de la demande d'ordre.
     *
     * @return boolean $resultat retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
  public static function setFilPilote($zone,$request) {
        log::add('racoon','DEBUG','-----------------------------------------------------------------');
        log::add('racoon','DEBUG','[Appel] setFilPilote() avec les paramètres, ordre : ' . $request . ', zone : ' . $zone);
        if(is_int($zone) && is_string($request)) {
          $parametre = $zone . $request;
         //$racoonSparkCore = self::byLogicalId('sparkCore','racoon');
          //log::add('racoon','DEBUG','[Data] ' . print_r(cmd::searchConfigurationEqLogic($racoonSparkCore->getId(),'utiliserPar','action'),true));
          $fonctionSparkCore = self::selectionFonctionSparkCore('filPilote');
          if(isset($fonctionSparkCore)){
            if(self::requeteFonctionSparkCore($fonctionSparkCore,$parametre)) {
              $logical = 'zone' . $zone;
              $racoon = self::byLogicalId($logical, 'racoon');
              $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),'statut');
              $ordre = self::nomStatutFilPilote($request);
              $racoonCmd->setValue($ordre);
              $racoonCmd->setConfiguration('value', $ordre);
              $racoonCmd->save();
              $racoonCmd->event($ordre);
              log::add('racoon','INFO','[Succès] Ordre ' . $ordre . ' envoyé dans la zone ' . $zone);
              $resultat = true;
            } else {
              log::add('racoon','ERROR','[Requête] Impossible de traiter la requête');
              $resultat = false;
            }
          } else {
            log::add('racoon','ERROR','[Equipement] Spark Core n\'est pas configuré correctement (Option utiliser par sur la page de gestion du Spark Core');
            $resultat = false;
          }
        } else {
          log::add('racoon','ERROR','[Paramètres] Non-conformes');
          $resultat = false;
      }
      if(empty($resultat))
        $resultatLog = "ERROR";
      else 
        $resultatLog = print_r($resultat,true);
      log::add('racoon','DEBUG','Valeur de retour de la méthode setFilPilote : ' . $resultatLog);
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      return $resultat;
    }
     /**
     * Envoi de la consigne $request à la zone $zone via requête HTTP par méthode POST pour les radiateurs avec une config définie par l'utilisateur ou par défaut.  
     *
     * @param int $zone Variable correspondant au numéro de la zone sélectionnée.
     *
     * @param string $request Variable correspondant de la demande de consigne.
     *
     * @param double $temperature Variable correspond à la valeur d'un capteur de température
     *
     * @param double $kp Coefficient pour la régulation PID
     *
     * @param double $ki Coefficient pour la régulation PID
     *
     * @param double $kd Coefficient pour la régulation PID
     *
     * @return boolean $resultat retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
    public static function setRegulation($zone,$request,$temperature/**,$kp,$ki,$kd,$tempMin,$tempMax**/) {
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      log::add('racoon','DEBUG','[Appel] de setRegulation() avec les paramètres, zone ' . $zone . ' ,consigne ' . $request . ' ,temperature ' . $temperature);
      if(isset($zone) && isset($request) && isset($temperature)/**&& is_double($kp) && is_double($ki) && is_double($kd)**/) {
        //  $parametre = '(' . $zone . '/' . $request . '/' . $temperature . '/' . $kp . '/' . $ki . '/' . $kd .'/'. $tempMin .'/'. $temMax . ')';
        $ecart = 0.5;
        $parametre = '(' . $zone . '/' . $request . '/' . $temperature . '/' . $ecart . '/)';
        $fonctionSparkCore = self::selectionFonctionSparkCore('regulation');
        if(isset($fonctionSparkCore)){
          if(self::requeteFonctionSparkCore($fonctionSparkCore,$parametre))
            log::add('racoon','INFO','[Succès] Régulation demandé à la température ' . $request . ' dans la zone ' . $zone);
            $resultat = true;
         } else {
            log::add('racoon','ERROR','[Paramètres] Non-conformes');
            $resultat = false;
         }
        }
      if(empty($resultat))
        $resultatLog = "ERROR";
      else 
        $resultatLog = print_r($resultat,true);
      log::add('racoon','DEBUG','Valeur de retour de la méthode setRegulation : ' . $resultatLog);
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      return $resultat;
    }
  
   public static function selectionFonctionSparkCore($fonctionnalite) {
    log::add('racoon','DEBUG','-----------------------------------------------------------------');
    log::add('racoon','DEBUG','[Appel] de selectionFonctionSparkCore() avec le paramètre, fonctionnalité : ' . $fonctionnalite);
    $racoonSparkCore = self::byLogicalId('sparkCore','racoon');
    $cmdSparkCore = cmd::searchConfigurationEqLogic($racoonSparkCore->getId(),'utiliserPar','action');
    if(isset($racoonSparkCore) && isset($cmdSparkCore)) {
      foreach ($cmdSparkCore as $cmdFonction) {
        log::add('racoon','DEBUG','[Data] Fonction Spark Core ' . $cmdFonction->getName());
        if($cmdFonction->getConfiguration('utiliserPar') == $fonctionnalite) {
          if(!isset($fonctionSparkCore)) {
            $fonctionSparkCore = $cmdFonction->getName();
            $resultat = $fonctionSparkCore;
          } else {
            log::add('racoon','ERROR','');
            $resultat = false;
          }
        }
      }
    } else {
      log::add('racoon','ERROR','');
      $resultat = false;
    }
    if(empty($resultat))
      $resultatLog = "ERROR";
    else 
      $resultatLog = print_r($resultat,true);
    log::add('racoon','DEBUG','Valeur de retour de la méthode setRegulation : ' . $resultatLog);
    log::add('racoon','DEBUG','-----------------------------------------------------------------');
    return $resultat;
   }

   public static function nomStatutFilPilote($lettreStatut) {
    log::add('racoon','DEBUG','-----------------------------------------------------------------');
    log::add('racoon','DEBUG','[Appel] de nomStatutFilPilote() avec le paramètre, lettreStatut : ' . $lettreStatut);
     switch ($lettreStatut) {
                case 'A':
                  $resultat = 'Arrêt';
                  break;
                case 'C':
                  $resultat = 'Confort';
                  break;
                case 'E':
                  $resultat = 'Eco';
                  break;
                case 'H':
                  $resultat = 'Hors-Gel';
                  break;
                case 'D':
                  $resultat = 'Délestage';
                  break;
                default:
                  $resultat = null;
                  break;
      }
      if(!isset($lettreStatut))
        $resultatLog = "ERROR";
      else
        $resultatLog = print_r($resultat,true);
      log::add('racoon','DEBUG','Valeur de retour de la méthode nomStatutFilPilote : ' . $resultatLog);
      log::add('racoon','DEBUG','-----------------------------------------------------------------');
      return $resultat;
   }
    /*     * *********************Méthodes d'instance************************* */
    /**

    public function preInsert() {
        
    }

    public function postInsert() {
        
    }

    public function preSave() {
        
    }

    public function postSave() {
        
    }

    public function preUpdate() {
        
    }

    public function postUpdate() {
        
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }
    **/

    /*     * **********************Getteur Setteur*************************** */
}

/**
     * Class racoonCmd : Commande des eqLogics Racoon 
     *
     * @author Simon Desnoë <sdesnoe@gmail.com>
     *
     * @see https://www.jeedom.com/doc/documentation/code/classes/cmd.html Documentation de la Classe cmd
     *
     * @version 1.1
     */
class racoonCmd extends cmd {
    /*     * *************************Attributs****************************** */

    const NOMBRE_MINI_FILPILOTE = 1;

    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */
   /**
     * Permet l'execution des commandes associées aux objets selon les types de commandes.
     *
     * @param  $_options 
     *
     * @return boolean $resultat renvoit les résultats de la commande si c'est 0 erreur
     *
     */
    public function execute($_options = array()) {
      $racoon = $this->getEqLogic();
      $resultat = false;
      log::add('racoon','DEBUG','[Appel] CMD ' . $this->name  . ' de l\' eqLogic ' . $racoon->getName());
      switch($this->getConfiguration('mode')) {
       case 'sparkCore' :
         switch ($this->getType()) {
            case 'action' :
              $resultat = self::requeteFonctionSparkCore($this->getName(),$this->getConfiguration('parametre'));
              break;
            case 'info' :
              if($resultatRequete = self::requeteVariableSparkCore($this->getName())) {
                $this->setConfiguration('value',$resultatRequete);
                $resultat = $this->getConfiguration('value');
              } else {
                $resultat = false;
              }
              break;
          }
        break;
        
        case 'filPilote' :
          switch ($this->getType()) {
            case 'info' :
              $resultat = $this->getConfiguration('value');
            break;
            case 'action' :
              $request = $this->getConfiguration('request');
              $zone = $racoon->getConfiguration('zone');
              $resultat = racoon::setFilPilote($zone,$request);
            break;
          }
        break;

        case 'teleinfo' :
          switch ($this->getType()) {
            case 'info':
              $resultat = $this->getConfiguration('value');
              break;
          }
        break;
        case 'temperature' :
          switch ($this->getType()) {
            case 'info':
            $resultat = $this->getConfiguration('value');
            break;
          }
        case 'regulation' :
          switch ($this->getType()) {
            case 'info':
              $resultat = $this->getConfiguration('value');
            break;
            case 'action':
              switch ($this->getSubType()) {
                case 'slider':
                  $request = $this->getConfiguration('request');
                  $zone = $racoon->getConfiguration('zone');
                  $temperature = $this->getConfiguration('temperature');
                  //$kp = $this->getConfiguration('kp');
                  //$ki = $this->getConfiguration('ki');
                  //$kd = $this->getConfiguration('kd');
                  $request = $_options['slider'];
                 //$tempMin = $this->getConfiguration('minValue');
                  //$tempMax = $this->getConfiguration('maxValue');
                  $resultat = racoon::setRegulation($zone,$request,$temperature/**,$kp,$ki,$kd,$tempMin,$tempMax**/);
                break;
              }
            break;
          }
        break;
      }
       if(empty($resultat))
        $resultatLog = "ERROR";
      else 
        $resultatLog = print_r($resultat,true);
      return $resultat;
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
