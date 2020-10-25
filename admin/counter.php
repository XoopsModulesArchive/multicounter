<?php

//マルチ機能アクセスカウンター「まるかんた」 v1.4

//制作・川畑智哉  webmaster@planet-green.com
//最新版は→ http://www.planet-green.com/
//最終更新日 03/08/25

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

//PHPでGDライブラリが使えるか。(1:使う 0:GDを使わずに画像を並べて表示する)
//(これを0にすると、現時点ではJavaScript未対応ブラウザで動作しません)
$UseGD = 1;

//元画像のファイル名(上記の$UseGDを1に設定した場合のみ有効)
$ImageSrcName = 'image.png';	//トータルアクセス数
$SubImageSrcName = 'image.png';	//昨日・今日のカウント

//元画像の種類( 0:PNG 1:GIF 2:JPG 3:BMP)
//ただし、GIFはGDライブラリ最新版ではサポートしていません。BMPも動作保証外
$ImageSrcType = 0;

//数字画像の1桁あたりのサイズ(ピクセル数)
$ImgSize_number_x = 10;	//横
$ImgSize_number_y = 10;	//縦

//数字画像(今日と昨日)の1桁あたりのサイズ(ピクセル数)
$SubImgSize_number_x = 10; //横
$SubImgSize_number_y = 10; //縦

//カウンタを何桁表示するか
$ViewFigures = 6;	//トータル
$SubViewFigures = 3;	//昨日と今日

//連続再アクセスとみなしカウントしない間隔(秒)
$c_limit = 60;

//何人おきにキリ番にするか。0にするとキリ番無しに
$kiribantani = 0;

//その他、特別なキリ番を指定する(何個でも追加できます)。
//指定しない場合は$sp_kiriban = array(0); にしてください
$sp_kiriban = [40, 42, 44, 46, 1234, 222, 333, 444, 555, 666, 888, 999];

//キリ番ゲッター一覧の所に表示する文
$KiribanListHeader = 'キリ番をゲットした方々';

//キリ番をゲットした時に表示するメッセージ
$KMes_a = 'おめでとうございます☆あなたは';
$KMes_b = '人目の訪問者です！！\\n記念に名前を残していってね☆';

//キリ番ゲットして名前を入力する時に表示されるデフォルト値
//例) $DefName = "ななしのごんべ";
$DefName = 'ななしのごんべ';

//アクセスログを保存するか (1:する 0:しない)
$SaveAccessLog = 1;

//(アクセスログに記録するための)カウンタを設置したページのファイル名とページIDを対応させておく
//例) このように、何ページでも追加可能
//$PageIndex[1] = "index.html";
//$PageIndex[2] = "bbs.html";
//$PageIndex[3] = "link.html";
$PageIndex[1] = 'index.php';

//アクセスログの形式 0:まるかんた標準形式  1:Apache互換形式
$ApacheMode = 0;

//アクセスログにIPアドレスから逆引きしたホスト名も記録するか。(1:する 0:しない)
//アクセスの多いサイトでこれを有効にするとサーバに負荷がかかるので注意
$nslookup = 1;

//アクセスログのファイル名
$FnameAccessLog = './data/access.cgi';

//データ保存ディレクトリ
$DataDir = './data/';

//カウントしないIPアドレス(何個でも追加できます)。
//	例)
//	$ExceptionIP = array("192.168.0.");	←LAN内からのアクセスを除外したい時
//	$ExceptionIP = array("210.123.456.789","202.123.456.789" );	←特定のIPアドレスを指定
$ExceptionIP = [''];

//カウントしないホスト名(何個でも追加できます)。$nslookupを1に設定してないと無効になります。
//	例)
//	$ExceptionHOST = array( "foo.hogehoge.ne.jp")
//	$ExceptionHOST = array( "xxx.com" , "proxy." , "ns.mogomogo.com" )
//	注: 単に"ne.jp"と指定すると、日本からのほとんどのアクセスを除外してしまいます
$ExceptionHOST = [''];

//!!!!ここから先は、意味がよくわからない場合は変更しないでください

