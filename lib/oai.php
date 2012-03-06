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

require_once(dirname(__FILE__).'/z3950.php');

global $z3950target, $oai_mps;

if (!file_exists('config.xml')) {
    header("HTTP/1.0 400 This is not a valid OAI-PMH repository .");
    echo("HTTP/1.0 400 This is not a valid OAI-PMH repository.");
    die;
} else {
    header("Content-Type: text/xml");
    parse_oai_config();
}
    
foreach (array_merge($_GET, $_POST) as $key => $value) {  // To cope with "register_globals=off" in PHP-4.2.x
    $$key = $value;
}

if (isset($_REQUEST['metadataPrefix'])) $metadataPrefix = $_REQUEST['metadataPrefix']; else $metadataPrefix='';
if (isset($_REQUEST['resumptionToken'])) $resumptionToken = $_REQUEST['resumptionToken']; else $resumptionToken='';
if (isset($_REQUEST['set'])) $set = $_REQUEST['set']; else $set='';

// Here goes the valid verbs as well as the additional parameters, mandatory (1) as well as optional (2)
$verbs = array(
    "Identify"            => array(),
    "ListMetadataFormats" => array("identifier"=>2),
    "ListSets"            => array("resumptionToken" =>2),
    "ListRecords"         => array("metadataPrefix"=>1, "from"=>2, "until"=>2, "set"=>2, "resumptionToken" =>2),
    "GetRecord"           => array("metadataPrefix"=>1, "identifier"=>1),
    "ListIdentifiers"     => array("metadataPrefix"=>1, "from"=>2, "until"=>2, "set"=>2, "resumptionToken" =>2),
);

// The XML-header
$xml_header = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
$xsl_header = "<?xml-stylesheet type=\"text/xsl\" href=\"../xsl/oai2.xsl\" ?>";

// The OAI-PMH root tag should be like this according to OAI/v2
$oai_pmh = <<<END
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/
         http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">
END;

function do_oai() {
    global $oai_pmh;
    global $xml_header;
    global $xsl_header;
    global $oai_base_url;
    global $repositoryName;
    global $id_prefix;
    global $adminEmail;
    global $from, $until;
    global $resumptionToken;
    global $set;
    global $enable_compression;
    global $metadataPrefix;
    global $datexslp;
    global $idxslp;
    global $recxslp;
    global $oai_mps;
    global $z3950target;
    global $earliestDatestamp;

    $verb =  $_REQUEST['verb'];
    $dir =  get_server_dir();

    $oai_base_url = "http://" .
        $_SERVER['SERVER_NAME'] .
        ($_SERVER['SERVER_PORT'] != 80 ? (":" . $_SERVER['SERVER_PORT']) : '').
        $dir;

    $outputXML = '';
    $outputXML .= $xml_header . "\n";
    $outputXML .= $xsl_header . "\n";
    $outputXML .= $oai_pmh . "\n";
    $outputXML .= "<responseDate>" . get_utc() . "</responseDate>\n";
    switch($_SERVER['REQUEST_METHOD'])
    {
        case 'GET': $the_request = $_GET; break;
        case 'POST': $the_request = $_POST; break;
        default: $the_request = array(); break;
    }
    $outputXML .= "<request";
    foreach ($the_request as $rqkey => $rqvalue) {
        $outputXML .= " $rqkey=\"" . make_wellformed_XML(stripslashes($rqvalue)) ."\"";
    }
    $outputXML .= ">$oai_base_url</request>\n";
    
    $sanityXML = '';
    if (sanity_check($sanityXML)) {
        if ( ($verb == "GetRecord") || 
             ($verb == "ListRecords") || 
             ($verb == "ListIdentifiers")) {
            $recxsldoc = new DOMDocument();
            $recxsldoc->load( $oai_mps[$metadataPrefix]['xslt'] );
            $recxslp = new XSLTProcessor();
            $recxslp->importStyleSheet($recxsldoc);
            $recxslp->registerPHPFunctions();

            $idxsldoc = new DOMDocument();
            $idxsldoc->load( $z3950target['recordid_xslt'] );
            $idxslp = new XSLTProcessor();
            $idxslp->importStyleSheet($idxsldoc);
            $idxslp->registerPHPFunctions();

            $datexsldoc = new DOMDocument();
            $datexsldoc->load( $z3950target['datestamp_xslt'] );
            $datexslp = new XSLTProcessor();
            $datexslp->importStyleSheet($datexsldoc);
            $datexslp->registerPHPFunctions();
            $datexslp->setParameter('', 'format' , (check_date_format ($earliestDatestamp) == '1' ? 'long' : 'short'));
        }
        $outputXML .= $verb();
    } else {
        $outputXML .= $sanityXML;
    }
    
    $outputXML .= "</OAI-PMH>\n";
    $domdoc = new DOMDocument;
    if (!$domdoc) {
        die;
    }
    $domdoc->preserveWhiteSpace = false;
    $domdoc->formatOutput = true;
    if ($domdoc->loadXML($outputXML) === FALSE) {
       die;
    }
 
    $outputXML =  $domdoc->saveXML();

    if (($enable_compression == true) && (strstr($_SERVER['HTTP_ACCEPT_ENCODING'],'gzip'))) {
      header('Content-Encoding: gzip');
      echo gzencode($outputXML ,9, FORCE_GZIP);
      //echo gzencode(preg_replace("/(\r\n|\n)/",'',$outputXML),9, FORCE_GZIP);
    } else if (($enable_compression == true) && (strstr($_SERVER['HTTP_ACCEPT_ENCODING'],'deflate'))) {
      header('Content-Encoding: deflate');
      echo gzdeflate($outputXML, 9);
      //echo gzdeflate(preg_replace("/(\r\n|\n)/",'',$outputXML),9);
    } else {
      echo $outputXML;
    }

    die;
}


