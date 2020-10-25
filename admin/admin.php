<?php

//マルチ機能アクセスカウンター「まるかんた」 管理用CGI	v1.31

//制作・川畑智哉  webmaster@planet-green.com
//最新版は→ http://www.planet-green.com/
//最終更新日 03/03/04

/*
サーバ上での各ファイルのパーミッションは次のようになります。

/(設置ディレクトリ)/ [755]
　|
　|--/data/ [777]...アクセスログやキリ番ゲット者のリストを保存する空ディレクトリ
　|
　|-- counter.php [744]... カウンターCGI
　|-- admin.php   [744]... 管理用CGI
　|-- image.png   [744]... カウンタ用の元画像
*/

//----------------------------------
//-------- 各種設定ここから --------
//----------------------------------

//管理人パスワード
$admin_pass = 'pass';

//ログ表示のフォントサイズ
$log_fontsize = 12;

//アクセスログのファイル名(counter.phpと同じ設定にしてください)
$FnameAccessLog = './data/access.cgi';

//データ保存ディレクトリ(counter.phpと同じ設定にしてください)
$DataDir = './data/';

//Referer(リンク元)に集計しないURL。
//例えば、自サイトのURLを指定しておくと純粋に外部からのリンクだけを集計できる。
//コンマで区切って何個でも指定できる。何も指定しない場合は $ExcludeRefUrl = array(""); と記述する。
//例) $ExcludeRefUrl = array( "http://www.hogehoge.com/","http://www.mogomogo.co.jp/" );
$ExcludeRefUrl = ['http://www.rc-net.jp/'];

//デフォルトで何日間のログを表示・解析するか。
//ログの量にもよりますが、一度に全ログを解析すると時間がかかるので
//適当な大きさに調整しておくと便利です。
$DefLogDays = 10;

//アクセスログ表示のテーブル背景色
$log_back_color1 = '#D0D0D0';
$log_back_color2 = '#F0F0F0';

//アクセス解析での表・棒グラフのサイズと色、テーブル背景色
$GraphWidth = 400;
$GraphHeight = 6;
$GraphBarColor = '#8080CC';
$GraphbBackColor = '#FFFFFF';
$TColor1 = '#E0E0E8';
$TColor2 = '#CCCCFF';

//----------------------------------
//-------- 各種設定ここまで --------
//----------------------------------

session_start();	//セッション開始の宣言

//キャッシュ防止用
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');	// HTTP/1.1
//header("Cache-Control: post-check=0, pre-check=0", false);
//header("Pragma: no-cache"); // HTTP/1.0

//パスワードチェック
function PassCheck()
{
    global $admin_pass;

    if ($admin_pass == $_POST['pass']) {
        $_SESSION['pass'] = $admin_pass;

        return 1;
    }

    return 0;
}

function PassError()
{
    ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=EUC-JP">
</head>
<body>
パスワードが違います
</body>
</html>
<?php
}

function LogFileExistsCheck()
{
    global $FnameAccessLog;

    if (true == file_exists($FnameAccessLog)) {
        return;
    } ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=EUC-JP">
</head>
<body>
アクセスログがありません
</body>
</html>
<?php
exit;
}

//文字列中の 「,」 や「"」 を処理してCSVファイルにしてもいいようにする関数
function str_convert_for_csv($str)
{
    //改行を取り除く

    $str = mb_preg_replace("\r\n", ' ', $str, 'i');

    $str = mb_preg_replace("(\r|\n)", ' ', $str, 'i');

    if (mb_substr_count($str, "\n") || mb_substr_count($str, '"') || mb_substr_count($str, ',')) {
        $tmpstr = '';

        //「"」を「""」に変換

        $str = str_replace('"', '""', $str);

        $tmpstr = '"' . $str . '"';

        return $tmpstr;
    }

    return $str;
}

//配列中の最大の数字を求める関数
function SearchMaxValue($arr)
{
    $max = 0;

    $tmpc = count($arr);

    for ($i = 0; $i < $tmpc; $i++) {
        if ($arr[$i] > $max) {
            $max = $arr[$i];
        }
    }

    return $max;
}

//クラフで棒の表示
function WriteBar($width)
{
    global $GraphWidth;

    global $GraphHeight;

    global $GraphBarColor;

    $w2 = $GraphWidth - $width;

    print "<table border=0 cellspacing=0 cellpadding=0><tr><td width={$width} height={$GraphHeight} bgcolor={$GraphBarColor}></td><td width={$w2} height={$GraphHeight}></td></tr></table>";
}