//カウンターを設置するページの文字コード "SJIS"か"EUC-JP"
//ただし、古いブラウザーではJavaScriptの関係でEUCだと動作しない場合があります。
$HTMLpageEncoding = 'EUC-JP';

//このスクリプト自体の文字コード。現時点(v4.2.3)のPHPはEUC-JPしかサポートしてない
$ScriptEncoding = 'EUC-JP';

//キリ番リストファイルに書き込む文字コード
$KListEncoding = 'EUC-JP';

//-------- 各種設定ここまで --------

$flag_Exception = 0;
$flag_renzoku_access = 0;

//IPアドレスを返す関数
function GetIp()
{
    return $_SERVER['REMOTE_ADDR'];
}

//リンク元読み出し。呼出し時URLで"ref="を一番最後に記述すること
function GetRef()
{
    $str = $_SERVER['QUERY_STRING'];

    $p = mb_strpos($str, 'ref=');

    if (0 == $p) {
        return '';
    }

    return mb_substr($str, mb_strpos($str, 'ref=') + 4);
}

//除外IP or HOST のチェック
function CheckException($ipaddr, $host)
{
    global $ExceptionHOST;

    global $ExceptionIP;

    for ($i = 0, $iMax = count($ExceptionIP); $i < $iMax; $i++) {
        if ('' != $ExceptionIP[$i] && false != mb_stristr($ipaddr, $ExceptionIP[$i])) {
            return 1;
        }
    }

    if ('' == $host) {
        return 0;
    }

    for ($i = 0, $iMax = count($ExceptionHOST); $i < $iMax; $i++) {
        if ('' != $ExceptionHOST[$i] && false != mb_stristr($host, $ExceptionHOST[$i])) {
            return 1;
        }
    }

    return 0;
}

//カウンタファイル入出力処理
function CountRead($mode, $page)
//mode  0: 読み出しのみで、カウンタを増やさない
//      1: カウンタを増やし、記録
//      2: 今日の数を返す(カウンタを増やさない)
//      3: 昨日の数を返す(カウンタを増やさない)
{
    global $DataDir;

    $fname = $DataDir . 'count_' . $page . '.dat';

    if (@file_exists($fname)) {
        $fp = @fopen($fname, 'r+b');

        $ex_flag = 1;
    } else {
        $fp = @fopen($fname, 'wb');
    }

    if (FLASE == $fp) {
        exit();
    }

    if (1 == $mode) {
        if (!@flock($fp, LOCK_EX)) {
            exit();
        }	//write mode
    } else {
        if (!@flock($fp, LOCK_SH)) {
            exit();
        }	//read only mode
    }

    if ($ex_flag) {
        $count = (int)fgets($fp, 256);

        $today = (int)@fgets($fp, 256);

        $yesterday = (int)@fgets($fp, 256);

        $LastUpdate = @fgets($fp, 256);

        if (1 == $mode) {	//カウンタの値を増やす
            $count++;

            $tmp_last = getdate(strtotime($LastUpdate));

            $tmp_now = getdate();

            $tmp_yes = getdate(strtotime('-1 day'));

            if ($tmp_last['yday'] != $tmp_now['yday']) {
                if ($tmp_last['yday'] == $tmp_yes['yday']) {
                    $yesterday = $today;
                } else {
                    $yesterday = 0;
                }

                $today = 0;
            }

            $today++;
        }
    } else {
        $count = 1;
    }

    if (1 == $mode) {	//カウンタファイルに保存
        @ftruncate($fp, 0);	//ファイルサイズを0に
        @rewind($fp);

        @fwrite($fp, $count . "	;total\n");

        @fwrite($fp, $today . "	;today\n");

        @fwrite($fp, $yesterday . "	;yesterday:\n");

        @fwrite($fp, date('Y/m/d H:i:s') . "\n");
    }

    @fclose($fp);

    switch ($mode) {
        default:
        case 0:
        case 1: return $count;
        case 2: return $today;
        case 3: return $yesterday;
    }
}

