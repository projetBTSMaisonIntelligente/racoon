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
    require_once dirname(__FILE__) . '/../../3rdparty/phpParticle/phpSpark.class.php';
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
    const NOMBRE_MINI_FILPILOTE = 1;
    public static $_fonctionnalite = array("fonction_teleinfo"=>0,"fonction_regulation"=>1,"fonction_filPilote"=>2);
    public static $_idSpark = array("device_id"=>0,"access_token"=>1);
    public static $_activerFonctionnalite = array("desactiver"=>0,"activer"=>1);
    public static $_configRegulation = array("kp"=>0,"ki"=>1,"kd"=>2,"tempMin"=>3,"tempMax"=>4);
    const CHEMIN_FICHIERJSON = '/usr/share/nginx/www/jeedom/plugins/racoon/core/resources/';


    /*     * ***********************Methode static*************************** */

    /*
     * méthode exécutée automatiquement toutes les minutes par Jeedom*/
    /**
     * Méthode appelée par le système toutes les minutes
     *
     */
      public static function cron() {
          self::getVariable();
      }

  /**
     * Ajout de tous les objets du plugin appelé après la sauvegarde de la configuration
     *
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
    public static function creationEquipement() {
      log::add('racoon','debug','Appel de creationEquipement');
      log::add('racoon','info','Création des objets');
      if($equipement = self::getConfigJSON('equipement')) {
        foreach ($equipement as $keyEquipement) {
           $commande = $keyEquipement['commande'];
           $configuration = $keyEquipement['configuration'];
           $racoon = self::byLogicalId($keyEquipement['logicalId'], 'racoon');
           if(!is_object($racoon)) {
                $racoon = new racoon();
                $racoon->setEqType_name($keyEquipement['eqType_name']);
                $racoon->setName($keyEquipement['name']);
                $racoon->setLogicalId($keyEquipement['logicalId']);
                $racoon->setIsEnable($keyEquipement['isEnable']);
                foreach ($configuration as $keyConfiguration) {
                  $racoon->setConfiguration($keyConfiguration['name'],$keyConfiguration['value']);
                }
                $racoon->save();
                log::add('racoon', 'info',print_r($racoon,true));
           } else {
                log::add('racoon','debug','objet ' . $racoon->getName() .' déjà crée');
           }
           self::creationCommande($racoon,$commande);
        }
        return true;
      } else {
        log::add('racoon','error','Fichier JSON pour les équipements n\'est pas sous le bon format ou n\'existe pas ');
        throw new Exception(__('Fichier JSON pour les équipements n\'est pas sous le bon format ou n\'existe pas ', __FILE__));
      }
  } 

  /**
     * Création des commandes par rapport aux objets
     *
     * @param racoon $racoon
     *
     * @param array $listeCommande liste des commandes pour l'objet racoon
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
    public static function creationCommande($objetRacoon,$listeCommande)
    {
        log::add('racoon','debug','Appel de creationCommande');
        log::add('racoon','info','Création des commandes pour l\'objet ' . $objetRacoon->getName());
        if ($commande = self::getConfigJSON('commande')) {
        foreach ($listeCommande as $keyCommandeACreer) {
          $racoonCmd = racoonCmd::byEqLogicIdCmdName($objetRacoon->getId(),$commande[$keyCommandeACreer['num']]['name']);
           if(!is_object($racoonCmd)) {
                $racoonCmd = new racoonCmd();
                $racoonCmd->setName($commande[$keyCommandeACreer['num']]['name']);
                $racoonCmd->setEqLogic_id($objetRacoon->getId());
                $racoonCmd->setEqType($commande[$keyCommandeACreer['num']]['eqType']);
                $racoonCmd->setLogicalId($commande[$keyCommandeACreer['num']]['logicalId']);
                $racoonCmd->setType($commande[$keyCommandeACreer['num']]['type']);
                foreach ($commande[$keyCommandeACreer['num']]['configuration'] as $keyConfiguration) {
                   $racoonCmd->setConfiguration($keyConfiguration['name'],$keyConfiguration['value']);    
                }
                $racoonCmd->setSubType($commande[$keyCommandeACreer['num']]['subType']);
                $racoonCmd->setDisplay('generic_type',$commande[$keyCommandeACreer['num']]['display']);
                $racoonCmd->setUnite($commande[$keyCommandeACreer['num']]['unite']);
                $racoonCmd->save();
                log::add('racoon', 'info',   print_r($racoonCmd,true));
            } else {
              log::add('racoon','debug','Commande ' . $racoonCmd->getName() . ' déjà crée');
            }
        }
        return true;
      } else {
        log::add('racoon','error','Fichier JSON pour les commandes n\'est pas sous le bon format ou n\'existe pas');
         throw new Exception(__('Fichier JSON pour les commandes n\'est pas sous le bon format ou n\'existe pas ', __FILE__));
       }
    }

  /**
     * Configuration de l'objet Spark Core (utilisé dans les méthodes call et getStatut)
     *
     * @see https://secure.php.net/manual/fr/function.json-last-error.php Pour les codes d'erreur JSON
     *
     * @param string $what le nom du tableau à récuperer dans le fichier JSON, soit 'objet' pour récupérer tous les objets à créer soit toutes les commandes disponibles
     *
     * @return array $objet|$commande selon le paramètre
     *
     */
  public static function getConfigJSON($element)
  {
      log::add('racoon','debug','Appel de getConfigJSON avec en paramètre, l\'élèment à récupérer : ' . $element);
        if(file_get_contents(self::CHEMIN_FICHIERJSON . $element . '.json') == TRUE) {
            log::add('racoon','debug','fichier JSON récupéré');
            $json_source = file_get_contents(self::CHEMIN_FICHIERJSON . $element . '.json');
            if(json_decode($json_source,TRUE) == TRUE) {
              $data = json_decode($json_source,TRUE);
              switch ($element) {
                case 'equipement':
                    $equipement = $data['equipement'];
                    return $equipement;
                    break;
                case 'commande':
                    $commande = $data['commande'];
                    return $commande;
                    break;
                case 'nombreFilPilote':
                    $nombreFilPilote = $data['nombreFilPilote'];
                    return $nombreFilPilote;
                    break;
              }
            } else {
              log::add('racoon','error','Erreur dans le fichier JSON ' . json_last_error());
              throw new Exception(__('Erreur dans le fichier JSON ' . json_last_error(), __FILE__));
              return false;
            }
        }
        else {
            log::add('racoon','error','Fichier JSON introuvable');
            throw new Exception(__('Fichier JSON introuvable', __FILE__));
            return false;
        }
  }
 /**
     * Configuration de l'objet Spark Core (utilisé dans les méthodes call et getStatut)
     *
     * @see https://secure.php.net/manual/fr/function.json-last-error.php Pour les codes d'erreur JSON
     *
     * @param string $what le nom du tableau à récuperer dans le fichier JSON, soit 'objet' pour récupérer tous les objets à créer soit toutes les commandes disponibles
     *
     * @return array $objet|$commande selon le paramètre
     *
     */
