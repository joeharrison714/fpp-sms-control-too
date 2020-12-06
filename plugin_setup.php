
<?
include_once "sms-common.php";

$pluginJson = convertAndGetSettings();
?>

<div id="global" class="settings">
<fieldset>
<legend>SMS Control Config</legend>

<script>
var uniqueId = 1;
function AddSMS() {
    var id = $("#midiEventTableBody > tr").length + 1;
    
    var html = "<tr class='fppTableRow";
    if (id % 2 != 0) {
        html += " oddRow'";
    }
    html += "'><td class='colNumber rowNumber'>" + id + ".<td><input type='text' size='20' maxlength='50' class='kwrd'><span style='display: none;' class='uniqueId'>" + uniqueId + "</span></td>";
    html += "<td><select class='smsstatus' id='smsstatus" + uniqueId + "'>";
    html += "<option value=''>(any)</option>";
    html += "<option value='idle'>idle</option>";
    html += "<option value='playing'>playing</option>";
    html += "</select></td>";
    html += "<td><select class='smsplaylist' id='smsplaylist" + uniqueId + "'>";
    html += "</select></td>";
    html += "<td><table class='fppTable' border=0 id='tableSMSCommand_" + uniqueId +"'>";
    html += "<tr><td>Command:</td><td><select class='smscommand' id='smscommand" + uniqueId + "' onChange='CommandSelectChanged(\"smscommand" + uniqueId + "\", \"tableSMSCommand_" + uniqueId + "\" , false, PrintArgsInputsForEditable);'><option value=''></option></select></td></tr>";
    html += "</table></td></tr>";
    
    $("#smsKeywordTableBody").append(html);
    LoadCommandList($('#smscommand' + uniqueId));

    newRow = $('#smsKeywordTableBody > tr').last();
    $('#smsKeywordTableBody > tr').removeClass('selectedEntry');
    DisableButtonClass('deleteEventButton');

    LoadPlaylists($('#smsplaylist' + uniqueId));

    uniqueId++;

    return newRow;
}

function LoadPlaylists(sel){
    $.ajaxSetup({
        async: false
    });

    sel.append($('<option>', {
        value: '',
        text: '(any)'
    }));
    
    $.getJSON('/api/playlists/playable', function (data) {
        $.each(data, function (index, value) {
            sel.append($('<option>', {
                value: value,
                text: value
            }));
        });
    });

    $.ajaxSetup({
        async: true
    });

}

function RemoveSMS() {
    if ($('#smsKeywordTableBody').find('.selectedEntry').length) {
        $('#smsKeywordTableBody').find('.selectedEntry').remove();
        RenumberEvents();
    }

    DisableButtonClass('deleteEventButton');
}


function RenumberEvents() {
    var id = 1;
    $('#smsKeywordTableBody > tr').each(function() {
        $(this).find('.rowNumber').html('' + id++ + '.');
        $(this).removeClass('oddRow');

        if (id % 2 != 0) {
            $(this).addClass('oddRow');
        }
    });
}

var smsConfig = <? echo json_encode($pluginJson, JSON_PRETTY_PRINT); ?>;
function SaveSMSConfig(config) {
    var data = JSON.stringify(config);
    //alert(data);
    $.ajax({
        type: "POST",
        url: 'fppjson.php?command=setPluginJSON&plugin=<?= $pluginName ?>',
        dataType: 'json',
        async: false,
        data: data,
        processData: false,
        contentType: 'application/json',
        success: function (data) {
        }
    });
    SetRestartFlag(2);
}

function SaveKeyword(row) {
    var keyword = $(row).find('.kwrd').val();
    var status = $(row).find('.smsstatus').val();
    var playlist = $(row).find('.smsplaylist').val();

    var id = $(row).find('.uniqueId').html();
    
    var json = {
        "keyword": keyword,
        "statusCondition": status,
        "playlistCondition": playlist
    };
    CommandToJSON('smscommand' + id, 'tableSMSCommand_' + id, json, true);
    return json;
}