function MakeImage($number, $mode)
//$mode: 0:トータル数  1:昨日・今日
{
    global $ViewFigures;

    global $ImgSize_number_x;

    global $ImgSize_number_y;

    global $ImageSrcName;

    global $SubViewFigures;

    global $SubImgSize_number_x;

    global $SubImgSize_number_y;

    global $SubImageSrcName;

    global $ImageSrcType;

    if ($number < 0 || $number > 9999999999) {
        $number = 0;
    }

    //header("Content-Disposition: filename=\"count.png\"");

    $head_str[0] = 'Content-type: image/png';

    $head_str[1] = 'Content-type: image/gif';

    $head_str[2] = 'Content-type: image/jpeg';

    $head_str[3] = 'Content-type: image/bmp';

    if (0 == $mode) {
        $img_fname = $ImageSrcName;

        $size_x = $ImgSize_number_x;

        $size_y = $ImgSize_number_y;

        $figures = $ViewFigures;
    } else {
        $img_fname = $SubImageSrcName;

        $size_x = $SubImgSize_number_x;

        $size_y = $SubImgSize_number_y;

        $figures = $SubViewFigures;
    }

    switch ($ImageSrcType) {
        default:
        case 0:	$src_img = @imagecreatefrompng($img_fname); break;
        case 1:	$src_img = @imagecreatefromgif($img_fname); break;
        case 2:	$src_img = @imagecreatefromjpeg($img_fname); break;
        case 3:	$src_img = @imagecreatefrombmp($img_fname); break;
    }

    if (!$src_img) {	//読み込みエラー発生
        header($head_str[0]);

        $img = @imagecreate(160, 16); //空の画像を作成

        $bgc = @imagecolorallocate($img, 0, 0, 0);

        $tc = @imagecolorallocate($img, 255, 255, 0);

        @imagefilledrectangle($img, 0, 0, 160, 16, $bgc);

        @imagestring($img, 2, 4, 0, 'Error loading imgage file', $tc);

        @imagepng($img);

        return;
    }

    header($head_str[$ImageSrcType]);

    $CounterImage = @imagecreate($size_x * $figures, $size_y); //空の画像を作成

    $str = sprintf("%0{$figures}d", $number);

    for ($i = 0; $i < $figures; $i++) {
        @imagecopy($CounterImage, $src_img, $size_x * $i, 0, ($size_x * ($str[$i])), 0, $size_x, $size_y);
    }

    @imagepng($CounterImage);
}

//アクセスログ書き込み関数
function WriteLog($now, $count, $ref, $page, $host, $visit, $LastVisit)
{
    global $PageIndex;

    global $ApacheMode;

    global $FnameAccessLog;

    //$now				現在時刻

    //$count			アクセスカウント

    //$host				接続元ホスト

    //$dd				日時

    //$p_name			表示したページ

    //$ref				リンク元(Referer)

    //$visit			その人の訪問回数

    //$LastVisit		その人の前回の訪問日

    //$user_agent		ブラウザの種類

    //$server_protocol	HTTP/1.0 or  1.0 の情報

    $p_name = '/' . $PageIndex[$page];

    //if($p_name=="") $p_name = "/";

    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    $server_protocol = $_SERVER['SERVER_PROTOCOL'];

    if (mb_strlen($ref) > 512) {
        $ref = mb_substr($ref, 0, 512);
    }

    if ($ApacheMode) {
        //Apache互換形式で記録する場合

        $LastVisit = date('m/d H:i', $LastVisit);	//前回の訪問日を書式化

        $dd = date('d/M/Y:H:i:s +0', $now) . (date('Z', $now) / 36);

        $str = sprintf(
            "%s - - [%s] \"GET %s %s\" 200 300 \"%s\" \"%s\"\n",
            $host,
            $dd,
            $p_name,
            $server_protocol,
            $ref,
            $user_agent
        );
    } else {
        //標準形式で記録

        //スペースを変換

        $user_agent = str_replace(' ', '%20', $user_agent);

        $p_name = str_replace(' ', '%20', $p_name);

        $ref = str_replace(' ', '%20', $ref);

        $str = "{$count}\t{$now}\t{$host}\t{$p_name}\t{$ref}\t{$LastVisit}\t{$visit}\t{$user_agent}\n";
    }

    //ファイル書き込み

    $fp = @fopen($FnameAccessLog, 'a+b');

    if (!@flock($fp, LOCK_EX)) {
        exit();
    }	//ロック

    @fwrite($fp, $str);

    @fclose($fp);
}

