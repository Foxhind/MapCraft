/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

var olmap;
var kmllayer;

var selectedFeature;
var selectCtrl;
const stateColors = ['#d94b4b', '#e75a45', '#ef7045', '#f38c45', '#f4aa49', '#f3c956', '#dfd55b', '#b9cf62', '#72b764', '#4ba85c'];
var supported_langs = ['de', 'en', 'es', 'fr', 'it', 'jp', 'pl', 'ru', 'sk', 'uk'];
var users = [];
var claims = [];
var pieceLabel = 'none';
var pieceColor = 'state';
var showOwned;
var chatShowInfo = true;
var chatScrollPosition = -1;
let selectedProgress = null;
let sectorHistory = [];
let chatUnread = 0;

var me = {
    nick: "???",
    role: "anon",
    adminMode: false,

    update: function(data) {
        this.nick = data['nick'];
        this.role = data['role'];
        this.adminMode = false;
    },
    hasRole: function(minRole) {
        var levels = {
            anon:       0,
            member:    10,
            admin:     20,
            owner:     30,
            developer: 40
        };
        return levels[minRole] <= levels[this.role];
    },
    inAdminMode: function() {
        return this.hasRole('admin') && this.adminMode;
    },
    toggleAdminMode: function() {
        this.adminMode = !this.adminMode;

        // TODO: move to events
        $('#badmin').toggleClass('admin-active', this.adminMode);
        $('#dadmin-am-toggle').toggleClass('admin-active', this.adminMode);
        $('#dadmin-am-toggle').text(this.adminMode ? 'Turn off Admin Mode' : 'Turn on Admin Mode');
    }
};


// ---------------
// Translations
// ---------------
var _trans_hash = {};
function t(str) {
    if(_trans_hash[str]) return _trans_hash[str];
    return str;
}

function LoadTransData() {
    trans = typeof(trans) == 'undefined' ? [] : trans;
    for (i in trans) _trans_hash[trans[i]] = trans[++i];
}

function simpleHash(str) {
    var hash = 0;
    if (str.length === 0) return hash;
    for (i = 0; i < str.length; i++) {
        char = str.charCodeAt(i);
        hash = ((hash<<5)-hash)+char;
        hash = hash & hash; // Convert to 32bit integer
    }
    return hash;
}

function ownerColor(owner) {
    var hue, sat = 40, light = 40;
    var hash = Math.abs(simpleHash(owner));
    hue = hash % 255;
    sat = sat + Math.round(hash / 255 % 20);
    light = light + Math.round(hash / 255 / 20 % 20);
    return 'hsl(' + hue + ', ' + sat + '%, ' + light + '%)';
}

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function sectorStage(state) {
    state = parseInt(state, 10);
    if (state === 0) return 'Не начат';
    if (state < 6) return 'В работе';
    if (state < 9) return 'На проверке';
    return 'Готов';
}

function sectorHistoryEvent(type, text, author, date) {
    sectorHistory.unshift({ type, text, author: author || '', date: date || '' });
    renderSectorHistory();
}

function renderSectorHistory() {
    const $comments = $('#comments');

    if (!selectedFeature) {
        $comments.html('<p class="sector-history__empty">История появится после выбора сектора.</p>');
        return;
    }

    if (!sectorHistory.length) {
        $comments.html('<p class="sector-history__empty">Для этого сектора пока нет истории.</p>');
        return;
    }

    const expanded = $('#show-full-history').data('expanded');
    const entries = expanded ? sectorHistory : sectorHistory.slice(0, 5);
    let html = '';

    for (const event of entries) {
        const meta = `${escapeHtml(event.author)}${event.author && event.date ? ' · ' : ''}${escapeHtml(event.date)}`;

        if (event.type === 'comment') {
            html += `<div class="sector-history__comment"><div class="sector-history__meta">${meta}</div><div>${event.text}</div></div>`;
        } else {
            html += `<div class="sector-history__event sector-history__event_type_${escapeHtml(event.type)}"><span class="sector-history__marker"></span><div>${event.text}</div><div class="sector-history__meta">${meta}</div></div>`;
        }
    }

    $comments.html(html);
    $('#show-full-history').toggle(entries.length < sectorHistory.length || expanded);
}

function renderSectorCard(message, isError) {
    const $card = $('#sector-card');

    if (!selectedFeature) {
        $card.addClass('sector-card_empty').html('<p class="sector-card__empty-state">Выберите сектор на карте или найдите его по номеру.</p>');
        return;
    }

    const attrs = selectedFeature.attributes;
    const state = parseInt(attrs.description, 10) || 0;
    const owner = attrs.owner || '';
    const ownedByMe = owner && me && owner === me.nick;
    const primary = !owner ? 'Зарезервировать' : (ownedByMe ? 'Сохранить готовность' : 'Подать заявку');

    if (selectedProgress === null) {
        selectedProgress = state;
    }

    const progressButtons = stateColors.map((color, progress) => {
        const selectedClass = progress === selectedProgress ? ' progress-selector__option_selected' : '';
        const checkedState = progress === selectedProgress ? 'true' : 'false';
        return `<button class="progress-selector__option${selectedClass}" type="button" data-progress="${progress}" aria-label="Готовность ${progress} из 9" aria-checked="${checkedState}" style="background-color:${color}">${progress}</button>`;
    }).join('');
    const releaseAction = ownedByMe ? '<button type="button" id="sector-release">Освободить</button>' : '';
    const saveStateClass = isError ? ' sector-card__save-state_error' : '';
    const saveStateMessage = escapeHtml(message || 'Данные синхронизированы');
    const html = `
        <div class="sector-card__header"><h2>Сектор ${escapeHtml(attrs.name)}</h2><span class="sector-card__stage">${sectorStage(state)}</span></div>
        <p class="sector-card__owner">Владелец: <strong>${owner ? escapeHtml(owner) : 'Свободен'}</strong></p>
        <p class="sector-card__ready">Готовность <strong>${selectedProgress}/9</strong></p>
        <div class="progress-selector" role="radiogroup" aria-label="Готовность сектора">${progressButtons}</div>
        <div class="sector-card__actions">
            <button type="button" class="sector-card__primary-action" id="sector-primary">${primary}</button>
            ${releaseAction}
            <button type="button" id="sector-josm">Открыть в JOSM</button>
            <button type="button" id="sector-osm">Открыть в OSM</button>
        </div>
        <div class="sector-card__save-state${saveStateClass}">${saveStateMessage}</div>`;

    $card.removeClass('sector-card_empty').html(html);
}

function updateUnread() {
    $('#chat-unread').text(chatUnread).toggle(chatUnread > 0);
}

