/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

function getquery (uri) {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.open('GET', uri, true);
    xmlhttp.send(null);
}

function get_toggle_id(e) {
    return e.id.match(/(\d+)$/)[0] || null;
}

function toggleDetails(e) {
    var toggle_id = get_toggle_id(e.target);

    $('.list_descr').each(function() {
        var id = get_toggle_id(this);
        if (id == toggle_id) {
            $('#toggle_' + id).toggleClass('closed');
            $(this).toggleClass('closed');
        } else {
            $('#toggle_' + id).addClass('closed');
            $(this).addClass('closed');
        }
    });
}


$(function() {

    // on row click expand or collapse it
    $('.list_toggle').click(toggleDetails);
});