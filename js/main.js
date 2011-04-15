var olmap;
var kmllayer;
var nick;
var selectedFeature;
var selectCtrl;
var color = ["#ff0000","#ff4c00","#ff8600","#ffc000","#ffee00","#ffff00","#cbff00","#97ff00","#5fff00","#00ff00"];
var users = [];
var isEnd;

var In = {};
var Out = {};

In.chat = function (data) {
    if (typeof(data['message']) == 'undefined')
        return false;
    var chat = $("#chat tbody");
    var time = '';
    var mclass = 'msg';
    var author = '';
    if (typeof(data['date']) != 'undefined') {
        var d = new Date();
        d.setTime(parseInt(data['date'])*1000);
        var s = d.getSeconds();
        var m = d.getMinutes();
        s = (s < 10) ? '0' + s : s;
        m = (m < 10) ? '0' + m : m;
        var time = d.getHours().toString() + ':' + m.toString() + ':' + s.toString()
    }
    if (typeof(data['class']) != 'undefined')
        mclass = data['class'];
    if (typeof(data['author']) != 'undefined')
        author = data['author'];
    chat.append("<tr><td class='nick'>&lt;" + author + "&gt;</td><td class='" + mclass + "'>" + data['message'] + "</td><td>" + time + "</td></tr>");
};

In.javascript = function (data) {
    eval(data.code);
};

In.piece_comment = function (data) {
    if (typeof(data['message']) == 'undefined')
        return false;
    var mclass = 'msg';
    var author = '';
    if (typeof(data['class']) != 'undefined')
        mclass = data['class'];
    if (typeof(data['author']) != 'undefined')
        author = data['author'];
}

In.piece_state = function (data) {
    var pieces = kmllayer.getFeaturesByAttribute('name', data['piece_id']);
    if (pieces.length > 0)
    {
        pieces[0].description = data['state'];
        pieces[0].style.fillColor = color(parseInt(data['state']));
    }
}

In.refresh_pie_data = function (data) {
    if (typeof(data['url']) == 'string')
        kmllayer.addOptions({protocol: new OpenLayers.Protocol.HTTP({url: data['url'], format: new OpenLayers.Format.KML({extractStyles: true, extractAttributes: true, maxDepth: 0})})});
    kmllayer.refresh(true);
    kmllayer.redraw(true);
};

In.reload = function (data) {
    if (typeof(data['reason']) == 'string')
        ChatMsg(data['reason'], '', 'info');
    if (typeof(data['delay']) == 'undefined')
        Reload();
    else if (data['delay'] == 'random')
        setTimeout("Reload();", Math.round(Math.random() * 10000));
    else
        setTimeout("Reload();", parseInt(data['delay']));
};

In.userlist = function (data) {
    if (typeof(data) == 'object' && data != null) users = data;
    else data = users;
    var nicks = [];
    newhtml = "<table><tr><td id='lname'>" + ldata[18] + "</td><td id='lpieces'>" + ldata[19] + "</td><td id='lclaims'>" + ldata[20] + "</td></tr>";
    for (var u = 0; u < users.length; u++)
    {
        nicks.push(users[u]['user_nick']);
        var reserved = "";
        var claims = "";
        for (var i = 0; i < users[u]['reserved'].length; i++)
        {
            reserved += ("<span class='num'>" + users[u]['reserved'][i] + "</span> ");
        }
        for (claim_id in users[u]['claims'])
        {
            if (nick == users[u]['user_nick'])
                claims += ("<span class='claim ui-state-default'><span class='num'>" + claim_id + "</span>&nbsp;[" + users[u]['claims'][claim_id] + "&nbsp;<div title='Снять заявку' class='close'></div>]</span> ");
            else
                claims += ("<span class='claim ui-state-default'><span class='num'>" + claim_id + "</span>&nbsp;[<div title='За' class='up'></div>&nbsp;" + users[u]['claims'][claim_id] + "&nbsp;<div title='Против' class='down'></div>]</span><br />");
        }
        newhtml += ("<tr><td class='nick'>" + (users[u]['online'] ? "<img src='img/onl.png'>&nbsp;" : "") + "<span class='nickname'>" + users[u]['user_nick'] + "</span></td><td class='msg'>" + reserved + "</td><td>" + claims + "</td></tr>");
    }
    newhtml += "</table>";
    $('#userlist').html(newhtml);
    $('#pac_text').autocomplete('option', 'source', nicks);
    $('.nickname').click( function() { $('#pac_text').val($('#pac_text').val() + $(this).text() + ': '); $("#pac_text").focus(); } );
    $('.up').click( function() {
        Vote($(this).parent().find('span.num').text(), 1);
        $(this).remove(); } );
    $('.down').click( function() {
        Vote($(this).parent().find('span.num').text(), -1);
        $(this).remove(); } );
    $('.close').click( function() {
        CloseClaim($(this).parent().find('span.num').text());
        $(this).remove(); } );
    $('.num').click( function() {
    if (selectedFeature != null) selectCtrl.unselect(selectedFeature); SelectPiece($(this).text()); } );
};

