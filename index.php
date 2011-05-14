<html>
<head>
<title>Z39.50 to OAI-PMH gateway</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<style type="text/css">
<!--

a:link, a:visited { background-color: transparent; }
a:link { color: #02008D; }
a:hover { color: #2700f5; }
a:visited { color: #02008D; }
a:visited:hover { color: #2700f5; }
body { color: #333; font-family: Verdana, Geneva, "Arial Unicode MS", Arial, Helvetica, sans-serif; }
p { line-height: 125%; margin: 0; padding: 6px 0 9px 0; }
h2 { clear: left; color: #222; font-weight: bold; margin: 0; padding: 16px 10px 2px 10px; }
hr { color: #999; height: 1px; width: 100%; }
/* colors */
.blue { background-color: #2700A5; }
.green { background-color: #62C448; }
.orange { background-color: #FF7600; }

table { border-right: 1px solid #999; border-bottom: 1px solid #999; margin: 12px 0; }
th, td { border-top: 1px solid #999; border-left: 1px solid #999; color: #333; line-height: 140%; padding: 9px; text-align: left; vertical-align: top; } 
th { background-color: #e0e0ff; }
/ * tables */
table#List { border: none; height: 100px; margin: 20px 10px 20px 10px; padding: 0; width: 100%; }
table#List td { background: #666; border: none; vertical-align: top; width: 50%; }
table#List td a:link, table#List td a:visited { color: #CCC; text-decoration: none; }
table#List td a:hover, table#List td a:visited:hover { color: #FFF; text-decoration: underline; }
table#List td#tdHeader { background-color:#b0d5ff; font-weight: bold; padding: 10px 20px 10px 20px; }
table#List td#tdHeader a:link, table#List td#tdHeader a:visited  { color: #FFF; text-decoration: none; }
table#List td#tdHeader a:hover, table#List td#tdHeader a:visited:hover { color: #FFF; text-decoration: underline; }
table#List td#tdHeader h2 { background-color:#e0e0ff; color: #000; text-align:center; padding: 10px 10px 10px 10px; }
table#List td#tdResearch { color: #EEE; font-size: xx-small; padding: 10px 0 10px 20px; }

h2 { font-size: 120%; }

.formtable { background: #F5F5F5; }
table.formtable th, table.formtable td { border-top: 1px solid #999; border-left: 1px solid #999; color: #333; padding: 4px; text-align: left; vertical-align: top; }
-->
</style>
</head>
<body>
<div align="center">
<table cellspacing="0" id="List">
<tr>
<td id="tdHeader"><h2>Repositories</h2></td>
</tr>
</table>
<table class="formtable">

<?php

    $dbs = array();
    $dir = ".";
    $dirs = scandir($dir, 0);
    foreach ($dirs as $file) {
      if ((filetype($dir .'/'. $file) == 'dir') && ($file != '.') && ($file != '..')) {
        $dh2 = @opendir($dir .'/'. $file);
        if ($dh2 != FALSE) {
          while (($file2 = readdir($dh2)) !== false) {
            if (preg_match("/^db\.info$/", $file2, $match)) {
              $db = array();
              $db['dir'] = $file;
              $path = "$dir/$file/$file2";
              $x = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
              foreach ($x as $l) {
                $j =explode('=',$l);
                $db[$j[0]] = $j[1];
              }
              $dbs[] = $db;
            }
          }
          closedir($dh2);
        }
      }
    }

    foreach ($dbs as $db) {
      echo "<tr><th><a href=\"".$db['dir']."?verb=Identify\">".$db['name']."</a></th><td>".$db['description']."</td></tr>";
    }

?>
</table>
</div>
</body>
</html>
