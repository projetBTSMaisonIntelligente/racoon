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

$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

/*
 * Fonction pour l'ajout de commande, appellé automatiquement par plugin.template
 */


    
function addCmdToTable(_cmd) {
    if(!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if(!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
        tr += '<td>';
        tr += '<span class="cmdAttr" data-l1key="id"></span>';
        tr += '</td>';
        tr += '<td>';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="name">';
        tr += '</td>';
       tr +='<td>'; 
        if (init(_cmd.type) == 'info') {
            tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="value" style="height : 33px;" placeholder="{{Valeur}}" readonly="true"></textarea>';
            tr += '<input class="cmdAttr form-control tooltips input-sm" data-l1key="unite" style="width : 100px;" placeholder="Unité" title="Unité">';
        }
        tr += '</td>';
        tr += '<td>';
        if(init(_cmd.type) == 'action') {
                if(init(_cmd.subType) == 'other') {
                    tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="request" style="height : 15px placeholder="{{Valeur}}" readonly=true></input>';
                }
                if(init(_cmd.subType) == 'slider') {
                     tr += '<div class="input-group input-group-sm">'
                     tr += '<span class="input-group-addon">{{Capteur}}</span>'
                     tr += '<input class="cmdAttr form-control" id="nameTemperature" data-l1key="configuration" data-l2key="temperature">'
                     tr += '<span class="input-group-btn">'
                     tr += '<a class="btn btn-default" id="bt_searchVariableTemperature"><i class="fa fa-list-alt"></i></a>'
                     tr += '</span>'
                     tr += '</div>'
                    //tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="kp" style="height : 33px" placeholder="{{Coefficient }}" ></input>';
                    //tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="ki" style="height : 33px" placeholder="{{Coefficient}}"></input>';
                    //tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="kd" style="height : 33px" placeholder="{{Coefficient}}"></input>';
                    tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" style="height : 33px" placeholder="{{Température minimum}}"></input>';
                    tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" style="height : 33px" placeholder="{{Température maximum}}"></input>';
                }
        }
        tr += '</td>';
        tr += '<td>';
        if(isset(_cmd.configuration.utiliserPar)) {
            tr += '<select style="width : 220px;" class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="utiliserPar">';
            tr += '<option value="">Aucun</option>';
            tr += '<option value="filPilote">Fil Pilote</option>';
            tr += '<option value="teleinfo">Téléinfo</option>';
            tr += '<option value="temperature">Température</option>';
            tr += '<option value="regulation">Régulation</option>';
            tr += '</select>';
    }
        tr += '</td>';
        tr += '<td>';
        if(isset(_cmd.configuration.aRecuperer)) {
            tr += '<span><input type="checkbox" data-size="mini" data-label-text="{{Récupérer}}" class="cmdAttr bootstrapSwitch" data-l1key="configuration"  data-l2key="aRecuperer" /></span>';
    }
        tr += '</td>';
        tr += '<td>';
        tr += '<span><input type="checkbox" data-size="mini" data-label-text="{{Afficher}}" class="cmdAttr bootstrapSwitch" data-l1key="isVisible" /></span>';
        if(init(_cmd.type) == 'info') {
            tr += '<span><input type="checkbox" data-size="mini" data-label-text="{{Historiser}}" class="cmdAttr bootstrapSwitch" data-l1key="isHistorized" /></span>';
        }
        if(is_numeric(_cmd.id)) {
            tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
            tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
        }
     tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
        tr += '</td>';
        tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    
    $('#bt_searchVariableTemperature').on('click', function() {
    var el = $(this);
       jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function(result) {
        $('#nameTemperature').atCaret('insert', result.human);
      });
    });
}