In.user_add = function (data) {
    users.push(data);
    In.userlist();
}

In.user_remove = function (data) {
    for (var i = 0; i < users.length; i++) {
        if (users[i]['user_nick'] == data['user_nick']) {
            users.splice(i, 1);
            break;
        }
    }
    In.userlist();
}

In.user_update = function (data) {
    for (var i = 0; i < users.length; i++) {
        if (users[i]['user_nick'] == data['current_nick']) {
            users[i] = data['update'];
            break;
        }
    }
    In.userlist();
}

Out.claim = function (piece_id) {
    if (typeof(piece_id) != 'string' && typeof(piece_id) != 'number') return false;
    return ['claim', {piece_id: piece_id.toString()}];
};

Out.claim_remove = function (claim_id) {
    if (typeof(claim_id) != 'string' && typeof(claim_id) != 'number') return false;
    return ['claim_remove', {claim_id: claim_id.toString()}];
};

Out.color_set = function (color) {
    if (typeof(color) != 'string') return false;
    return ['color_set', {color: color.toString()}];
};

Out.msg = function (msg, pub, target) {
    if (typeof(msg) != 'string') return false;
	if (typeof(pub) != 'boolean') pub = true;
	if (typeof(target) != 'string') target = '';
    var cmd = ['msg', {type: (pub ? 'public' : 'private'), message: msg }];
    if (target != '')
        cmd.target_nick = target;
    return cmd;
};

Out.piece_comment = function (piece_id, comment) {
    if (typeof(piece_id) != 'string' && typeof(piece_id) != 'number') return false;
    if (typeof(comment) != 'string') return false;
    return ['piece_comment', {piece_id: piece_id.toString(), comment: comment}];
};

Out.piece_state = function (piece_id, percent) {
    if (typeof(piece_id) != 'string' && typeof(piece_id) != 'number') return false;
    if (typeof(percent) != 'string' && typeof(percent) != 'number') return false;
    return ['piece_state', {piece_id: piece_id.toString(), percent: percent.toString()}];
};

Out.piece_reserve = function (piece_id) {
    if (typeof(piece_id) != 'string' && typeof(piece_id) != 'number') return false;
    return ['piece_reserve', {piece_id: piece_id.toString()}];
};

Out.piece_free = function (piece_id) {
    if (typeof(piece_id) != 'string' && typeof(piece_id) != 'number') return false;
    return ['piece_free', {piece_id: piece_id.toString()}];
};

Out.vote_claim = function (claim_id, vote) {
    if (typeof(claim_id) != 'string' && typeof(piece_id) != 'number') return false;
    if (typeof(vote) != 'string' && typeof(vote) != 'number') return false;
    return ['vote_claim', {claim_id: claim_id.toString(), vote: vote.toString()}];
};

function Dispatch(data) {
    if (typeof In[data[0]] == 'function')
        In[data[0]](data[1]);
}

function Reload() {
    window.location.reload(true);
}