function GetSearchKeyword($ref)
{
    //global $search_site_count;

    if (0 == strncmp($ref, 'http://www.google.', 18) || 0 == strncmp($ref, 'http://216.239', 14)) {
        $q_tag = 'q=';

        $q_len = 2; //$s_type = "google";// $q_code = "UTF-8";

        $googleflag = 1;
    } elseif (0 == strncmp($ref, 'http://www.goo.ne.jp', 20)) {
        $q_tag = 'MT=';

        $q_len = 3; //$s_type = "goo";// $q_code = "EUC-JP";
    } elseif (0 == strncmp($ref, 'http://www.infoseek.co.jp', 25)) {
        $q_tag = 'qt=';

        $q_len = 3; //$s_type = "infoseek";// $q_code = "EUC-JP";
    } elseif (0 == strncmp($ref, 'http://websearch.yahoo.co.jp', 28)) {
        $q_tag = 'p=';

        $q_len = 2; //$s_type = "yahoo(websearch)";	// $q_code = "EUC-JP";
    } elseif (0 == strncmp($ref, 'http://search.msn.co.jp', 23)) {
        $q_tag = 'q=';

        $q_len = 2; //$s_type = "msn";// $q_code = "SJIS"; // or UTF-8
    } elseif (0 == strncmp($ref, 'http://wisenut.lycos.co.jp', 26)) {
        $q_tag = 'q=';

        $q_len = 2; //$s_type = "lycos";// $q_code = "SJIS";
    } elseif (0 == strncmp($ref, 'http://search.fresheye.com', 26)) {
        $q_tag = 'kw=';

        $q_len = 3; //$s_type = "fresheye";// $q_code = "SJIS";
    } else {
        return [''];
    }

    $p_s = mb_strpos($ref, $q_tag) + $q_len;

    if ($p_s == $q_len) {
        return [''];
    }

    $p_e = mb_strpos($ref, '&', $p_s);

    $len = $p_e - $p_s;

    if ($len > 0) {
        $ref = mb_substr($ref, $p_s, $len);
    } else {
        $ref = mb_substr($ref, $p_s);
    }

    if ($googleflag && (0 == strncmp($ref, 'cache:', 6) || 0 == strncmp($ref, 'related:', 8))) {
        return [''];
    }

    $ref = rawurldecode($ref);

    $e_type = mb_detect_encoding($ref, 'auto', true);

    //print mb_detect_encoding($ref). "<p>";

    $ref = mb_convert_encoding($ref, 'EUC-JP', $e_type);

    //$ret_arry = preg_split( "[' '|'　'|'+']" , $ref);

    //半角カタカナ→全角カタカナ 全角英数字→半角 英大文字→小文字

    $ref = mb_strtolower(mb_convert_kana($ref, 'KVa'));

    $ref = str_replace('　', ' ', $ref);

    $ret_arry = preg_preg_split("/['+'|' ']+/", $ref, 255, PREG_SPLIT_NO_EMPTY);

    /*
    $i=0;
    while($i < count($ret_arry))
    {
    	if( $ret_arry[$i]=="" )
    	{
    		array_splice( $ret_arry, $i,1);
    		continue;
    	}
    	$i++;
    }
    */

    //if( $ret_arry[0]!="" ) $search_site_count[$s_type]++;

    return $ret_arry;
}

function StrLimitCut($str)
{
    if (mb_strlen($str) > 100) {
        return mb_substr($str, 0, 100) . '～';
    }

    return $str;
}

function ExcludeRefUrlCheck($url)
{
    global $ExcludeRefUrl;

    $tmpc = count($ExcludeRefUrl);

    for ($i = 0; $i < $tmpc; $i++) {
        if ('' != $ExcludeRefUrl[$i] && 0 == strncmp($url, $ExcludeRefUrl[$i], mb_strlen($ExcludeRefUrl[$i]))) {
            return 1;
        }
    }

    return 0;
}

function time_to_str($t)
{
    $sec = $t % 60;

    $min = (int)($t / 60) % 60;

    $hour = (int)($t / 3600) % 24;

    $day = (int)($hour / 24);

    $hour = (int)($hour % 24);

    if ($day) {
        $str = $day . '日';
    }

    if ($hour) {
        $str .= $hour . '時間' . $min . '分';
    } elseif ($min) {
        $str = $min . '分';
    }

    return $str . $sec . '秒';
}

//保存されてるログの記録開始日・終了日
function search_log_date()
{
    global $FnameAccessLog;

    $log_size = filesize($FnameAccessLog);

    $fp = fopen($FnameAccessLog, 'rb');

    $tmp = fgets($fp, 1024);

    [$a_cnt, $now, $host, $p_name, $ref, $LastVisit, $visit, $user_agent] = sscanf($tmp, "%d\t%d\t%s\t%s\t%s\t%d\t%d\t%s\n");

    $tmp_date = getdate($now);

    $log_s_year = $tmp_date['year'];

    $log_s_mon = $tmp_date['mon'];

    $log_s_day = $tmp_date['mday'];

    if ($log_size > 1024) {
        fseek($fp, $log_size - 512);

        $tmp = fgets($fp, 1024);	//dummy
    }

    while ($tmp2 = fgets($fp, 1024)) {
        if ('' != $tmp) {
            $tmp = $tmp2;
        }
    }

    fclose($fp);

    [$a_cnt, $now2, $host, $p_name, $ref, $LastVisit, $visit, $user_agent] = sscanf($tmp, "%d\t%d\t%s\t%s\t%s\t%d\t%d\t%s\n");

    $tmp_date = getdate($now2);

    $log_e_year = $tmp_date['year'];

    $log_e_mon = $tmp_date['mon'];

    $log_e_day = $tmp_date['mday'];

    return [ $log_s_year, $log_s_mon, $log_s_day, $log_e_year, $log_e_mon, $log_e_day];
}

