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
        poll_callback: null
    },
    myid: null,

    /*
     * init (PieId, poll_callback)
     */
    init: function(options) {
        $.extend(this.options, options);
        this.myid = this.gen_myid();
    },

    /*
     * Polling: poll - after first call it will be called periodically
     */
    progressive_timeout: 100,
    poll: function() {
        var self=this;
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
            console.error("Failed to parse event: " + event);
        }

        var json = m[1];
        if ( json == null ) {
            console.error("Failed to find json in event: " + event);
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
	 * Session save and restore sessionID
	 */
	store_sesid: function(id) {
        // skip, we do not store into cookie
	},
    load_sesid: function() {
        var ca = document.cookie.split(/\s*;\s*/);
        for (var i = 0; i < ca.length; i++) {
            var cookie = ca[i].split('=', 2);
            if(cookie[0] == 'PHPSESSID') {
                return cookie[1];
            }
        }
        return false;
    },
    gen_random: function(templ) {
        return templ.replace(/X/g, function(c) { return (Math.random()*16|0).toString(16); });
    },
    // MyID = SesId/TabId
    gen_myid: function() {
        var id = this.load_sesid() || this.gen_random("XXXXXXXXXXXXXX");
        this.store_sesid(id);
        return id + "/" + this.gen_random("XXXXXX");
    }
};
