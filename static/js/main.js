var olmap;
var kmllayer;

var selectedFeature;
var selectCtrl;
var color = ["#ff0000","#ff4000","#ff6000","#ff7000","#ff8000","#ff9000","#ffb000","#ffd000","#ffff00","#00ff00"];
var supported_langs = ['en', 'ru', 'jp'];
var users = [];
var claims = [];
var me;

// ---------------
// Translations
// ---------------
var _trans_hash = {};
function _(str) {
    if(_trans_hash[str]) return _trans_hash[str];
    return str;
}

function LoadTransData() {
    trans = typeof(trans) == 'undefined' ? [] : trans;
    for (i in trans) _trans_hash[trans[i]] = trans[++i];
}

var Progress = {
    draw: function(width) {
        var w = this.width = width || 200;
        var p = this.paper = Raphael("progress_bar", w + 2, 24);

        var a  = this.area   = p.rect(1, 1, w, 23).attr({fill: "90-#333-#fff", "fill-opacity": 0.3, "stroke-opacity": 0.3});

        var common = {
            stroke: "none",
            opacity: "0.3"
        };
        var nb = this.none   = p.rect(1, 1, 0, 23).attr(common).attr({fill: "#f33"});
        var pb = this.partly = p.rect(1, 1, 0, 23).attr(common).attr({fill: "#ff3"});
        var gb = this.good   = p.rect(1, 1, 0, 23).attr(common).attr({fill: "#3f3"});
    },

    update: function(cnone, cpartly, cgood, t) {
        if (!t) t = 1000;
        var sum = cnone + cpartly + cgood;
        var xnone = 0;
        var xpartly = cnone / sum * this.width;
        var xgood = cpartly / sum * this.width + xpartly;
        this.none.animate(  {            width: xpartly},            t).attr({title: cnone});;
        this.partly.animate({x: xpartly, width: xgood - xpartly},    t).attr({title: cpartly});;
        this.good.animate(  {x: xgood,   width: this.width - xgood}, t).attr({title: cgood});
    }
};

function config_get(key, defval) {
    if (typeof(MapCraft) == 'undefined' || typeof(MapCraft.config) == 'undefined') {
        return defval;
    }
    return MapCraft.config[key];
}