function SaveSMS() {
    smsConfig = { "keywords": []};
    var i = 0;
    $("#smsKeywordTableBody > tr").each(function() {
        smsConfig["keywords"][i++] = SaveKeyword(this);
    });

    smsConfig["voipmsApiUsername"] = $("input[name=voipms_api_username]").val();
    smsConfig["voipmsApiPassword"] = $("input[name=voipms_api_password]").val();
    smsConfig["voipmsDid"] = $("input[name=voipms_did]").val();
    smsConfig["messageSuccess"] = $("input[name=message_success]").val();
    smsConfig["messageInvalid"] = $("input[name=message_invalid]").val();
    smsConfig["messageCondition"] = $("input[name=message_condition]").val();
    smsConfig["enabled"] = $("input[name=sms_enabled]").is(':checked');
    smsConfig["logLevel"] = $("select[name=log_level]").val();
    
    SaveSMSConfig(smsConfig);
}

$(document).ready(function() {

    $('#smsKeywordTableBody').sortable({
        update: function(event, ui) {
            RenumberEvents();
        },
        item: '> tr',
        scroll: true
    }).disableSelection();

    $('#smsKeywordTableBody').on('mousedown', 'tr', function(event,ui){
        $('#smsKeywordTableBody tr').removeClass('selectedEntry');
        $(this).addClass('selectedEntry');
        EnableButtonClass('deleteEventButton');
    });

});
</script>


<p>Press F1 for setup instructions</p>
<table cellspacing="5">
<tr>
	<th style="text-align: left">Enable SMS Control</th>
<td>
<input type="checkbox" name="sms_enabled">
</td>
</tr>


<tr>
	<th style="text-align: left">Voip.ms API Username</th>
<td>
    <input type='text' size='50' maxlength='50' name='voipms_api_username'>
</td>
</tr>

<tr>
	<th style="text-align: left">Voip.ms API Password</th>
<td>
    <input type='password' size='50' maxlength='50' name='voipms_api_password'>

</td>
</tr>

<tr>
	<th style="text-align: left">Voip.ms DID (Phone Number)<br /><small>(Format: 6105551234)</small></th>
<td>
<input type='text' size='50' maxlength='50' name='voipms_did'>
</td>
</tr>

<tr>
	<th style="text-align: left">Success Message</th>
<td>
<input type='text' size='80' maxlength='160' name='message_success'>
</td>
</tr>


<tr>
	<th style="text-align: left">Invalid Command message</th>
<td>
<input type='text' size='80' maxlength='160' name='message_invalid'>
</td>
</tr>

<tr>
	<th style="text-align: left">Unmet condition message</th>
<td>
<input type='text' size='80' maxlength='160' name='message_condition'>
</td>
</tr>

<tr>
	<th style="text-align: left">Log level</th>
<td>
<select name="log_level">
<option value='INFO'>INFO</option>
<option value='DEBUG'>DEBUG</option>
</select>
</td>
</tr>

</table>

<div>
<input type="button" value="Save" class="buttons genericButton" onclick="SaveSMS();">
        <input type="button" value="Add" class="buttons genericButton" onclick="AddSMS();">
        <input id="delButton" type="button" value="Delete" class="deleteEventButton disableButtons genericButton" onclick="RemoveSMS();">
</div>

<div class='fppTableWrapper'>
<div class='fppTableContents'>
<table class="fppTable" id="smsKeywordsTable"  width='100%'>
<thead><tr class="fppTableHeader"><th>#</th><th>SMS Message</th><th>FPPD Status Condition</th><th>Playlist Condition</th><th>Command</th></tr></thead>
<tbody id='smsKeywordTableBody'>
</tbody>
</table>
</div>
</div>

<script>
$.each(smsConfig["keywords"], function( key, val ) {
    var row = AddSMS();
    $(row).find('.kwrd').val(val["keyword"]);
    $(row).find('.smsstatus').val(val["statusCondition"]);
    $(row).find('.smsplaylist').val(val["playlistCondition"]);

    var id = parseInt($(row).find('.uniqueId').html());
    PopulateExistingCommand(val, 'smscommand' + id, 'tableSMSCommand_' + id, false, PrintArgsInputsForEditable);
});

$("input[name=voipms_api_username]").val(smsConfig["voipmsApiUsername"]);
$("input[name=voipms_api_password]").val(smsConfig["voipmsApiPassword"]);
$("input[name=voipms_did]").val(smsConfig["voipmsDid"]);
$("input[name=message_success]").val(smsConfig["messageSuccess"]);
$("input[name=message_invalid]").val(smsConfig["messageInvalid"]);
$("input[name=message_condition]").val(smsConfig["messageCondition"]);
$("input[name=sms_enabled]").prop('checked', smsConfig["enabled"]);
$("input[name=log_level]").val(smsConfig["logLevel"]);


</script>
</fieldset>
</div>