//ログの読み込み位置の頭出し
function search_log_seekpoint($fp, $s_year, $s_mon, $s_day)
{
    global $FnameAccessLog;

    $log_size = filesize($FnameAccessLog);

    $last_searchpoint = $log_size;

    $now_seekpoint = 0;

    $s_timestamp = mktime(0, 0, 0, $s_mon, $s_day, $s_year);

    $c = 0;

    while (($flag_over || abs($last_searchpoint - $now_seekpoint) > 16384)
           && ($now_seekpoint >= 0 && $now_seekpoint < $log_size - 16384)) {
        fseek($fp, $now_seekpoint);

        $tmp = fgets($fp, 1024);	//dummy

        $tmp = fgets($fp, 1024);

        [$a_cnt, $now, $host, $p_name, $ref, $LastVisit, $visit, $user_agent] = sscanf($tmp, "%d\t%d\t%s\t%s\t%s\t%d\t%d\t%s\n");

        $now_tmp = $now_seekpoint;

        if ($now >= $s_timestamp) {
            $now_seekpoint -= abs((int)(($last_searchpoint - $now_seekpoint) / 2));

            $flag_over = 1;
        } else {
            $now_seekpoint += abs((int)(($last_searchpoint - $now_seekpoint) / 2));

            $flag_over = 0;
        }

        $last_searchpoint = $now_tmp;

        $c++;

        if ($c > 8) {
            break;
        }
    }

    $last_searchpoint = ftell($fp);

    while ($tmp = fgets($fp, 1024)) {
        [$a_cnt, $now, $host, $p_name, $ref, $LastVisit, $visit, $user_agent] = sscanf($tmp, "%d\t%d\t%s\t%s\t%s\t%d\t%d\t%s\n");

        if ($now >= $s_timestamp) {
            return $last_searchpoint;
        }

        $last_searchpoint = ftell($fp);
    }

    return 0;
}

function GetDateFromPost()
{
    $s_year = $_POST['s_year'];

    $s_mon = $_POST['s_mon'];

    $s_day = $_POST['s_day'];

    $e_year = $_POST['e_year'];

    $e_mon = $_POST['e_mon'];

    $e_day = $_POST['e_day'];

    if ($s_year < 2000) {
        $s_year = 2000;
    }

    if ($s_year > 2100) {
        $s_year = 2100;
    }

    if ($s_mon < 1) {
        $s_mon = 1;
    }

    if ($s_mon > 12) {
        $s_mon = 12;
    }

    if ($s_day < 1) {
        $s_year = 1;
    }

    if ($s_day > 31) {
        $s_year = 31;
    }

    if ($e_year < 2000) {
        $e_year = 2000;
    }

    if ($e_year > 2100) {
        $e_year = 2100;
    }

    if ($e_mon < 1) {
        $e_mon = 1;
    }

    if ($e_mon > 12) {
        $e_mon = 12;
    }

    if ($e_day < 1) {
        $e_year = 1;
    }

    if ($e_day > 31) {
        $e_year = 31;
    }

    $sts = mktime(0, 0, 0, $s_mon, $s_day, $s_year);

    $ets = mktime(0, 0, 0, $e_mon, $e_day, $e_year);

    if ($sts > $ets) {
        $s_year = $e_year;

        $s_mon = $e_mon;

        $s_day = $e_day;
    }

    return [$s_year, $s_mon, $s_day, $e_year, $e_mon, $e_day];
}

