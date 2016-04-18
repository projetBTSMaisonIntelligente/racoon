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
  <div class="form-group">    
	      <div class="form-group">
	        <label class="col-lg-4 control-label">{{Device ID du Spark Core}}</label>
	        <div class="col-lg-4">
	          <input class="configKey form-control" data-l1key="deviceid" style="margin-top:5px" placeholder="ID Spark.io"/>
	        </div>
	      </div>
	      <div class="form-group">
	        <label class="col-lg-4 control-label">{{Token d'accès du Spark Core}}</label>
	        <div class="col-lg-4">
	          <input class="configKey form-control" data-l1key="accessToken" style="margin-top:5px" placeholder="ex : 4025"/>
	        </div>
	      </div>
    </div>
    <legend><i class="fa fa-cog"></i>  {{Fonctionnalités}}</legend>
    <div class="form-group">
	      <div class="form-group">
	             <label class="col-lg-4 control-label">{{Téléinfo}}</label>
	             <div class="col-lg-4">
	                 <input type="checkbox" class="configKey  bootstrapSwitch" data-l1key="teleinfo" />
	             </div>
	      </div>
	      <div class="form-group">
	             <label class="col-lg-4 control-label">{{Fil pilote}}</label>
	             <div class="col-lg-4">
	                 <input type="checkbox" class="configKey  bootstrapSwitch" data-l1key="filPilote" />
	             </div>
	      </div>
	       <div class="form-group">
	             <label class="col-lg-4 control-label">{{Régulation}}</label>
	             <div class="col-lg-4">
	                 <input id="regulation_activer" type="checkbox" class="configKey  bootstrapSwitch" data-l1key="regulation" />
	             </div>

	        	<div id="regulation_kp" class="form-group">
	        		<label class="col-lg-4 control-label">{{Kp : action proportionnelle}}</label>
	        		<div class="col-lg-4">
	          			<input class="configKey form-control" data-l1key="Kp" style="margin-top:5px" placeholder="ex : 4025"/>
	        		</div>
	      		</div>
	      		<div id="regulation_ki" class="form-group">
	        		<label class="col-lg-4 control-label">{{Ki : action intégrale}}</label>
	        		<div class="col-lg-4">
	          			<input class="configKey form-control" data-l1key="Ki" style="margin-top:5px" placeholder="ex : 4025"/>
	        		</div>
	      		</div>
	      		<div id="regulation_kd" class="form-group">
	        		<label class="col-lg-4 control-label">{{Kd : action dérivée}}</label>
	        		<div class="col-lg-4">
	          			<input class="configKey form-control" data-l1key="Kd" style="margin-top:5px" placeholder="ex : 4025"/>
	        		</div>
	      		</div>
	      		<div id="regulation_tempMin" class="form-group">
	        		<label class="col-lg-4 control-label">{{Température minimum de la régulation}}</label>
	        		<div class="col-lg-4">
	          			<input class="configKey form-control" data-l1key="limiteTempMin" style="margin-top:5px" placeholder="ex : 16"/>
	        		</div>
	      		</div>
	      		<div id="regulation_tempMax" class="form-group">
	        		<label class="col-lg-4 control-label">{{Température maximum de la régulation}}</label>
	        		<div class="col-lg-4">
	          			<input class="configKey form-control" data-l1key="limiteTempMax" style="margin-top:5px" placeholder="ex : 22"/>
	        		</div>
	      		</div>
	      </div>
   </div>

      <script>
      	$(document).ready(function(){
      		if($('#regulation_activer').prop('checked',true)){
      			$('#regulation_kp').show();
      			$('#regulation_ki').show();
      			$('#regulation_kd').show();
      			$('#regulation_tempMin').show();
      			$('#regulation_tempMax').show();
      		}
      		else {
      			$('#regulation_kp').hide();
      			$('#regulation_ki').hide();
      			$('#regulation_kd').hide();
      			$('#regulation_tempMin').hide();
      			$('#regulation_tempMax').hide();
      		}

		});

        function racoon_postSaveConfiguration(){
            $.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // methode de transmission des données au fichier php
            url: "plugins/racoon/core/ajax/racoon.ajax.php", // url du fichier php
            data: {
            action: "postSave",
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
      		}
    </div>
</fieldset>
</form>