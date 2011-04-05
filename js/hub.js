/*
 * Object for accessing to MapCraft hub.
 * It handles:
 *  * polling hub for events. on every event PieHub.poll_callback will be called
 *  * pushing data into hub (the result will be returned via poll)
 *  * genarating and saving sesid (session id)
 */

PieHub = {
    sesid: null,
    pieid: null,
    hub_url: '/hub',

    polling: false,
    poll_callback: null,

    /*
     * init (PieId, poll_callback)
     */
    init: function(pieid, callback) {
        this.sesid = this.get_sesid();
        this.pieid = pieid;
        this.poll_callback = callback;
    },

    /*
     * Polling: poll - after first call it will be called periodically
     */
    poll: function() {
        var self=this;
        var timeout = 10;

        this.poll_xhr = jQuery.ajax({
            type: 'GET',
            url: this.get_poll_url('pie'),
            cache: false,
            dataType: 'json',
            success: function (data) {
                self.poll_callback(data);
            },
            error: function(data) {
                timeout = 5000;
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
        jQuery.ajax({
            type: 'POST',
            url: this.get_poll_url('pie'),
            data: JSON.stringify(['async', data]),
            //dataType: 'json',
            //success: cb,
            //error: err_cb
        });
    },

    /*
     * Sync call -- will wait for answer
     */
    call: function(data, cb, err_cb) {
        jQuery.ajax({
            type: 'POST',
            url: this.get_poll_url('call'),
            data: JSON.stringify(['sync', data]),
            dataType: 'json',
            success: cb,
            error: err_cb
        });
    },

    /*
     * Getters
     */
    get_poll_url: function(part) {
        return this.hub_url + '/' + part + '/' + this.pieid + '/' + this.sesid;
    },
    get_sesid: function() {
        var id = this.load_sesid() || this.gen_sesid();
        this.store_sesid(id);
        return id;
    },

    /*
     * Setters
     */
    set_pieid: function(pieid) {
        this.pieid = pieid;
        this.restart_poll();
    },

	/*
	 * Session save and restore sessionID
	 */
	store_sesid: function(id) {
        localStorage.sesid = id || this.sesid;
	},
	load_sesid: function() {
		return localStorage.sesid;
	},
    gen_sesid: function() {
        var templ = 'xxxxxxxxxxxxx';
        var id = templ.replace(/x/g, function(c) { return (Math.random()*16|0).toString(16); });
        return id;
    }
};
