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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>

<form class="form-horizontal">
<fieldset>
    <legend><i class="fa fa-cog"></i>  {{Paramètre du Spark Core}}</legend>

    <div class="row">    
          <div class="form-group">
            <label class="col-lg-4 control-label">{{Device ID du Spark Core}}</label>
            <div class="col-lg-4">
              <input id="deviceId"class="configKey form-control" data-l1key="deviceid" style="margin-top:5px" placeholder="ID Spark.io"/>
            </div>
          </div>
          <div class="form-group">
            <label class="col-lg-4 control-label">{{Token d'accès du Spark Core}}</label>
            <div class="col-lg-4">
              <input id="accessToken" class="configKey form-control" data-l1key="accessToken" style="margin-top:5px" placeholder="ex : 4025"/>
            </div>
          </div>
    </div>
    <div class="row">
        <div class="col-lg-12">        
            <legend><i class="fa fa-cog"></i>  {{Fonctionnalités annexes (voir https://github.com/projetBTSMaisonIntelligente)}}</legend>
            <div class="col-lg-offset-4">
                <button id="bt_creationEquipementAnnexe" type="button" class="btn btn-warning">{{Création des équipements annexes}}</button>
            </div>
        </div>
    </div>

<script>
    $('#bt_creationEquipementAnnexe').on('click',function() {
        creationEquipementAnnexe();
    });
    function creationEquipementAnnexe() {
         $.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // methode de transmission des données au fichier php
            url: "plugins/racoon/core/ajax/racoon.ajax.php", // url du fichier php
            data: {
            action: "creationEquipementAnnexe",
            },
            dataType: 'json',
            error: function (request, status, error) {
            handleAjaxError(request, status, error);
            },
            success: function (data) { // si l'appel a bien fonctionné
            if (data.state != 'ok') {
            $('#div_alert').showAlert({message: data.result, level: 'danger'});
            return;
            }
         }
        });
     }
     function racoon_postSaveConfiguration(){
            $.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // methode de transmission des données au fichier php
            url: "plugins/racoon/core/ajax/racoon.ajax.php", // url du fichier php
            data: {
            action: "creationSparkCore",
            },
            dataType: 'json',
            error: function (request, status, error) {
            handleAjaxError(request, status, error);
            },
            success: function (data) { // si l'appel a bien fonctionné
            if (data.state != 'ok') {
            $('#div_alert').showAlert({message: data.result, level: 'danger'});
            return;
            }
         }
        });
     }
    </script>
</fieldset>
</form>