function ParseText (text) {
    text = text.replace(/(http\:\/\/[A-Za-z0-9%&_\-./]+)/g, '<a href="$1" target="_blank">$1</a>');
    text = text.replace(/(\s)#(\d+)\b/g, '$1<span class="piecenum" onclick="SelectPiece($2)">#$2</span>');
    text = text.replace(/\bc(\d+)\b/g, '<a href="http://www.openstreetmap.org/browse/changeset/$1" target="_blank">c$1</a>');
    text = text.replace(/\bn(\d+)\b/g, '<a href="http://www.openstreetmap.org/browse/node/$1" target="_blank">n$1</a>');
    text = text.replace(/\bw(\d+)\b/g, '<a href="http://www.openstreetmap.org/browse/way/$1" target="_blank">w$1</a>');
    text = text.replace(/\br(\d+)\b/g, '<a href="http://www.openstreetmap.org/browse/relation/$1" target="_blank">r$1</a>');
	return text;
}

var In = {};
var Out = {};

In.chat = function (data) {
    if (typeof(data['message']) == 'undefined')
        return false;
    var chat = $("#chat tbody");
    var time = '';
    var mclass = 'msg';
    var author = '';
    if (typeof(data['date']) == 'number') {
        var d = new Date();
        d.setTime(data['date']*1000);
        var s = d.getSeconds();
        var m = d.getMinutes();
        s = (s < 10) ? '0' + s : s;
        m = (m < 10) ? '0' + m : m;
        time = d.getHours().toString() + ':' + m.toString() + ':' + s.toString()
    }
    else if (typeof(data['date']) == 'string') {
        time = data['date'].substr(11, 8);
    }
    if (typeof(data['class']) != 'undefined')
        mclass = data['class'];
    if (typeof(data['author']) != 'undefined')
        author = data['author'];
    var history_class = data['history'] ? 'history' : '';
    var message = ParseText(data['message']);
    var chatbox = $("#chat");
    var isEnd = (chatbox.attr("scrollHeight") - chatbox.height() - chatbox.scrollTop() < 20);
    chat.append("<tr class='" + history_class + "'><td class='nick'>" + author + "</td><td class='" + mclass + "'>" + message + "</td><td class='time'>" + time + "</td></tr>");
    if (isEnd) ScrollDown();
};

In.claim_list = function (data) {
    claims = data;
    RedrawUsersList();
};

In.claim_add = function (data) {
    claims.push(data);
    RedrawUsersList();
};

In.claim_remove = function (data) {
    for (var i = 0; i < claims.length; i++) {
        if (claims[i]['claim_id'] == data['claim_id']) {
            claims.splice(i, 1);
            break;
        }
    }
    RedrawUsersList();
};

In.claim_update = function (data) {
    for (var i = 0; i < claims.length; i++) {
        if (claims[i]['claim_id'] == data['claim_id']) {
            for(var field in data) {
                claims[i][field] = data[field];
            }
            break;
        }
    }
    RedrawUsersList();
};

In.javascript = function (data) {
    eval(data.code);
};

In.no_comments = function (data) {
    if (selectedFeature == null)
        return;
    $('#dprop #comments').html('');
}

In.piece_comment = function (data) {
    if (selectedFeature == null)
        return;
    if (data['piece_id'].toString() == selectedFeature.attributes.name) {
        var comments_div = $('#dprop #comments');
        if ($('#dprop .loading').length != 0)
            comments_div.html('');

        var msg = data['type'] == 'comment' ? data['message'] : _(data['message']);
        msg = ParseText(msg);
        var date = data['date'].replace(/\.\d+$/, '');
        $('#dprop #comments').append('<p class="' + data['type'] + '"><strong>' + data['author'] + '</strong><span class="date">' + date + '</span><br />' + msg + '</p>');
    }
};

In.piece_owner = function (data) {
    var pieces = kmllayer.getFeaturesByAttribute('name', data['piece_id']);
    if (pieces.length > 0) {
        var isFreeing = (data['owner'] == '' && typeof(pieces[0].attributes.owner) != 'undefined');
        if (isFreeing)
            delete pieces[0].attributes.owner;
        else
            pieces[0].attributes.owner = data['owner'];
        if (selectedFeature != null)
            if (data['piece_id'].toString() == selectedFeature.attributes.name)
                $('#bowner').button("option", "label", (isFreeing ? ldata[12] : data['owner']) );
    }
}

In.piece_state = function (data) {
    var pieces = kmllayer.getFeaturesByAttribute('name', data['piece_id']);
    if (pieces.length > 0)
    {
        pieces[0].attributes.description = data['state'];
        pieces[0].style.fillColor = color[parseInt(data['state'])];
    }
    if (selectedFeature != null) {
        if (data['piece_id'].toString() == selectedFeature.attributes.name)
            $('#bstatus').button("option", "label", data['state'].toString() + '/9');
    }
    kmllayer.redraw(true);
};

In.piece_progress = function(data) {
    var cnts = data['progress'];
    Progress.update(cnts[0], cnts[1], cnts[2], 400);
};

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

In.user_list = function (data) {
    users = data;
    RedrawUsersList();
};

In.user_update = function (data) {
    var idx = -1;
    for (var i = 0; i < users.length; i++) {
        if (users[i]['user_nick'] == data['current_nick']) {
            idx = i;
            break;
        }
    }
    // If user entry is not found, add one
    if (idx == -1) {
        var entry = {
            user_nick: data['current_nick'],
            color: data['color'] || '000',
            online: data['online'] || false,
            reserved: data['reserved'] || []
        };
        users.push(entry);
        idx = users.length - 1;
    } else {
        // If found then update and delete if needed
        for(var field in data) {
            users[i][field] = data[field];
        }
    }

    if(is_user_entry_is_empty(users[i])) {
        users.splice(i, 1);
    }

    RedrawUsersList();
};

In.anons_update = function (data) {
    count = parseInt(data.count);
    text = '';
    if (count) {
        text = 'and ' + count + ' anon';
        if (count > 1) {
            text = text + 's';
        }
    }
    $('#anonscounter').text(text);
};

In.youare = function (data) {
    me = data;
    $('#pac_nick').button("option", "label", me.nick);
    $('#pac_nick').button("option", "label", me.nick);
    if (data['role'] == 'anon')
        $('#pac_nick').click(function() { window.open('/app/auth.php'); } );
    else
        $('#pac_nick').unbind('click');
    $("#pac_text").focus();
    PieHub.push( Out.get_user_list() );
};

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

Out.get_chat_history = function () {
    return ['get_chat_history', {}];
};

Out.get_piece_comments = function (piece_id) {
    return ['get_piece_comments', {piece_id: piece_id}];
};

Out.get_user_list = function () {
    return ['get_user_list', {}]
}

Out.chat = function (msg, pub, target) {
    if (typeof(msg) != 'string') return false;
	if (typeof(pub) != 'boolean') pub = true;
	if (typeof(target) != 'string') target = '';
    var cmd = ['chat', {type: (pub ? 'public' : 'private'), message: msg }];
    if (target != '')
        cmd.target_nick = target;
    return cmd;
};

Out.piece_comment = function (piece_id, comment) {
    if (typeof(piece_id) != 'string' && typeof(piece_id) != 'number') return false;
    if (typeof(comment) != 'string') return false;
    return ['piece_comment', {piece_id: piece_id.toString(), comment: comment}];
};

Out.piece_state = function (piece_id, state) {
    if (typeof(piece_id) != 'string' && typeof(piece_id) != 'number') return false;
    if (typeof(state) != 'string' && typeof(state) != 'number') return false;
    return ['piece_state', {piece_id: piece_id.toString(), state: state.toString()}];
};

Out.piece_reserve = function (piece_id) {
    if (typeof(piece_id) != 'string' && typeof(piece_id) != 'number') return false;
    return ['piece_reserve', {piece_id: piece_id.toString()}];
};

Out.piece_free = function (piece_id) {
    if (typeof(piece_id) != 'string' && typeof(piece_id) != 'number') return false;
    return ['piece_free', {piece_id: piece_id.toString()}];
};

Out.piece_progress = function () {
    return ['piece_progress', {}];
};

Out.vote_claim = function (claim_id, vote) {
    if (typeof(claim_id) != 'string' && typeof(piece_id) != 'number') return false;
    if (typeof(vote) != 'string' && typeof(vote) != 'number') return false;
    return ['vote_claim', {claim_id: claim_id.toString(), vote: vote.toString()}];
};

Out.whoami = function() {
    return ['whoami', {}];
};

function is_user_entry_is_empty(entry) {
    if(entry.online) return false;
    if(entry.reserved.length) return false;

    // search for claims
    found = false;
    for (idx in claims) {
        if (claims[idx]['owner'] == entry.user_nick) {
            found = true;
            break;
        }
    }
    if(found) return false;

    // all checks failed -- entry is useless
    return true;
}

function Dispatch(data) {
    if (typeof In[data[0]] == 'function')
        In[data[0]](data[1]);
};

function Reload() {
    window.location.reload(true);
};

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
        var lang = navigator.language ? navigator.language : (navigator.browserLanguage ? navigator.browserLanguage : "en");
        lang = lang.replace(/-\w+$/, '');
        lang = lang in supported_langs ? lang : "en";

        $('#slang').val(lang);
        localStorage.lang = lang;
        LoadLanguage();
    }

    // Progress bar
    var show_pb = localStorage.progress_bar ? true : false;
    $('#sprogress_bar').attr('checked', show_pb);
    $('#progress_bar').toggle(show_pb);
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

    var show_pb = $('#sprogress_bar').attr('checked') ? true : false;
    localStorage.progress_bar = show_pb ? 'show' : '';
    $('#progress_bar').toggle(show_pb);
}