function AdminConfirm(text, confirm_cb) {
    $('#dialog-body')
        .html('<div id="exclamation"></div>')
        .append('<span>' + text + '</span>')
        .dialog({
            autoOpen: true,
            resizable: false,
            modal: true,
            dialogClass: 'admin-confirm',
            //height: 150,
            title: "Confirm admin action",

            buttons: [
                {
                    text: 'Proceed',
                    click: function() {
                        confirm_cb();
                        $(this).dialog('close');
                    }
                },{
                    text: 'Cancel',
                    click: function() {
                        $(this).dialog('close');
                    }
                }
            ]});
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

        this.parts = [];
        for (var i = 0; i < 10; i++) {
            this.parts[i] = p.rect(1, 1, 0, 23).attr(common).attr({fill: stateColors[i]});
        }
    },

    update: function(states, t) {
        var self = this;
        if (!t) t = 1000;
        var sum = _(states).reduce(function(n, s) {
            return s + n;
        }, 0);

        var last_x = 0;
        _(states).each(function(state, i) {
            var current_width = state / sum * self.width;
            self.parts[i].animate( {x:last_x, width: current_width}, t).attr({title: state});
            last_x += current_width;
        });
    }
};

var Chat = {
    chatEl: function() {
        return $("#chat tbody");
    },

    isAtEnd: function() {
        var chatbox = $("#chat");
        return chatbox.attr("scrollHeight") - chatbox.height() - chatbox.scrollTop() < 20;
    },

    append: function(message, type, author, ts, is_history) {

        ts = this._stringifyTimestamp(ts);
        if (_.isUndefined(type)) type = 'msg';
        if (_.isUndefined(author)) author = '-';

        // Replace all special shortcuts
        message = TextReplacer.parse(message);

        var toMe = false;
        if (type == 'chat' && !is_history) {
            var re = new RegExp('\\b' + (me.nick || 'unknown') + '\\b');
            toMe = re.exec(message);
        }

        // Suuport for me
        if (message.substr(0, 4) === '/me ') {
            message = author + message.substr(3);
            author = '*';
        }

        var authorColor = ownerColor(author);
        // Append to the end and scroll
        var history_class = is_history ? 'history' : '';
        var entry = $("<tr><td class='nick project-chat__author' style='color: " + authorColor + "'>" + author + "</td><td class='message project-chat__text'>" + message + "</td><td class='time project-chat__time'>" + ts + "</td></tr>");

        entry.addClass('chat-' + type);
        entry.addClass('project-chat__message');
        entry.addClass('project-chat__message_type_' + type);
        if (is_history) entry.addClass('history project-chat__message_history');
        if (!chatShowInfo && type === 'info') entry.addClass('hidden project-chat__message_hidden');
        if (toMe) entry.addClass('tome project-chat__message_to-me');

        var atEnd = this.isAtEnd();
        this.chatEl().append(entry);
        if (atEnd) {
            ScrollDown();
        }
    },

    handleCliCommand: function(text) {
        if (!(text[0] === '/' && text.substr(0, 4) !== '/me ')) {
            return false;
        }

        if (text === '/quit') {
            window.location = '/';
        } else if (text === '/help') {
            this.append("Supported commands:<br/>" +
                "/quit      - quit to main list<br/>" +
                "/logout    - logout from the mapcraft<br/>" +
                "/me TEXT   - say in /me form<br/>" +
                "/help      - show supported commands",
                'cli');
        } else if (text === '/logout') {
            $.get("/app/auth.php?action=logout");
        } else {
            this.append("Unsupported CLI command. Type '/help' to see known commands", 'cli');
        }
        return true;
    },

    _stringifyTimestamp: function(ts) {
        if (_.isString(ts)) {
            return ts.substr(11, 8);
        }

        var d = new Date();
        if (_.isNumber(ts)) {
            d.setTime(ts*1000);
        }

        var s = d.getSeconds();
        var m = d.getMinutes();
        s = (s < 10) ? '0' + s : s;
        m = (m < 10) ? '0' + m : m;
        return d.getHours().toString() + ':' + m.toString() + ':' + s.toString();
    },

    updateStyle: function() {
        $(".chat-info").toggleClass('hidden', !chatShowInfo);
        $(".project-chat__message_type_info").toggleClass('project-chat__message_hidden', !chatShowInfo);
    }
};


var CakeSettings = {
    orig: {},
    data: {},
    modified: {},

    init: function(data) {
        this.data = data;
        this.orig = JSON.parse(JSON.stringify(this.data));
        this.modified = {};

        window.document.title = data['name'] + ' - MapCraft Dev';
        $('#project-title').text(data['name']);
    },
    reset: function() {
        this.init(this.orig);
    },
    push: function() {
        PieHub.push(Out.update_cake(this.modified));
    },

    // Setters, getters
    set: function(key, value) {
        this.modified[key] = this.data[key] = value;
        this.onModify();
        return this.data[key];
    },
    touch: function(key, value) {
        this.modified[key] = this.data[key];
        this.onModify();
    },
    toggle: function(key) {
        this.modified[key] = this.data[key] = !this.data[key];
        this.onModify();
        return this.data[key];
    },
    get: function(key) {
        return this.data[key];
    },

    // Events (like so)
    onModify: function() {
    }
};