function LoadSettings() {
    if (localStorage.style) {
        if ($('#sstyle').val() != localStorage.style) {
            $('#sstyle').val(localStorage.style);
            SetStyle();
        }
    }
    else {
        localStorage.style = $('#sstyle').val();
        if ($('#sstyle').val() != "smoothness") {
            SetStyle();
        }
    }
    if (localStorage.lang) {
        $('#slang').val(localStorage.lang);
        LoadLanguage();
    }
    else {
        $('#slang').val(navigator.language ? navigator.language : (navigator.browserLanguage ? navigator.browserLanguage : "en"));
        localStorage.lang = $('#slang').val();
        LoadLanguage();
    }
}

function ApplySettings() {
    if ($('#sstyle').val() != localStorage.style) {
        localStorage.style = $('#sstyle').val();
        SetStyle();
    }
    if ($('#slang').val() != localStorage.lang) {
        localStorage.lang = $('#slang').val();
        LoadLanguage();
    }
}

function LoadLanguage() {
    $.get("/js/lang/" + $('#slang').val() + ".js", {}, function() {
        $('#dchat').dialog("option", "title", ldata[4]);
        $('#duserlist').dialog("option", "title", ldata[5]);
        $('#dprop').dialog("option", "title", ldata[6]);
        $('#dsettings').dialog("option", "title", ldata[7]);
        $('#dnick').dialog("option", "title", ldata[8]);
        $('#dstatus').dialog("option", "title", ldata[9]);
        $('#bremote').button("option", "label", ldata[10]);
        $('#bsettings').button("option", "label", ldata[7]);
        $('#bpie').button("option", "label", ldata[11]);
        $('#bowner').button("option", "label", ldata[12]);
        $('#bcomment').button("option", "label", ldata[13]);
        $('#lnick').text(ldata[14]);
        $('#lowner').text(ldata[15]);
        $('#lreadiness').text(ldata[16]);
        $('#lcomments').text(ldata[17]);
        $('#lname').text(ldata[18]);
        $('#lpieces').text(ldata[19]);
        $('#lclaims').text(ldata[20]);
        $('#ltheme').text(ldata[21]);
        $('#llang').text(ldata[22]);
        $('#comment_text').attr('placeholder', ldata[23]);
        $('#ltake').text(ldata[24]);
        $('#lclaim').text(ldata[25]);
        $('#lrefuse').text(ldata[26]);
    });
}

function ChatMsg(text, name, type) {
    $('#chat tbody').append("<tr><td>" + name + "</td><td class='" + type + "'>" + text + "</td><td> </td></tr>");
    ScrollDown();
}

function PromptNick() {
    $('#newnick').removeAttr('disabled');
    $('#newnick').val(nick);
    $('#dnick').dialog("open");
    $('#newnick').get(0).selectionStart = 0;
    $('#newnick').get(0).selectionEnd = nick.length;
}

function PromptColor() {
    $('#dcolor').dialog("open");
}

OpenLayers.Layer.Vector.prototype.getFeaturesByAttribute = function getFeaturesByAttribute(attrName, attrValue, strict) {
    var i,
        feature,
        doStrictComparison = !!(typeof strict !== 'undefined'),
        useAttrValue = !!(typeof attrValue !== 'undefined'),
        len = this.features.length,
        foundFeatures = [];
    for( i = 0; i < len; i++ ) {
        feature = this.features[i];
        if(feature && feature.attributes && typeof feature.attributes[attrName] !== 'undefined'){
            if (useAttrValue) {
                if (doStrictComparison) {
                    if ( feature.attributes[attrName] === attrValue) {
                        foundFeatures.push(feature);
                    }
                } else {
                    if ( feature.attributes[attrName] == attrValue) {
                        foundFeatures.push(feature);
                    }
                }
            } else {
                foundFeatures.push(feature);
            }
        }
    }
    return foundFeatures;
};

function SelectPiece(num) {
    pieces = kmllayer.getFeaturesByAttribute('name', num);
    if (pieces.length > 0)
    {
        selectCtrl.select(pieces[0]);
    }
}

function OpenViaRemote() {
    if (selectedFeature != null)
    {
        var from = new OpenLayers.Projection("EPSG:900913");
        var to = new OpenLayers.Projection("EPSG:4326");
        var bounds = selectedFeature.geometry.getBounds().toArray()
        var p1 = (new OpenLayers.LonLat(bounds[0], bounds[1])).transform(from, to);
        var p2 = (new OpenLayers.LonLat(bounds[2], bounds[3])).transform(from, to);
        $.get("http://127.0.0.1:8111/load_and_zoom", {left: p1.lon, right: p2.lon, top: p2.lat, bottom: p1.lat});
    }
}