// Check if we have a valid OAI request
function sanity_check (&$sXML) {
    global $verbs;
    global $verb;
    global $from;
    global $until;
    global $metadataPrefix, $oai_mps;
    global $identifier;
    global $id_prefix;
    global $resumptionToken;
    $sXML = '';
    
    if (! isset($verbs[$verb])) {
        $sXML = "<error code=\"badVerb\">Unrecognized OAI verb</error>\n";
        return 0;
    }

    $required = $verbs[$verb];
    
    // Check for multiple arguments in URI (this must be a joke - but it isn't!)
    $fields = preg_split("/&/", $_SERVER['QUERY_STRING']);
    $args = array();
    foreach ($fields as $field) {
        if (preg_match("/(.*?)=/", $field, $match)) {
            $arg_name = $match[1];
            if (isset($args[$arg_name])) {
                $sXML = "<error code=\"badArgument\">Repeated $arg_name argument</error>\n";
                return 0;
            } else {
                $args[$arg_name] = 1;
            }
        }
    }

    if (isset($_REQUEST['junk'])) {
        $sXML = "<error code=\"badVerb\">Unrecognized OAI verb</error>\n";
        return 0;
    }

    if (!(is_array($verbs[$verb]) && function_exists($verb))) {
        $sXML = "<error code=\"badVerb\">Unrecognized OAI verb</error>\n";
        return 0;
    }

    if ((isset($resumptionToken)) && ($resumptionToken != '')) {
        $rs = parse_resumption_token ($resumptionToken);
        if ($rs == FALSE) {
          $sXML = "<error code=\"badResumptionToken\">Bad resumption token</error>\n";
          return 0;
        }
    }

    if ( ! (isset($required["resumptionToken"]) && ($resumptionToken != ''))) {
        foreach ($required as $p => $type) {                // Check that required arguments are there
            if (($type == 1) && empty($_REQUEST[$p])) {
                $sXML = "<error code=\"badArgument\">$p must be specified</error>\n";
                //$sXML = "<error code=\"badArgument\">Missing $p argument to $verb</error>\n";
                return 0;
            }
        }
    }
    
    // Check if we have unexpected arguments...
    foreach (array_merge($_GET, $_POST) as $req_name => $req_val) {
        if ((strtolower($req_name) != "verb") && (!isset($verbs[$verb][$req_name]))) {
            $sXML = "<error code=\"badArgument\">Illegal argument</error>\n";
            return 0;
        }
    }
    
    // Check date pattern...
    if (strlen($from) && !check_date_format($from)) {
        $sXML = "<error code=\"badArgument\">Bad from argument</error>\n";
        return 0;
    }
    if (strlen($until) && !check_date_format($until)) {
        $sXML = "<error code=\"badArgument\">Bad until argument</error>\n";
        return 0;
    }

    // Check if we can provide the data in the specified metadata format...
    if (strlen($metadataPrefix)) {
        if (!is_array($oai_mps[$metadataPrefix])) {
            $sXML = "<error code=\"cannotDisseminateFormat\">Unknown format.</error>\n";
            return 0;
        }
    }

    // Verify format of Identifier (if specified) ...
    if (strlen($identifier)) {
        if (!preg_match("/^$id_prefix:.*/", $identifier)) {
            $sXML = "<error code=\"idDoesNotExist\">Identifier '$identifier' not recognized.</error>\n";
            return 0;
        }
    }

    // Default is OKAY:
    return 1;
}