var InfoDialog  = {
    init: function() {
        var self = this;

        $('#dinfo').dialog({
            title: 'Information',
            autoOpen: false,
            modal: false,
            width: 450,
            height: 450,
            minWidth: 450,
            minHeight: 150,
            resizable: true,
            position: 'center',
            buttons: [
                {
                    id: 'dinfo-save',
                    text: 'Save',
                    disabled: 'disabled',
                    click: function() {
                        CakeSettings.push();
                    }
                },
                {
                    id: 'dinfo-reset',
                    text: 'Reset',
                    click: function() {
                        CakeSettings.reset();
                    }
                },
                {
                    text: "Close",
                    click: function() {
                        $(this).dialog("close");
                    }
                }
            ]
        });

        $('#dinfo-link-editor').dialog({
            title: 'Edit link',
            autoOpen: false,
            modal: true,
            width: 450,
            height: 170,
            resizable: false,
            position: 'center',
            buttons: [
                {
                    text: 'Apply',
                    'class': 'apply-button',
                    click: function() {
                        var action = $('#dinfo-link-editor').data('action');
                        var index  = $('#dinfo-link-editor').data('index');

                        var link = {
                            name: $('#dinfo-link-name').val(),
                            ref: $('#dinfo-link-ref').val()
                        };
                        if ( action === 'add') {
                            CakeSettings.data['links'].push(link);
                        } else {
                            CakeSettings.data['links'][index] = link;
                        }
                        CakeSettings.touch('links');

                        $(this).dialog("close");
                    }
                },
                {
                    text: "Close",
                    click: function() {
                        $(this).dialog("close");
                    }
                }
            ]
        });

        $('#dinfo-status-editor').dialog({
            title: 'Edit link',
            autoOpen: false,
            modal: true,
            width: 450,
            height: 170,
            resizable: false,
            position: 'center',
            buttons: [
                {
                    text: 'Apply',
                    'class': 'apply-button',
                    click: function() {
                        var action = $('#dinfo-link-editor').data('action');
                        var index  = $('#dinfo-link-editor').data('index');

                        var link = {
                            name: $('#dinfo-link-name').val(),
                            ref: $('#dinfo-link-ref').val()
                        };
                        if ( action === 'add') {
                            CakeSettings.data['links'].push(link);
                        } else {
                            CakeSettings.data['links'][index] = link;
                        }
                        CakeSettings.touch('links');

                        $(this).dialog("close");
                    }
                },
                {
                    text: "Close",
                    click: function() {
                        $(this).dialog("close");
                    }
                }
            ]
        });

        $('#dinfo-name-editor').dialog({
            title: 'Edit link',
            autoOpen: false,
            modal: true,
            width: 450,
            height: 140,
            resizable: false,
            position: 'center',
            buttons: [
                {
                    text: 'Apply',
                    'class': 'apply-button',
                    click: function() {
                        CakeSettings.set('name', $(this).find('input').val());
                        $(this).dialog("close");
                    }
                },
                {
                    text: "Close",
                    click: function() {
                        $(this).dialog("close");
                    }
                }
            ]
        });

        $('#dinfo-description-editor').dialog({
            title: 'Edit link',
            autoOpen: false,
            modal: true,
            width: 450,
            height: 300,
            resizable: false,
            position: 'center',
            buttons: [
                {
                    text: 'Apply',
                    'class': 'apply-button',
                    click: function() {
                        CakeSettings.set('description', $(this).find('textarea').val());
                        $(this).dialog("close");
                    }
                },
                {
                    text: "Close",
                    click: function() {
                        $(this).dialog("close");
                    }
                }
            ]
        });

        $('#dinfo-status-editor').dialog({
            title: 'Edit status',
            autoOpen: false,
            modal: true,
            width: 450,
            height: 140,
            resizable: false,
            position: 'center',
            buttons: [
                {
                    text: 'Apply',
                    'class': 'apply-button',
                    click: function() {
                        var index = $('#dinfo-status-editor').data('index');
                        CakeSettings.data['statuses'][index] = $(this).find('input').val();
                        CakeSettings.touch('statuses');
                        $(this).dialog("close");
                    }
                },
                {
                    text: "Close",
                    click: function() {
                        $(this).dialog("close");
                    }
                }
            ]
        });
        // Init basic links
        var origin = window.location.protocol + '//' + window.location.host;
        var wms_link = origin + '/wms/' + PieHub.options.pieid + '?SRS={proj}&WIDTH={width}&height={height}&BBOX={bbox}';
        var log_link = origin + '/log/' + PieHub.options.pieid;
        $("#wms_link").html(this._createLink('wms:' + wms_link, "WMS layer for editors"));
        $("#log_link").html(this._createLink(log_link, "Cake chat log"));
        $("#wms_action").html(this._createWmsButtons(wms_link));
    },

    redraw: function() {
        var self = this;

        // Init buttons Save/Reset for owner
        $('#dinfo-save').toggle(me.hasRole('owner'));
        $('#dinfo-reset').toggle(me.hasRole('owner'));

        // Cleanup
        $('#dinfo .user-data').remove();

        $('#dinfo-name').text(CakeSettings.get('name'));
        $('#dinfo-pie-actions').html('')
            .append(this._createEditNameBtn())
            .append(' ')
            .append(this._createDeletePieBtn());

        $('#dinfo-description').html(CakeSettings.get('description'));
        $('#dinfo-description').append('<br/>').append(this._createEditDescriptionBtn());


        //
        // Fill links
        //
        _(CakeSettings.get('links')).each(function(link, index) {
            var proto = self._detectLinkProto(link['ref']);
            var row = $(_.template($('#dinfo-row-template').html(), {
                prop: proto,
                value: self._createLink(link['ref'], link['name'])
            }));
            row.find('.tbl-atcions')
                .append(self._createEditLinkBtn(index))
                .append(' ')
                .append(self._createDeleteLinkBtn(index));
            $('#dinfo-links').append(row);
        });

        //
        // Fill statuses
        //
        _(CakeSettings.get('statuses')).each(function(status, index) {
            if (status === '') {
                if (!me.hasRole('owner')) return;
                status = '<span class="placeholder">hidden</span>';
            }

            var row = $(_.template($('#dinfo-row-template').html(), {prop: index + '/9', value: status }));
            row.find('.tbl-actions').append(self._createEditStatusBtn(index));
            $('#dinfo-statuses').append(row);
        });

        // Row with 'add' link for owners
        if (me.hasRole('owner')) {
            var new_link_row = $(_.template($('#dinfo-row-template').html(), {prop: '', value: ''}));
            new_link_row.find('.tbl-value').append(this._createAddLinkBtn());
            $('#dinfo-links').append(new_link_row);
        }

        //
        // Fill details
        //
        var details = [
            ['author', CakeSettings.get('author')],
            ['created at', CakeSettings.get('created_at')],
            ['visibility', CakeSettings.get('visible') ? 'shared' : 'hidden', this._createHideBtn()],
            ['cake', CakeSettings.get('pieces_count') + " slices " + this._exportBtn(), this._updateCakeBtn()]
        ];
        _(details).each(function(pair) {
            var row = $(_.template($('#dinfo-row-template').html(), {prop: pair[0], value: pair[1]}));
            if (!_.isUndefined(pair[2]))
                row.find('.tbl-actions').append(pair[2]);
            $('#dinfo-details').append(row);
        });

        //this.show();
    },
    show: function() {
        $('#dinfo').dialog('open');
    },

    //
    // Helpers
    //
    _createLink: function(ref, name) {
        if (_.isUndefined(name)) {
            name = name || ref;
            if (name.length > 40) {
                name = name.substr(0,40) + '...';
            }
        }
        return "<a href='" + ref + "' target='_blank'>" + name + "</a>";
    },
    _detectLinkProto: function(ref) {
        var delim_index = ref.indexOf(':');
        if (delim_index > 0) {
            var proto = ref.substr(0, delim_index);
            if (['wms', 'http', 'https', 'irc', 'tms'].indexOf(proto) > -1)
                return proto;
            return '?';
        }
        return '?';
    },
    _createOwnerBtn: function(text, cb) {
        if (!me.hasRole('owner')) return '';
        return $('<a class="owner" href="#">' + text + '</a>').click(cb);
    },
    _createWmsButtons: function(wms_ref) {
        var remote = $('<a href="#">Remote</a>');
        remote.button();
        remote.click(function() {
            var title = 'Mapcraft-cake-' + PieHub.options.pieid;
            RemoteControlJosm('imagery?title=' + title + '&type=wms&url=' + encodeURIComponent(wms_ref));
        });
        return remote;
    },

    //
    // Admin buttons
    //
    _createHideBtn: function() {
        var self = this;
        return this._createOwnerBtn(CakeSettings.get('visible') ? 'hide' : 'share', function() {
            CakeSettings.toggle('visible');
        });
    },
    _createDeletePieBtn: function() {
        var self = this;
        return this._createOwnerBtn('delete', function() {
            window.open('/delete/' + PieHub.options.pieid, '_blank');
        });
    },
    _createEditNameBtn: function() {
        var self = this;
        return this._createOwnerBtn('edit', function() {
            $('#dinfo-name-editor input').val(CakeSettings.get('name'));
            $('#dinfo-name-editor').dialog('open');
        });
    },
    _createEditDescriptionBtn: function() {
        var self = this;
        return this._createOwnerBtn('edit', function() {
            $('#dinfo-description-editor textarea').val(CakeSettings.get('description'));
            $('#dinfo-description-editor').dialog('open');
        });

    },
    _createAddLinkBtn: function() {
        var self = this;
        return this._createOwnerBtn('add', function() {
            self._openLinkEditor('add');
        });
    },
    _createEditLinkBtn: function(index) {
        var self=this;
        return this._createOwnerBtn('edit', function() {
            self._openLinkEditor('edit', index);
        });
    },
    _createDeleteLinkBtn: function(index) {
        var self = this;
        return this._createOwnerBtn('delete', function() {
            CakeSettings.data['links'].splice(index, 1);
            CakeSettings.touch('links');
        });
    },
    _createEditStatusBtn: function(index) {
        var self = this;
        return this._createOwnerBtn('edit', function() {
            var self = this;
            $('#dinfo-status-editor input').val(CakeSettings.get('statuses')[index]);
            $('#dinfo-status-editor').data("index", index);
            $('#dinfo-status-editor').dialog('open');
        });
    },

    // Open Link Editor dialog TODO: move to separate obj
    _openLinkEditor: function(action, index) {
        var self = this;
        var data;

        if (action === 'add') {
            data = {name: '', ref: ''};
        } else {
            data = CakeSettings.get('links')[index];
        }
        $('#dinfo-link-name').val(data['name']);
        $('#dinfo-link-ref').val(data['ref']);
        $('#dinfo-link-editor')
            .data("action", action)
            .data("index", index);

        $('#dinfo-link-editor').dialog('open');
    },

    _exportBtn: function() {
        return '<a href="/export/' + PieHub.options.pieid + '" target="_blank">export</a>';
    },

    _updateCakeBtn: function() {
        return this._createOwnerBtn('update', function() {
            window.open('/update/' + PieHub.options.pieid, '_blank');
        });
    }
};




