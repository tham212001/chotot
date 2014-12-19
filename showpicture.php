<?php
date_default_timezone_set("Asia/Saigon"); //timezone
/*echo(date('Y-m-d H:i:s', 1418918856));
echo(date('Y-m-d H:i:s', 1418918872));
die;*/

$urlchotot = 'http://www.chotot.vn/tp_ho_chi_minh/';
$patternList = '/<div class=\"chotot-list-row(.*?)\">(.*?)class=\"listing_thumbs_image\"(.*?)<img(.*?)src="(.*?)"(.*?)class=\"thumbs_subject\"(.*?)>(.*?)<a(.*?)href="(.*?)"(.*?)class=\"ad-subject\"(.*?)>(.*?)<\/a>(.*?)<div(.*?)class=\"listing_thumbs_date\"(.*?)>(.*?)<\/div>(.*?)<\/div>/s';
//$patternImage = '/<img(.*?)src="(.*?)"(.*?)/s';

$connection = mysql_connect('localhost', 'root', '');
mysql_selectdb('chotot', $connection);
mysql_query("SET NAMES 'utf8'", $connection);

function getAllRowChototHCM($url, $page = 0)
{
    global $patternList, $connection;
    if ($page > 0) {
        $url .= '?o=' . $page;
    }
    $numOfElement = preg_match_all($patternList, file_get_contents($url), $match);
    //var_dump($match);die;
    if ($numOfElement > 0) {
        $arrData = array();
        for($ix = 0; $ix < $numOfElement; $ix++) {//$match[5] as $ix => $src
            $match[10][$ix] = trim($match[10][$ix]);
            $match[13][$ix] = trim($match[13][$ix]);
            $match[17][$ix] = trim($match[17][$ix]);
            $src = trim($match[5][$ix]);
            if (!empty($src) && !empty($match[11][$ix]) && !empty($match[15][$ix])) {
                $arrData[] = array(
                    'src' => $src,
                    'url' =>  $match[10][$ix],
                    'title' => $match[13][$ix],
                    'time' => convertTimeAgoToTimeStamp($match[17][$ix]),
                    'orders' => str_replace('.', '', microtime(true))
                );
            }
        }
        return $arrData;
    }
    return false;
}

function convertTimeAgoToTimeStamp($ago)
{
    $arrConvertion = array(
        'giờ' => 'hour',
        'Giờ' => 'hour',
        'phút' => 'min',
        'Phút' => 'min',
        'Hôm qua' => '-1day',
        'hôm qua' => '-1day',
        'ngày' => '',
        'Ngày' => '',
        'tháng' => '-',
        'Tháng' => '-',
    );
    $truoc = 'trước';
    $ago = trim($ago);
    $ago = trim(str_replace(array_keys($arrConvertion), array_values($arrConvertion), $ago));
    if (strpos($ago, $truoc)) {
        $exp = explode(' ', $ago);
        $ago = trim(str_replace($truoc, '', $ago));
        if (count($ago) == 4) {
            return strtotime(date('Y-m-d H:i') . ':00', strtotime('-' . $exp[0] . $exp[1] . ' -' . $exp[2] . $exp[3]));
        } else {
            return strtotime('-' . $ago);
        }
    } elseif (strpos($ago, '-')) {
        $exp = explode('-', $ago);
        return strtotime(date('Y-m-d H:i') . ':00', strtotime(date('Y') . '-' . trim(substr(trim($exp[1]), 0, 2)) . '-' . trim($exp[0]) . ' ' . substr(trim($exp[1]), 3)));
    } else {
        return strtotime(date('Y-m-d H:i') . ':00', strtotime($ago));
    }
}

//get first 100 pages
/*for ($i = 100; $i >-1; $i--) {
    $data = getAllRowChototHCM($urlchotot, $page);
    insertDataToDB($data);
}*/

function insertDataToDB($data)
{
    global $connection;

    $numOfElement = count($data) - 1;
    $sql = '';
    for($ix = $numOfElement; $ix >-1; $ix--) {//$match[5] as $ix => $src
        $title = trim($data[$ix]['title']);
        $url = trim($data[$ix]['url']);
        $src = trim($data[$ix]['src']);
        $time = trim($data[$ix]['time']);
        $sorders = trim($data[$ix]['orders']);//
        if (!empty($src) && !empty($time) && !empty($title) && !empty($url) && !empty($src)) {
            $sql .= '("' . addslashes($title) . '", "' . addslashes($src) . '", "' . $sorders . '", "' . $url . '", "' . $time . '", ' . time() . '),';
        }
    }
    if (!empty($sql)) {
        $sql = substr($sql, 0, -1);
        $sql = 'INSERT INTO pictures(title, imgsrc, orders, url, timeonchotot, createddate) VALUES ' . $sql;
        mysql_query($sql, $connection);
    }
}