function LoadLanguage() {
    $.get("/js/lang/" + $('#slang').val() + ".js", {}, function() {
        LoadTransData();
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
        $('#lprogress_bar').text(_("Progress bar:"));
    });
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
    if (selectedFeature != null)
        selectCtrl.unselect(selectedFeature);

    pieces = kmllayer.getFeaturesByAttribute('name', num);
    if (pieces.length > 0)
        selectCtrl.select(pieces[0]);
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

function RedrawUsersList() {
    var nicks = [];
    newhtml = "<table><tr><td id='lname'>" + ldata[18] + "</td><td id='lpieces'>" + ldata[19] + "</td><td id='lclaims'>" + ldata[20] + "</td></tr>";
    for (var u = 0; u < users.length; u++) {
        nicks.push(users[u]['user_nick']);
        var sreserved = "";
        var sclaims = "";
        for (var i = 0; i < users[u]['reserved'].length; i++)
            sreserved += ("<span class='num'>" + users[u]['reserved'][i] + "</span> ");
        var userclaims = [];
        for (i = 0; i < claims.length; i++) {
            if (claims[i]['owner'] == users[u]['user_nick'])
                userclaims.push(claims[i]);
        }
        for (i = 0; i < userclaims.length; i++) {
            if (me.nick == users[u]['user_nick'])
                sclaims += ("<span class='claim ui-state-default'><span class='num'>" + userclaims[i]['piece_id'] + "</span>&nbsp;[" + userclaims[i]['vote_balance'] + "&nbsp;<div title='Снять заявку' class='close'></div>]</span> ");
            else
                sclaims += ("<span class='claim ui-state-default'><span class='num'>" + userclaims[i]['piece_id'] + "</span>&nbsp;[<div title='За' class='up'></div>&nbsp;" + userclaims[i]['vote_balance'] + "&nbsp;<div title='Против' class='down'></div>]</span><br />");
        }
        newhtml += ("<tr><td class='nick'>" + (users[u]['online'] ? "<img src='/img/onl.png'>&nbsp;" : "") + "<span class='nickname'>" + users[u]['user_nick'] + "</span></td><td class='msg'>" + sreserved + "</td><td>" + sclaims + "</td></tr>");
    }
    newhtml += "</table>";
    $('#userlist-table').html(newhtml);
    $('#pac_text').autocomplete('option', 'source', nicks);
    $('.nickname').click( function() { $('#pac_text').val($('#pac_text').val() + $(this).text() + ': '); $("#pac_text").focus(); } );
    $('.up').click( function() {
        var piece_id = $(this).parent().find('span.num').text();
        var user_nick = $(this).parent().parent().parent().find('span.nickname').text();
        for (var i = 0; i < claims.length; i++) {
            if (claims[i]['owner'] == user_nick && claims[i]['piece_id'] == piece_id) {
                Vote(claims[i]['claim_id'], 1);
                break;
            }
        }
        $(this).remove(); } );
    $('.down').click( function() {
        var piece_id = $(this).parent().find('span.num').text();
        var user_nick = $(this).parent().parent().parent().find('span.nickname').text();
        for (var i = 0; i < claims.length; i++) {
            if (claims[i]['owner'] == user_nick && claims[i]['piece_id'] == piece_id) {
                Vote(claims[i]['claim_id'], -1);
                break;
            }
        }
        $(this).remove(); } );
    $('.close').click( function() {
        var piece_id = $(this).parent().find('span.num').text();
        var user_nick = $(this).parent().parent().parent().find('span.nickname').text();
        for (var i = 0; i < claims.length; i++) {
            if (claims[i]['owner'] == user_nick && claims[i]['piece_id'] == piece_id) {
                CloseClaim(claims[i]['claim_id']);
                break;
            }
        }
        $(this).remove(); } );
    $('.num').click( function() { SelectPiece($(this).text()); } );
}

function Debug(data) {
    $("#debug").text(data.toString());
}

function Enter() {
    PieHub.push( Out.get_chat_history() );
    PieHub.push( Out.whoami() );
    PieHub.push( Out.piece_progress() );
}

function Vote(claim_id, vote) {
    PieHub.push( Out.vote_claim(claim_id, vote) );
}

function CloseClaim(claim_id) {
    PieHub.push( Out.claim_remove(claim_id) );
}

function Send() {
    var text = $("#pac_text").val();
    if (!text.match(/^\s*$/)) {
        PieHub.push(Out.chat(text));
        $("#pac_text").val("");
    }
    $("#pac_text").focus();
    return false;
}

function SetStatus(e) {
    PieHub.push( Out.piece_state(selectedFeature.attributes.name, $('#sstatus').slider('value')) );
}

function ScrollDown() {
    var chatbox = $("#chat");
    chatbox.scrollTop(chatbox.attr("scrollHeight") - chatbox.height());
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
    PieHub.push( Out.get_piece_comments(selectedFeature.attributes.name) );
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
    $('#bowner').click( function() { $(e.attributes.owner ? (e.attributes.owner == me.nick ? '#drefuse' : '#dclaim') : '#dtake').dialog('open'); });
    $('#bowner').button("option", "label", e.attributes.owner ? e.attributes.owner : ldata[12]);
    $('#bowner').button("option", "icons", { primary: (e.attributes.owner ? (e.attributes.owner == me.nick ? 'ui-icon-closethick' : 'ui-icon-circle-check') : 'ui-icon-flag')});
    $("#dprop").dialog("option", "title", ldata[6] + ": " + e.attributes.name);
    $("#dprop").dialog("open");
    $('#bstatus').button("option", "label", e.attributes.description + '/9');
    $('#bstatus').button("enable");
    $('#bremote').button("enable");
    $('#bcomment').button("enable");
    GetComments();
}

function onUnselectPiece(e) {
    selectedFeature = null;
    e.style.fillOpacity = "0.5";
    e.style.strokeWidth = "1";
    kmllayer.redraw(true);
    $('#bowner').button("option", "label", ldata[12]);
    $('#bowner').button("option", "icons", {primary: 'ui-icon-flag'});
    $('#bowner').unbind('click');
    $('#bstatus').button("option", "label", '0/9');
    $('#dprop').dialog("option", "title", ldata[6]);
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
    Progress.draw();

    // Инициализация клиента хаба
    PieHub.init({
        pieid: parseInt(window.location.pathname.split('pie/')[1]),
        hub_url: config_get('hub_url', '/hub'),
        poll_callback: Dispatch
    });
    // Запуск поллинга
    PieHub.poll();
    Enter();

    var options = {controls: [new OpenLayers.Control.Navigation(), new OpenLayers.Control.ScaleLine(), new OpenLayers.Control.Permalink(), new OpenLayers.Control.Attribution()], projection: new OpenLayers.Projection("EPSG:900913"), displayProjection: new OpenLayers.Projection("EPSG:4326"), units: "m", numZoomLevels: 18, maxResolution: 156543.0339, maxExtent: new OpenLayers.Bounds(-20037508, -20037508, 20037508, 20037508.34) };
    olmap = new OpenLayers.Map(document.getElementById('olmap'), options);
    var mapnik = new OpenLayers.Layer.OSM();
    kmllayer = new OpenLayers.Layer.Vector("Pie", { strategies: [new OpenLayers.Strategy.Fixed()], protocol: new OpenLayers.Protocol.HTTP({url: "/kml/" + window.location.pathname.split('pie/')[1] + ".kml", format: new OpenLayers.Format.KML({extractStyles: true, extractAttributes: true, maxDepth: 0})}), projection: "EPSG:4326" });
    olmap.addLayers([mapnik, kmllayer]);

    selectCtrl = new OpenLayers.Control.SelectFeature(kmllayer, {clickout: true, onSelect: onSelectPiece, onUnselect: onUnselectPiece });
    selectCtrl.handlers.feature.stopDown = false; 

    olmap.addControl(selectCtrl);
    selectCtrl.activate();

    // Zoom what it will be loaded
    kmllayer.events.register("loadend", this, function() {
        if (!olmap.getCenter()) {
            olmap.zoomToExtent(kmllayer.getDataExtent());
            olmap.zoomOut(); // a bit smaller
        }
    });

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
        buttons: { "OK": function() { SetNick(); $(this).dialog("close"); }, "Cancel": function() { $(this).dialog("close"); } }
    });
    $('#dstatus').dialog({
        autoOpen: false,
        modal: true,
        width: 410,
        resizable: false,
        draggable: false,
        position: 'center',
        buttons: { "OK": function() { SetStatus(); $(this).dialog("close"); }, "Cancel": function() { $(this).dialog("close"); } }
    });
    $('#dtake').dialog({
        autoOpen: false,
        modal: true,
        width: 250,
        resizable: false,
        draggable: false,
        position: 'center',
        buttons: { "Yes": function() { TakePiece(); $(this).dialog("close"); }, "No": function() { $(this).dialog("close"); } }
    });
    $('#dclaim').dialog({
        autoOpen: false,
        modal: true,
        width: 250,
        resizable: false,
        draggable: false,
        position: 'center',
        buttons: { "Yes": function() { ClaimPiece(); $(this).dialog("close"); }, "No": function() { $(this).dialog("close"); } }
    });
    $('#drefuse').dialog({
        autoOpen: false,
        modal: true,
        width: 250,
        resizable: false,
        draggable: false,
        position: 'center',
        buttons: { "Yes": function() { RefusePiece(); $(this).dialog("close"); }, "No": function() { $(this).dialog("close"); } }
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
    $('#bstatus').click(function() { $('#sstatus').slider('value', selectedFeature.attributes.description); $('#vcolor').css({ color: color[$('#sstatus').slider('value')] }); $('#newstatus').text($('#sstatus').slider('value')); $('#dstatus').dialog('open'); });
    $('#pac_nick').button({ icons: { primary: 'ui-icon-person'} });
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
    $("#pac_form").submit(Send);
    $("#pac_text").focus();
    LoadSettings();
});
