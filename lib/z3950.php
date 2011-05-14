<?php
/*
   Copyright (C) 2011 - Giannis Kosmas

   This file is part of Z3950toOAI-PMH.

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; version 2 dated June, 1991.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   A copy of the GNU General Public License is also available at
   <URL:http://www.gnu.org/copyleft/gpl.html>.  You may also obtain
   it by writing to the Free Software Foundation, Inc., 59 Temple
   Place - Suite 330, Boston, MA 02111-1307, USA.
*/

function set_options ($info) {
    $opt = set_wait_options ('30', FALSE);
    if (isset($info['user']) && $info['user'] != "") {
        $opt['user'] = $info['user'];
    }
    if (isset($info['password']) && $info['password'] != "") {
        $opt['password'] = $info['password'];
    }
    if (isset($info['proxy']) && $info['proxy'] != "") {
        $opt['proxy'] = $info['proxy'];
    }
    //$opt['piggyback'] = 'FALSE';
    return $opt;
}

function set_wait_options ($timeout, $event) {
    $opt["timeout"] = $timeout;
    $opt["event"] = $event;

    return $opt;
}

function display_yaz_error ($connection) {
    if (is_resource($connection)) {
        if (($error = yaz_error($connection))){
            $errorno = yaz_errno($connection);
            $addinfo = yaz_addinfo($connection);
            return "Search error: [$errorno] $error; addinfo '$addinfo'";
        }
    }
    return '';
}

function z_search ($target, $query, $sort_rpn = '') {
    $opt = set_options($target);
    $connection = yaz_connect($target['zurl'], $opt);
    if(is_resource($connection)) { // Connect succeded
        yaz_set_option($connection, "rpnCharset", $target['query_charset']);
        yaz_syntax($connection,$target["record_syntax"]);

        $rpn_query = $query;
        if ($sort_rpn != '' ) $rpn_query = $sort_rpn . " " . $rpn_query;
        yaz_search($connection,"rpn",$rpn_query);
    } else {
        return "Z39.50 Connection failed ";
    }
  return $connection;
}

function search_oai($target, $query, $number, $start) {
    global $document;
    global $debug;
    $debug = 0;

    $res = array();
    $res["records"] = array();

    if ($target['record_syntax'] == 'opac') { 
      $rsyntax = 'opac';
    } else {
      $rsyntax = 'xml';
    }
    $connection = z_search($target, $query);
    if (is_resource($connection)) {
        yaz_range($connection, $start, $number);
        $wait_opt = set_wait_options (30, FALSE);
        $yres = yaz_wait($wait_opt);
        if (yaz_errno($connection))
        {
         // print "<error code=\"" . yaz_errno($connection) . "\">" . yaz_error($connection) .  yaz_addinfo($connection)."$query </error>";
         $res["error"] = yaz_error($connection);
         $res["errorcode"] = yaz_errno($connection);
         yaz_close($connection);
         return $res;
        }
        $hits = yaz_hits($connection);
        $records = array();
        if ($hits > 0) {
            yaz_element($connection, $target['element']);
            $end = $start + $number - 1;
            if ($end > $hits) $end = $hits;

            $res["start"] = $start;
            $res["number"] = $number;
            $res["hits"] = $hits;
            $res["end"] = $end;

            for ($recno = $start, $count = 1; $recno <= $hits && $count <= $number; $recno++, $count++) {
                $record= array();
                $record["offset"] = $recno;

                $rec = preg_replace("/<\?.*?\?>/", "", yaz_record($connection, $recno, $rsyntax."; charset=".$target['record_charset'].",utf-8"));

                if (!$rec)
                 $record["record"] = "";
                else {
                 $record["record"] = $rec;
                }
                $records[] = $record;
            }
        }

        yaz_close($connection);
        if ($debug) print "<pre/>RPN=$query<pre/>";

        $res["records"] = $records;

    } else {
        $res["error"] = "Connection  to Z39.50 server failed";
        $res["errorcode"] = 1;
    }
    return $res;
}
?>
