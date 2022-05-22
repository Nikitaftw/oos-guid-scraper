<?php

/**
 * Утилита для получения из ЕИС GUID номеров для извещений и их лотов
 * 
 * @author Никита Коротков <n.korotkov@estp.ru>
 * @created 04.2022
 */

include_once 'simple_html_dom.php';

$oosNumber = filter_input(INPUT_GET, 'oos');
$oosUrl = 'https://zakupki.gov.ru/223/purchase/public/purchase/info/changes-and-clarifications.html?regNumber=' . $oosNumber;

function curlGetPage($url, $referer = 'https://google.com')
{
    $ch = curl_init();
    //Указываем данные браузера print_r($_SERVER); [HTTP_USER_AGENT]=>
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.84 Safari/537.36');
    //Для следования редиректам
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    //Игнорируем проверки сертификатов
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    //Загружаемый URL
    curl_setopt($ch, CURLOPT_URL, $url);
    //Содержимое заголовка "Referer: ", который будет использован в HTTP-запросе
    curl_setopt($ch, CURLOPT_REFERER, $referer);
    //Отключаем вывод заголовка
    curl_setopt($ch, CURLOPT_HEADER, 0);
    //для возврата результата передачи в качестве строки из curl_exec() вместо прямого вывода в браузер.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $responce = curl_exec($ch);
    $info = curl_getinfo($ch);
    
    if ($info['http_code'] == 200) {
        curl_close($ch);
        return $responce;
    } else {
        curl_close($ch);
        exit('Ошибка ' . $info['http_code']);
    }
}

function getData($url)
{
    //Заводим в эту видимость переменную с ЕИСовским урлом
    global $oosUrl;

    //Ветвление на то с каким урлом работать
    if ($url == $oosUrl) {
        //Выполняется если передаваемый урл будет равен глобальному ЕИСовскому
        $page = curlGetPage($url);
        $html = str_get_html($page);
        $array_post = array();
        //По всем <li> с onclick внутри
        foreach ($html->find('li[onclick]') as $post) {
            $array_post[] = $post->onclick;
        }
        //Получаем id печатный формы извещения
        return substr($array_post[0], 58, -16);
    } else {
        //Выполняется если будет передана ссылка на печатную форму
        $page =  curlGetPage($url);
        $html = str_get_html($page);

        foreach ($html->find('div[id=tabs-2]') as $xmlData) {
            return $xmlData->xmltext;
        }
    }
}

$pfid = 'https://zakupki.gov.ru/223/purchase/public/print-form/show.html?pfid=' . getData($oosUrl);

$xml = getData($pfid);
$xml_decoded = htmlspecialchars_decode($xml, ENT_QUOTES);
$xml1 = simplexml_load_string($xml_decoded);

$guidArray = [];
$itemDataArray = [];
$ns = $xml1->getNamespaces(true);

foreach ($xml1->children($ns['ns2'])->body->item as $itemData) {
    $itemDataArray[] = json_decode(json_encode($itemData), true);
}

$purchaseData = key($itemDataArray[0]);

foreach ($xml1->children($ns['ns2'])->body->item->$purchaseData->guid as $noticeData) {
    echo '<strong>GUID извещения</strong>' . '<br>' . $noticeData . '<br>' . '<br>';
}

foreach ($xml1->children($ns['ns2'])->body->item->$purchaseData->lots->children($ns[''])->lot as $lotsData) {
    $guidArray[] = $lotsData->guid;
}

echo '<strong>GUID лота</strong>' . '<br>';
echo implode('<br>', $guidArray);