//キリ番かどうかをチェックする関数
function kiriban_check($count)
{
    global $kiribantani;

    global $sp_kiriban;

    global $flag_Exception;	//除外IP

    global $flag_renzoku_access;

    if ($flag_Exception || $flag_renzoku_access) {
        return 0;
    }

    if ($kiribantani && 0 == ($count % $kiribantani)) {
        return 1;
    }

    for ($i = 0, $iMax = count($sp_kiriban); $i < $iMax; $i++) {
        if ($count == $sp_kiriban[$i]) {
            return 1;
        }
    }

    return 0;
}

//GDライブラリを使用せずに数字画像を並べて表示する場合の処理
function NonGDimgWrite($number, $figures)
{
    global $ImageSrcType;

    switch ($ImageSrcType) {
        default:
        case 0:	$imgt = 'png'; break;
        case 1:	$imgt = 'gif'; break;
        case 2:	$imgt = 'jpg'; break;
        case 3:	$imgt = 'bmp'; break;
    }

    if ($number < 0 || $number > 9999999999) {
        $number = 0;
    }

    $str = sprintf("%0{$figures}d", $number);

    for ($i = 0; $i < $figures; $i++) {
        $tag = sprintf("<img src='%d.%s'>", ($str[$i]), $imgt);

        print "document.write(\"$tag\");";
    }
}

function PrintJS_image($number)
{
    global $ViewFigures;

    global $UseGD;

    if ($UseGD) {
        ?>document.write("<img src='counter.php?mode=counter&count=<?php printf('%d', $number); ?>'>");<?php
    } else {
        NonGDimgWrite($number, $ViewFigures);
    }
}

function PrintJavaScript($count)
{
    //	header("Content-Disposition: filename=\"js1.js\"");

    header('Content-Type: application/x-javascript');

    global $KMes_a;

    global $KMes_b;

    global $DefName;

    global $HTMLpageEncoding;

    global $ScriptEncoding;

    if (kiriban_check($count)) {	//キリ番の時
?>
function GetName() {
<?php
    $str = $KMes_a . $count . $KMes_b;
    $str = mb_convert_encoding($str, $HTMLpageEncoding, $ScriptEncoding);

    print 'DefName="' . mb_convert_encoding($DefName, $HTMLpageEncoding, $ScriptEncoding) . "\";\n";
    print 'istr="' . $str . "\";\n";
?>
name=prompt(istr,DefName);
if(name=="null" || name=="") name=DefName;
url_sendname = "counter.php?mode=sendname&<?=SID?>&name="+escape(name);
document.write("<img src='" +url_sendname+ "' height=0 width=0><p>\n");
}
<?php
    PrintJS_image($count);
    } else { //キリ番で無い時
?>
function GetName() {return;}
<?php
    PrintJS_image($count);
    }
}

//ブラウザから送られたUTF-16のデータをデコード
function EncoedUnicoed($str)
{
    global $ScriptEncoding;

    if (0 == preg_match('%u', $str)) {
        return $str;
    }

    $dist = '';

    $len = mb_strlen($str);

    $p = 0;

    while ($p < $len) {
        if ('%u' == mb_substr($str, $p, 2) && preg_match('^[0-9a-fA-F]{4}$', mb_substr($str, $p + 2, 4))) {
            $tmp = (chr(hexdec(mb_substr($str, $p + 2, 2)))) . (chr(hexdec(mb_substr($str, $p + 4, 2))));

            $dist .= mb_convert_encoding($tmp, $ScriptEncoding, 'UTF-16');

            $p += 6;
        } else {
            $dist .= mb_substr($str, $p, 1);

            $p++;
        }
    }

    return $dist;
}

