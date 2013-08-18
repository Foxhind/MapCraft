/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

var olmap;
var kmllayer;

var selectedFeature;
var selectCtrl;
var stateColors = ["#ff0000","#ff4000","#ff6000","#ff7000","#ff8000","#ff9000","#ffb000","#ffd000","#ffff00","#00ff00"];
var supported_langs = ['en', 'fr', 'it', 'jp', 'ru', 'sk'];
var users = [];
var claims = [];
var pieceLabel = 'none';
var pieceColor = 'state';
var showOwned;
var chatShowInfo = true;
var chatScrollPosition = -1;

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
        var entry = $("<tr><td class='nick' style='color: " + authorColor + "'>" + author + "</td><td class='message'>" + message + "</td><td class='time'><time datetime='" + moment(ts).format("YYYY-MM-DDTHH:mm:ssZ") + "' title='" + moment(ts).format("YYYY-MM-DD HH:mm:ss Z") + "'>" + moment(ts).format("HH:mm:ss") + "</time></td></tr>");

        entry.addClass('chat-' + type);
        if (is_history) entry.addClass('history');
        if (!chatShowInfo && type === 'info') entry.addClass('hidden');
        if (toMe) entry.addClass('tome');

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

    updateStyle: function() {
        $(".chat-info").toggleClass('hidden', !chatShowInfo);
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

        // TODO: move to events
        $('#dinfo-save').button("disable");
        InfoDialog.redraw();
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
        //TODO: move to events
        $('#dinfo-save').button("enable");
        InfoDialog.redraw();
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
        var wms_link = 'wms:' + origin + '/wms/' + PieHub.options.pieid + '?SRS={proj}&WIDTH={width}&height={height}&BBOX={bbox}';
        var log_link = origin + '/log/' + PieHub.options.pieid;
        $("#wms_link").html(this._createLink(wms_link, "WMS layer for editors"));
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
    if (data['piece_index'].toString() == selectedFeature.attributes.name) {
        var comments_div = $('#dprop #comments');
        if ($('#dprop .loading').length != 0)
            comments_div.html('');

        var msg = data['type'] == 'comment' ? data['message'] : t(data['message']);
        msg = TextReplacer.parse(msg);
        var date = data['date'].replace(/\.\d+$/, '');
        $('#dprop #comments').append('<p class="' + data['type'] + '"><strong>' + data['author'] + '</strong><span class="date">' + date + '</span><br />' + msg + '</p>');
    }
};

In.piece_owner = function (data) {
    var pieces = kmllayer.getFeaturesByAttribute('name', data['piece_index']);
    if (pieces.length > 0) {
        var isFreeing = (data['owner'] == '' && typeof(pieces[0].attributes.owner) != 'undefined');
        pieces[0].attributes.owner = isFreeing ? null : data['owner'];
        updatePieceStyle(pieces[0], true);

        if (selectedFeature != null)
            if (data['piece_index'].toString() == selectedFeature.attributes.name)
                $('#bowner').button("option", "label", (isFreeing ? ldata[12] : data['owner']) );
    }
}

In.piece_state = function (data) {
    var pieces = kmllayer.getFeaturesByAttribute('name', data['piece_index']);
    if (pieces.length > 0)
    {
        pieces[0].attributes.description = data['state'];
        updatePieceStyle(pieces[0], true);
    }
    if (selectedFeature != null) {
        if (data['piece_index'].toString() == selectedFeature.attributes.name)
            $('#bstatus').button("option", "label", data['state'].toString() + '/9');
    }
};