//管理ページ(TOP)表示
if ('' == $_POST['mode']) {
    global $DefLogDays;

    global $FnameAccessLog;

    if (false == file_exists($FnameAccessLog)) {
        $flag_file_error = 1;
    } else {
        [$log_s_year, $log_s_mon, $log_s_day, $log_e_year, $log_e_mon, $log_e_day] = search_log_date();

        $sts = mktime(0, 0, 0, $log_s_mon, $log_s_day, $log_s_year);

        $ets = mktime(0, 0, 0, $log_e_mon, $log_e_day, $log_e_year);

        if (($ets - $sts) > 86400 * $DefLogDays) {
            $sts = $ets - 86400 * $DefLogDays;

            $tmp_date = getdate($sts);

            $ss_year = $tmp_date['year'];

            $ss_mon = $tmp_date['mon'];

            $ss_day = $tmp_date['mday'];
        } else {
            $ss_year = $log_s_year;

            $ss_mon = $log_s_mon;

            $ss_day = $log_s_day;
        }
    } ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=EUC-JP">
<title>管理ページ</title>
</head>
<body bgcolor="#FFFFFF" text="#000000" leftmargin="8">
<font size="-1" color="#606060">マルチ機能アクセスカウンター</font><br>
<B><FONT size="+2" color="#000099">管理ページ</FONT></B>
<form name="form1" method="post" action="admin.php" target="_self">
<input type="hidden" name="menu" value="yes">
<?php
    if ($flag_file_error) {
        print '<p><b><font color="#FF0000" size="+1">エラー! アクセスログがありません!</font></b></p>';
    } ?>
<table cellpadding="4" cellspacing="1" bgcolor="#202020">
<tr bgcolor="#E0E0E8">
<td bgcolor="#CCCCFF">
<input type="radio" name="mode" value="html">
</td>
<td><strong>アクセスログ表示</strong></td>
</tr>
<tr bgcolor="#E0E0E8">
<td bgcolor="#CCCCFF">
<input type="radio" name="mode" value="csv"></td>
<td><strong>アクセスログ表示(CSV形式)</strong> </td>
</tr>
<tr bgcolor="#E0E0E8">
<td bgcolor="#CCCCFF">
<input type="radio" name="mode" value="analyze" checked></td>
<td>
<p><strong>アクセス解析</strong></p>
<p>(解析対象を選択してください)<br>
<input name="ana_mon" type="checkbox" value="1" checked>
月別アクセス状況<br>
<input name="ana_week" type="checkbox" value="1" checked>
曜日別アクセス状況<br>
<input name="ana_time" type="checkbox" value="1" checked>
時間帯別アクセス状況<br>
<input name="ana_ref" type="checkbox" value="1" checked>
リンク元(Referer)
<font size="-1">　
(<input name="ana_ref_lowcut" type="text" size="3" value="1">アクセス以上のリンク元を表示)</font><br>
<input name="ana_keyword" type="checkbox" value="1" checked>
サーチエンジンでの検索キーワード<br>
<input name="ana_day" type="checkbox" value="1" checked>
日別アクセス状況</p>
</td>
</tr>
</table>
<br>
<p>
<?php print "ログ保存期間 $log_s_year/$log_s_mon/$log_s_day ～ $log_e_year/$log_e_mon/$log_e_day"; ?>
<p>解析対象期間　
<input name="s_year" type="text" value="<?php echo $ss_year; ?>" size="4" maxlength="4">
年
<input name="s_mon" type="text" value="<?php echo $ss_mon; ?>" size="3" maxlength="3">
月
<input name="s_day" type="text" value="<?php echo $ss_day; ?>" size="3" maxlength="3">
日～
<input name="e_year" type="text" value="<?php echo $log_e_year; ?>" size="4" maxlength="4">
年
<input name="e_mon" type="text" value="<?php echo $log_e_mon; ?>" size="3" maxlength="3">
月
<input name="e_day" type="text" value="<?php echo $log_e_day; ?>" size="3" maxlength="3">
日</p>
<p>

管理パスワード
<input type="password" name="pass" size="8" value="<?php echo $_SESSION['pass'] ?>">
<input type="submit" name="Submit" value=" 実行 ">
</p>
</form>
<p>
<HR>
マルチ機能アクセスカウンター v1.40 スクリプト配布元　<a href="http://www.planet-green.com/" target="_blank">http://www.planet-green.com/</a></p>
</body>
</html>
<?php

return;
}

