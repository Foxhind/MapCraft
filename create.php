<div id="pageheader" style="background-color: #92836c;">Создание нового пирога</div>
<?php
if (isset($_SESSION['osm_user'])) {
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
        
        $geojson = array('type'=>'FeatureCollection', 'features'=>array());
        $nodes = array();
        $currentway = null;
        $index = 0;
        function saxStartElement($parser, $name, $attrs)
        {
            global $currentway, $index, $nodes;
            switch($name)
            {
                case 'node':
                    $nodes[$attrs['id']] = array(floatval($attrs['lon']), floatval($attrs['lat']));
                    break;
                case 'way':
                    $currentway = array('type'=>'Feature','geometry'=>array('type'=>'Polygon', 'coordinates'=>array(array())));
                    break;
                case 'nd':
                    $currentway['geometry']['coordinates'][0][] = $nodes[$attrs['ref']];
                    break;
            };
        }

        function saxEndElement($parser, $name)
        {
            global $geojson, $nodes, $currentway, $index;
            if ($name == 'way') {
                $geojson['features'][] = $currentway;
                $index += 1;
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

        file_put_contents('/srv/www/mapcraft.nanodesu.ru/pies.txt', json_encode($geojson));
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
        <label for="file">osm-файл <em>*</em><br/><small>Замкнутые полигоны, файл меньше 512 Кб.</small></label>
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