function check_date_format ($str) {
    if (preg_match("/^(\d\d\d\d)-(\d\d)-(\d\d)T(\d\d):(\d\d):(\d\d)Z/", $str, $match)) {
        $month = (int) $match[2];
        $day = (int) $match[3];

        if (($day < 1) or ($day > 31)) {
            return 0;
        }

        if (($month < 1) or ($month > 12)) {
            return 0;
        }

        return 1;
    } else if (preg_match("/^(\d\d\d\d)-(\d\d)-(\d\d)$/", $str, $match)) {
        $month = (int) $match[2];
        $day = (int) $match[3];

        if (($day < 1) or ($day > 31)) {
            return 0;
        }

        if (($month < 1) or ($month > 12)) {
            return 0;
        }

        return 2;
    } else {
        return 0;
    }
}

function get_datestamp ($xdoc) {
    global $datexslp;

    $datestamp = "";
    //FIXME check if xsltprocessor is set...

    if (!( $datestamp = $datexslp->transformToXML($xdoc)) ) {
      $datestamp = "";
    }

    return $datestamp;
}

function dump_record($xdoc, $datestamp) {
    global $recxslp;

    $ret = "";

    set_error_handler("XSLErrorHandling");
    try {
    $result = $recxslp->transformToXML($xdoc);
    } catch (Exception $e) {
        restore_error_handler();
        die(echoException($e));
    }

    restore_error_handler();
    if ($result) {

        $result = preg_replace("/<\?.*?\?>\s*/", "", $result);

        if (preg_match("/<nothing/", $result))
            return $ret;

        $ret .= "<record>\n";
        $ret .= make_record_identifier($xdoc, $datestamp);
        $ret .= "<metadata>\n";
        $ret .= "$result";
        $ret .= "</metadata>\n";
        $ret .= "</record>\n";
    }
    return $ret;
}

function dump_records($handler) {
    global $z3950target, $max_items_per_request, $doctypes, $from, $until, $set, $metadataPrefix, $resumptionToken;

    $ret = "";
    $count_items = 0;

    $number = 100;
    $start = 0;

    if ($resumptionToken == '') {
      $start = 1;
    } else {
      $rst = parse_resumption_token ($resumptionToken);
      $set = $rst['set'];
      $from = $rst['from'];
      $until = $rst['until'];
      $metadataPrefix = $rst['metadataPrefix'];
      if (preg_match('@^[0-9]+$@',$rst['pos']) == 1) {
        $start = $rst['pos'];
      } else { //FIXME
        $start = 1;
        $resumptionToken = "";
      } 
    }

    $last_offset = "";

    $pqf = get_pqf($set);

    if ($pqf == '') echo 'ERROR: FIX PQF Setting for '.$set.' set.'; //FIXME

    do {
      $response = search_oai($z3950target, $pqf, $number, $start);

      if ((isset($response["error"])) || (!(isset($response["hits"]))))  {
        return empty_result_set();
      } else {

        foreach ($response["records"] as $zrec) {
         $item = $zrec["record"];
         $inc = 1;
         if ($item != '') {
           $xdoc = new DOMDocument;
           $xdoc->loadXML($item);
           $date_stamp = get_datestamp($xdoc);

           // Enforce date selection, if we have "from" specified...
           if (strlen($from)) {
             if (strcmp($date_stamp, $from) < 0) {
               $inc = 0;
             }
           }

           // ditto if we have until specified...
           if (strlen($until)) {
             if (strcmp($date_stamp, $until) > 0) {
               $inc = 0;
             }
           }

           if ($inc == 1) {
             $count_items++;
             $last_offset = $zrec["offset"];
             $ret .= $handler($xdoc, $date_stamp);
           }
           if ($max_items_per_request == $count_items) break;
         }
        }
      }
      $start = $start + $number;
      //$ret = $ret . "<x>$last_offset $count_items $max_items_per_request $start ". $response["hits"] ."</x>";
    } while (($count_items < $max_items_per_request) && ($start <= $response["hits"]));


    if (($resumptionToken == "") && ($count_items == 0)) {
     $ret .= empty_result_set();
    }

    if (($last_offset != "") && ($response["hits"] != $last_offset)) {
      $a = $last_offset + 1 ;
      $resumptionToken = $set.':'.$metadataPrefix.':'.$from.':'.$until.':'.$a;
    }
    else 
      $resumptionToken = "";

    return $ret;
}