var AdminDialog  = {
    init: function() {
        var self = this;

        $('#dadmin').dialog({
            title: 'Administration tools',
            autoOpen: false,
            modal: false,
            width: 450,
            height: 450,
            minWidth : 300,
            minHeight: 150,
            resizable: true,
            position: 'center',
            buttons: [
               {
                    text: "Close",
                    click: function() {
                        $(this).dialog("close");
                    }
                }
            ]
        });
        $('#dadmin-mass-slider').slider({
            min: 0,
            max: 9,
            values: [0, 9],
            slide: function(e, ui) {
                if (ui.values[1] < ui.values[0]) return false;
                var range = ui.values[0] + ' - ' + ui.values[1];
                $('#dadmin-range-help').text(range);
            }
        });
        $('#dadmin-am-toggle').click(function() { me.toggleAdminMode(); });
        $('#dadmin-mass-apply').click(function() { self.applyMassStatusChange(); });
        $('#dadmin-free-all').click(function() { self.freePieces('all'); });
        $('#dadmin-free-completed').click(function() { self.freePieces('completed'); });

        //this.show();
    },

    show: function() {
        $('#dadmin').dialog('open');
    },

    applyMassStatusChange: function() {
        var $slider = $('#dadmin-mass-slider');
        var range = [$slider.slider('values', 0), $slider.slider('values', 1)];
        var raise = [];
        var lower = [];
        _(kmllayer.features).each(function (feature) {
            var state = parseInt(feature.attributes.description, 10);
            var index = parseInt(feature.attributes.name, 10);
            if (state < range[0]) {
                raise.push(index);
            } else if (state > range[1]) {
                lower.push(index);
            }
        });

        if (raise.length + lower.length === 0) {
            alert('Nothing should be changed');
            return;
        }

        var msg = 'This modification will do:<br/>';
        if (raise.length)
            msg = msg + '- raise status to ' + range[0] + ' for ' + raise.length + ' slice(s)<br/>';
        if (lower.length)
            msg = msg + '- lower status to ' + range[1] + ' for ' + lower.length + ' slice(s)<br/>';
        msg = msg + '<br/>Are you sure? Note that this is <strong>unrevertable</strong> change!';

        AdminConfirm(msg, function() {
            var actions = [];
            actions.push({ state: range[0], indexes: raise });
            actions.push({ state: range[1], indexes: lower });
            PieHub.push( Out.piece_mass_state(actions) );
        });
     },

    freePieces: function(condition) {
        var indexes = [];
        _(kmllayer.features).each(function (feature) {
            var index = parseInt(feature.attributes.name, 10);
            var state = parseInt(feature.attributes.description, 10);
            if (_.isUndefined(feature.attributes.owner) || feature.attributes.owner === null)
                return;
            if (condition === 'completed' && state != 9)
                return;

            indexes.push(index);
        });

        if (indexes.length === 0) {
            alert('Nothing should be changed');
            return;
        }

        var msg = 'This modification will free ' + indexes.length + ' slice(s)<br/>';
        msg = msg + '<br/>Are you sure? Note that this is <strong>unrevertable</strong> change!';
        AdminConfirm(msg, function() {
            PieHub.push( Out.piece_mass_free(indexes) );
        });

    }
};


function config_get(key, defval) {
    if (typeof(MapCraft) == 'undefined' || typeof(MapCraft.config) == 'undefined') {
        return defval;
    }
    return MapCraft.config[key];
}

