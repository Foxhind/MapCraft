<div id="pageheader" style="background-color: #92836c;">Создание нового пирога</div>
<?php

require 'update_kml.php';

$osm_user = $_SESSION['osm_user'];
if (isset($osm_user)) {
    if (isset($_POST['captcha'])) {
/*         if ($_SESSION['security_code'] != strtolower($_POST['captcha'])) {
            unset($_SESSION['security_code']);
            echo 'Неправильно введена капча.<br /><a href="javascript:history.back();">Назад</a>';
            exit();
        } */
        unset($_SESSION['security_code']);
        if ($_FILES['file']['size'] > 524288) {
            echo 'Слишком большой файл.<br /><a href="javascript:history.back();">Назад</a>';
            exit();
        }
        if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
            echo 'Не указан файл с пирогом.<br /><a href="javascript:history.back();">Назад</a>';
            exit();
        }
        if (!isset($_POST['name']) or empty($_POST['name'])) {
            echo 'Не указано название.<br /><a href="javascript:history.back();">Назад</a>';
            exit();
        }

        include 'config.php';

        $user = pg_fetch_assoc(pg_query($connection, 'SELECT * FROM users WHERE nick=\''.$osm_user.'\''), 0);
        if (!$user) {
            echo 'Пользователь '.$osm_user.' не имеет прав на создание пирогов.<br /><a href="javascript:history.back();">Назад</a>';
            exit();
        }

        $coordinates = array();
        $nodes = array();
        $currentway = null;
        $bbox = null;
        function saxStartElement($parser, $name, $attrs)
        {
            global $currentway, $nodes, $bbox;
            switch($name)
            {
                case 'node':
                    $lon = floatval($attrs['lon']);
                    $lat = floatval($attrs['lat']);
                    if ($bbox == null)
                        $bbox = array($lon, $lat, $lon, $lat);
                    else {
                        if ($lon < $bbox[0]) $bbox[0] = $lon;
                        if ($lon > $bbox[2]) $bbox[2] = $lon;
                        if ($lat < $bbox[1]) $bbox[1] = $lat;
                        if ($lat > $bbox[3]) $bbox[3] = $lat;
                    }
                    $nodes[$attrs['id']] = $attrs['lon'].','.$attrs['lat'];
                    break;
                case 'way':
                    $currentway = array();
                    break;
                case 'nd':
                    if ($currentway !== null)
                        $currentway[] = $nodes[$attrs['ref']];
                    break;
                case 'tag':
                    $currentway = null;
                    break;
            };
        }

        function saxEndElement($parser, $name)
        {
            global $coordinates, $currentway;
            if ($name == 'way' and $currentway != null) {
                if ($currentway[0] == $currentway[count($currentway)-1])
                    $coordinates[] = implode(' ', $currentway);
                $currentway = null;
            }
        }

        $parser = xml_parser_create();
        xml_set_element_handler($parser,'saxStartElement','saxEndElement');
        xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING, false);

        $data = file_get_contents($_FILES['file']['tmp_name']);
        if (!xml_parse($parser, $data)) {
            echo 'Ошибка XML: '.xml_error_string(xml_get_error_code($parser)).' в строке '.xml_get_current_line_number($parser);
            echo '<br /><a href="javascript:history.back();">Назад</a>';
            exit();
        }

        xml_parser_free($parser);

        if (count($coordinates) < 2) {
            echo 'Число секторов должно быть больше одного, иначе вся эта затея не имеет смысла.<br /><a href="javascript:history.back();">Назад</a>';
            exit();
        }

        // Adding pie
        $bboxcenter = json_encode( array(($bbox[0]+$bbox[2])/2, ($bbox[1]+$bbox[3])/2) );
        $result = pg_query($connection, 'INSERT INTO pies VALUES(DEFAULT, \''.pg_escape_string($_POST['name']).'\', '.pg_escape_string($user['id']).', DEFAULT, NULL, \''.pg_escape_string(htmlspecialchars($_POST['description'])).'\', '.(isset($_POST['hide']) ? 'false' : 'true').', \''.$bboxcenter.'\') RETURNING id');
        $pie_id = pg_fetch_result($result, 0, 0);

        // Adding pieces
        foreach ($coordinates as $c) {
            $result = pg_query($connection, 'INSERT INTO pieces VALUES(DEFAULT, DEFAULT, DEFAULT, '.$pie_id.', \''.$c.'\')');
        }

        // Accesses
        $raccess = array();
        $rwaccess = array();
        foreach (explode(',', $_POST['waccess']) as $username) {
            $username = trim($username);
            if (!empty($username) and $username != $osm_user)
                $rwaccess[] = $username;
        }
        foreach (explode(',', $_POST['raccess']) as $username) {
            $username = trim($username);
            if (!empty($username) and !in_array($username, $rwaccess) and $username != $osm_user)
                $raccess[] = $username;
        }
        // Adding it to table
        foreach ($raccess as $username) {
            $username = pg_escape_string($username);
            $result = pg_query($connection, 'SELECT id FROM users WHERE nick=\''.$username.'\'');
            if (pg_num_rows($result) > 0)
                $user_id = pg_fetch_result($result, 0 ,0);
            else
                $user_id = pg_fetch_result(pg_query($connection, 'INSERT INTO users VALUES(\''.$username.'\', DEFAULT, DEFAULT) RETURNING id'), 0 ,0);
            $result = pg_query($connection, 'INSERT INTO access VALUES('.$user_id.', '.$pie_id.', \''.$username.'\', \'r\')');
        }
        foreach ($rwaccess as $username) {
            $username = pg_escape_string($username);
            $result = pg_query($connection, 'SELECT id FROM users WHERE nick=\''.$username.'\'');
            if (pg_num_rows($result) > 0)
                $user_id = pg_fetch_result($result, 0 ,0);
            else
                $user_id = pg_fetch_result(pg_query($connection, 'INSERT INTO users VALUES(\''.$username.'\', DEFAULT, DEFAULT) RETURNING id'), 0 ,0);
            $result = pg_query($connection, 'INSERT INTO access VALUES('.$user_id.', '.$pie_id.', \''.$username.'\', \'rw\')');
        }
        // And owner
        $result = pg_query($connection, 'SELECT id FROM users WHERE nick=\''.$osm_user.'\'');
        if (pg_num_rows($result) > 0)
            $user_id = pg_fetch_result($result, 0 ,0);
        else
            $user_id = pg_fetch_result(pg_query($connection, 'INSERT INTO users VALUES(\''.$osm_user.'\', DEFAULT, DEFAULT) RETURNING id'), 0 ,0);
        $result = pg_query($connection, 'INSERT INTO access VALUES('.$user_id.', '.$pie_id.', \''.$osm_user.'\', \'o\')');

        update_kml($pie_id);
        echo 'Готово!';
    }
    else {
?>
<form action="" method="post" enctype="multipart/form-data">
<fieldset>
    <div class="row">Всю информацию, за исключением геометрии пирога, можно будет изменить в дальнейшем.</div>
    <div class="row"><div>
        <label for="name">Название <em>*</em><br/><small>Город, местность и т.п.</small></label>
        <input type="text" id="name" name="name" />
    </div><div>
        <label for="file">osm-файл <em>*</em><br/><small>Импортируются замкнутые полигоны без тегов, файл меньше 512 Кб.</small></label>
        <input type="file" id="file" name="file" />
    </div></div>
    <div class="row"><div>
        <label for="description">Описание</label>
        <textarea id="description" name="description"></textarea>
    </div></div>
    <div class="row"><div>
        <label for="raccess">Доступ к просмотру<br/><small>Список ников через запятую, пустое поле — доступ всем.</small></label>
        <input type="text" id="raccess" name="raccess" />
    </div><div>
        <label for="waccess">Доступ к изменению<br/><small>Список ников через запятую, пустое поле — доступ всем.</small></label>
        <input type="text" id="waccess" name="waccess" />
    </div></div>
    <div class="row"><div>
        <label><input class="btn" type="checkbox" id="hide" name="hide" /> Скрыть пирог<br/><small>Скрытие не влияет на права доступа!</small></label>
    </div></div>
    <div class="row"><div>
        <label for="captcha">Капча <em>*</em> <img src="captcha.php" /></label>
        <input type="text" id="captcha" name="captcha" />
    </div></div>
    <input class="btn" type="submit" value="&nbsp Создать »" />
</fieldset>
<?php
    }
}
else {
    echo 'Анонимусы не могут печь пироги! Залогиньтесь же!';
}
?>
</form>