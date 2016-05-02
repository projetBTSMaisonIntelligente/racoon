 <?php

if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}
sendVarToJS('eqType', 'racoon');
$eqLogics = eqLogic::byType('racoon');

?>
<div class="row row-overflow">
  <div class="col-lg-2 col-md-3 col-sm-4">
    <div class="bs-sidebar">
      <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
        <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
        <?php
        foreach ($eqLogics as $eqLogic) {
          echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
        }
        ?>
      </ul>
    </div>
</div>

<div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
    <legend>{{Mes racoon}}</legend>
    <div class="eqLogicThumbnailContainer">
      <?php
      $dir = dirname(__FILE__) . '/../../doc/images/';
      $files = scandir($dir);
      foreach ($eqLogics as $eqLogic) {
        $opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
        echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff ; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;' . $opacity . '" >';
        echo "<center>";
        $path = $eqLogic->getConfiguration('icone');
        echo '<img src="plugins/racoon/doc/images/' . $path . '.png" height="105" width="95" />';
        echo "</center>";
        echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
        echo '</div>';
      }
      ?>
    </div>
</div>

<div class="col-lg-10 col-md-9 col-sm-8 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
    <div class="row">
      
      <div class="col-lg-6">
        <form class="form-horizontal">
          <fieldset>
              <legend><i class="fa fa-arrow-circle-left eqLogicAction cursor" data-action="returnToThumbnailDisplay"></i>  {{Général}}
                <i class='fa fa-cogs eqLogicAction pull-right cursor expertModeVisible' data-action='configure'></i>
              </legend>
              <div class="form-group">
                <label class="col-md-2 control-label">{{Nom du racoon}}</label>
                <div class="col-md-3">
                  <input id="idEqLogic" type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                  <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement racoon}}"/>
                </div>
              </div>

                  <div class="form-group">
                    <label class="col-md-2 control-label" >{{Objet parent}}</label>

                    <div class="col-md-3">
                      <select class="form-control eqLogicAttr" data-l1key="object_id">
                        <option value="">{{Aucun}}</option>
                        <?php
                        foreach (object::all() as $object) {
                          echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                        }
                        ?>
                     </select>
                  </div>
                </div>
              
        <div class="form-group">
                      <label class="col-md-2 control-label">{{Catégorie}}</label>
                      <div class="col-md-8">
                        <?php
                        foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                          echo '<label class="checkbox-inline">';
                          echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                          echo '</label>';
                        }
                        ?>
                      </div>
        </div>

        <div class="form-group">
                      <label class="col-sm-2 control-label" ></label>
                      <div class="col-sm-9">
                        <input name="activer" type="checkbox" class="eqLogicAttr bootstrapSwitch" data-label-text="{{Activer}}" data-l1key="isEnable" checked/>
                        <input type="checkbox" class="eqLogicAttr bootstrapSwitch" data-label-text="{{Visible}}" data-l1key="isVisible" checked/>
                      </div>
        </div>
      </fieldset>
  </form>
  </div>
  <!--
  <div class="col-lg-6">
    <legend>{{Paramètres}}</legend>
    <form class="form-horizontal">
        <div class="input-group" id="variableSparkCore">
                  <input id="nomVariableSparkCore" type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="variable"  placeholder="{{Nom de la variable Spark Core à traiter" />
                  <span class="input-group-btn">
                            <a class="btn btn-default" id="bt_searchInfoCmd"><i class="fa fa-list-alt"></i></a>
                  </span>
        </div>
        <div class="input-group" id ="fonctionSparkCore">
                  <input id="nomFonctionSparkCore" type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="fonction" placeholder="{{Nom de la fonction Spark Core à utiliser}}"/>
                  <span class="input-group-btn">
                            <a class="btn btn-default" id="bt_searchActionCmd"><i class="fa fa-list-alt"></i></a>
                  </span>
        </div>
    </form>
  </div>
  -->
</div>

<div class="row">
  <div class="col-lg-12">
      <legend>{{Informations et Commandes}}</legend>
      <table id="table_cmd" class="table table-bordered table-condensed">
        <thead>
            <tr>
              <th class="col-md-1">#</th>
              <th class="col-md-2">{{Nom}}</th>
              <th class="col-md-3">{{Valeur}}</th>
              <th class="col-md-3">{{Paramètres}}</th>
              <th class="col-md-2">{{Utiliser pour}}</th>
              <th class="col-md-2">{{Activer récupération}}</th>
              <th class="col-md-2">{{Option}}</th>
            </tr>
          </thead>
          <tbody>

          </tbody>
        </table>
      </div>
</div>

<div class="row">
    <div class="col-lg-12">
      <form class="form-horizontal">
          <fieldset>
            <div class="form-actions">
              <a class="btn btn-danger eqLogicAction" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
              <a class="btn btn-success eqLogicAction" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
            </div>
          </fieldset>
      </form>
    </div>
  </div>

</div>
<?php include_file('desktop', 'racoon', 'js', 'racoon'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>