function ListRecords () {
global $metadataPrefix;
global $oai_sets;
global $resumptionToken;

    $xml = '';
    $result = dump_records("dump_record");
    if ($result) {
        $xml .= "<ListRecords>\n";
        $xml .= $result;
        if ($resumptionToken != "")
           $xml .= "<resumptionToken>". $resumptionToken ."</resumptionToken>";
        $xml .= "</ListRecords>\n";
    } else {
        $xml .= empty_result_set();
    }
    return $xml;
}


function empty_result_set () {
    return "<error code=\"noRecordsMatch\">Nothing satisfies the specification.</error>\n";
}

function Identify () {
    global $oai_base_url;
    global $repositoryName;
    global $repositoryIdentifier;
    global $sampleIdentifier;
    global $adminEmail;
    global $enable_compression;
    global $granularity;
    global $earliestDatestamp;
    global $oaiIdentifyDescriptions;

    $xml = '';   
    $xml .= "<Identify>\n";
    $xml .= "<repositoryName>$repositoryName</repositoryName>\n";
    $xml .= "<baseURL>$oai_base_url</baseURL>\n";
    $xml .= "<protocolVersion>2.0</protocolVersion>\n";
    $xml .= "<adminEmail>$adminEmail</adminEmail>\n";
    $xml .= "<earliestDatestamp>".$earliestDatestamp."</earliestDatestamp>\n";
    $xml .= "<deletedRecord>no</deletedRecord>\n";
    $xml .= "<granularity>".$granularity."</granularity>\n";
    if ($enable_compression == true) {
      $xml .= "<compression>gzip</compression>";
      $xml .= "<compression>deflate</compression>";
    }
    $xml .= <<<END
<description>
  <oai-identifier xmlns="http://www.openarchives.org/OAI/2.0/oai-identifier"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai-identifier http://www.openarchives.org/OAI/2.0/oai-identifier.xsd">
    <scheme>oai</scheme> 
END;
    $xml .= "<repositoryIdentifier>$repositoryIdentifier</repositoryIdentifier>";
    $xml .= "<delimiter>:</delimiter>";
    $xml .= "<sampleIdentifier>$sampleIdentifier</sampleIdentifier>";
    $xml .= "</oai-identifier>";
    $xml .= "</description>";

    foreach ($oaiIdentifyDescriptions as $idd) {
      $xml .= $idd;
    }

    $xml .= "</Identify>\n";

    return $xml;
}

// Returns record Identifier given the OAI identifier
function get_Identifier ($id) {
    global $id_prefix;
    return preg_replace("/^$id_prefix:/", "", $id);
}

function GetRecord () {
    global $identifier, $z3950target;
    $xml = '';
    $xml .= "<GetRecord>\n";
    $id = get_Identifier($identifier);

    $response = search_oai($z3950target, $z3950target['recordid_searchquery']. " ".$id, 1, 1);

    if ((isset($response["error"])) || (!(isset($response["hits"]))))  {
        return empty_result_set();
    } else {
        $zrec = $response["records"][0];
        $item = $zrec["record"];
        if ($item != '') {
            $xdoc = new DOMDocument;
            $xdoc->loadXML($item);
            $date_stamp = get_datestamp($xdoc);
            $xml .= dump_record($xdoc, $date_stamp);
        } else {
            return empty_result_set();
        }
    }
    $xml .= "</GetRecord>\n";
    return $xml;
}