var TextReplacer = {
    shortcuts: {
        c: 'http://www.openstreetmap.org/browse/changeset/%s',
        n: 'http://www.openstreetmap.org/browse/node/%s',
        w: 'http://www.openstreetmap.org/browse/way/%s',
        r: 'http://www.openstreetmap.org/browse/relation/%s',
        '#': 'SelectPiece(%s)',
        'bug:': 'https://github.com/Foxhind/MapCraft/issues/%s',
        'user:': 'http://www.openstreetmap.org/user/%s'
    },

    // Using template, replacment  value and link text
    // it creates new link for the chat
    create_elem: function(tpl, val, text) {
        var elem;
        var url = tpl.replace(/\%s/, val);
        if(url.indexOf('http') == 0) {
            elem = '<a href="' + url + '" target="_blank">' + text  + '</a>';
        } else {
            elem = '<span class="pseudolink" onclick="' + url + '">' + text + '</span>';
        }
        return elem;
    },

    // Returns replacement value if the string matches template
    match: function(tpl, str) {
        tpl = tpl + '!END!';
        str = str + '!END!';
        var parts = tpl.split('%s');
        if (str.indexOf(parts[0]) != 0) {
            return null;
        }
        var i_beg = parts[0].length;
        var i_end = str.indexOf(parts[1]);
        if (i_end == -1) {
            return null;
        }
        return str.substr(i_beg, i_end - i_beg);
    },

    // Searches is there any template in shortcuts that matches given url
    search_in_shortcuts: function(url) {
        for (sc in this.shortcuts) {
            var tpl = this.shortcuts[sc];
            var val = this.match(tpl, url);
            if ( val != null ) {
                return {sc: sc, val: val};
            }
        }
        return null;
    },

    // converts given token to <a>..</a> if this is a shortcut or URL
    format_token: function(token) {
        var m;
        // shortcut<ID> -> url
        if( (m = token.match(/^([a-zA-Z#]+)(\d+)$/)) != null) {
            var sc = m[1].toLowerCase();
            if (sc in this.shortcuts) {
                return this.create_elem(this.shortcuts[sc], m[2], token);
            }
        }

        // shortcut:<TEXT> -> url
        if( (m = token.match(/^([a-zA-Z]+:)(\w+)$/)) != null) {
            var sc = m[1].toLowerCase();
            if (sc in this.shortcuts) {
                return this.create_elem(this.shortcuts[sc], m[2], token);
            }
        }

        // any other URL
        if( (m = token.match(/^(?:https?|ftp):\/\/\S+$/)) != null) {
            var data = this.search_in_shortcuts(m[0]);
            if (data != null) {
                return this.create_elem(m[0], '', data.sc + data.val);
            } else {
                return this.create_elem(m[0], '%s', m[0]);
            }
        }
        return token;
    },

    // separates next token from the string
    // returns: { head, sep, tail }.
    // given string = head + sep + tail;
    next: function(str) {
        var m;
        if ( (m= str.match(/^((?:https?|ftp):\S+)(\s*)(.*)$/)) != null ) {
            return {head: m[1], sep: m[2], tail: m[3]};  // URL found
        }
        if ( (m = str.match(/^(\S*?)([\s\(\)\,\.\;\!\?]+)(.*)$/)) != null ) {
            return {head: m[1], sep: m[2], tail: m[3]};  // simple token found
        }
        return {head: str, sep: "", tail: ""};
    },

    // tokenize string and replace all links and shortcuts in it
    parse: function(str) {
        if(str == "") { return str; }
        var next = this.next(str);
        return this.format_token(next.head) + next.sep + this.parse(next.tail);
    }
}

var In = {};
var Out = {};

In.dump = function(data) {
    console.log(data);
};

In.chat = function (data) {
    if (typeof(data['message']) == 'undefined')
        return false;

    Chat.append(data['message'], data['class'], data['author'], data['date'], data['history']);
    if ($('#dchat').hasClass('project-chat_collapsed') && !data['history']) {
        chatUnread++;
        updateUnread();
    }

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
    sectorHistory = [];
    renderSectorHistory();
}

In.piece_comment = function (data) {
    if (selectedFeature == null)
        return;
    if (data['piece_index'].toString() == selectedFeature.attributes.name) {
        var comments_div = $('#comments');
        if ($('.sector-history__loading', comments_div).length != 0)
            comments_div.html('');

        var msg = data['type'] == 'comment' ? data['message'] : t(data['message']);
        msg = TextReplacer.parse(msg);
        var date = data['date'].replace(/\.\d+$/, '');
        sectorHistoryEvent(data['type'] === 'comment' ? 'comment' : 'state', msg, data['author'], date);
    }
};

In.piece_owner = function (data) {
    var pieces = kmllayer.getFeaturesByAttribute('name', data['piece_index']);
    if (pieces.length > 0) {
        var isFreeing = (data['owner'] == '' && typeof(pieces[0].attributes.owner) != 'undefined');
        pieces[0].attributes.owner = isFreeing ? null : data['owner'];
        updatePieceStyle(pieces[0], true);

        if (selectedFeature != null && data['piece_index'].toString() == selectedFeature.attributes.name) {
            sectorHistoryEvent(isFreeing ? 'free' : 'reserve', isFreeing ? 'Сектор освобождён' : 'Сектор зарезервирован', data['owner']);
            renderSectorCard('Данные синхронизированы');
        }
    }
}

In.piece_state = function (data) {
    var pieces = kmllayer.getFeaturesByAttribute('name', data['piece_index']);
    if (pieces.length > 0)
    {
        pieces[0].attributes.description = data['state'];
        updatePieceStyle(pieces[0], true);
    }
    if (selectedFeature != null && data['piece_index'].toString() == selectedFeature.attributes.name) {
        selectedProgress = parseInt(data['state'], 10);
        sectorHistoryEvent('state', 'Готовность изменена: ' + data['state'] + '/9');
        renderSectorCard('Готовность обновлена');
    }
};

In.piece_progress = function(data) {
    // The redesigned map does not show the legacy aggregate progress widget.
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
    me.update(data);
    $('#pac_nick').text(me.nick.charAt(0).toUpperCase()).attr('title', me.nick);
    if (data['role'] == 'anon')
        $('#pac_nick').click(function() { window.open('/app/auth.php'); } );
    else
        $('#pac_nick').unbind('click');
    $("#pac_text").focus();

    renderSectorCard();
    PieHub.push( Out.get_user_list() );
};

In.cake_info = function (data) {
    CakeSettings.init(data);
};

In.after_init = function (data) {
    selectPieceFromURL();
};

Out.claim = function (piece_index) {
    if (typeof(piece_index) != 'string' && typeof(piece_index) != 'number') return false;
    return ['claim', {piece_index: piece_index.toString()}];
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

Out.get_piece_comments = function (piece_index) {
    return ['get_piece_comments', {piece_index: piece_index}];
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

Out.piece_comment = function (piece_index, comment) {
    if (typeof(piece_index) != 'string' && typeof(piece_index) != 'number') return false;
    if (typeof(comment) != 'string') return false;
    return ['piece_comment', {piece_index: piece_index.toString(), comment: comment}];
};

Out.piece_state = function (piece_index, state) {
    if (typeof(piece_index) != 'string' && typeof(piece_index) != 'number') return false;
    if (typeof(state) != 'string' && typeof(state) != 'number') return false;
    return ['piece_state', {
        piece_index: piece_index.toString(),
        state: state.toString(),
        admin_mode: me.inAdminMode()
    }];
};

Out.piece_mass_state = function (actions) {
    return ['piece_mass_state', {actions: actions}];
};


Out.piece_reserve = function (piece_index) {
    if (typeof(piece_index) != 'string' && typeof(piece_index) != 'number') return false;
    return ['piece_reserve', {piece_index: piece_index.toString()}];
};

Out.piece_free = function (piece_index) {
    if (typeof(piece_index) != 'string' && typeof(piece_index) != 'number') return false;
    return ['piece_free', {
        piece_index: piece_index.toString(),
        admin_mode: me.inAdminMode()
    }];
};

Out.piece_mass_free = function (indexes) {
    return ['piece_mass_free', {indexes: indexes}];
};


Out.piece_progress = function () {
    return ['piece_progress', {}];
};

Out.vote_claim = function (claim_id, vote) {
    if (typeof(claim_id) != 'string' && typeof(piece_index) != 'number') return false;
    if (typeof(vote) != 'string' && typeof(vote) != 'number') return false;
    return ['vote_claim', {claim_id: claim_id.toString(), vote: vote.toString()}];
};

Out.whoami = function() {
    return ['whoami', {}];
};

Out.update_cake = function(data) {
    return ['update_cake', {data: data}];
};

Out.init_session = function() {
    return ['init_session', {}];
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
    // Show owned
    showOwned = (localStorage.show_owned === '') ? false : true; // true by default
    $('#sshow_owned').attr('checked', showOwned);

    // Piece style
    if (localStorage.piece_label) {
        pieceLabel = localStorage.piece_label;
        $('#spiece_label').val(localStorage.piece_label);
    }
    if (localStorage.piece_color) {
        pieceColor = localStorage.piece_color;
        $('#spiece_color').val(localStorage.piece_color);
    }
    updateAllPieceStyles();

    chatShowInfo = (localStorage.chat_show_info === '')? false : true;  // true by default
    $('#schat_show_info').attr('checked', chatShowInfo);
    Chat.updateStyle();
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

    // Piece style
    if ($('#spiece_label').val() != localStorage.piece_label) {
        pieceLabel = localStorage.piece_label = $('#spiece_label').val();

    }
    if ($('#spiece_color').val() != localStorage.piece_color) {
        pieceColor = localStorage.piece_color = $('#spiece_color').val();

    }
    showOwned = $('#sshow_owned').attr('checked') ? true : false;
    localStorage.show_owned = showOwned ? 'show' : '';
    updateAllPieceStyles();

    chatShowInfo = $('#schat_show_info').attr('checked') ? true : false;
    localStorage.chat_show_info = chatShowInfo ? 'show' : '';
    Chat.updateStyle();
}

function LoadLanguage() {
    $.get("/js/lang/" + $('#slang').val() + ".js", {}, function() {
        LoadTransData();
        $('#pac_text').attr('placeholder', ldata[23] || 'Написать сообщение…');
        renderSectorCard();
    });
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

function selectPieceFromURL() {
    var hash = window.location.hash;
    hash = hash.charAt(0) == '#' ? hash.substring(1, hash.length) : hash;
    if (hash === '') {
        return;
    }

    SelectPiece(hash);
    if (selectedFeature !== null) {
        olmap.zoomToExtent(selectedFeature.geometry.getBounds());
        olmap.zoomOut();
    }
}

function OpenViaRemote(method) {
    if (method === null) {
        method = 'josm';
    }
    if (selectedFeature != null)
    {
        var from = new OpenLayers.Projection("EPSG:900913");
        var to = new OpenLayers.Projection("EPSG:4326");
        var bounds = selectedFeature.geometry.getBounds().toArray();
        var p1 = (new OpenLayers.LonLat(bounds[0], bounds[1])).transform(from, to);
        var p2 = (new OpenLayers.LonLat(bounds[2], bounds[3])).transform(from, to);

        if (method === 'josm') {
            RemoteControlJosm("load_and_zoom?left=" + p1.lon + "&right=" + p2.lon + "&top=" + p2.lat + "&bottom=" + p1.lat);
        }
        if (method === 'potlatch2') {
            RemoteControlPotlatch('http://www.openstreetmap.org/?lat=' + ((p1.lat + p2.lat) /2)+'&lon=' + ((p1.lon + p2.lon)/2) + '&zoom=19&layers=M');
        }
    }
}

function RemoteControlJosm(cmd) {
    if ( !$('#hiddenIframe').length ){
        $('body').append('<iframe id="hiddenIframe" style="display:hidden" />');
    }
    $('#hiddenIframe').attr("src", "http://127.0.0.1:8111/" + cmd);
}

function RemoteControlPotlatch(cmd) {
    window.open(cmd, "_blank");
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
            sreserved += ("<span class='num project-chat__sector-link'>" + users[u]['reserved'][i] + "</span> ");
        var userclaims = [];
        for (i = 0; i < claims.length; i++) {
            if (claims[i]['owner'] == users[u]['user_nick'])
                userclaims.push(claims[i]);
        }
        for (i = 0; i < userclaims.length; i++) {
            if (me.nick == users[u]['user_nick'])
                sclaims += ("<span class='claim ui-state-default project-chat__claim'><span class='num project-chat__sector-link'>" + userclaims[i]['piece_index'] + "</span>&nbsp;[" + userclaims[i]['vote_balance'] + "&nbsp;<div title='Снять заявку' class='close'></div>]</span> ");
            else
                sclaims += ("<span class='claim ui-state-default project-chat__claim'><span class='num project-chat__sector-link'>" + userclaims[i]['piece_index'] + "</span>&nbsp;[<div title='За' class='up'></div>&nbsp;" + userclaims[i]['vote_balance'] + "&nbsp;<div title='Против' class='down'></div>]</span><br />");
        }
        newhtml += ("<tr><td class='nick project-chat__member-name'>" + (users[u]['online'] ? "<img src='/img/onl.png'>&nbsp;" : "") + "<span class='nickname'>" + users[u]['user_nick'] + "</span></td><td class='msg project-chat__member-sectors'>" + sreserved + "</td><td>" + sclaims + "</td></tr>");
    }
    newhtml += "</table>";
    $('#userlist-table').html(newhtml);
    $('.nickname').click( function() { $('#pac_text').val($('#pac_text').val() + $(this).text() + ': '); $("#pac_text").focus(); } );
    $('.up').click( function() {
        var piece_index = $(this).parent().find('span.num').text();
        var user_nick = $(this).parent().parent().parent().find('span.nickname').text();
        for (var i = 0; i < claims.length; i++) {
            if (claims[i]['owner'] == user_nick && claims[i]['piece_index'] == piece_index) {
                Vote(claims[i]['claim_id'], 1);
                break;
            }
        }
        $(this).remove(); } );
    $('.down').click( function() {
        var piece_index = $(this).parent().find('span.num').text();
        var user_nick = $(this).parent().parent().parent().find('span.nickname').text();
        for (var i = 0; i < claims.length; i++) {
            if (claims[i]['owner'] == user_nick && claims[i]['piece_index'] == piece_index) {
                Vote(claims[i]['claim_id'], -1);
                break;
            }
        }
        $(this).remove(); } );
    $('.close').click( function() {
        var piece_index = $(this).parent().find('span.num').text();
        var user_nick = $(this).parent().parent().parent().find('span.nickname').text();
        for (var i = 0; i < claims.length; i++) {
            if (claims[i]['owner'] == user_nick && claims[i]['piece_index'] == piece_index) {
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
    PieHub.push( Out.piece_progress() );
    PieHub.push( Out.init_session() );
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
        var is_cmd = Chat.handleCliCommand(text);

        if (!is_cmd) {
            PieHub.push(Out.chat(text));
        }
        $("#pac_text").val("");
    }
    $("#pac_text").focus();
    return false;
}

function SetStatus() {
    if (selectedFeature == null || selectedProgress === null) {
        return;
    }

    const serverState = parseInt(selectedFeature.attributes.description, 10) || 0;

    if ((selectedProgress < serverState || selectedProgress === 9) && !window.confirm('Подтвердите изменение готовности сектора до ' + selectedProgress + '/9.')) {
        return;
    }

    PieHub.push(Out.piece_state(selectedFeature.attributes.name, selectedProgress));
    renderSectorCard('Сохраняем…');
}

function ScrollDown(to) {
    var chatbox = $('#chat');
    if (typeof(to) == 'undefined')
        chatbox.scrollTop(chatbox.attr("scrollHeight") - chatbox.height());
    else
        chatbox.scrollTop(to);
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
    selectedProgress = parseInt(e.attributes.description, 10) || 0;
    sectorHistory = [];
    updatePieceStyle(e, true);
    window.location.hash = e.attributes.name;
    $('#comments').html('<div class="sector-history__loading"></div>');
    renderSectorCard('Загружаем историю…');
    GetComments();
}

function onUnselectPiece(e) {
    selectedFeature = null;
    selectedProgress = null;
    sectorHistory = [];
    updatePieceStyle(e, true);
    window.location.hash = '';
    renderSectorCard();
    renderSectorHistory();
}

function updatePieceStyle(e, redraw) {
    var owner = e.attributes.owner;
    var state = parseInt(e.attributes.description, 10);
    var index = e.attributes.name;

    // Set label
    if (pieceLabel == 'owner' && owner) {
        e.style.label = owner;
    } else if (pieceLabel == 'index') {
        e.style.label = index;
    } else {
        e.style.label = null;
    }
    e.style.fontSize = 11;

    // Set color
    var fillColor, hue, sat, light;
    if (!showOwned && owner) {
        fillColor = "rgba(255,255,255,0)";
    } else if (pieceColor == 'state') {
        fillColor = stateColors[state];
    } else if (pieceColor == 'busy') {
        hue = state == '9' ? 120 : 60;
        fillColor = owner ? 'hsl(' + hue + ', 100%, 50%)' : 'hsl(' + hue + ', 70%, 85%)';
    } else if (pieceColor == 'owner') {
        fillColor = owner ? ownerColor(owner) : "hsl(0,100%,100%)";
    } else {
        fillColor = "rgba(255,255,255,0)";
    }
    e.style.fillColor = fillColor;

    // set Opacity and stroke for selected
    var selected = e == selectedFeature;
    e.style.fillOpacity = selected ? '0.78' : '0.52';
    e.style.strokeColor = selected ? '#364bff' : '#202a3d';
    e.style.strokeWidth = selected ? '3' : '1';
    e.style.graphicZIndex = selected ? 10 : 1;

    if(redraw) {
        kmllayer.redraw(true);
    }
}

function updateAllPieceStyles() {
    var features = kmllayer.features;
    for( i in features) {
        updatePieceStyle(features[i], false);
    }
    kmllayer.redraw();
}

function SetStyle() {
    // Themes were coupled to the removed floating-window interface.
}

$(document).ready(function () {
    // The workspace layout intentionally uses only jQuery and native controls.
    PieHub.init({
        pieid: parseInt(window.location.pathname.split('pie/')[1]),
        hub_url: config_get('hub_url', '/hub'),
        poll_callback: Dispatch
    });
    PieHub.poll();
    Enter();

    const options = {
        controls: [
            new OpenLayers.Control.Navigation(),
            new OpenLayers.Control.ScaleLine(),
            new OpenLayers.Control.Permalink(),
            new OpenLayers.Control.Attribution()
        ],
        projection: new OpenLayers.Projection('EPSG:900913'),
        displayProjection: new OpenLayers.Projection('EPSG:4326'),
        units: 'm',
        numZoomLevels: 18,
        maxResolution: 156543.0339,
        maxExtent: new OpenLayers.Bounds(-20037508, -20037508, 20037508, 20037508.34)
    };
    olmap = new OpenLayers.Map(document.getElementById('olmap'), options);
    const mapnik = new OpenLayers.Layer.OSM();
    const pieId = window.location.pathname.split('pie/')[1];
    const kmlFormat = new OpenLayers.Format.KML({
        extractStyles: true,
        extractAttributes: true,
        maxDepth: 0
    });
    const kmlProtocol = new OpenLayers.Protocol.HTTP({
        url: `/kml/${pieId}.kml`,
        format: kmlFormat
    });

    kmllayer = new OpenLayers.Layer.Vector('Pie', {
        strategies: [new OpenLayers.Strategy.Fixed()],
        protocol: kmlProtocol,
        projection: 'EPSG:4326'
    });
    olmap.addLayers([mapnik, kmllayer]);
    selectCtrl = new OpenLayers.Control.SelectFeature(kmllayer, {
        clickout: true,
        onSelect: onSelectPiece,
        onUnselect: onUnselectPiece
    });
    selectCtrl.handlers.feature.stopDown = false;
    olmap.addControl(selectCtrl);
    selectCtrl.activate();
    kmllayer.events.register('loadend', this, function() {
        olmap.zoomToExtent(kmllayer.getDataExtent());
        olmap.zoomOut();
        updateAllPieceStyles();
    });

    $('#bzoomp').click(() => {
        olmap.zoomIn();
    });
    $('#bzoomm').click(() => {
        olmap.zoomOut();
    });
    $('#bfit').click(() => {
        const extent = kmllayer.getDataExtent();

        if (extent) {
            olmap.zoomToExtent(extent);
        }
    });
    $('#sector-search').keydown(function(e) {
        if (e.keyCode === 13) {
            SelectPiece($(this).val().replace(/[^0-9]/g, ''));
            return false;
        }
    });
    $(document).keydown(function(e) {
        if (e.keyCode === 191 && !$(e.target).is('input, textarea')) {
            e.preventDefault();
            $('#sector-search').focus();
        }

        if (e.keyCode === 27) {
            $('#map-popover').attr('hidden', 'hidden');
            $('#sector-sidebar').removeClass('sector-sidebar_open');
        }
    });
    $('#sector-card').delegate('[data-progress]', 'click', function() {
        selectedProgress = parseInt($(this).attr('data-progress'), 10);
        renderSectorCard('Готовность изменена. Сохраните, чтобы применить.');
    }).delegate('#sector-primary', 'click', function() {
        if (!selectedFeature) {
            return;
        }

        if (!selectedFeature.attributes.owner) {
            TakePiece();
        } else if (selectedFeature.attributes.owner === me.nick) {
            SetStatus();
        } else {
            ClaimPiece();
        }
    }).delegate('#sector-release', 'click', () => {
        if (selectedFeature && window.confirm('Освободить этот сектор?')) {
            RefusePiece();
        }
    }).delegate('#sector-josm', 'click', () => {
        OpenViaRemote('josm');
    }).delegate('#sector-osm', 'click', () => {
        OpenViaRemote('potlatch2');
    });
    $('#show-full-history').click(function() {
        $(this).data('expanded', !$(this).data('expanded'));
        renderSectorHistory();
    });
    $('#chat-toggle').click(function() {
        const closed = $('#dchat').toggleClass('project-chat_collapsed').hasClass('project-chat_collapsed');
        $(this).attr('aria-expanded', !closed);
        localStorage.mapcraftChatCollapsed = closed ? '1' : '';

        if (!closed) {
            chatUnread = 0;
            updateUnread();
            ScrollDown();
        }
    });

    if (localStorage.mapcraftChatCollapsed === '1') {
        $('#chat-toggle').click();
    }

    const savedChatHeight = parseInt(localStorage.mapcraftChatHeight, 10);

    if (savedChatHeight >= 160) {
        $('#dchat').css('height', `${savedChatHeight}px`);
    }

    $(document).mouseup(() => {
        if (!$('#dchat').hasClass('project-chat_collapsed')) {
            localStorage.mapcraftChatHeight = $('#dchat').height();
        }
    });
    $('#pac_form').submit(Send);
    $('#pac_text').keydown(function(e) {
        if (e.keyCode === 13 && !e.shiftKey) {
            e.preventDefault();
            Send();
        }
    });
    $('#bsettings').click(() => {
        const checked = showOwned ? ' checked' : '';
        $('#map-popover')
            .html(`<label><input id="toggle-owned" type="checkbox"${checked}> Показывать занятые сектора</label>`)
            .removeAttr('hidden');
    });
    $('#bfilters').click(() => {
        $('#map-popover')
            .html(`
                <label><input id="filter-free" type="checkbox"> Только свободные</label>
                <label><input id="filter-mine" type="checkbox"> Только мои</label>
                <label><input id="filter-ready" type="checkbox"> Скрыть готовые</label>`)
            .removeAttr('hidden');
    });
    $('#blayers').click(() => {
        $('#map-popover')
            .html('<label><input id="toggle-layer" type="checkbox" checked> Секторы</label>')
            .removeAttr('hidden');
    });
    $('#map-popover').delegate('#toggle-layer', 'change', function() {
        kmllayer.setVisibility($(this).is(':checked'));
    }).delegate('#toggle-owned', 'change', function() {
        showOwned = $(this).is(':checked');
        updateAllPieceStyles();
    }).delegate('#filter-free, #filter-mine, #filter-ready', 'change', () => {
        const free = $('#filter-free').is(':checked');
        const mine = $('#filter-mine').is(':checked');
        const ready = $('#filter-ready').is(':checked');

        for (const feature of kmllayer.features) {
            const hidden = (free && feature.attributes.owner)
                || (mine && feature.attributes.owner !== me.nick)
                || (ready && parseInt(feature.attributes.description, 10) === 9);

            feature.style.display = hidden ? 'none' : '';
        }

        kmllayer.redraw();
    });
    $('#reconnect').click(() => {
        PieHub.poll();
    });
    $(window).resize(() => {
        olmap.updateSize();
    });
    renderSectorCard();
});

/*
 * Legacy jquery-ui initialization retained for historical reference only.
 * The redesigned client above is the only active startup path.
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
        olmap.zoomToExtent(kmllayer.getDataExtent());
        olmap.zoomOut(); // a bit smaller
        updateAllPieceStyles();
    });

    $('.enter-submit').live("keyup", function(e) {
        if (e.keyCode === 13) {
            $(this).parent().find('.apply-button').click();
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
        buttons: {
            "OK": function() {
                if (me.inAdminMode()) {
                    var msg = "Are you sure you want to update state for slice owned by '" + selectedFeature.attributes.owner + "'?";
                    AdminConfirm(msg, function(){
                        SetStatus();
                    });
                } else {
                    SetStatus();
                }
                $(this).dialog("close");
            },
            "Cancel": function() { $(this).dialog("close"); }
        }
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
        width: 400,
        height: 450,
        minWidth: 300,
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
    $('#bpotlatch').button({ disabled: true });
    $('#bremote').click(function() {
        OpenViaRemote('josm')
    });
    $('#bpotlatch').click(function() {
        OpenViaRemote('potlatch2')
    });
    $('#bstatus').button({disabled: true});
    $('#bstatus').click(function() {
        $('#sstatus').slider('value', selectedFeature.attributes.description);
        $('#vcolor').css({ color: stateColors[$('#sstatus').slider('value')] });
        $('#newstatus').text($('#sstatus').slider('value'));
        $('#statuscomment').text(CakeSettings.data['statuses'][$('#sstatus').slider('value')]);
        $('#dstatus').dialog('open');
    });
    $('#pac_nick').button({ icons: { primary: 'ui-icon-person'} });
    $('#dchat').dialog( { resize: function(event, ui) { $('#chat').height($(this).height() - 45); $('#chat').width($(this).width() - 30); },
        beforeClose: function(event, ui) {
            var chatbox = $("#chat");
            var isEnd = (chatbox.attr("scrollHeight") - chatbox.height() - chatbox.scrollTop() < 20);
            chatScrollPosition = isEnd ? -1 : chatbox.scrollTop();
        },
        open: function(event, ui) {
            if (chatScrollPosition == -1)
                ScrollDown();
            else
                ScrollDown(chatScrollPosition);
        }
    } );
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
    $('#binfo').button( { icons: { primary: 'ui-icon-info'} } );
    $('#binfo').click( function () { InfoDialog.show(); } );
    $('#badmin').button( { icons: { primary: 'ui-icon-info'} } );
    $('#badmin').click( function () { AdminDialog.show(); } );
    $('#bpie').button( { icons: { primary: 'ui-icon-clock'} } );
    $('#bpie').click( function() { kmllayer.setVisibility($(this).attr("checked")); });
    $('#rfull').button({icons: { primary: 'ui-icon-bullet'}});
    $('#rfull').change( function() { $("div#dchat,div#duserlist,div#dprop").dialog("open"); $("div[id^='d']").parent().css({ opacity: $('#rtrans').attr("checked") ? 0.6 : 1.0 }); });
    $('#rtrans').button({icons: { primary: 'ui-icon-radio-off'}});
    $('#rtrans').change( function() { $("div#dchat,div#duserlist").dialog("open"); $("div[id^='d']").parent().css({ opacity: $(this).attr("checked") ? 0.6 : 1.0 }); });
    $('#rnone').button({icons: { primary: 'ui-icon-radio-on'}});
    $('#rnone').change( function() { $("div[id^='d']").dialog("close"); });
    $('#vis').buttonset();
    $('#sstatus').slider({
        min: 0,
        max: 9,
        slide: function(event, ui) {
            $('#vcolor').css({color: stateColors[ui.value]});
            $('#newstatus').text(ui.value);
            $('#statuscomment').text(CakeSettings.data['statuses'][ui.value]);
        }
    });
    $('#bowner').button({ disabled: true, icons: { primary: 'ui-icon-flag'}});
    $('#pac_text').autocomplete({ source: users, position: { my : "right bottom", at: "right top"} });
    $("#pac_form").submit(Send);
    $("#pac_text").focus();

    InfoDialog.init();
    AdminDialog.init();
    LoadSettings();
*/