function CreateNullImg()	//空画像の表示
{
    header('Content-type: image/png');

    $img = @imagecreate(1, 1); //空の画像を作成

    $bgc = @imagecolorallocate($img, 0, 0, 0);

    $bgc = @imagecolortransparent($img, $bgc);	//透明色を指定

    @imagefilledrectangle($img, 0, 0, 0, 0, $bgc);

    @imagepng($img);
}

function GetKiribanName($page)
{
    global $KListEncoding;

    global $ScriptEncoding;

    global $HTMLpageEncoding;

    global $DataDir;

    $page = $_GET['page'];					//カウンタを設置したページ

    if ($page < 1 || $page > 999) {
        $page = 1;
    }

    $name = $_GET['name'];

    $name = mb_convert_encoding($name, $ScriptEncoding, $HTMLpageEncoding);

    $name = EncoedUnicoed($name);

    $name = trim($name);

    $name = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5);

    $name = mb_convert_kana($name, 'KV'); //半角仮名を全角に

    if ('' == $name) {
        $name = 'ななしのごんべ';
    }

    if (mb_strlen($name) > 16) {
        $name = mb_substr($name, 0, 16);
    }

    if (kiriban_check($_SESSION['count'])) {
        $str = sprintf("%07d\t%s\n", $_SESSION['count'], $name);

        $str = mb_convert_encoding($str, $KListEncoding, $ScriptEncoding);	//SJISに

        $fanem = $DataDir . 'kiriban_' . $page . '.txt';

        $fp = fopen($fanem, 'a+b');

        if (!flock($fp, LOCK_EX)) {
            die('flock');
        }	//ロック

        fwrite($fp, $str);

        fclose($fp);
    }

    CreateNullImg();	//空画像の表示

    session_unregister('count');
}

function PrintKiribanGetters()
{
    global $HTMLpageEncoding;

    global $ScriptEncoding;

    global $KListEncoding;

    global $DataDir;

    global $KiribanListHeader;

    $page = $_GET['page'];					//カウンタを設置したページ

    if ($page < 1 || $page > 999) {
        $page = 1;
    }

    //	header("Content-Disposition: filename=\"js2.js\"");

    header('Content-Type: application/x-javascript'); ?>function cs() {if(document.form_kg.kiriban.value==99) document.location="http://www.planet-green.com/";}<?php

    print "document.write('<form name=\"form_kg\">');";

    $fanem = $DataDir . 'kiriban_' . $page . '.txt';

    if (file_exists($fanem)) {
        $fp = fopen($fanem, 'rb');

        if (!flock($fp, LOCK_SH)) {
            die('flock');
        }	//ロック

        while (($tmp = fscanf($fp, "%07d\t%s\n"))) {
            [$cc[], $tmp_name] = $tmp;

            $name[] = mb_convert_encoding($tmp_name, $ScriptEncoding, $KListEncoding);

            $i++;

            if ($i > 8) {
                array_shift($cc);

                array_shift($name);
            }
        }

        fwrite($fp, $str);

        fclose($fp);
    }

    print "document.write('<select name=\"kiriban\" style=\"width=220\" onChange=\"cs();\">');\n";

    $str = mb_convert_encoding($KiribanListHeader, $HTMLpageEncoding);

    print "document.write('<option>$str</option>');\n";

    if (0 == count($cc)) {
        $str = mb_convert_encoding('まだいません(^-^;', $HTMLpageEncoding);

        print "document.write('<option>$str</option>');\n";
    } else {
        for ($i = 0, $iMax = count($cc); $i < $iMax; $i++) {
            $str = sprintf('%06d ', $cc[$i])
                    . mb_convert_encoding($name[$i], $HTMLpageEncoding)
                    . mb_convert_encoding('様', $HTMLpageEncoding);

            print "document.write('<option>$str</option>');\n";
        }
    }

    $str = mb_convert_encoding('-- CGI配布元にジャンプ-- ', $KListEncoding);

    print "document.write('<option value=99>$str</option>');\n";

    print "document.write('</select>');";

    print "document.write('</form>');";
}