public static function getConfigSparkCore() {
    log::add('racoon','debug','Appel de getConfigSparkCore');
    $deviceId = config::byKey('deviceid','racoon',0);
    $accessToken = config::byKey('accessToken','racoon',0);
    if(is_string($deviceId) && is_string($accessToken))
      return $config = array($deviceId,$accessToken);
    else {
      log::add('racoon','error','Erreur de la configuration du Spark Core');
      throw new Exception(__('Erreur de la configuration du Spark Core ', __FILE__));
      }
    }
 /**
     * Configuration de l'objet Spark Core (utilisé dans les méthodes call et getStatut)
     *
     * @see https://secure.php.net/manual/fr/function.json-last-error.php Pour les codes d'erreur JSON
     *
     * @param string $what le nom du tableau à récuperer dans le fichier JSON, soit 'objet' pour récupérer tous les objets à créer soit toutes les commandes disponibles
     *
     * @return array $objet|$commande selon le paramètre
     *
     */
public static function getConfigFonctionnalite() {
  log::add('racoon','debug','Appel de getConfigFonctionnalite');
  $fonctTeleinfo =  config::byKey('teleinfo','racoon',0);
  $fonctRegulation =  config::byKey('regulation','racoon',0);
  $fonctFilPilote =  config::byKey('filPilote','racoon',0);
 if(in_array($fonctTeleinfo,self::$_activerFonctionnalite) && in_array($fonctRegulation,self::$_activerFonctionnalite)  && in_array($fonctFilPilote,self::$_activerFonctionnalite))
    return $config = array($fonctTeleinfo,$fonctRegulation,$fonctFilPilote);
else {
  log::add('racoon','error','Erreur de la configuration des fonctionnalités du plugin');
  throw new Exception(__('Erreur de la configuration des fonctionnalités du plugin ', __FILE__));
  }
}

