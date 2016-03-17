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
require_once dirname(__FILE__) . '/../../core/php/phpSpark.class.php';

class racoon extends eqLogic {
    /*     * *************************Attributs****************************** */
    //const CONFORT = "C";
    //const ECONOMIQUE = "E";
    //const HORSGEL = "H";
    //const ARRET = "A";
    //const NOMBRE_FILPILOTE = 7;


    /*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom*/
      public static function cron() {
          self::getStatut();
      }
    /* */


    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDayly() {

      }
     */
   /**
     * Récupération des statut des zones des radiateurs de la maison.
     *
     *
     * @return boolean retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   public function getStatut() {
      log::add('racoon','debug','appel de getstatut()');
      //Récupération des données enregistrées dans la page de configuration
      $deviceId = config::byKey('deviceid','racoon',0);
      $accessToken = config::byKey('accessToken','racoon',0);
      //
      $spark = new phpSpark();
      $spark->setDebug(false);
      $spark->setDebugType("TXT");
      $spark->setAccessToken($accessToken);
      $spark->setTimeout("5");
      if ($spark->getVariable($deviceId,"etatfp") == true) {
        $obj = $spark->getResult();
        $result = $obj['result'];
      } else {
          log::add('racoon','error','erreur d\'appel de la variable etatfp,' . $spark->getError() . ' source ' . $spark->getErrorSource());
          return false;
      }
      log::add('racoon','debug','Retour ' . print_r($result,true));
      $izone = 1;
      while ($izone <=7) {
        $sparkZone = $izone-1;
        $statut = $result[$sparkZone];
        $logical = 'zone' . $izone;
        log::add('racoon','debug','Retour statut zone ' . $izone . ' valeur ' . $statut);
        $racoon = self::byLogicalId($logical,'racoon');
        $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),'statut');
        $racoonCmd->setConfiguration('value',$statut);
        $racoonCmd->save();
        $racoonCmd->event($statut);
        $izone++;
      }
      return true;
   }

   /**
     * Envoi d'ordre $request à la zone $zone via requête HTTP par méthode POST pour les radiateurs.  
     *
     * @param int $zone Variable correspondant au numéro de la zone sélectionnée.
     *
     * @param string $request Variable correspondant de la demande d'ordre.
     *
     * @return boolean retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */

  public function racoonCall($zone,$request) {
     if(is_string($request) && is_int($zone)) {
      if ( $zone >= 1 && $zone <=7 && ($request == 'A' || $request == 'H' || $request == 'E'|| $request == 'C')) {
        $params=$zone.$request;
        log::add('racoon','debug','Commande recu : ' . $request . '  vers la zone ' . $zone);
        //Récupération des données enregistrées dans la page de configuration
        $deviceId = config::byKey('deviceid', 'racoon',0);
        $accessToken = config::byKey('accessToken','racoon',0);
        //Création de l'objet phpSpark pour communiquer avec le Spark Core
        $spark = new phpSpark();
        $spark->setDebug(false);
        $spark->setDebugType("TXT");
        $spark->setTimeout("5");
        $spark->setAccessToken($accessToken);
        //Appel de la fonction setfp avec les params
        if($spark->callFunction($deviceId,"setfp",$params) == true) {
          log::add('racoon','debug','Commande envoye au Spark Core');
        } else {
          log::add('racoon','error','Erreur d\'appel ' . $spark->getError() . ' source ' . $spark->getErrorSource());
          return false;
        }
        //Enregistrement de l'état sur l'objet
        $logical = 'zone' . $zone;
        $racoon = self::byLogicalId($logical, 'racoon');
        $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),'statut');
        $racoonCmd->setConfiguration('value', $request);
        $racoonCmd->save();
        $racoonCmd->event($request);
      }
    }
    return true;
}
   /**
     * Création des objets composant le plugin
     *
     * @return boolean retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   /**
public function configSparkCore($accessToken) {
        $spark = new phpSpark();
        $spark->setDebug(false);
        $spark->setDebugType("TXT");
        $spark->setTimeout("5");
        $spark->setAccessToken($accessToken);
        return $spark;
}**/


   /**
     * Sauvegarde du statut de la zone
     *
     * @param string $logicalId LogicalId nécessitant une mise à jour de sa data
     *
     * @param string $statut Statut de la zone à enregister 
     *
     * @return boolean retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   /**
public function sauvegardeStatut($logicalId,$statut) {
        $racoon = self::byLogicalId($logicalId, 'racoon');
        $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),'statut');
        $racoonCmd->setConfiguration('value', $statut);
        $racoonCmd->save();
        $racoonCmd->event($request);
}**/


   /**
     * Création des objets composant le plugin
     *
     * @return boolean retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
public static function ajouterZoneRadiateur() {
  //ajout Radiateur
  log::add('racoon','debug','Création des équipements');
  $nbZone = 1;
    while ($nbZone <= 7) {
      $logical = 'zone' . $nbZone;
      $racoon = self::byLogicalId($logical, 'racoon');
      if (!is_object($racoon)) {
        log::add('racoon', 'info', 'Equipement n existe pas, création en cours' . $logical);
        $racoon = new racoon();
        $racoon->setEqType_name('racoon');
        $racoon->setLogicalId($logical);
        $racoon->setName('Zone ' . $nbZone);
        $racoon->setIsEnable(true);
        $racoon->save();
        $racoon->setLogicalId('zone' . $nbZone);
        $racoon->setConfiguration('zone', $nbZone);
        $racoon->save();
        log::add('racoon', 'info',   print_r($racoon,true));
        self::ajouterCmd($racoon,'Confort','confort','action','C');
        self::ajouterCmd($racoon,'Eco','eco','action','E');
        self::ajouterCmd($racoon,'Hors Gel','horsgel','action','H');
        self::ajouterCmd($racoon,'Arret','arret','action','A');
        self::ajouterCmd($racoon,'Statut','statut','info',0);
        log::add('racoon','debug','fin de la creation de l\'equipement')
      } else {
        log::add('racoon','debug','l\'equipement ' . $logical . ' existe déjà');
      }
      //incrémentation
      $nbZone++;
  }
  return true;
}
   /**
     * Création des commandes composant le plugin
     *
     * @param Racoon racoon Object racoon du plugin
     *
     * @param string $nameCmd Nom de la commande
     *
     * @param string $logicalIdCmd Nom de la logicalId pour la commande
     *
     * @param string $typeCmd Nom du type de commande
     *
     * @param string $request Ordre sélectionné par l'utilisateur
     *
     * @return boolean retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
public static function ajouterCmd($racoon,$nameCmd,$logicalIdCmd,$typeCmd,$request) {
  log::add('racoon','debug','Ajout de la cmd ' . $nameCmd . ' sur l\'equipement ' . $racoon->getLogicalId());
  $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),$logicalIdCmd);
  if(!is_object($racoonCmd)) {
    $racoonCmd = new racoonCmd();
    $racoonCmd->setName($nameCmd);
    $racoonCmd->setEqLogic_id($racoon->getId());
    $racoonCmd->setEqType('racoon');
    $racoonCmd->setLogicalId($logicalIdCmd);
    switch ($typeCmd) {
      case 'action':
        $racoonCmd->setType($typeCmd);
        $racoonCmd->setConfiguration('request',$request);
        $racoonCmd->SetSubType('other');
        $racoonCmd->setDisplay('generic_type','HEATING_OTHER');
        break;
      
      case 'info':
        $racoonCmd->setType($typeCmd);
        $racoonCmd->setSubType('string');
        $racoonCmd->setEventOnly(1);
        $racoonCmd->setDisplay('generic_type','HEATING_STATE');
        break;

     // default:
        
       // break;
    }
    $racoonCmd->save();
    log::add('racoon','debug','fin de la création de la commande')
    return true;
  } else {
    log::add('racoon','debug','Commande déjà crée');
  }
  return true;
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
    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class racoonCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */
   /**
     * Permet l'execution des commandes associées aux objets selon les types de commandes.
     *
     * @return boolean retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
    public function execute($_options = array()) {
      switch($this->getType()) {
        case 'info' :
          return $this->getConfiguration('value');
          break;
        case 'action' :
          $request = $this->getConfiguration('request');
        default:
          break;
      }
      $eqLogic = $this->getEqLogic();
      $logicalId = $this->getLogicalId();
      $zone = $eqLogic->getConfiguration('zone');
      if ($zone == '0') {
        $izone = 1;
        while ($izone <= 7) {
          racoon::racoonCall($izone,$request);
        }
      } else {
        racoon::racoonCall($zone,$request);
      }
      return true;
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