function ListIdentifiers () {
global $oai_sets;
global $resumptionToken;

    $xml = '';

    $result = dump_records("make_record_identifier");
    if ($result) {
        $xml .= "<ListIdentifiers>\n";
        $xml .= $result;
        if ($resumptionToken != "") 
           $xml .= "<resumptionToken>". $resumptionToken ."</resumptionToken>";
        $xml .= "</ListIdentifiers>\n";
    } else {
        $xml .= empty_result_set();
    }
    return $xml;
}

function make_record_identifier ($xdoc, $datestamp) {
    global $repositoryIdentifier, $idxslp, $set, $default_set;
    
    if (!( $recid = $idxslp->transformToXML($xdoc)) ) {
      $recid = "";
    }

    $ret = "<header>\n";
    if ($recid != '' ) {
        $ret .= "<identifier>oai:$repositoryIdentifier:" . $recid . "</identifier>\n";
    }
    
    if ($datestamp != '' ) {
        $ret .= "<datestamp>$datestamp</datestamp>\n";
    }
    if ($set != '' ) {
      $ret .= "<setSpec>$set</setSpec>\n";
    } else {
      $ret .= "<setSpec>$default_set</setSpec>\n";
    }
    $ret .= "</header>\n";

    return $ret;
}

function ListMetadataFormats () {
    global $oai_mps, $identifier;

    $xml = '';
    if (!is_array($oai_mps)) {
        die("<b>Fatal:</b> List of metadataformats not initialized");
    }

    $available_formats = $oai_mps;

    $xml .= "<ListMetadataFormats>\n";

    foreach ($available_formats as $prefix => $info) {
        $xml .= "<metadataFormat>\n";
        
        foreach ($info as $key => $value) {
            if (($key == 'schema') || ($key == 'metadataPrefix') || ($key == 'metadataNamespace'))  {
                $xml .= "<$key>$value</$key>\n";
            }
        }

        $xml .= "</metadataFormat>\n";
    }
    
    $xml .= "</ListMetadataFormats>\n";
    return $xml;
}

