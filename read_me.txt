マルチ機能アクセスカウンター(XOOPS2)
(ま る か ん た v1.40)
オリジナルスクリプト配布先
ぷらねっとぐりーん ( http://www.planet-green.com/ )

■動作対象 
PHP v4.1.0以降

■強力なアクセスログ解析機能
　・Referer（訪問者がどのリンクページから来たか）を記録できます。
　・全文検索型のサーチエンジン(Google,Infoseek,goo等)から来た時の検索キーワードを集計できます。
   google、goo、infoseek、yahoo。msn、lycos、fresheye
　・各訪問者について、何回目の訪問か、前回の訪問日時はいつかを記録できます。
　・アクセスログをCSVファイルとして、エクセル等で開くことが出来ます。

■設置方法
・ダウンロード
ダウンロードページより、マルチ機能アクセスカウンター(XOOPS)「Multicounter.zip」
をダウンロード、readme.txt 及びmanual/readme.htmlを参考にして下さい
特に設定はしなくても問題なく表示できるはずですが、ご自身の環境により設定すると良いでしょう。
・アップロード（アクセス権の変更）
(設置ディレクトリ/modeles/ここに入れる) [755]
　|
　|--/admin/data/ [777]...ログデータを保存する空ディレクトリ
　|
　|--/admin/counter.php [744]... カウンタースクリプト
　|--/admin/admin.php   [744]... 管理用スクリプト
　|--/admin/image.png   [744]... カウンタ用の元画像

下記タグを「footer.php」最下部に挿入する。

<!-- カウンター表示部ここから -->
<SCRIPT LANGUAGE="JavaScript"><!--
c_url = "<?php echo XOOPS_URL;?>/modules/Multicounter/admin/counter.php?page=1&mode=noimage&ref="+document.referrer;
document.write("<img src='"+c_url+"' width=1 height=1>");
// -->
</SCRIPT>
<NOSCRIPT><img src="<?php echo XOOPS_URL;?>/modules/Multicounter/admin/counter.php?page=1&mode=noimage" width=1 height=1></NOSCRIPT>
<!-- カウンター表示部ここまで --> 



■アクセスカウンタの表示方法
・GDライブラリー使用

管理メニュー項目よりブロック管理を選択、新規ブロック作成から
タイトル（任意名）仮にアクセスカウンターとする
コンテンツ入力フォームに下記タグをコピーして貼り付ける
コンテンツタイプは「HTML]を選択、
プレビューで確認して正常に表示されているか確認、送信ボタンをクリックする。

尚、下記タグはあくまでも参考です、ご自身で編集してご使用下さい

<--ここから
<TABLE width="100%" cellpadding="0" cellspacing="1">
<TBODY>
<TR>
<TD colspan="2" bgcolor="#3155bd" align="center" height="14"><FONT color="#ffffff"><B>マルチ機能カウンター</B></FONT></TD>
</TR>
<tr class='even'>
<TD width="224" height="20">本日</TD>
<TD align="right" width="391" height="20"><img src="{X_SITEURL}modules/Multicounter/admin/counter.php?page=1&mode=today"></TD>
</tr>
<tr class='odd'>
<TD width="224" height="20">昨日</TD>
<TD align="right" width="391" height="20"><img src="{X_SITEURL}modules/Multicounter/admin/counter.php?page=1&mode=yesterday"></TD>
</tr>
<tr class='even'>
<TD width="224" height="20">総合</TD>
<TD align="right" width="391" height="20"><SCRIPT LANGUAGE="JavaScript"><!--
c_url = "{X_SITEURL}modules/Multicounter/admin/counter.php?page=1&ref="+document.referrer;
document.write("<img src='"+c_url+"'>");
// -->
</SCRIPT>
<NOSCRIPT><IMG src="{X_SITEURL}modules/Multicounter/admin/counter.php?page=1"></NOSCRIPT>
</TD>
</TR>
<tr class='odd'>
<TD colspan="2" height="21">FROM 2004/5/28</TD>
</TBODY>
</TABLE>
<--ここまで
以上