function counter_main($mode, $page)
{
    global $c_limit;

    global $nslookup;

    global $SaveAccessLog;

    global $flag_Exception;

    global $flag_renzoku_access;

    $ipaddr = GetIp();									//接続元IPアドレス

    if ($nslookup) {
        $host = gethostbyaddr($ipaddr);
    }		//接続元ホスト

    else {
        $host = $ipaddr;
    }

    if (CheckException($ipaddr, $host)) {
        $flag_Exception = 1;
    }

    $visit = trim($_COOKIE['visit' . $page]);	//訪問回数
    $LastVisit = trim($_COOKIE['lastvisit' . $page]);	//最後に訪問した日時
    $now = time();

    if ($visit <= 0 || $visit > 100000) {
        $visit = 0;
    }

    if (0 == $LastVisit || $LastVisit < ($now - 86400 * 365) || $LastVisit > $now) {
        $LastVisit = 0;
    }

    //連続アクセスチェック

    if (0 == $LastVisit || $LastVisit <= ($now - $c_limit)) {
        $visit++;

        $flag_renzoku_access = 0;
    } else {
        $flag_renzoku_access = 1;
    }

    //連続アクセスチェック & 除外IPorHOSTチェック

    if (0 == $flag_Exception && 0 == $flag_renzoku_access) {
        $count = CountRead(1, $page);	//カウンタの値をファイルから読み書き

        $save_flag = 1;
    } else {
        //連続アクセスならカウンタの値を増やさない
        $count = CountRead(0, $page);	//カウンタの値をファイルから読み書き
    }

    //クッキーをセット。365日で期限切れ

    setcookie('visit' . $page, $visit, $now + 86400 * 365);

    setcookie('lastvisit' . $page, time(), $now + 86400 * 365);

    $_SESSION['count'] = $count;

    if ('js' == $mode) {
        PrintJavaScript($count);
    } elseif ('noimage' == $mode) {
        CreateNullImg();	//空画像の表示
    } else {
        MakeImage($count, 0);
    }		//カウンタ画像を作成・表示

    if ($SaveAccessLog && $save_flag) {
        //データ受け取り処理

        //$ref	= $_GET['ref'];				//リンク呼び出し元

        //$ref = rawurldecode ( $_GET['ref'] );

        $ref = GetRef();

        if ('' == $ref) {
            $ref = '-';
        }

        WriteLog($now, $count, $ref, $page, $host, $visit, $LastVisit);	//アクセスログファイルに書き込み
    }
}

//キャッシュ防止用
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');	// HTTP/1.1

$mode = $_GET['mode'];
$page = $_GET['page'];	//カウンタを設置したページ

if ($page < 1 || $page > 999) {
    $page = 1;
}

//PNG画像生成
if ('counter' == $mode) {
    MakeImage($_GET['count'], 0);

    exit;
}

//PNG生成・今日
if ('today' == $mode) {
    $count = CountRead(2, $page);	//カウンタの値をファイルから読む

    MakeImage($count, 1);

    exit;
}

//PNG生成・昨日
if ('yesterday' == $mode) {
    $count = CountRead(3, $page);	//カウンタの値をファイルから読む

    MakeImage($count, 1);

    exit;
}

//GDライブラリが使えない場合の今日のカウント表示
if ('today_js' == $mode) {
    $count = CountRead(2, $page);	//カウンタの値をファイルから読む

    NonGDimgWrite($count, $SubViewFigures);

    exit;
}

//GDライブラリが使えない場合の昨日のカウント表示
if ('yesterday_js' == $mode) {
    $count = CountRead(3, $page);	//カウンタの値をファイルから読む

    NonGDimgWrite($count, $SubViewFigures);

    exit;
}

session_start();

if ('sendname' == $mode) {
    GetKiribanName($page);

    exit;
}

if ('kg' == $mode) {
    PrintKiribanGetters();

    exit;
}

//JavaScriptからの書き出し or 画像無し解析ONLY (mode = "js" or "noimage" or "")
counter_main($mode, $page);

?>