function parse_oai_config() {

global $oai_sets;
global $repositoryName;
global $repositoryIdentifier;
global $earliestDatestamp;
global $sampleIdentifier;
global $id_prefix;
global $adminEmail;
global $granularity;
global $oaiIdentifyDescriptions = array();
global $deletedRecord;
global $oai_mps;
global $max_items_per_request;
global $z3950target;
global $enable_compression;
global $default_set;

    $default_set = '';
    $repositoryName = 'Unknown';
    $repositoryIdentifier = 'Unknown';
    $id_prefix = "oai:$repositoryIdentifier";
    $sampleIdentifier = $id_prefix.":1";
    $adminEmail = 'admin@example.com';
    $max_items_per_request = 100;

    $oai_sets = array();
    $oai_mps = array();

    if (file_exists('config.xml')) {
      $d = new DOMDocument();
      if ( $d->load('config.xml') == TRUE) {
        $doc = $d->documentElement;

        $nodeList = $d->getElementsByTagName('repositoryName');
        if ($nodeList->length > 0) {
          $repositoryName = $nodeList->item( 0 )->firstChild->nodeValue;
        }
        $nodeList = $d->getElementsByTagName('repositoryIdentifier');
        if ($nodeList->length > 0) {
          $repositoryIdentifier = $nodeList->item( 0 )->firstChild->nodeValue;
          $id_prefix = "oai:$repositoryIdentifier";
        }
        $nodeList = $d->getElementsByTagName('sampleIdentifier');
        if ($nodeList->length > 0) {
          $sampleIdentifier = $nodeList->item( 0 )->firstChild->nodeValue;
        }
        $nodeList = $d->getElementsByTagName('earliestDatestamp');
        if ($nodeList->length > 0) {
          $earliestDatestamp = $nodeList->item( 0 )->firstChild->nodeValue;
        }
        $nodeList = $d->getElementsByTagName('granularity');
        if ($nodeList->length > 0) {
          $granularity = $nodeList->item( 0 )->firstChild->nodeValue;
        }
        $nodeList = $d->getElementsByTagName('deletedRecord');
        if ($nodeList->length > 0) {
          $deletedRecord = $nodeList->item( 0 )->firstChild->nodeValue;
        }
        $nodeList = $d->getElementsByTagName('adminEmail');
        if ($nodeList->length > 0) {
          $adminEmail = $nodeList->item( 0 )->firstChild->nodeValue;
        }
        $nodeList = $d->getElementsByTagName('max_items_per_request');
        if ($nodeList->length > 0) {
          $max_items_per_request = $nodeList->item( 0 )->firstChild->nodeValue;
        }
        $enable_compression = false;
        $nodeList = $d->getElementsByTagName('enable_compression');
        if ($nodeList->length > 0) {
          $enable_compression = strtolower($nodeList->item( 0 )->firstChild->nodeValue);
          if (($enable_compression == 'true') || ($enable_compression == 'yes')) {
            $enable_compression = true;
          } else {
            $enable_compression = false;
          }
        }

        $xpid = new DOMXPath($d);
        $id_descr = $xpid->query("/oai_description/IdentifyDescriptions/description");
        if ( isset($id_descr) ) {
          foreach ($id_descr as $idd) {
            $oaiIdentifyDescriptions[] = $d->saveXML($idd);
          }
        }

        $xp = new DOMXPath($d);
        $sets = $xp->query("/oai_description/sets/set");
        if ( isset($sets) ) {
          foreach ($sets as $set) {
            $oai_set = array();
            $oai_set["default"] = "0";
            if ($set->hasAttributes() == TRUE) {
              if ($set->getAttribute("default") == "1") {
                $oai_set["default"] = "1";
              }
            }
            if ($set->hasChildNodes() == TRUE) {
              $nodes = $set->childNodes;
              foreach ($nodes as $n) {
                if (($n->nodeType == XML_ELEMENT_NODE)) {
                  if (($n->nodeName == "pqf") 
                      || ($n->nodeName == "spec")
                      || ($n->nodeName == "name")
                      || ($n->nodeName == "description")) {
                    $oai_set[$n->nodeName] = $n->nodeValue;
                  }
                }
              }
              if ($oai_set["default"] == "1") $default_set = $oai_set["spec"];
            }
            $oai_sets[] = $oai_set;
          }
        }

        $xp = new DOMXPath($d);
        $mps = $xp->query("/oai_description/metadataFormats/metadataFormat");
        if ( isset($mps) ) {
          foreach ($mps as $mp) {
            $mpx = '';
            $oai_mp = array();
            if ($set->hasChildNodes() == TRUE) {
              $nodes = $mp->childNodes;
              foreach ($nodes as $n) {
                if (($n->nodeType == XML_ELEMENT_NODE)) {
                  if ($n->nodeName == "metadataPrefix") $mpx = $n->nodeValue;
                  if (($n->nodeName == "metadataPrefix")
                      || ($n->nodeName == "schema")
                      || ($n->nodeName == "metadataNamespace")
                      || ($n->nodeName == "xslt")) {
                    $oai_mp[$n->nodeName] = $n->nodeValue;
                  }
                }
              }
            }
            if ($mpx != '') $oai_mps[$mpx] = $oai_mp;
          }
        }

        $z3950target = array();
        $xp = new DOMXPath($d);
        $z = $xp->query("/oai_description/z3950");
        if ( isset($z) ) {
          foreach ($z as $ze) {
            if ($set->hasChildNodes() == TRUE) {
              $nodes = $ze->childNodes;
              foreach ($nodes as $n) {
                if (($n->nodeType == XML_ELEMENT_NODE)) {
                  $z3950target[$n->nodeName] = $n->nodeValue;
                }
              }
            }
          }
        }


      }
    }
}

//-----------------------------------------------------------

function get_server_dir() {

    if (isset($_SERVER['PATH_INFO']) && strlen($_SERVER['PATH_INFO'])) return $_SERVER['PATH_INFO'];

    if(strpos($_SERVER['PHP_SELF'], ".php") === false ) {
        return $_SERVER['PHP_SELF'];
    } else {
        $pathinfo = explode('.php',$_SERVER['PHP_SELF']);
        return strlen($pathinfo[1]) ? $pathinfo[1] : $_SERVER['PHP_SELF'];
    }

}