if ('html' == $_POST['mode']) {
    global $log_fontsize;

    global $log_back_color1;

    global $log_back_color2;

    if (0 == PassCheck()) {
        PassError();

        return;
    }

    //	header("Content-Disposition: filename=\"access_log.html\"");

    LogFileExistsCheck(); ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=EUC-JP">
<title>まるかんた・アクセスログ</title>
<style type="text/css">
<!--
.css1 {
	font-size: <?php echo $log_fontsize; ?>px;
}
-->
</style>
</head>
<body>
<table border="0" cellspacing="1" cellpadding="1" class="css1">
<tr bgcolor=<?php echo $log_back_color1; ?>>
<td nowrap>カウント</td><td nowrap>アクセス日時</td><td nowrap>接続元</td><td nowrap>表示ページ</td>
<td nowrap>前回アクセス</td><td nowrap>訪問回数</td><td nowrap>リンク元</td><td nowrap>ブラウザー種別</td>
</tr>
<?php

    [$s_year, $s_mon, $s_day, $e_year, $e_mon, $e_day] = GetDateFromPost();

    $s_year = $_POST['s_year'];

    $s_mon = $_POST['s_mon'];

    $s_day = $_POST['s_day'];

    $e_year = $_POST['e_year'];

    $e_mon = $_POST['e_mon'];

    $e_day = $_POST['e_day'];

    $fp = fopen($FnameAccessLog, 'rb');

    $sp = search_log_seekpoint($fp, $s_year, $s_mon, $s_day);

    fseek($fp, $sp);

    $end_time = mktime(0, 0, 0, $e_mon, $e_day + 1, $e_year);

    $c = 0;

    while ($tmp = fgets($fp, 1024)) {
        [$a_cnt, $now, $host, $p_name, $ref, $LastVisit, $visit, $user_agent] = sscanf($tmp, "%d\t%d\t%s\t%s\t%s\t%d\t%d\t%s\n");

        if ($now >= $end_time) {
            break;
        }

        if ($LastVisit) {
            $LastVisit = date('m/d H:i', $LastVisit);	//前回の訪問日を書式化
        } else {
            $LastVisit = '&nbsp;';
        }

        $dd = date('y/m/d H:i:s', $now);

        //&や", < > を変換

        $p_name = htmlspecialchars($p_name, ENT_QUOTES | ENT_HTML5);

        $user_agent = htmlspecialchars($user_agent, ENT_QUOTES | ENT_HTML5);

        $ref = htmlspecialchars($ref, ENT_QUOTES | ENT_HTML5);

        $user_agent = str_replace('%20', '&nbsp;', $user_agent);

        $p_name = str_replace('%20', '&nbsp;', $p_name);

        $ref = str_replace('%20', '&nbsp;', $ref);

        if ($c % 2) {
            print "<tr bgcolor=\"{$log_back_color1}\">";
        } else {
            print "<tr bgcolor=\"{$log_back_color2}\">";
        }

        print "<td nowrap>{$a_cnt}</td><td nowrap>{$dd}</td><td nowrap>{$host}</td><td nowrap>{$p_name}</td><td nowrap>{$LastVisit}</td><td nowrap>{$visit}</td><td nowrap>{$ref}</td><td nowrap>{$user_agent}</td></tr>\n";

        $c++;
    }

    fclose($fp); ?>
</table>
</body>
</html>
<?php
return;
}

if ('csv' == $_POST['mode']) {
    if (0 == PassCheck()) {
        PassError();

        return;
    }

    LogFileExistsCheck();

    header('Content-Type: application/vnd.ms-excel');

    header('Content-Disposition: filename="access_log.csv"');

    //header("Content-disposition: attachment; filename=\"access_log.csv\"");

    //Header("Content-type: application/octet-stream; name=result.csv");

    [$s_year, $s_mon, $s_day, $e_year, $e_mon, $e_day] = GetDateFromPost();

    $tmp = "カウント,アクセス日時,接続元,表示ページ,前回アクセス日時,訪問回数,リンク元,ブラウザー種別\r\n";

    $tmp = mb_convert_encoding($tmp, 'SJIS');

    print $tmp;

    $fp = fopen($FnameAccessLog, 'rb');

    $sp = search_log_seekpoint($fp, $s_year, $s_mon, $s_day);

    fseek($fp, $sp);

    $end_time = mktime(0, 0, 0, $e_mon, $e_day + 1, $e_year);

    while ($tmp = fgets($fp, 1024)) {
        [$a_cnt, $now, $host, $p_name, $ref, $LastVisit, $visit, $user_agent] = sscanf($tmp, "%d\t%d\t%s\t%s\t%s\t%d\t%d\t%s");

        if ($now >= $end_time) {
            break;
        }

        if ($LastVisit) {
            $LastVisit = date('m/d H:i', $LastVisit);	//前回の訪問日を書式化
        } else {
            $LastVisit = '';
        }

        $dd = date('y/m/d H:i:s', $now);

        $user_agent = str_replace('%20', ' ', $user_agent);

        $p_name = str_replace('%20', ' ', $p_name);

        $ref = str_replace('%20', ' ', $ref);

        $p_name = str_convert_for_csv($p_name);

        $user_agent = str_convert_for_csv($user_agent);

        $ref = str_convert_for_csv($ref);

        print "{$a_cnt},{$dd},{$host},{$p_name},{$LastVisit},{$visit},{$ref},{$user_agent}\r\n"; //\r=CR \n=LF
    }

    fclose($fp);

    return;
}

