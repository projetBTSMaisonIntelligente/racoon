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
    require_once dirname(__FILE__) . '/../../core/php/phpSpark.class.php';
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
    const NOMBRE_FILPILOTE = 7;
    const CHEMIN_FICHIERJSON = '/usr/share/nginx/www/jeedom/plugins/racoon/core/resources/objetEtCommande.json';

    /*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom*/
    /**
     * Méthode appelée par le système toutes les minutes
     *
     */
      public static function cron() {
          self::getStatut();
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
  public function recupererJSON($element)
  {
        if(file_get_contents(self::CHEMIN_FICHIERJSON) == TRUE) {
            log::add('racoon','debug','fichier JSON récupéré');
            $json_source = file_get_contents(self::CHEMIN_FICHIERJSON);
            if(json_decode($json_source,TRUE) == TRUE) {
              $data = json_decode($json_source,TRUE);
              switch ($element) {
                case 'objet':
                    $objet = $data['objet'];
                    return $objet;
                    break;
                case 'commande':
                    $commande = $data['commande'];
                    return $commande;
                    break;
              }
            } else {
              log::add('racoon','error','erreur dans le fichier JSON ' . json_last_error());
            }
        }
        else {
            log::add('racoon','error','fichier JSON introuvable');
        }
  }

  /**
     * Ajout de tous les objets du plugin appelé après la sauvegarde de la configuration
     *
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
    public static function creationObjet() {
      log::add('racoon','info','Création des objets');
        $objet = self::recupererJSON('objet');
        $nbObjet = count($objet);
        //Premiere boucle pour les différents types d'objets
        //le maximum est donc $nbObjet
        for ($iObjet=0; $iObjet <$nbObjet; $iObjet++) { 
          //Deuxieme boucle pour la quantité d'objet d'un type
          //le maximum est donc $objet[$iObjet]['nombre']

          $commande = $objet[$iObjet]['commande'];
          $nbCommande = count($commande);

           for ($iNb=0; $iNb < $objet[$iObjet]['nombre']; $iNb++) { 
              if($objet[$iObjet]['nombre'] == 1) {
                $racoon = self::byLogicalId($objet[$iObjet]['logicalId'], 'racoon');
              } else {
                $racoon = self::byLogicalId($objet[$iObjet]['logicalId'] . ($iNb + 1), 'racoon');
              }
              if(!is_object($racoon)) {
                $racoon = new racoon();
                $racoon->setEqType_name($objet[$iObjet]['eqType_name']);
                if($objet[$iObjet]['nombre'] == 1) {
                  $racoon->setName($objet[$iObjet]['name']);
                  $racoon->setLogicalId($objet[$iObjet]['logicalId']);
                } else {
                  $racoon->setName($objet[$iObjet]['name'] .  ' ' . ($iNb + 1));
                  $racoon->setLogicalId($objet[$iObjet]['logicalId'] . ($iNb + 1));
                }
                $racoon->setIsEnable($objet[$iObjet]['isEnable']);
                if($objet[$iObjet]['configuration'][0]['value'] == 'iNb'){
                  $racoon->setConfiguration($objet[$iObjet]['configuration'][0]['name'],($iNb +1));
                } else {
                  $racoon->setConfiguration($objet[$iObjet]['configuration'][0]['name'],$objet[$iObjet]['configuration'][0]['value']);
                }
                $racoon->save();
                log::add('racoon', 'info',print_r($racoon,true));
           } else {
                log::add('racoon','debug','objet ' . $racoon->getName() .'déjà crée');
           }
           self::creationCommande($racoon,$objet[$iObjet]['commande'],$nbCommande);

        }
      }
      return true;
  } 

  /**
     * Création des commandes en fonction des objets
     *
     * @param racoon $racoon
     *
     * @param array $listeCommande liste des commandes pour l'objet racoon
     *
     * @param int $tailleListeCommande taille de la liste des commandes pour l'objet racoon
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
    public static function creationCommande($objetRacoon,$listeCommande,$tailleListeCommande)
    {
        log::add('racoon','info','Création des commandes pour l\'objet' . $objetRacoon->getName());
        $commande = self::recupererJSON('commande');
        for ($nbCommande=0 ; $nbCommande < $tailleListeCommande ; $nbCommande++) { 
            $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($objetRacoon->getId(),$listeCommande[$nbCommande]['name']);
            if(!is_object($racoonCmd)) {
                $racoonCmd = new racoonCmd();
                $racoonCmd->setName($commande[$listeCommande[$nbCommande]['num']]['name']);
                $racoonCmd->setEqLogic_id($objetRacoon->getId());
                $racoonCmd->setEqType($commande[$listeCommande[$nbCommande]['num']]['eqType']);
                $racoonCmd->setLogicalId($commande[$listeCommande[$nbCommande]['num']]['logicalId']);
                $racoonCmd->setType($commande[$listeCommande[$nbCommande]['num']]['type']);
                $racoonCmd->setConfiguration($commande[$listeCommande[$nbCommande]['num']]['configuration'][0]['name'],$commande[$listeCommande[$nbCommande]['num']]['configuration'][0]['value']);
                $racoonCmd->SetSubType($commande[$listeCommande[$nbCommande]['num']]['subType']);
                $racoonCmd->setDisplay('generic_type',$commande[$listeCommande[$nbCommande]['num']]['display']);
                $racoonCmd->save();
                log::add('racoon', 'info',   print_r($racoonCmd,true));
            } else {
              log::add('racoon','debug','Commande ' . $racoonCmd->getName() . 'déjà crée');
            }
        }
        return true;
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
    public function configurationSparkCore($accessToken){
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
    public static function checkConfig(){
      $deviceId = config::byKey('deviceid','racoon',0);
      $accessToken = config::byKey('accessToken','racoon',0);
      $spark = new phpSpark();
      $spark->setDebug(true);
      $spark->setDebugType("TXT");
      $spark->setAccessToken($accessToken);
      if($spark->signalDevice($deviceId,1) == true) {
        $spark->debug_r($spark->getResult());
      } else {
        throw new Exception(__('La connexion avec le Spark Core n\'a pas été établi, vérifier les identifiants', __FILE__));
      }
      return TRUE;
    }

   /**
     * Récupération des statut des zones des radiateurs de la maison.
     *
     * @see https://github.com/harrisonhjones/phpParticle librairie phpParticle pour la classe phpSpark()
     *
     * @return boolean $bool retourne TRUE si ça marche / FALSE si ça ne marche pas + message log. 
     *
     */
   public function getStatut() {
      log::add('racoon','debug','appel de getstatut()');
      //Récupération des données enregistrées dans la page de configuration
      $deviceId = config::byKey('deviceid','racoon',0);
      $accessToken = config::byKey('accessToken','racoon',0);
      $spark = self::configurationSparkCore($accessToken);
      if ($spark->getVariable($deviceId,"etatfp") == true) {
        $obj = $spark->getResult();
        $result = $obj['result'];
      } else {
          log::add('racoon','error','erreur d\'appel de la variable etatfp,' . $spark->getError() . ' source ' . $spark->getErrorSource());
          return false;
      }
      log::add('racoon','debug','Retour ' . print_r($result,true));
      $izone = self::NOMBRE_MINI_FILPILOTE;
      while ($izone <=self::NOMBRE_FILPILOTE) {
        $sparkZone = $izone-1;
        $statut = $result[$sparkZone];
        $logical = 'zone' . $izone;
        log::add('racoon','debug','Retour statut zone ' . $izone . ' valeur ' . $statut);
        $racoon = self::byLogicalId($logical,'racoon');
        $racoonCmd = racoonCmd::byEqLogicIdAndLogicalId($racoon->getId(),'statut');
        $racoonCmd->setConfiguration('statut',$statut);
        $racoonCmd->save();
        $racoonCmd->event($statut);
        $izone++;
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
  public function racoonCall($zone,$request) {
        $params=$zone.$request;
        log::add('racoon','debug','Commande recu : ' . $request . '  vers la zone ' . $zone);

        //Récupération des données enregistrées dans la page de configuration
        $deviceId = config::byKey('deviceid', 'racoon',0);
        $accessToken = config::byKey('accessToken','racoon',0);
        //Création de l'objet phpSpark pour communiquer avec le Spark Core
        $spark = self::configurationSparkCore($accessToken);
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
        $racoonCmd->setConfiguration('statut', $request);
        $racoonCmd->save();
        $racoonCmd->event($request);
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
    const NOMBRE_FILPILOTE = 7;

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
      switch($this->getType()) {
        case 'info' :
          return $this->getConfiguration('statut');
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
        for ($iZone=self::NOMBRE_MINI_FILPILOTE; $iZone <= self::NOMBRE_FILPILOTE ; $iZone++) { 
          racoon::racoonCall($iZone,$request);
        }
      } else {
        racoon::racoonCall($zone,$request);
      }
      return true;
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