function make_wellformed_XML($str) {
    $patterns[0] = "/&/";
    $patterns[1] = "/'/";
    $patterns[2] = "/>/";
    $patterns[3] = "/</";
    $patterns[4] = "/\"/";

    $replacements[0] = "&amp;";
    $replacements[1] = "&apos;";
    $replacements[2] = "&gt;";
    $replacements[3] = "&lt;";
    $replacements[4] = "&quot;";

    return preg_replace($patterns, $replacements, $str);
}

function get_pqf($name) {
 global $oai_sets;

 $res = "";
 if ($name != '') {
   foreach ($oai_sets as $oai_set) {
    if ($oai_set["spec"] == $name) {
     $res = $oai_set["pqf"];
     break;
    }
   }
 } else {
   foreach ($oai_sets as $oai_set) {
    if ($oai_set["default"] == "1") {
     $res = $oai_set["pqf"];
     break;
    }
   }
   if (($res == "") && (isset($oai_sets[0]["pqf"])))
     $res = $oai_sets[0]["pqf"];
 }
 return $res;
}

function parse_resumption_token ($r) {
  // Should be in this format : set:metadataPrefix:from:until:position
 global $set, $metadataPrefix, $from, $until;
 $params = array();
 $params = explode( ':', $r );
 if (count($params) != 5) {
   return FALSE;
 }
 $ret = array();
 $ret['set'] = $params[0];
 $set = $params[0];
 $ret['metadataPrefix'] = $params[1];
 $metadataPrefix = $params[1];
 $ret['from'] = $params[2];
 $from = $params[2];
 $ret['until'] = $params[3];
 $until = $params[3];
 $ret['pos'] = $params[4];
 unset($params);
 return $ret;
}

function get_utc ($time='') {
    if (!$time)
        $time = time();

    $epoch = $time - date("Z");        // The current Unix time-stamp with respect to UTC

    return date("Y-m-d", $epoch) . "T" . date("H:i:s", $epoch) . "Z";
}

function ListSets () {
    global $oai_sets;
    $xml = '';
    $xml .= "<ListSets>\n";

    foreach ($oai_sets as $oai_set) {
        $xml .= "<set>\n";
        if (isset($oai_set["spec"])) $xml .= "<setSpec>".$oai_set["spec"]."</setSpec>\n";
        if (isset($oai_set["name"])) $xml .= "<setName>".$oai_set["name"]."</setName>\n";
        if (isset($oai_set["description"])) {
          $xml .= "<setDescription>\n";
          $xml .= "<oai_dc:dc xmlns:oai_dc=\"http://www.openarchives.org/OAI/2.0/oai_dc/\"
            xmlns:dc=\"http://purl.org/dc/elements/1.1/\"
            xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
            xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/oai_dc/
            http://www.openarchives.org/OAI/2.0/oai_dc.xsd\">
            <dc:description>";
          $xml .= $oai_set["description"];
          $xml .= "</dc:description>\n";
          $xml .= "</oai_dc:dc>";
          $xml .= "</setDescription>\n";
        }
        $xml .= "</set>\n";
    }
    
    $xml .= "</ListSets>\n";
    return $xml;
}

if (!function_exists('XSLErrorHandling')) {
function XSLErrorHandling ($errno, $errstr, $errfile, $errline, $errcontext) {

    echo "<div><h1>Error parsing XML:</h1>";

    switch ($errno) {
        case E_USER_ERROR:
             echo "<b>XSL ERROR</b> [$errno] $errstr<br />\n";
             echo "<i>Fatal error on line $errline in file $errfile</i>";
             break;

        case E_USER_WARNING:
             echo "<b>XSL WARNING</b> [$errno] $errstr<br />\n";
             echo "<i>Warning on line $errline in file $errfile</i>";
             break;

        case E_USER_NOTICE:
             echo "<i><b>XSL NOTICE</b> [$errno] $errstr<br /></i>\n";
             echo "<i>Notice on line $errline in file $errfile</i>";
             break;

        default:
             echo "Unknown error type: [$errno] $errstr<br />\n";
             echo "<i>Error on line $errline in file $errfile</i>";
             break;
    }

    echo "</div>";

    if ($errno == E_USER_ERROR) {
        exit(1);
    }

    //DO NOT CALL DEFAULT ERROR HANDLER
    return false;
}
}

function echoException($e) {
    return '<h3>Exception error</h3><hr/><p><b>Exception message:</b><br/><i>'.$e->getMessage().'</i></p>';
}


?>
