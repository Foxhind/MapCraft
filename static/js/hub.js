/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

/*
 * Object for accessing to MapCraft hub.
 * It handles:
 *  * polling hub for events. on every event PieHub.poll_callback will be called
 *  * pushing data into hub (the result will be returned via poll)
 *  * genarating and saving sesid (session id)
 */

PieHub = {
    options: {
        pieid: null,
        hub_url: '/hub',
        poll_callback: null,
        ses_init_url: "/app/init_session.php"
    },
    myid: null,
    registered: false,
    deferred: [],

    /*
     * init (PieId, poll_callback)
     */
    init: function(options) {
        $.extend(this.options, options);

        // Init my id (can be async process)
        // On finish register us in the hub
        this.init_myid(function() {
            PieHub.register_channel();
        });
    },

    /*
     * Polling: poll - after first call it will be called periodically
     */
    progressive_timeout: 100,
    poll: function() {
        var self = this;

        // defer it if the channel is not registered yet
        if(!this.registered) {
            this.deferred.push(arguments);
            return;
        }

        var timeout = 10;
        this.poll_xhr = jQuery.ajax({
            type: 'GET',
            url: this.get_poll_url('pie'),
            cache: false,
            //dataType: 'json',
            success: function (data) {
                self.progressive_timeout = 100;
                self.handle_events(data);
            },
            error: function(data) {
                timeout = self.progressive_timeout;
                self.progressive_timeout += 100;
            },
            complete: function(res) {
                setTimeout(function() {self.poll();}, timeout);
            }
        });
    },
    restart_poll: function() {
        if(this.poll_xhr) {
            this.poll_xhr.abort();
        }
    },

    /*
     * Pushing: just call this func. Answer will be sent using poll connection
     */
    push: function(data) {
        var self = this;

        // defer it if the channel is not registered yet
        if(!this.registered) {
            this.deferred.push(arguments);
            return;
        }

        var event = "async!json:" +  JSON.stringify(data);
        jQuery.ajax({
            type: 'POST',
            url: this.get_poll_url('pie'),
            data: event,
            //dataType: 'json',
            //success: cb,
            //error: err_cb
        });
    },

    /*
     * Sync call -- will wait for answer
     */
    call: function(data, cb, err_cb) {
        var self = this;

        // defer it if the channel is not registered yet
        if(!this.registered) {
            this.deferred.push(arguments);
            return;
        }

        var event = "sync!json:" +  JSON.stringify(data);
        jQuery.ajax({
            type: 'POST',
            url: this.get_poll_url('pie'),
            data: event,
            dataType: 'json',
            success: cb,
            error: err_cb
        });
    },

    /*
     * Registering me in the hub
     */
    register_channel: function() {
        var self = this;

        this.poll_xhr = jQuery.ajax({
            type: 'GET',
            url: this.get_poll_url('pie') + '/init',
            cache: false,
            success: function (data) {
                self.registered = true;
                while( (args = self.deferred.shift()) ) {
                    args.callee.apply(self, args);
                }
            },
            error: function(data) {
                throw "Failed to register channel in the hub!";
            }
        });
    },

    /*
     * Event handling
     */
    handle_events: function (data) {
        var events = data.split("\n");
        for ( i in events ) {
            try {
                this.handle_event(events[i]);
            } catch (err) {
                console.error("Error while handling event: ", events[i], err);
            }
        }
    },
    handle_event: function (event) {
        // Simplest implementation for now
        if (event == '') {
            return;
        }

        m = event.match(/^event!json:(.+)/);
        if( m == null) {
            //console.error("Failed to parse event: " + event);
        }

        var json = m[1];
        if ( json == null ) {
            //console.error("Failed to find json in event: " + event);
        }

        var data = JSON.parse(json);
        this.options.poll_callback(data);
    },

    /*
     * Getters
     */
    get_poll_url: function(part) {
        return this.options.hub_url + '/' + part + '/' + this.options.pieid + '/' + this.myid;
    },

    /*
     * Setters
     */
    set_pieid: function(pieid) {
        this.options.pieid = pieid;
        this.restart_poll();
    },


    /*
     * Session ID sync/async loading
     */
    read_sesid_from_cookie: function() {
        var ca = document.cookie.split(/\s*;\s*/);
        for (var i = 0; i < ca.length; i++) {
            var cookie = ca[i].split('=', 2);
            if(cookie[0] == 'PHPSESSID') {
                return cookie[1];
            }
        }
        return false;
    },
    load_sesid: function(cb) {
        // try to read sesid from cookie
        var sesid = this.read_sesid_from_cookie();
        if(sesid) {
            cb(sesid);
            return true;
        };
        // on fail try to do ajax request to php script
        jQuery.get(this.options.ses_init_url, function(data) {
            cb(data);
        });
        return true;
    },
    gen_random: function(templ) {
        return templ.replace(/X/g, function(c) { return (Math.random()*16|0).toString(16); });
    },
    init_myid: function(cb) {
        // Load sesid, on finish set myid = SesId/TabId and continue init process
        this.load_sesid(function(sesid) {
            PieHub.myid  = sesid + "/" + PieHub.gen_random("XXXXXX");
            cb();
        });
    }
};