if ('analyze' == $_POST['mode']) {
    global $log_fontsize;

    global $GraphWidth;

    global $GraphHeight;

    global $GraphBarColor;

    global $GraphbBackColor;

    global $TColor1;

    global $TColor2;

    if (0 == PassCheck()) {
        PassError();

        return;
    }

    //header("Content-type: text/html");

    header('Content-Disposition: filename="analyze.html"');

    LogFileExistsCheck(); ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=EUC-JP">
<title>まるかんた・アクセス解析</title>
<style type="text/css">
<!--
.css1 { font-size: <?php echo $log_fontsize; ?>px; }
.rsub { color: #0000C0; font-size: <?php echo $log_fontsize; ?>px; text-decoration: none}
.rsub:visited { color: #404060; }
.rsub2 { color: #4040C0; font-size: <?php echo $log_fontsize - 2; ?>px; text-decoration: none}
.rsub2:visited { color: #404060; }
-->
</style>
</head>
<body>
<?php

    $flag_ana_mon = $_POST['ana_mon'];

    $flag_ana_week = $_POST['ana_week'];

    $flag_ana_time = $_POST['ana_time'];

    $flag_ana_ref = $_POST['ana_ref'];

    $flag_ana_keyword = $_POST['ana_keyword'];

    $flag_ana_day = $_POST['ana_day'];

    $ana_ref_lowcut = $_POST['ana_ref_lowcut'];

    [$s_year, $s_mon, $s_day, $e_year, $e_mon, $e_day] = GetDateFromPost();

    $fp = fopen($FnameAccessLog, 'rb');

    $sp = search_log_seekpoint($fp, $s_year, $s_mon, $s_day);

    fseek($fp, $sp);

    $end_time = mktime(0, 0, 0, $e_mon, $e_day + 1, $e_year);

    for ($i = 0; $i < 24; $i++) {
        $AccessHour[$i] = 0;
    }

    for ($i = 0; $i < 7; $i++) {
        $AccessWeek[$i] = 0;
    }

    for ($i = 0; $i < 365; $i++) {
        $AccessDay[$i] = 0;
    }

    $start_date = 0;

    $last_date = 0;

    $last_month = 0;

    $total_ref_count = 0;

    $total = 0;

    $dc = 0;

    $mc = 0;

    $dmax = 0;

    $mmax = 0;

    $wmax = 0;

    $repeater = 0;

    $total_keyhit = 0;

    $sum_LastVisit = 0;

    while ($tmp = fgets($fp, 1024)) {
        [$a_cnt, $now, $host, $p_name, $ref, $LastVisit, $visit, $user_agent] = sscanf($tmp, "%d\t%d\t%s\t%s\t%s\t%d\t%d\t%s");

        if ($now >= $end_time) {
            break;
        }

        $user_agent = str_replace('%20', ' ', $user_agent);

        $p_name = str_replace('%20', ' ', $p_name);

        $ref = str_replace('%20', ' ', $ref);

        if (0 == $a_cnt || 0 == $now) {
            continue;
        }

        if (0 == $start_date) {
            $start_date = $now;
        }

        if ($visit > 1) {	//再訪問
            $repeater++;

            $sum_LastVisit += ($now - $LastVisit);
        }

        //リンク元

        if ('-' != $ref && 0 == ExcludeRefUrlCheck($ref)) {
            if ($flag_ana_keyword) {
                $key = GetSearchKeyword($ref);

                if ('' != $key[0]) {
                    $total_keyhit += count($key);

                    $kco = count($key);

                    for ($i = 0; $i < $kco; $i++) {
                        $RefKey[$key[$i]]++;
                    }
                }
            }

            if ($flag_ana_ref) {
                if (mb_strlen($ref) > 200) {
                    $ref = mb_substr($ref, 0, 200);
                }

                $ref_po = mb_strpos($ref, '?');

                if (false !== $ref_po) {
                    $ref_DelTail = mb_substr($ref, 0, $ref_po);

                    $RefTreeFlag[$ref_DelTail] = 1;

                    $RefTreeIndex[$ref_DelTail][$ref]++;

                    $ref = $ref_DelTail;
                }

                $Referer[$ref]++;

                $total_ref_count++;
            }
        }

        //$dd = date("y/m/d H:i:s",$now);

        $tmp_date = getdate($now);

        //$mday  = $tmp_date['mday']; //月単位の日付

        //$year  = $tmp_date['year'];

        $hours = $tmp_date['hours'];

        $wday = $tmp_date['wday'];	//曜日、数字: 0が日曜、6が土曜日

        $mon = $tmp_date['mon'];

        $yday = $tmp_date['yday'];	//1月1日から数えた日付。たとえば299

        if (0 == $total) {
            $table_ddate[0] = date('y/m/d (D)', $now);

            $table_mdate[0] = date('Y年m月', $now);
        }

        //日別

        if ($last_date != $yday) {
            $d_diff = $yday - $last_date;

            if ($d_diff < 0) {
                $d_diff += 365;
            }	// - $last_date +  $yday;

            if ($last_date && $d_diff > 1) {	//前日にアクセス0の日があったら
                for ($i = 1; $i < $d_diff; $i++) {
                    $dc++;

                    $table_ddate[$dc] = date('y/m/d (D)', $now - ($d_diff - $i) * 86400);

                    $table_dcount[$dc] = 0;
                }
            }

            $last_date = $yday;

            if ($total) {
                if ($dmax < $table_dcount[$dc]) {
                    $dmax = $table_dcount[$dc];
                }

                $dc++;

                $table_ddate[$dc] = date('y/m/d (D)', $now);
            }
        }

        $table_dcount[$dc]++;

        if ($flag_ana_mon) {
            //月別

            if ($last_month != $mon) {
                $last_month = $mon;

                if ($total) {
                    if ($mmax < $table_mcount[$mc]) {
                        $mmax = $table_mcount[$mc];
                    }

                    $mc++;

                    $table_mdate[$mc] = date('Y年m月', $now);
                }
            }

            $table_mcount[$mc]++;
        }

        //時間別

        $AccessHour[$hours]++;

        //曜日別

        $AccessWeek[$wday]++;

        $end_date = $now;

        $total++;
    }

    fclose($fp);

    //↓一ヶ月、または一日しかログが無い場合のため

    if ($flag_ana_mon && $mmax < $table_mcount[$mc]) {
        $mmax = $table_mcount[$mc];
    }

    if ($flag_ana_day && $dmax < $table_dcount[$dc]) {
        $dmax = $table_dcount[$dc];
    }

    //--------------------------------------------------------------

    //					   ここから集計結果

    //--------------------------------------------------------------

    $ttag_o = "<table border=\"0\" cellpadding=\"2\" cellspacing=\"1\" class=\"css1\" bgcolor=\"#202020\">\n";

    $ttag_c = '</table><p>';

    $tr = "<tr valign=\"middle\" bgcolor=\"{$TColor1}\">";

    $td = "<td bgcolor=\"{$TColor2}\" valign=\"top\" nowrap>";

    $couns_days = count($table_dcount);

    if ($couns_days > 0) {
        $ave_d = $total / $couns_days;

        $ave_interval = time_to_str(86400 / $ave_d) . 'に１アクセス';
    } else {
        $ave_d = 0;

        $ave_interval = '-';
    }

    $ave_d = round($ave_d, 2);

    if ($repeater > 0) {
        $repeat_interval = time_to_str((int)($sum_LastVisit / $repeater));
    } else {
        $repeat_interval = '-';
    }

    if ($total > 0) {
        $repeat_rate = round(($repeater / $total) * 100, 2) . '%';
    } else {
        $repeat_rate = '-';
    }

    print '<h1><font color="#5555cc">アクセスログ解析</font></h1>';

    print '解析対象期間 : ';

    print date('Y/m/d H:i:s', $start_date) . ' ～ ' . date('Y/m/d H:i:s', $end_date) . "<br>\n<p>\n";

    print $ttag_o;

    print $tr . $td . "総アクセス数</td><td align=\"right\">&nbsp;{$total}</td></tr>";

    print $tr . $td . "一日平均</td><td align=\"right\">&nbsp;{$ave_d}</td></tr>";

    print $tr . $td . "平均アクセス間隔</td><td align=\"right\">&nbsp;{$ave_interval}</td></tr>";

    print $tr . $td . '一意の訪問者</td><td align="right">&nbsp;' . ($total - $repeater) . '</td></tr>';

    print $tr . $td . "再訪問</td><td align=\"right\">&nbsp;{$repeater}</td></tr>";

    print $tr . $td . "再訪問率</td><td align=\"right\">&nbsp;{$repeat_rate}</td></tr>";

    print $tr . $td . "再訪問までの平均間隔</td><td align=\"right\">&nbsp;{$repeat_interval}</td></tr>";

    print $ttag_c;

    if ($flag_ana_mon) {
        print '<b>月別アクセス状況</b>';

        print $ttag_o;

        $tmpc = count($table_mdate);

        for ($i = 0; $i < $tmpc; $i++) {
            $v = $table_mcount[$i];

            $w1 = (int)(($v / $mmax) * $GraphWidth);

            $w2 = $GraphWidth - $w1;

            print $tr . $td . "{$table_mdate[$i]}</td><td>{$v}</td><td>";

            WriteBar($w1);

            print '</td></tr>';
        }

        print $ttag_c;

        unset($table_mdate,$table_mcount);
    }

    if ($flag_ana_week) {
        print '<b>曜日別アクセス状況</b>';

        print $ttag_o;

        $wname = [ '日曜日', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日' ];

        $wmax = SearchMaxValue($AccessWeek);

        if (0 == $wmax) {
            $wmax = 1;
        }

        for ($i = 0; $i < 7; $i++) {
            $v = (int)$AccessWeek[$i];

            $w1 = (int)(($v / $wmax) * $GraphWidth);

            $w2 = $GraphWidth - $w1;

            print $tr . $td . "{$wname[$i]}</td><td>{$v}</td><td>";

            WriteBar($w1);

            print '</td></tr>';
        }

        print $ttag_c;

        unset($AccessWeek,$wname);
    }

    if ($flag_ana_time) {
        print '<b>時間帯別アクセス状況</b>';

        print $ttag_o;

        $hmax = SearchMaxValue($AccessHour);

        if (0 == $hmax) {
            $hmax = 1;
        }

        for ($i = 0; $i < 24; $i++) {
            $v = (int)$AccessHour[$i];

            $w1 = (int)(($v / $hmax) * $GraphWidth);

            $w2 = $GraphWidth - $w1;

            print $tr . $td . sprintf('%02d', $i) . "時</td><td>{$v}</td><td>";

            WriteBar($w1);

            print "</td></tr>\n";
        }

        print $ttag_c;

        unset($AccessHour);
    }

    if ($flag_ana_ref) {
        print "<b>リンク元(Referer)</b>　(リンクからのアクセス:{$total_ref_count})";

        if (count($Referer)) {
            arsort($Referer);

            print $ttag_o;

            $RefIndex = array_keys($Referer);

            $tmpc = count($RefIndex);

            for ($i = 0; $i < $tmpc; $i++) {
                $url = $RefIndex[$i];

                if ($ana_ref_lowcut > $Referer[$url]) {
                    break;
                }

                $rr = round($Referer[$url] / $total_ref_count * 100, 1);

                print $tr . $td . "{$Referer[$url]} ({$rr}%)</td><td nowrap>";	//ヒット数

                if ($RefTreeFlag[$url]) {
                    arsort($RefTreeIndex[$url]);

                    $RefSubIndex = array_keys($RefTreeIndex[$url]);

                    $srcount = count($RefSubIndex);

                    if (1 == $srcount) {
                        $url = $RefSubIndex[0];

                        print "<a href=\"{$url}\" target=\"_blank\" class=\"rsub\">" . StrLimitCut($url) . '</a>';
                    } else {
                        print "<a href=\"{$url}\" target=\"_blank\" class=\"rsub\">" . StrLimitCut($url) . '</a><br>';

                        for ($j = 0; $j < $srcount; $j++) {
                            $sub_url = $RefSubIndex[$j];

                            print "　<a href=\"{$sub_url}\" target=\"_blank\" class=\"rsub2\">" . StrLimitCut($sub_url) . ' ('
                            . $RefTreeIndex[$url][$sub_url] . ')</a><br>';
                        }
                    }
                } else {
                    print "<a href=\"{$url}\" target=\"_blank\" class=\"rsub\">" . StrLimitCut($url) . '</a>';
                }

                print '</td></tr>';
            }

            print $ttag_c;
        } else {
            print '<p>記録無し<p>';
        }

        unset($Referer, $RefIndex,$RefSubIndex,$RefTreeIndex,$RefTreeFlag);
    }

    if ($flag_ana_keyword) {
        print '<b>検索キーワード</b>';

        $tmp_ck = count($RefKey);

        if ($tmp_ck) {
            print "　({$tmp_ck}種類　全{$total_keyhit}hit)";

            arsort($RefKey);

            print $ttag_o;

            $RefKeyIndex = array_keys($RefKey);

            $tmpc = count($RefKeyIndex);

            for ($i = 0; $i < $tmpc; $i++) {
                $key = $RefKeyIndex[$i];

                print $tr . $td . "{$RefKey[$key]}</td><td nowrap>{$key}</td></tr>";
            }

            print $ttag_c;
        } else {
            print '<p>記録無し<p>';
        }

        unset($RefKey, $RefKeyIndex);

        /*
        global $search_site_count;
        print "検知したサーチエンジン<br>";
        print "google:".(int)$search_site_count['google']." ";
        print "goo:".(int)$search_site_count['goo']." ";
        print "infoseek:".(int)$search_site_count['infoseek']." ";
        print "yahoo(websearch):".(int)$search_site_count['yahoo(websearch)']." ";
        print "msn:".(int)$search_site_count['msn']." ";
        print "lycos:".(int)$search_site_count['lycos']." ";
        print "fresheye:".(int)$search_site_count['fresheye']." ";
        print "<p>";
        */
    }

    if ($flag_ana_day) {
        print '<b>日別アクセス状況</b>';

        print $ttag_o;

        $tmpc = count($table_ddate);

        for ($i = 0; $i < $tmpc; $i++) {
            $v = (int)$table_dcount[$i];

            $w1 = (int)(($v / $dmax) * $GraphWidth);

            $w2 = $GraphWidth - $w1;

            print $tr . $td . "{$table_ddate[$i]}</td><td>{$v}</td><td>";

            WriteBar($w1);

            print "</td></tr>\n";
        }

        print $ttag_c;
    } ?>
<HR>マルチ機能アクセスカウンター v1.40 スクリプト配布元 <a href="http://www.planet-green.com/" target="_blank">http://www.planet-green.com/</a></p>
<?php
}

?>
