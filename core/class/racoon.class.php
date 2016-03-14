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
    const CONFORT = "C";
    const ECONOMIQUE = "E";
    const HORSGEL = "H";
    const ARRET = "A";
    const NOMBRE_FILPILOTE = 7;


    /*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom*/
      public static function cron() {
          self::getStatus();
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
     * Récupération des status des zones des radiateurs de la maison.
     *
     *
     * @return boolean retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   public function getStatus() {
      log::add('racoon','debug','appel de getStatus()');
       //Récupération des données enregistrées dans la page de configuration
      $deviceId = config::byKey('deviceid','racoon',0);
      $accessToken = config::byKey('accessToken','racoon',0);
      $spark = new phpSpark();
      $spark->setDebug(false);
      $spark->setDebugType("TXT");
      $spark->setAccessToken($accessToken);
      $spark->setTimeout("5");
      if ($spark->getVariable($deviceId,"etatfp") == true) {
        #
      }
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
      if ( $zone >= 1 && $zone <=NOMBRE_FILPILOTE && ($request == ARRET || $request == HORSGEL || $request == ECONOMIQUE || $request == CONFORT)) {
        $params=$zone.$request;
        log::add('racoon','debug','Commande recu : ' . $request . 'vers la zone ' . $zone);
        //Récupération des données enregistrées dans la page de configuration
        $deviceId = config::byKey('deviceid', 'racoon',0);
        $accessToken = config::byKey('token','racoon',0);
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
      }
    }
}
   /**
     * Création des objets composant le plugin
     *
     * @return boolean retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
public static function ajouterZoneRadiateur() {
  //ajout Radiateur
  log::add('racoon','debug','Tentative de création des zones');
  $nbZone = 1;

    while ($nbZone <= 7) {
      $logical = 'zone' . $nbZone;
      $racoon = self::byLogicalId($logical, 'racoon');
      if (!is_object($racoon)) {
        log::add('racoon', 'info', 'Equipement n existe pas, création ' . $logical);
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
        self::ajouterCmd('Confort','confort','action',CONFORT);
        self::ajouterCmd('Eco','eco','action',ECONOMIQUE);
        self::ajouterCmd('Hors Gel','horsgel','action',HORSGEL);
        self::ajouterCmd('Arret','arret','action',ARRET);
        self::ajouterCmd('Statut','statut','info',0);
      }
  }
}
   /**
     * Création des commandes composant le plugin
     *
     * @return boolean retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
public static function ajouterCmd($nameCmd,$logicalIdCmd,$typeCmd,$request) {
  $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),$logicalIdCmd);
  if(!is_objet($racoonCmd)) {
    $racoonCmd = new racoonCmd();
    $racoonCmd->setName($nameCmd);
    $racoonCmd->setEqLogic_id($racoon->getId());
    $racoonCmd->setEqType('racoon');
    $racoonCmd->setLogicalId($logicalIdCmd);
    switch ($typeCmd) {
      case 'action':
        $racoonCmd->setType($typeCmd);
        $racoonCmd->setConfiguration('request',$request);
        $projetRemoraCmd->setDisplay('generic_type','HEATING_OTHER');
        break;
      
      case 'info':
        $racoonCmd->setType($typeCmd);
        $racoonCmd->setSubType('string');
        $racoonCmd->setEventOnly(1);
        $projetRemoraCmd->setDisplay('generic_type','HEATING_STATE');
        break;

     // default:
        
       // break;
    }
    $racoon->save();

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
        while ($izone <= NOMBRE_FILPILOTE) {
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