function SetNick() {
    var newnick = $("#newnick").val().substr(0, 64);
    if (newnick != "" && newnick != nick)
    {
        $("#newnick").attr('disabled', 'disabled');
        $.post("ajax.php", { act: "setnick", nick: nick, newnick: newnick }, function (result)
            {
            localStorage.nick = nick;
            $('#dnick').dialog("close");
            });
    }
    else
        $('#dnick').dialog("close");
}

function Debug(data) {
    $("#debug").text(data.toString());
}

function Enter() {
    if (localStorage.nick)
        nick = localStorage.nick;
    $.post("ajax.php", { act: "enter", nick: nick }, function (result)
        {
        localStorage.nick = nick;
        $("#pac_form").submit(Send);
        $("#pac_text").focus();
        Load();
        });
}

function Vote(claim_id, vote) {
    PieHub.push( Out.vote_claim(claim_id, vote) );
}

function CloseClaim(id) {
    $.post("ajax.php", { act: "closeclaim", nick: nick, id: id });
}

function Send() {
    PieHub.push(Out.msg($("#pac_text").val()));
    $("#pac_text").val("");
    $("#pac_text").focus();
    return false;
}

function SetStatus(e) {
    PieHub.push( Out.piece_state(selectedFeature.attributes.name, $('#sstatus').slider('value')) );
}

function ScrollDown() {
    if (isEnd)
        $("#chat").scrollTop($("#chat").attr("scrollHeight") - $("#chat").height());
}

function Load() {
    if(!load_in_process)
    {
        load_in_process = true;
        isEnd = ($("#chat").scrollTop() == $("#chat").attr("scrollHeight") - $("#chat").height());
        $.post("load.php", { last: last_message_id, nick: nick, rand: (new Date()).getTime() }, function (result)
        {
            ScrollDown();
            load_in_process = false;
            Load();
        });
    }
}

function TakePiece() {
    PieHub.push( Out.piece_reserve(selectedFeature.attributes.name) );
}

function ClaimPiece() {
    PieHub.push( Out.claim(selectedFeature.attributes.name) );
}

function RefusePiece() {
    PieHub.push( Out.piece_free(selectedFeature.attributes.name) );
}

function GetComments() {
}

function PostComment() {
    PieHub.push(Out.piece_comment(selectedFeature.attributes.name, $("#comment_text").val()));
    $("#comment_text").val("");
    return false;
}

function onSelectPiece(e) {
    selectedFeature = e;
    e.style.fillOpacity = "0.8";
    e.style.strokeWidth = "3";
    kmllayer.redraw(true);
    $("#comments").html("<div class='loading'></div>");
    $('#bowner').button("enable");
    $('#bowner').click( function() { $(e.attributes.owner ? (e.attributes.owner == nick ? '#drefuse' : '#dclaim') : '#dtake').dialog('open'); });
    $('#bowner').button("option", "label", e.attributes.owner ? e.attributes.owner : "Нет");
    $('#bowner').button("option", "icons", { primary: (e.attributes.owner ? (e.attributes.owner == nick ? 'ui-icon-closethick' : 'ui-icon-circle-check') : 'ui-icon-flag')});
    $("#dprop").dialog("option", "title", "Свойства: " + e.attributes.name);
    $("#dprop").dialog("open");
    $('#bstatus').button("enable");
    $('#bremote').button("enable");
    $('#bcomment').button("enable");
    $("#status").text(e.attributes.description);
    GetComments();
}

function onUnselectPiece(e) {
    selectedFeature = null;
    e.style.fillOpacity = "0.5";
    e.style.strokeWidth = "1";
    kmllayer.redraw(true);
    $('#bowner').button("option", "label", "Нет");
    $('#bowner').button("option", "icons", {primary: 'ui-icon-flag'});
    $('#bowner').unbind('click');
    $('#status').text("0");
    $('#dprop').dialog("option", "title", "Свойства");
    $('#comments').html("");
    $('#bowner').button("disable");
    $('#bstatus').button("disable");
    $('#bremote').button("disable");
    $('#bcomment').button("disable");
}