if (!empty($_POST['is_ajax'])) {
    if (!empty($_POST['newpic'])) {

        $result = getNewPicture();
        echo json_encode($result);

    } elseif(!empty($_POST['refreshorder'])) {
        //idea to do auto change orders that apply to all other users
        /*
         * 1. User click on change, i'll save the order of 2 elements that change the position and the time changing
         * 2. Do a ajax request each 1min/1time to get all the item that sorting of current day, then find element of it to change all
         * */
    }
}

function getNewPicture()
{
    global $connection, $urlchotot;
    $lasttimefromchotot = 0;
    if (!empty($_POST['lasttime'])) {
        $lasttimefromchotot = $_POST['lasttime'];
    } else {
        $result = mysql_query('SELECT timeonchotot FROM pictures ORDER BY timeonchotot DESC LIMIT 1', $connection);

        if (!empty($result)) {
            $getLastTime = mysql_fetch_assoc($result);
            if (!empty($getLastTime)) {
                $lasttimefromchotot = $getLastTime['timeonchotot'];
            }
        }
    }
    $page = 0;
    $arrData = array();
    $timeloop = 0;
    while(1) {
        $listrowchotot = getAllRowChototHCM($urlchotot, $page);
        $cnt = 0;
        array_walk($listrowchotot, function($val) use (&$arrData, &$cnt, $lasttimefromchotot) {
            if ($val['time'] >= $lasttimefromchotot) {
                $arrData[] = $val;
                $cnt++;
            }
        });
        if ($cnt == count($listrowchotot) && $lasttimefromchotot >0) {
            $page++;
        } else {
            break;
        }
        $timeloop++;
        if ($timeloop > 10) {
            break;
        }
    }
    if (!empty($arrData)) {
        if (!empty($arrData[0]['time'])) {
            $lasttimefromchotot = $arrData[0]['time'];
        }
        insertDataToDB($arrData);
    }

    return array('data' => $arrData, 'lasttime' => $lasttimefromchotot);
}

function getPicture($offset, $limit)
{
    global $connection;
    $sql = 'SELECT * FROM pictures ORDER BY orders DESC LIMIT ' . $offset . ', ' . $limit;
    $result = mysql_query($sql, $connection);
    $data = array();
    if (!empty($result)) {
        while($row = mysql_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    return $data;
}

$offset = 0;
//get top 20 picturees
$newReturn = getNewPicture();
$top20picutre = getPicture($offset, 20);
$lasttime = $newReturn['lasttime'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Show Pictures</title>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.2/themes/smoothness/jquery-ui.css">
    <script src="//code.jquery.com/jquery-1.10.2.js"></script>
    <script src="//code.jquery.com/ui/1.11.2/jquery-ui.js"></script>
    <style>
        #main{margin: 0px auto; width: 1000px;}
        #sortable { list-style-type: none; margin: 0; padding: 0; width: 100%; }
        #sortable li { display: inline-block; border: 1px solid #ccc; margin: 5px 2px; padding: 5px; height: 100px; width: 100px;overflow: hidden;text-align: center; vertical-align: middle; cursor: pointer;background: #fff;}
        #itemnewattop{width: 100%;text-align: center;margin-bottom: 50px;}
    </style>
    <script>
        $(function() {
            $( "#sortable" ).sortable();
            function getPicture()
            {
                $.post('showpicture.php', {is_ajax: 1, newpic: 1, lasttime: $('#lasttime').val()}, function(result) {
                    $('#lasttime').val(result.lasttime);
                    if (result.data) {
                        var num = result.data.length - 1;
                        for (i = num; i > -1; i--) {
                            $('#sortable').prepend('<li>' + $('#itemnewattop').html() + '</li>');
                            $('#itemnewattop img').toggle('drop', {}, 500);
                            $('#itemnewattop img').attr('src', result.data[i].src);
                            $('#itemnewattop img').attr('rel', result.data[i].orders);
                        }
                    }
                });
            }
            setTimeout(getPicture, 1000);
            //setInterval(runEffect, 10000);
        });
    </script>
</head>
<body>
<div id="main">
    <input type="hidden" value="<?php echo $lasttime;?>" id="lasttime" name="lasttime" />

    <?php if (!empty($top20picutre)):?>
    <div id="itemnewattop">
        <?php $pic = $top20picutre[0]; unset($top20picutre[0]);?>
        <img src="<?php echo $pic['imgsrc']?>" alt="<?php echo $pic['title']?>" title="<?php echo $pic['title']?>" id="<?php echo $pic['id']?>" rel="<?php echo $pic['orders']?>" />
    </div>

    <ul id="sortable" class="ui-sortable">

        <?php foreach($top20picutre as $pic):?>
            <li>
                <img src="<?php echo $pic['imgsrc']?>" alt="<?php echo $pic['title']?>" title="<?php echo $pic['title']?>" id="<?php echo $pic['id']?>" rel="<?php echo $pic['orders']?>" />
            </li>
        <?php endforeach;?>

    </ul>
    <?php endif;?>
</div>
</body>
</html>