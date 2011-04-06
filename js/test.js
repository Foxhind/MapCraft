

function printResult(msg, data) {
    if(data != null) {
        msg = msg + JSON.stringify(data);
    }
    $('#container').prepend($('<pre/>').append(msg));
}

function get_msg_cmd(msg) {
    return ['msg', { type: 'public', message: msg }];
}

$(function() {
    // инициализация пирога
    PieHub.init({
        pieid: 5,
        hub_url: 'http://localhost:8080/hub',   // CORS!
        poll_callback: function(data) { printResult("Got: ", data); }
    });

    // Запуск поллинга. Раз в 30 секунд будет приходить [nop, reason].
    PieHub.poll();

    // Pie set -- меняет пирог и перезапускает poll соединение
    $('#pie_set').click(function() {
        var pie_id = $('#pie_id').val();
        console.log("New pie id: " + pie_id);

        PieHub.set_pieid(pie_id);
    });

    // push -- отправило и всё. Ничего не ждёт
    $('#push').click(function() {
        var msg = $('msg').val();
        msg = get_msg_cmd(msg),

        PieHub.push(msg);

        printResult("Pushed!");
    });

    // call -- отправило и ждёт ответа. Пока только ["ok"].
    $('#call').click(function() {
        var msg = $('msg').val();
        msg = get_msg_cmd(msg),

        PieHub.call(msg, function(data) {
            printResult("Call res: ", data);
        });
    });
});