function SetStyle() {
    $('head>link.ui-theme').remove();
    var link = $('<link href="/css/' + $('#sstyle').val() + '/jquery-ui-1.8.11.custom.css" type="text/css" media="screen, projection" rel="Stylesheet" class="ui-theme" />');
    var linkpatch = $('<link rel="stylesheet" href="/css/jquery-theme-patch.css" class="ui-theme" type="text/css" media="screen, projection" />');
    $('head').append(link);
    $('head').append(linkpatch);
}

$(document).ready(function () {
    // Инициализация клиента хаба
    PieHub.init({
        pieid: 5,
        hub_url: 'http://mapcraft.nanodesu.ru:8080/hub',   // CORS!
        poll_callback: Dispatch
    });
    // Запуск поллинга
    PieHub.poll();

    var options = {controls: [new OpenLayers.Control.Navigation(), new OpenLayers.Control.ScaleLine(), new OpenLayers.Control.Permalink(), new OpenLayers.Control.Attribution()], projection: new OpenLayers.Projection("EPSG:900913"), displayProjection: new OpenLayers.Projection("EPSG:4326"), units: "m", numZoomLevels: 18, maxResolution: 156543.0339, maxExtent: new OpenLayers.Bounds(-20037508, -20037508, 20037508, 20037508.34) };
    olmap = new OpenLayers.Map(document.getElementById('olmap'), options);
    var mapnik = new OpenLayers.Layer.OSM();
    kmllayer = new OpenLayers.Layer.Vector("Pie", { strategies: [new OpenLayers.Strategy.Fixed()], protocol: new OpenLayers.Protocol.HTTP({url: "pie.kml", format: new OpenLayers.Format.KML({extractStyles: true, extractAttributes: true, maxDepth: 0})}), projection: "EPSG:4326" });
    olmap.addLayers([mapnik, kmllayer]);

    selectCtrl = new OpenLayers.Control.SelectFeature(kmllayer, {clickout: true, onSelect: onSelectPiece, onUnselect: onUnselectPiece });
    selectCtrl.handlers.feature.stopDown = false; 

    olmap.addControl(selectCtrl);
    selectCtrl.activate();

	if (!olmap.getCenter()) {olmap.zoomToMaxExtent()}

    var ww = $(window).width();
    var wh = $(window).height();
    $('#dchat').dialog({
        autoOpen: true,
        width: 0.4*ww,
        height: 200+0.05*wh,
        minHeight: 200,
        minWidth: 300,
        resizable: true,
        closeOnEscape: false,
        position: ['left', 'bottom']
    });
    $('#duserlist').dialog({
        autoOpen: true,
        width: 150+0.05*ww,
        height: 0.25*wh,
        minHeight: 200,
        minWidth: 150,
        resizable: true,
        position: ['right', 'bottom']
    });
    $('#dnick').dialog({
        autoOpen: false,
        modal: true,
        width: 300,
        resizable: false,
        draggable: false,
        position: 'center',
        buttons: { "OK": function() { SetNick(); }, "Cancel": function() { $(this).dialog("close"); } }
    });
    $('#dstatus').dialog({
        autoOpen: false,
        modal: true,
        width: 410,
        resizable: false,
        draggable: false,
        position: 'center',
        buttons: { "OK": function() { SetStatus(); }, "Cancel": function() { $(this).dialog("close"); } }
    });
    $('#dtake').dialog({
        autoOpen: false,
        modal: true,
        width: 250,
        resizable: false,
        draggable: false,
        position: 'center',
        buttons: { "Yes": function() { TakePiece(); }, "No": function() { $(this).dialog("close"); } }
    });
    $('#dclaim').dialog({
        autoOpen: false,
        modal: true,
        width: 250,
        resizable: false,
        draggable: false,
        position: 'center',
        buttons: { "Yes": function() { ClaimPiece(); }, "No": function() { $(this).dialog("close"); } }
    });
    $('#drefuse').dialog({
        autoOpen: false,
        modal: true,
        width: 250,
        resizable: false,
        draggable: false,
        position: 'center',
        buttons: { "Yes": function() { RefusePiece(); }, "No": function() { $(this).dialog("close"); } }
    });
    $('#dprop').dialog({
        autoOpen: true,
        width: 150+0.05*ww,
        height: 0.7*wh,
        minWidth: 190,
        minHeight: 280,
        resizable: true,
        position: ['right', 'top']
    });
    $('#dsettings').dialog({
        autoOpen: false,
        modal: true,
        width: 0.5*ww,
        height: 0.5*wh,
        minWidth: 190,
        minHeight: 280,
        resizable: true,
        position: 'center',
        buttons: { "OK": function() {$('#dsettings').dialog("close"); ApplySettings(); }, "Cancel": function() { $(this).dialog("close");} }
    });
    $('#chat').height($('#dchat').height() - 45);
    $('#chat').width($('#dchat').width() - 30);
    $('#comments').width($('#dprop').width() - 15);
    $('#comments').height($('#dprop').height() - 215);
    $('#bcomment').button({ disabled: true });
    $('#bcomment').click(PostComment);
    $('#bremote').button({ disabled: true, icons: { primary: 'ui-icon-signal'}});
    $('#bremote').click(OpenViaRemote);
    $('#bstatus').button({disabled: true});
    $('#bstatus').click(function() { $('#sstatus').slider('value', $('#status').text()); $('#vcolor').css({ color: color[$('#sstatus').slider('value')] }); $('#newstatus').text($('#sstatus').slider('value')); $('#dstatus').dialog('open'); });
    $('#pac_nick').button({ icons: { primary: 'ui-icon-person'} });
    $('#pac_nick').click(PromptNick);
    $('#pac_color').button();
    $('#pac_color').click(PromptColor);
    $('#dchat').dialog( { resize: function(event, ui) { $('#chat').height($(this).height() - 45); $('#chat').width($(this).width() - 30); } } );
    $('#dprop').dialog( { resize: function(event, ui)
        {
        $('#comments').width($('#dprop').width() - 15);
        $('#comments').height($('#dprop').height() - 215);
        },
    beforeClose: function(event, ui)
        {
        if (selectedFeature != null)
            selectCtrl.unselect(selectedFeature);
        }
    } );
    $('#bzoomp').button();
    $('#bzoomp').click( function () { olmap.zoomIn(); } );
    $('#bzoomm').button();
    $('#bzoomm').click( function () { olmap.zoomOut(); } );
    $('#bsettings').button( { icons: { primary: 'ui-icon-wrench'} } );
    $('#bsettings').click( function () { $('#dsettings').dialog('open'); } );
    $('#bpie').button( { icons: { primary: 'ui-icon-clock'} } );
    $('#bpie').click( function() { kmllayer.setVisibility($(this).attr("checked")); });
    $('#rfull').button({icons: { primary: 'ui-icon-bullet'}});
    $('#rfull').change( function() { $("div#dchat,div#duserlist,div#dprop").dialog("open"); $("div[id^='d']").parent().css({ opacity: $('#rtrans').attr("checked") ? 0.6 : 1.0 }); });
    $('#rtrans').button({icons: { primary: 'ui-icon-radio-off'}});
    $('#rtrans').change( function() { $("div#dchat,div#duserlist").dialog("open"); $("div[id^='d']").parent().css({ opacity: $(this).attr("checked") ? 0.6 : 1.0 }); });
    $('#rnone').button({icons: { primary: 'ui-icon-radio-on'}});
    $('#rnone').change( function() { $("div[id^='d']").dialog("close"); });
    $('#vis').buttonset();
    $('#sstatus').slider({ min: 0, max: 9, slide: function(event, ui) { $('#vcolor').css({color: color[ui.value]}); $('#newstatus').text(ui.value); } });
    $('#bowner').button({ disabled: true, icons: { primary: 'ui-icon-flag'}});
    $('#pac_text').autocomplete({ source: users, position: { my : "right bottom", at: "right top"} });
    $('#nick_form').submit( function () { SetNick(); return false; } );
    $("#pac_form").submit(Send);
    $("#pac_text").focus();

    LoadSettings();
});
