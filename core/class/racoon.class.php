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
if(!class_exists("phpSpark"))
{
    require_once dirname(__FILE__) . '/../../3rdparty/SparkPhp/phpSpark.class.php';
}

/**
     * Class racoon
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

    /*
     * méthode exécutée automatiquement toutes les minutes par Jeedom*/
    /**
     * Méthode appelée par le système toutes les minutes
     *
     */
public static function cron() {
  if(is_object($racoonSparkCore = self::byLogicalId('sparkCore','racoon'))) {
    try {
      $resultatRequete = self::requeteInformationSparkCore();
      self::traitementInformationSparkCore($resultatRequete);
      if($cmdSparkCore = $racoonSparkCore->searchCmdByConfiguration('aRecuperer')) {
        foreach ($cmdSparkCore as $keyVariable) {
          if($keyVariable->getConfiguration('aRecuperer') == 1) {
            if($resultatRequete = self::requeteVariableSparkCore($keyVariable->getName())) {
              $keyVariable->setConfiguration('value',$resultatRequete);
              $keyVariable->save();
              $keyVariable->event($resultatRequete);
              switch ($keyVariable->getConfiguration('utiliserPar')) {
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
    } catch (Exception $e) {

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
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
public static function creationEquipement($typeEquipement,$nombreEquipement) {
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
        return true;
      } else {
        log::add('racoon','ERROR','[Config] Fichier JSON');
        return false;
      }
} 

  /**
     * Création des commandes par rapport aux objets
     *
     * @param racoon $equipRacoon correspond au racoon sur lequel les commandes vont être créer.
     *
     * @param string $typeEquipement correspond au type de l'équipement pour récupérer les commandes par rapport à cet type.
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
    public static function creationCommande($equipRacoon,$typeEquipement,$nomCommande = null)
    {
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
                $racoonCmd->setSubType($typeCommande['subType']);
                $racoonCmd->setDisplay('generic_type',$typeCommande['display']);
                $racoonCmd->setUnite($typeCommande['unite']);
                $racoonCmd->save();
                log::add('racoon','DEBUG','[Data] Commande : '  . print_r($racoonCmd,true));
                log::add('racoon','INFO','[Succès] Création de la commande ' . $racoonCmd->getName() . ' du racoon ' . $equipRacoon->getName());
            } else {
              log::add('racoon','DEBUG','[Commande] ' . $racoonCmd->getName() . ' déjà crée');
            }
        }
        log::add('racoon','INFO','[Succès] Création des commandes pour l\'équipement Racoon ' . $equipRacoon->getName() . ' réussie');
        return true;
      } else {
        log::add('racoon','ERROR','[Config] Fichier JSON');
        return false;
       }
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
      log::add('racoon','DEBUG','[Appel] getConfigJSON avec le paramètre, fichier : ' . $element);
        if(file_get_contents(self::CHEMIN_FICHIERJSON . $element . '.json') == FALSE) {
            log::add('racoon','ERROR','[Fichier] ' . $element . '.json introuvable');
            return false;
        } else {
            $json_source = file_get_contents(self::CHEMIN_FICHIERJSON . $element . '.json');
            if(json_decode($json_source,TRUE) == FALSE) {
              log::add('racoon','ERROR','[JSON] [CODE] ' . json_last_error()); 
              return false;           
            } else {
              $data = json_decode($json_source,TRUE);
              switch ($element) {
                case 'equipement':
                    $equipement = $data['equipement'];
                    log::add('racoon','DEBUG','[Data] Array équipement ' . print_r($equipement,true));
                    log::add('racoon','INFO','[Succès] Récupération des données du fichierJSON ' . $element . ' réussie');
                    return $equipement;
                    break;
                case 'commande':
                    $commande = $data['commande'];
                    log::add('racoon','DEBUG','[Data] Array commande ' . print_r($commande,true));
                    log::add('racoon','INFO','[Succès] Récupération des données du fichierJSON ' . $element . ' réussie');
                    return $commande;
                    break;
              }
            }
        }
  }
 /**
     * Récupération à partir de la page de configuration, des informations sur le Spark Core.
     *
     * @return array $objet|$commande selon le paramètre
     *
     */
public static function getConfigSparkCore() {
    log::add('racoon','DEBUG','[Appel] getConfigSparkCore sans paramètre');
    $deviceId = config::byKey('deviceid','racoon');
    $accessToken = config::byKey('accessToken','racoon');
    log::add('racoon','DEBUG','[Data] DeviceID du spark core : ' . $deviceId . ', accessToken : ' . $accessToken);
    if(is_string($deviceId) && is_string($accessToken)) {
      $racoonSparkCore = self::byLogicalId('sparkCore','racoon');
      if(is_object($racoonSparkCore)) {
        $racoonSparkCore->setConfiguration('deviceId',$deviceId);
        $racoonSparkCore->setConfiguration('accessToken',$accessToken);
        $racoonSparkCore->save();
        log::add('racoon','INFO','[Succès] Récupération des données de configuration du Spark Core réussie');
        return true;
      } else {
        log::add('racoon','ERROR','[Equipement] Racoon SparkCore n\'existe pas');
        return false;
      }
    } else {
      log::add('racoon','ERROR','[Config] Données page de configuration non-conformes');
      return false;
      }
    }
//

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
    public static function configurationPhpParticle($accessToken){
      log::add('racoon','DEBUG','[Appel] ConfigurationSparkCore avec le paramètre, accessToken : ' .$accessToken);
      $spark = new phpSpark();
      $spark->setDebug(false);
      $spark->setDebugType("TXT");
      $spark->setAccessToken($accessToken);
      $spark->setTimeout("5");
      return $spark;
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
    * @return array $data Renvoit les informations récupérées de la requête
    *
    */
  public static function requeteInformationSparkCore()
  {
      log::add('racoon','DEBUG','[Appel] requeteInformationSparkCore() sans paramètre');
      $logical = 'sparkCore';
      $racoonSparkCore = self::byLogicalId($logical,'racoon');
      $spark = self::configurationPhpParticle($racoonSparkCore->getConfiguration('accessToken'));
      if($spark->getDeviceInfo($racoonSparkCore->getConfiguration('deviceId')) == true) {
        $data = $spark->getResult();
        log::add('racoon','INFO','[Succès] Requête HTTP sur le Spark Core pour récupérer ses informations réussie');
        log::add('racoon','DEBUG','[Data] Données reçues du Spark Core : ' . print_r($data,true));
        return $data;
        } else {
          log::add('racoon','ERROR','[Requête] Erreur d\'appel des informations du Spark Core [Code] : ' . $spark->getError() . ' [Source] : ' . $spark->getERRORSource());
          return false;
        }
        
  }
  /**
     * Requête envoyé au Spark Core pour une variable selon un paramètre, le nom de la variable.
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @param string $fonctionSparkCore correspond au nom de la fonction appelé sur le Spark Core
     *
     * @return array $resultat récupère les résultats de la requête de la variable SINON 
     *
     */
    public static function requeteVariableSparkCore($variableSparkCore)
    {
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
            return false;
        }
          return $resultat;
      }
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
      log::add('racoon','DEBUG','[Appel] requeteFonctionSparkCore avec les paramètres, fonctionSparkCore ' . $fonctionSparkCore . ' & les paramètres ' . $parametre);
      if(is_string($fonctionSparkCore) && is_string($parametre)) {
        $racoonSparkCore = self::byLogicalId('sparkCore','racoon');
        $spark = self::configurationPhpParticle($racoonSparkCore->getConfiguration('accessToken'));
        if($spark->callFunction($racoonSparkCore->getConfiguration('deviceId'),$fonctionSparkCore,$parametre) == true) {
          log::add('racoon','INFO','[Succès] Requête HTTP sur le Spark Core pour faire appel à la fonction ' . $fonctionSparkCore . ' avec les paramètres : ' . $parametre . 'réussie');
          return true;
        } else {
          log::add('racoon','ERROR','[Requête] Erreur d\'appel de la fonction '. $fonctionSparkCore . ' [Code] :' . $spark->getError() . ' [Source] : ' . $spark->getErrorSource());
          return false;
        }
      } else {
        log::add('racoon','ERROR','[Paramètres] Non-conformes');
        return false;
      }
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
*/
    public static function traitementInformationSparkCore($resultatRequete) {
      log::add('racoon','DEBUG','[Appel] traitementInformationSparkCore() avec es paramètre, résultat de la requête : ' . print_r($resultatRequete,true));
      $typeEquipement = 'sparkCore';
      $racoonSparkCore = self::byLogicalId('sparkCore','racoon');
      if($resultatRequete != null && is_array($resultatRequete)) {
        if(is_object($racoonSparkCore)) {
        $variables = $resultatRequete['variables'];
        if (count($variables) != 0) {
          foreach ($variables as $keyVariable => $type) {
            log::add('racoon','DEBUG','Retour informations variables ' . $keyVariable);
            $commandeVariable = racoonCmd::byEqLogicIdCmdName($racoonSparkCore->getId(),$keyVariable);
            if(!is_object($commandeVariable))
              self::creationCommande($racoonSparkCore,$typeEquipement . 'Variable',$keyVariable);
          }
        }
        $fonctions = $resultatRequete['functions'];
        if(count($fonctions) != 0) {
            foreach ($fonctions as $keyFonction){
            log::add('racoon','DEBUG','Retour informations fonctions ' . $keyFonction);
            if(!is_object(racoonCmd::byEqLogicIdCmdName($racoonSparkCore->getId(),$keyFonction)))
              self::creationCommande($racoonSparkCore,$typeEquipement . 'Fonction',$keyFonction);
          }
        }  
        log::add('racoon','INFO','[Succès] Traitement des informations du Spark Core réussi');
        //self::supprimerCommandeSparkCore($variables,$fonctions);
        return true;
      } else {
        log::add('racoon','ERROR','[Equipement] Racoon SparkCore n\'existe pas');
        return false;
      }
    } else
      log::add('racoon','ERROR','[Traitement] Informations du Spark Core non-conforme');
      return false;
  }
   /**
     * Traitement de la requête sur la variable correspondant aux statuts des fil pilotes
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @param string $resultatRequete correspond au traitement de la requête pour récupérer la variable correspondant à la fonctionnalité FilPilote c'est à dire les status des fil pilotes
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   public static function traitementFilPilote($resultatRequete) {
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
              $racoonCmd->setConfiguration('value',$valeur);
              $racoonCmd->save();
              $racoonCmd->event($valeur);
              log::add('racoon','INFO','[Succès] Traitement Fil Pilote réussi');
            } else {
              log::add('racoon','ERROR','[Equipement] Zone '. $izone . ' n\'existe pas');
              return false;
            }
        }
        return true;
      } else {
        log::add('racoon','ERROR','[Traitement] Informations \'Fil Pilote\' du Spark Core récupérés non-conformes');
        return false;
      }
   }
   /**
     * Récupération de la température enregistrée sur le Spark Core grâve au module 433MHz
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @param int $resultatRequete correspond au traitement de la requête pour récupérer la variable correspondant à la fonctionnalité Temperature c'est à dire les status des fil pilotes
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   public static function traitementTemperature($resultatRequete) {
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
          return true;
        } else {
            log::add('racoon','ERROR','[Equipement] Temperature n\'existe pas');
            return false;
        }
      } else {
          log::add('racoon','ERROR','[Traitement] Informations \'Température\' du Spark Core récupérés non-conformes');
          return false;
      }
    }
     /**
     * Récupération des téléinfos enregistrées sur le Spark Core
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @param json $resultatRequete correspond au traitement de la requête pour récupérer la variable correspondant à la fonctionnalité Teleinfo c'est à dire les status des fil pilotes
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   public static function traitementTeleinfo($resultatRequete) {
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
          return true;
        } else {
          log::add('racoon','ERROR','[Equipement] Teleinfo n\'existe pas');
          return false;
        }
      } else {
        log::add('racoon','ERROR','[Traitement] Informations \'Téléinfo\' du Spark Core récupérés non-conformes');
        return false;
      }
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
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
  public static function setFilPilote($zone,$request) {
        log::add('racoon','DEBUG','[Appel] setFilPilote() avec les paramètres, ordre : ' . $request . ', zone : ' . $zone);
        if(is_int($zone) && is_string($request)) {
          $parametre = $zone . $request;
          $racoonSparkCore = self::byLogicalId('sparkCore','racoon');
          log::add('racoon','DEBUG',print_r(cmd::searchConfigurationEqLogic($racoonSparkCore->getId(),'utiliserPar','action')));
         if($cmdSparkCore = cmd::searchConfigurationEqLogic($racoonSparkCore->getId(),'utiliserPar','action')) {
            foreach ($cmdSparkCore as $cmdFonction) {
              log::add('racoon','DEBUG','[Data] Fonction Spark Core ' . $cmdFonction->getName());
              if($cmdFonction->getConfiguration('utiliserPar') == 'filPilote')
                $fonctionSparkCore = $cmdFonction->getName();
            }
          } else {
             log::add('racoon','ERROR','[Equipement] Spark Core non-configuré');
             return false;
          }
          if(self::requeteFonctionSparkCore($fonctionSparkCore,$parametre)) {
            $logical = 'zone' . $zone;
            $racoon = self::byLogicalId($logical, 'racoon');
            $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),'statut');
            //$racoonCmd->setValue($request);
            $racoonCmd->setConfiguration('value', $request);
            $racoonCmd->save();
            $racoonCmd->event($request);
            log::add('racoon','INFO','[Succès] Ordre ' . $request . ' envoyé dans la zone ' . $zone);
            return true;
          } else {
            return false;
          }
        } else {
          log::add('racoon','ERROR','[Paramètres] Non-conformes');

        }
    }
     /**
     * Envoi de la consigne $request à la zone $zone via requête HTTP par méthode POST pour les radiateurs avec une config définie par l'utilisateur ou par défaut.  
     *
     * @param int $zone Variable correspondant au numéro de la zone sélectionnée.
     *
     * @param string $request Variable correspondant de la demande de consigne.
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
    public static function setRegulation($zone,$request,$temperature,$kp,$ki,$kd) {
      log::add('racoon','DEBUG','[Appel] de setRegulation()');
      if(is_int($zone) && is_double($request) && is_double($temperature) && is_double($kp) && is_double($ki) && is_double($kd)) {
          $parametre = '(' . $zone . '/' . $request . '/' . $temperature . '/' . $kp . '/' . $ki . '/' . $kd . ')';
        
        $racoonSparkCore = self::byLogicalId('sparkCore','racoon');
           if($cmdSparkCore = self::byTypeAndSearhConfiguration('action','utiliserPar')) {
              foreach ($cmdSparkCore as $cmdFonction) {
                if($cmdFonction->getConfiguration('utiliserPar') == 'filPilote')
                  $fonctionSparkCore = $cmdFonction->getName();
              }
            } else {
               log::add('racoon','ERROR','[Equipement] Spark Core non-configuré');
               return false;
        }
        if(self::requeteFonctionSparkCore($fonctionSparkCore,$parametre))
          log::add('racoon','INFO','[Succès] Régulation demandé à la température ' . $request . ' dans la zone ' . $zone);
        return true;
       } else {
          log::add('racoon','ERROR','[Paramètres] Non-conformes');
          return false;
       }
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
     * Class racoonCmd
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
     * @return boolean retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
    public function execute($_options = array()) {
      $racoon = $this->getEqLogic();
      log::add('racoon','DEBUG','[Appel] CMD ' . $this->name  . ' de l\' eqLogic ' . $racoon->getName());
      switch($this->getConfiguration('mode')) {
       case 'sparkCore' :
         switch ($this->getType()) {
            case 'action' :
              self::requeteFonctionSparkCore($this->getName(),$this->getConfiguration('parametre'));
              break;
            case 'info' :
              if($resultatRequete = self::requeteVariableSparkCore($this->getName())) {
                $this->setConfiguration('value',$resultatRequete);
                return $this->getConfiguration('value');
              }
              break;
          }
        break;
        
        case 'filPilote' :
          switch ($this->getType()) {
            case 'info' :
              return $this->getConfiguration('value');
            break;
            case 'action' :
              $request = $this->getConfiguration('request');
              $zone = $racoon->getConfiguration('zone');
              return (racoon::setFilPilote($zone,$request));
            break;
            default:
              log::add('racoon','ERROR', $this->getType() .': type de commande non-existant pour la fonctionnalité FilPilote dans le plugin');
            break;
          }
        break;

        case 'teleinfo' :
          switch ($this->getType()) {
            case 'info':
              return $this->getConfiguration('value');
              break;
            default:
              log::add('racoon','ERROR', $this->getType() .': type de commande non-existant pour la fonctionnalité Téléinfo dans le plugin');
            break;
          }
        break;

        case 'regulation' :
          switch ($this->getType()) {
            case 'info':
              return $this->getConfiguration('value');
            break;
            case 'action':
              switch ($this->getSubType()) {
                case 'slider':
                  $request = $this->getConfiguration('request');
                  $zone = $racoon->getConfiguration('zone');
                  $temperature = $this->getConfiguration('temperature');
                  $kp = $this->getConfiguration('kp');
                  $ki = $this->getConfiguration('ki');
                  $kd = $this->getConfiguration('kd');
                  $request = str_replace('#slider#',$_options['slider'],$request);
                  racoon::setRegulation($zone,$request,$temperature,$kp,$ki,$kd);
                break;
                default:
                   log::add('racoon','ERROR', $this->getType() .': sous-type de commande non-existant pour la fonctionnalité FilPilote dans le plugin');
                break;
              }
            break;
            default:
              log::add('racoon','ERROR', $this->getType() .': type de commande non-existant pour la fonctionnalité Régulation dans le plugin');
            break;
          }
        break;

        default :
          log::add('racoon','ERROR', $this->getConfiguration('mode') .': Mode non-existant dans le plugin (voir la page de configuration du plugin)');
        break;
      }
      return true;
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