In.piece_progress = function(data) {
    var cnts = data['progress'];
    Progress.update(cnts, 400);
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
    $('#pac_nick').button("option", "label", me.nick);
    $('#pac_nick').button("option", "label", me.nick);
    if (data['role'] == 'anon')
        $('#pac_nick').click(function() { window.open('/app/auth.php'); } );
    else
        $('#pac_nick').unbind('click');
    $("#pac_text").focus();

    InfoDialog.redraw();
    $('#badmin').toggle(me.hasRole('admin'));
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
        $('#dchat').dialog("option", "title", ldata[4]);
        $('#duserlist').dialog("option", "title", ldata[5]);
        $('#dprop').dialog("option", "title", ldata[6]);
        $('#dsettings').dialog("option", "title", ldata[7]);
        $('#dnick').dialog("option", "title", ldata[8]);
        $('#dstatus').dialog("option", "title", ldata[9]);
        $('#bremote').button("option", "label", ldata[10]);
        $('#bpotlatch').button("option", "label", "OSM");
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
        $('#lprogress_bar').text(t("Progress bar:"));
        $('#lshow_owned').text(ldata[29]);
        $('#lchat_show_info').text(ldata[30]);
        $('#binfo').button("option", "label", ldata[31]);
        $('#lpiece_label').text(ldata[32]);
        $('#lpiece_color').text(ldata[33]);
        $('#dinfo').dialog("option", "title", ldata[34]);
        $('#badmin').button("option", "label", ldata[35]);
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
            sreserved += ("<span class='num'>" + users[u]['reserved'][i] + "</span> ");
        var userclaims = [];
        for (i = 0; i < claims.length; i++) {
            if (claims[i]['owner'] == users[u]['user_nick'])
                userclaims.push(claims[i]);
        }
        for (i = 0; i < userclaims.length; i++) {
            if (me.nick == users[u]['user_nick'])
                sclaims += ("<span class='claim ui-state-default'><span class='num'>" + userclaims[i]['piece_index'] + "</span>&nbsp;[" + userclaims[i]['vote_balance'] + "&nbsp;<div title='Снять заявку' class='close'></div>]</span> ");
            else
                sclaims += ("<span class='claim ui-state-default'><span class='num'>" + userclaims[i]['piece_index'] + "</span>&nbsp;[<div title='За' class='up'></div>&nbsp;" + userclaims[i]['vote_balance'] + "&nbsp;<div title='Против' class='down'></div>]</span><br />");
        }
        newhtml += ("<tr><td class='nick'>" + (users[u]['online'] ? "<img src='/img/onl.png'>&nbsp;" : "") + "<span class='nickname'>" + users[u]['user_nick'] + "</span></td><td class='msg'>" + sreserved + "</td><td>" + sclaims + "</td></tr>");
    }
    newhtml += "</table>";
    $('#userlist-table').html(newhtml);
    $('#pac_text').autocomplete('option', 'source', nicks);
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

function SetStatus(e) {
    PieHub.push( Out.piece_state(selectedFeature.attributes.name, $('#sstatus').slider('value')) );
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
    updatePieceStyle(e, true);
    window.location.hash = e.attributes.name;
    $("#comments").html("<div class='loading'></div>");
    $('#bowner').button("enable");
    $('#bowner').click( function() {
        if(!e.attributes.owner) {
            $('#dtake').dialog('open');
        }
        else if (e.attributes.owner == me.nick) {
            $('#drefuse').dialog('open');
        }
        else if (me.inAdminMode()) {
            var msg = "Do you really want to free slice owned by '" + e.attributes.owner + "'?";
            AdminConfirm(msg , function() {
                RefusePiece();
            });
        } else {
            $('#dclaim').dialog('open');
        }
    });
    $('#bowner').button("option", "label", e.attributes.owner ? e.attributes.owner : ldata[12]);
    $('#bowner').button("option", "icons", { primary: (e.attributes.owner ? (e.attributes.owner == me.nick ? 'ui-icon-closethick' : 'ui-icon-circle-check') : 'ui-icon-flag')});
    $("#dprop").dialog("option", "title", ldata[6] + ": " + e.attributes.name);
    $("#dprop").dialog("open");
    $('#bstatus').button("option", "label", e.attributes.description + '/9');
    $('#bstatus').button("enable");
    $('#bremote').button("enable");
    $('#bpotlatch').button("enable");
    $('#bcomment').button("enable");
    GetComments();


}

function onUnselectPiece(e) {
    selectedFeature = null;
    updatePieceStyle(e, true);
    window.location.hash = '';
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
    e.style.fillOpacity = selected ? "0.8" : "0.5";
    e.style.strokeWidth = selected ? "3" : "1";

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
});
