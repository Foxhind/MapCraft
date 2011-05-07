
function getquery (uri) {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.open('GET', uri, true);
    xmlhttp.send(null);
}

function toggleDetails(e) {

    // Do not expand on link
    if ($(e.target).context.localName == 'a') {
        return true;
    }

    var to_show = this.nextSibling;
    $('.desc').each(function() {
        var $div = $('div', this).first();
        show = $div.css('height') == '0px' && this == to_show;

        $div.css('height', show ? 'auto' : '0');
        $div.css('padding', show ? '10px' : '0');
    });

    return false;
}


$(function() {

    // on row click expand or collapse it
    $('.entry').click(toggleDetails);
});