public static function getConfigRegulation() {
  log::add('racoon','debug','Appel de getConfigFonctionnalite');
  $Kp =  config::byKey('Kp','racoon',0);
  $Ki =  config::byKey('Ki','racoon',0);
  $Kd =  config::byKey('Kd','racoon',0);
  $limiteTempMin = config::byKey('limiteTempMin','racoon',0);
  $limiteTempMax = config::byKey('limiteTempMax','racoon',0);
  return $config = array($Kp,$Ki,$Kd,$limiteTempMin,$limiteTempMax);
}
 /**
     * Configuration de l'objet Spark Core (utilisé dans les méthodes call et getStatut)
     *
     * @see https://secure.php.net/manual/fr/function.json-last-error.php Pour les codes d'erreur JSON
     *
     * @param string $what le nom du tableau à récuperer dans le fichier JSON, soit 'objet' pour récupérer tous les objets à créer soit toutes les commandes disponibles
     *
     * @return array $objet|$commande selon le paramètre
     *
     */
public static function getNombreFilPilote() {
  log::add('racoon','debug','Appel de getConfigSparkCore');
   if ($nombreFilPilote = self::getConfigJSON('nombreFilPilote') && is_int($nombreFilPilote)) {
          return $nombreFilPilote;
   } else {
      log::add('racoon','error','Fichier JSON pour le nombre de fil pilote n\'est pas sous le bon format ou n\'existe pas ');
      throw new Exception(__('Fichier JSON pour le nombre de fil pilote n\'est pas sous le bon format ou n\'existe pas ', __FILE__));
   }
       
}

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
    public static function configurationSparkCore($accessToken){
      log::add('racoon','debug','Appel de configurationSparkCore avec en paramètre l\'access Token : ' .$accessToken);
      $spark = new phpSpark();
      $spark->setDebug(false);
      $spark->setDebugType("TXT");
      $spark->setAccessToken($accessToken);
      $spark->setTimeout("5");
      return $spark;
    }

    /**
     * Vérifie les données sur la page de configuration 
     *
     * @return boolean $bool 
     *
     */
    public static function checkConfig()
    {
      $config = self::getConfigSparkCore();
      $spark = self::configurationSparkCore();
      if($spark->signalDevice($config[self::$_idSpark["device_id"]],1) == true) {
        $spark->debug_r($spark->getResult());
      } else {
        log::add('racoon','error','La connexion avec le Spark Core n\'a pas été établi, vérifier les identifiants');
        throw new Exception(__('La connexion avec le Spark Core n\'a pas été établi, vérifier les identifiants', __FILE__));
      }
      return TRUE;
    }
  /**
     * Configuration de l'objet Spark Core (utilisé dans les méthodes call et getStatut)
     *
     * @see https://secure.php.net/manual/fr/function.json-last-error.php Pour les codes d'erreur JSON
     *
     * @param string $what le nom du tableau à récuperer dans le fichier JSON, soit 'objet' pour récupérer tous les objets à créer soit toutes les commandes disponibles
     *
     * @return array $objet|$commande selon le paramètre
     *
     */
   public static function getVariable()
    {
      log::add('racoon','debug','Appel de getVariable');
      $config = self::getConfigFonctionnalite();
      if($config[self::$_fonctionnalite["fonction_teleinfo"]]) {
        $variableSparkCore = "teleinfo";
        if($resultat = self::requeteVariableSparkCore($variableSparkCore)) {
          if(isset($resultat) && (json_decode($resultat))) { 
            self::getTeleinfo($resultat); 
          } else { 
            log::add('racoon','error','Format du résultat invalide pour les teleinfo'); 
          }
        }
      } else { 
        log::add('racoon','debug','Fonctionnalite Teleinfo non-activer'); 
      }

      if($config[self::$_fonctionnalite["fonction_regulation"]]) {
        $variableSparkCore = "temperature";
        if($resultat = self::requeteVariableSparkCore($variableSparkCore)) {
           if(isset($resultat) && is_int($resultat)) { 
            self::getTemperature($resultat);
          } else { 
            log::add('racoon','error','Format du résultat invalide pour la température'); 
          }
        }
      } else { 
        log::add('racoon','debug','Fonctionnalite Régulation non-activer'); 
      }

      if($config[self::$_fonctionnalite["fonction_filPilote"]]) {
        $variableSparkCore = "etatFp";
        if($resultat = self::requeteVariableSparkCore($variableSparkCore)) {
            if(isset($resultat) && is_string($resultat) && (strlen($resulat) == self::getNombreFilPilote())) {
              self::getTemperature($resultat);
            } else { 
              log::add('racoon','error','Format du résultat invalide pour les fils pilotes'); 
            }
        } 
      } else { 
          log::add('racoon','debug','Fonctionnalite Fil Pilote non-activer'); 
        }      
  }
  /**
     * Configuration de l'objet Spark Core (utilisé dans les méthodes call et getStatut)
     *
     * @see https://secure.php.net/manual/fr/function.json-last-error.php Pour les codes d'erreur JSON
     *
     * @param string $what le nom du tableau à récuperer dans le fichier JSON, soit 'objet' pour récupérer tous les objets à créer soit toutes les commandes disponibles
     *
     * @return array $objet|$commande selon le paramètre
     *
     */
    public static function requeteVariableSparkCore($variableSparkCore)
    {
      log::add('racoon','debug','Appel de requeteVariableSparkCore avec en paramètre, variable du Spark Core : ' . $variableSparkCore);
      $config = self::getConfigSparkCore();
      $spark = self::configurationSparkCore($config[self::$_idSpark["access_token"]]);
       if ($spark->getVariable($config[self::$_idSpark["device_id"]],$variableSparkCore) == true) {
        $data = $spark->getResult();
        $resultat = $data['result'];
        log::add('racoon','info','Data reçu du Spark Core :' . $resultat);
        } else {
          log::add('racoon','error','Erreur d\'appel de la variable ' . $variableSparkCore. ' , ' . $spark->getError() . ' source ' . $spark->getErrorSource());
          return false;
        }
        return $resultat;
    }
 /**
     * Configuration de l'objet Spark Core (utilisé dans les méthodes call et getStatut)
     *
     * @see https://secure.php.net/manual/fr/function.json-last-error.php Pour les codes d'erreur JSON
     *
     * @param string $what le nom du tableau à récuperer dans le fichier JSON, soit 'objet' pour récupérer tous les objets à créer soit toutes les commandes disponibles
     *
     * @return array $objet|$commande selon le paramètre
     *
     */
    public static function requeteFonctionSparkCore($fonctionSparkCore,$parametre)
    {
      log::add('racoon','debug','Appel de requeteVariableSparkCore avec en paramètre, méthode du Spark Core :' . $fonctionSparkCore . ' et le(s) paramètre(s) : ' . $parametre);
      $config = self::getConfigSparkCore();
      $spark = self::configurationSparkCore($config[self::$_idSpark["access_token"]]);
       if($spark->callFunction($config[self::$_idSpark["device_id"]],$fonctionSparkCore,$parametre) == true) {
          log::add('racoon','info','Commande envoye au Spark Core');
        } else {
          log::add('racoon','error','Erreur d\'appel de la fonction '. $fonctionSparkCore .' ' . $spark->getError() . ' source ' . $spark->getErrorSource());
          return false;
        }
        return true;
    }
   /**
     * Récupération des statut des zones des radiateurs de la maison.
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   public static function getStatut($resultatRequete) {
      log::add('racoon','debug','Appel de getStatut() avec en paramètre, résultat de la requête : ' . print_r($resultatRequete,true));
      for ($izone= NOMBRE_MINI_FILPILOTE; $izone < self::getNombreFilPilote() ; $izone++) { 
        $sparkZone = $izone-1;
        $valeur = $resultatRequete[$sparkZone];
        $logical = 'zone' . $izone;
        log::add('racoon','debug','Retour statut zone ' . $izone . ' valeur ' . $valeur);
        $racoon = self::byLogicalId($logical,'racoon');
        $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),'statut');
        $racoonCmd->setConfiguration('value',$valeur);
        $racoonCmd->save();
        $racoonCmd->event($valeur);
        $izone++;
      }
      return true;
   }
   /**
     * Récupération de la température enregistrée sur le Spark Core grâve au module 433MHz
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   public static function getTemperature($resultatRequete) {
      log::add('racoon','debug','Appel de getTemperature() avec en parametre, résultat de la requête : ' . print_r($resultatRequete,true));
      $logical = 'temperature';
      $racoon = self::byLogicalId($logical,'racoon');
      $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),$logical);
      $racoonCmd->setConfiguration('value',$resultatRequete);
      $racoonCmd->save();
      $racoonCmd->event($resultatRequete);
      return true;
    }
     /**
     * Récupération des téléinfos enregistrées sur le Spark Core
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   public static function getTeleinfo($resultatRequete) {
      log::add('racoon','debug','Appel de getTeleinfo() avec en paramètre, résultat de la requête : ' . print_r($resultatRequete,true));
      $teleinfo = json_decode($resultatRequete,true); 
      log::add('racoon','debug','Retour teleinfo traité' . print_r($teleinfo,true));
      $racoon = self::byLogicalId('teleinfo','racoon');
      foreach($teleinfo as $key => $valeur) {
        log::add('racoon','debug','Retour teleinfo ' . $key . ' valeur ' . $valeur);
        $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),$key);
        if(!is_object($racoonCmd))
        {
          $racoonCmd = new racoonCmd();
          $racoonCmd->setName($key);
          $racoonCmd->setEqLogic_id($racoon->getId());
          $racoonCmd->setEqType('racoon');
          $racoonCmd->setLogicalId($key);
          $racoonCmd->setType('info');
          $racoonCmd->setSubType('numeric');
          $racoonCmd->setConfiguration('mode','teleinfo');
        }
        $racoonCmd->setConfiguration('value',$valeur);
        $racoonCmd->save();
        $racoonCmd->event($valeur);
      }

      return true;
    }

   /**
     * Envoi d'ordre $request à la zone $zone via requête HTTP par méthode POST pour les radiateurs.  
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @param int $zone Variable correspondant au numéro de la zone sélectionnée.
     *
     * @param string $request Variable correspondant de la demande d'ordre.
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
  public static function setFilPilote($zone,$request) {
        log::add('racoon','debug','Appel de setFilPilote() avec en paramètre, Commande reçu : ' . $request . '  vers la zone ' . $zone);
        $parametre = $zone . "," . $request;
        $fonctionSparkCore = 'setFp';
        if(self::requeteFonctionSparkCore($fonctionSparkCore,$parametre))
        {
          $logical = 'zone' . $zone;
          $racoon = self::byLogicalId($logical, 'racoon');
          $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),'statut');
          $racoonCmd->setConfiguration('value', $request);
          $racoonCmd->save();
          $racoonCmd->event($request);
          return true;
        }
        return false;
    }
    
    public static function setRegulation() {
      log::add('racoon','debug','Appel de setRegulation()');
      $config = self::getConfigRegulation();

      $parametre = $zone . ',' . $request . ',' . $config[self::$_configRegulation["kp"]] . ',' . $config[self::$_configRegulation["ki"]] . ',' . $config[self::$_configRegulation["kd"]] . ',' . $config[self::$_configRegulation["tempMin"]] . ',' . $config[self::$_configRegulation["tempMax"]];
      $fonctionSparkCore = 'setRegulation';
       if(self::requeteFonctionSparkCore($fonctionSparkCore,$parametre))
       {
        return true;
       }
       return false;
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
 
  public static function setIsVisibleCmd()
    {
      log::add('racoon','debug','Appel de setIsVisibleCmd');
      $config = racoon::getConfigFonctionnalite();
      log::add('racoon','debug',print_r($config,true));
      $eqLogics = eqLogic::byType('racoon');
    log::add('racoon','debug','0');
      foreach ($eqLogics as $racoon) {
        log::add('racoon','debug','1');
        $cmdRacoons = cmd::byEqLogicId($racoon->getId());
        log::add('racoon','debug','2');
        foreach ($cmdRacoons as $racoonCmd) {
          log::add('racoon','debug','3');
        switch ($racoonCmd->getConfiguration('mode')) {
          case 'teleinfo':
            $racoonCmd->setIsVisible($config[racoon::$_fonctionnalite["fonction_teleinfo"]]);
            log::add('racoon','debug',print_r($racoonCmd,true));
            $racoonCmd->save();
            break;
          case 'regulation':
            $racoonCmd->setIsVisible($config[racoon::$_fonctionnalite["fonction_regulation"]]);
            log::add('racoon','debug',print_r($racoonCmd,true));
            $racoonCmd->save();
            break;
          case 'filPilote':
            $racoonCmd->setIsVisible($config[racoon::$_fonctionnalite["fonction_filPilote"]]);
            log::add('racoon','debug',print_r($racoonCmd,true));
            $racoonCmd->save();
            break;
          default:
            log::add('racoon','error', 'Type de mode non compatible avec le plugin, voir le fichier commande.json');
            throw new Exception(__('Type de mode non compatible avec le plugin, voir le fichier commande.json',__FILE__));
            break;
        }
      }
    }
  }
     
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
      log::add('racoon','debug','CMD ' . $this->name . ' en cours');
      switch($this->getConfiguration('mode')) {
        case 'filPilote' :
          switch ($this->getType()) {
            case 'info' :
              return $this->getConfiguration('value');
            break;
            case 'action' :
              $request = $this->getConfiguration('request');
              $zone = $racoon->getConfiguration('zone');
              if ($zone == '0') {
                for ($iZone = self::NOMBRE_MINI_FILPILOTE ; $iZone < racoon::getNombreFilPilote() ; $iZone++)
                  racoon::setFilPilote($iZone,$request);
              } else {
                racoon::setFilPilote($zone,$request);
              }
            break;
            default:
              log::add('racoon','error', $this->getType() .': type de commande non-existant pour la fonctionnalité FilPilote dans le plugin');
              throw new Exception(__($this->getType() .': type de commande non-existant pour la fonctionnalité FilPilote dans le plugin',__FILE__));
            break;
          }
        break;

        case 'teleinfo' :
          switch ($this->getType()) {
            case 'info':
              return $this->getConfiguration('value');
              break;
            default:
              log::add('racoon','error', $this->getType() .': type de commande non-existant pour la fonctionnalité Téléinfo dans le plugin');
              throw new Exception(__($this->getType() .': type de commande non-existant pour la fonctionnalité Téléinfo dans le plugin',__FILE__));
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
                  if($this->getConfiguration('activer') == 1)
                  $request = 17;
                  $zone = $racoon->getConfiguration('zone');
                  $request = str_replace('#slider#',$_options['slider'],$request);
                  racoon::setRegulation($zone,$request);
                break;
                default:
                   log::add('racoon','error', $this->getType() .': sous-type de commande non-existant pour la fonctionnalité FilPilote dans le plugin');
                   throw new Exception(__($this->getType() .': sous-type de commande non-existant pour la fonctionnalité FilPilote dans le plugin',__FILE__));
                break;
              }
            break;
            default:
              log::add('racoon','error', $this->getType() .': type de commande non-existant pour la fonctionnalité Régulation dans le plugin');
              throw new Exception(__($this->getType() .': type de commande non-existant pour la fonctionnalité Régulation dans le plugin',__FILE__));
            break;
          }
        break;

        default :
          log::add('racoon','error', $this->getConfiguration('mode') .': Mode non-existant dans le plugin (voir la page de configuration du plugin)');
          throw new Exception(__($this->getConfiguration('mode') .': Mode non-existant dans le plugin (voir la page de configuration du plugin)',__FILE__));
        break;
      }
      return true;
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
