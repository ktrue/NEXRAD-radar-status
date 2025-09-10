<?php
// PHP script by Ken True, webmaster@saratoga-weather.org
// radar-status.php  
//  Version 1.00 - 21-Jan-2008 - Inital release
//  Version 1.01 - 30-Jan-2008 - handle improperly terminated message
//  Version 1.02 - 25-Feb-2008 - integrate with carterlake/WD/PHP template settings control
//  Version 1.03 - 07-Apr-2008 - fixed $SITE['showradarstatus'] actions for true=show, false=suppress
//  Version 1.04 - 04-Feb-2009 - fixed for NWS site format change
//  Version 1.05 - 03-Jul-2009 - added support for PHP5 timezone setting
//  Version 1.06 - 26-Jan-2011 - added support for $cacheFileDir global cache directory
//  Version 1.07 - 31-Aug-2012 - removed excess \n characters in messages and handling for chunked response
//  Version 1.08 - 22-Jan-2013 - fixed deprecated function errata
//  Version 1.09 - 09-May-2014 - fixed data issue with NWS page design change
//  Version 1.10 - 04-Jun-2015 - changed to new URL for radar status report
//  Version 1.11 - 04-Jun-2015 - added display of messages from ftm.txt with new NWS page design
//  Version 1.12 - 06-Jul-2015 - fixes for NWS page design changes
//  Version 1.13 - 03-Aug-2016 - fixes for NWS URL/page design changes
//  Version 1.14 - 24-Sep-2016 - fixes for NWS page design changes
//  Version 1.15 - 22-Feb-2017 - use cURL for URL fetch+improved debugging info
//  Version 1.16 - 04-Feb-2019 - use https for radar3pub.ncep.noaa.gov
//  Version 1.17 - 22-May-2020 - update to allow use with wxnwsradar script set
//  Version 1.18 - 18-Jan-2022 - fixes for Deprecated errata with PHP 8.1
//  Version 1.19 - 06-Apr-2022 - more fixes for Deprecated errata with PHP 8.1
//  Version 1.20 - 27-Dec-2022 - fixes for PHP 8.2
//  Version 1.21 - 08-Jul-2024 - fixes for bad data from https://radar3pub.ncep.noaa.gov/
//  Version 2.00 - 10-Sep-2025 - rewrite to use api.weather.gov/radar information

    $Version = "radar-status.php V2.00 - 10-Sep-2025";
//  error_reporting(E_ALL);  // uncomment to turn on full error reporting
// script available at https://saratoga-weather.org/scripts.php
//  
// you may copy/modify/use this script as you see fit,
// no warranty is expressed or implied.
//
// Customized for: NOAA radar status from
//   https://api.weather.gov/radar/stations/{SITE} and
//   https://api.weather.gov/products/types/FTM/locations/{last-3-char-ID}  i.e. MUX messages
//
//
// output: creates XHTML 1.0-Strict HTML page (default)
//
// Usage:
//  you can use this webpage standalone (customize the HTML portion below)
//  or you can include it in an existing page:
//  no parms:  $doIncludeRS = true; include("radar-status.php"); 
//
// settings:  
//  change myRadar to your local NEXRAD radar site ID.
//    other settings are optional
//  Note: main settings here will be overridden by entries in Setings.php
//    when using the Saratoga website USA template
// 
  $myRadar = 'KMUX';   // San Francisco
//
  $noMsgIfActive = true; // set to true to suppress message when radar is active
//
  $ourTZ   = 'America/Los_Angeles'; // timezone
  $timeFormat = 'D, d-M-Y g:ia T';
//
// boxStyle is used for <div> surrounding the output of the script .. change styling to suit.
  $boxStyle = 'style="border: dashed 1px black; background-color:#FFFFCC; margin: 5px; padding: 0 5px;"';  
//
  $cacheFileDir = './';   // default cache file directory
  $cacheName = "radar-status.json";  // used to store the file so we don't have to
//                          fetch it each time
  $refetchSeconds = 60;     // refetch every nnnn seconds
  
  $showHMSAge = true; // =false for number of seconds, =true for H:M:S age display
  $showMsgCnt = 2;    // show up to 2 most recent messages
// end of settings

// Constants
// don't change $fileName or script may break ;-)
  $fileName = 'https://api.weather.gov/radar/stations/';
  $fileName2 = 'https://api.weather.gov/products/types/FTM/locations/';
// end of constants
// ---------------------------------------------------------
// overrides from Settings.php if available
global $SITE;
if (isset($SITE['GR3radar'])) 	{$myRadar = $SITE['GR3radar'];}
if (isset($SITE['tz'])) 		{$ourTZ = $SITE['tz'];}
if (isset($SITE['timeFormat'])) {$timeFormat = $SITE['timeFormat'];}
if (isset($SITE['showradarstatus'])) {$noMsgIfActive = ! $SITE['showradarstatus'];}
if (isset($SITE['cacheFileDir']))     {$cacheFileDir = $SITE['cacheFileDir']; }
// end of overrides from Settings.php if available

// ------ start of code -------
if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' 
    and strlen($_REQUEST['sce']) == 4) {
   //--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   
   readfile($filenameReal);
   exit;
}
if (isset($_REQUEST['sce'])) {
  header("HTTP/1.1 403 Forbidden");
  print "<h1>Hacking attempt. Denied.</h1>\n";
  exit();
}

// Check parameters and force defaults/ranges
if ( ! isset($_REQUEST['inc']) ) {
        $_REQUEST['inc']="";
}
if (!isset($doIncludeRS) ) { $doIncludeRS = true; }

if (isset($doIncludeRS) and $doIncludeRS ) {
  $includeMode = "Y";
 } else {
  $includeMode = $_REQUEST['inc']; // any nonblank is ok
}

if ($includeMode) {$includeMode = "Y";}

if (isset($_REQUEST['show']) ) { // for testing
  $noMsgIfActive = (strtolower($_REQUEST['show']) !== 'active');
}

if (isset($_REQUEST['nexrad']) ) { // for testing

  $myRadar = substr(strtoupper($_REQUEST['nexrad']),0,4);
}

if (isset($statRadar)) { // for include in wxnwsradar script
  $myRadar = $statRadar; // use current radar in wxnwsradar scripts
	$includeMode = true;   // force include mode
	$noMsgIfActive = true; // suppress message if active
}

if (isset($_REQUEST['cache'])) {$refetchSeconds = 1; }

$myRadar = strtoupper($myRadar);

// omit HTML <HEAD>...</HEAD><BODY> if only tables wanted	
// --------------- customize HTML if you like -----------------------
if (! $includeMode) {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Refresh" content="300" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Cache-Control" content="no-cache" />
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>Radar Status for <?php print $myRadar ?> NEXRAD station</title>
</head>
<body style="background-color:#FFFFFF;">
<?php
}

// ------------- code starts here -------------------
echo "<!-- $Version -->\n";
if(isset($statRadar)) {
		print "<!-- statRadar='$myRadar' used as myRadar -->\n";
}
// refresh cached copy of page if needed
// fetch/cache code by Tom at carterlake.org
$cacheName = $cacheFileDir . $cacheName;
$cacheName = str_replace('.json',"-".$myRadar.'.json',$cacheName);
$myRadar3   = strtoupper(substr($myRadar,1,3));

$Debug = '';
if (file_exists($cacheName) and filemtime($cacheName) + $refetchSeconds > time()) {
      print "<!-- using Cached version of $cacheName -->\n";
      $html = implode('', file($cacheName));
    } else {
      print "<!-- loading $cacheName from {$fileName}{$myRadar} -->\n";
      list($content1,$RC) = RS_fetchUrlWithoutHanging($fileName.$myRadar);
      print $Debug;
      $Debug = '';
      if($RC !== 200) {
        print "<p>Radar $myRadar station returns no data. RC=$RC</p>\n";
        return(0);
      }
      print "<!-- appending $cacheName from {$fileName2}{$myRadar3} -->\n";
      list($content2,$RC2)= RS_fetchUrlWithoutHanging($fileName2.$myRadar3);
      print $Debug;
      $Debug = '';
        // extract the messages

       $radarMsgs = array();  // for storing the messages in a 'cleansed' format by Radar key, then date

      #  $radarMsgs[$thisRadar][$thisDate] = $thisMsg; // save away for later lookup
      /*
          "@graph": [
              {
                  "@id": "https://api.weather.gov/products/f1e89484-b954-43f8-97a4-6450e5aeaf9f",
                  "id": "f1e89484-b954-43f8-97a4-6450e5aeaf9f",
                  "wmoCollectiveId": "NOUS66",
                  "issuingOffice": "KMTR",
                  "issuanceTime": "2025-09-09T01:00:00+00:00",
                  "productCode": "FTM",
                  "productName": "WSR-88D Radar Outage Notification / Free Text Message"
              },
      */
       $FTM = json_decode($content2,true);
  
       foreach ($FTM['@graph'] as $n => $J) {
         #print "<!-- J=".var_export($J,true)." -->\n";

         list($msg,$RC) = RS_fetchUrlWithoutHanging($J['@id']);
         /*
        {
          "@context": {
              "@version": "1.1",
              "@vocab": "https://api.weather.gov/ontology#"
          },
          "@id": "https://api.weather.gov/products/f1e89484-b954-43f8-97a4-6450e5aeaf9f",
          "id": "f1e89484-b954-43f8-97a4-6450e5aeaf9f",
          "wmoCollectiveId": "NOUS66",
          "issuingOffice": "KMTR",
          "issuanceTime": "2025-09-09T01:00:00+00:00",
          "productCode": "FTM",
          "productName": "WSR-88D Radar Outage Notification / Free Text Message",
          "productText": "\n000\nNOUS66 KMTR 090100\nFTMMUX\nMessage Date:  Sep 09 2025 01:00:57\n\nKMUX radar is back up and sending data.                                         \n\n"
        } 
         */

         $T =  json_decode($msg,true);

         $thisDate = strtotime($T['issuanceTime']);
         $rawMsg  = $T['productText'];
         $mparts = explode("\n",$rawMsg);
         #print "<!-- mparts=".var_export($mparts,true)." -->\n";
         foreach ($mparts as $k => $mp) {
           if(stripos($mp,'message') !== false or stripos($mp,'outage notif') !== false) {$k++; break;}
         }
         if($k >= count($mparts)) {$k=5;}
         $thisMsg = implode(' ',array_slice($mparts,$k));

         $radarMsgs[$myRadar][$thisDate] = $thisMsg; // save away for later lookup

       }
  
      $content3 = json_encode($radarMsgs);
 
      if(strlen($content1) > 100 and strlen($content2) > 50) {
      $fp = fopen($cacheName, "w");
      if ($fp) {
        $write = fputs($fp,$content1);
        $write = fputs($fp,"\n||||||\n");
        $write = fputs($fp,$content2."\n");
        $write = fputs($fp,"\n||||||\n");
        $write = fputs($fp,$content3."\n");
        fclose($fp);  
         print "<!-- cache written to $cacheName. -->\n";
      } else {
         print "<!-- unable to save cache to $cacheName. -->\n";
      }
		  $html = $content1."\n||||||\n".$content2."\n||||||\n".$content3."\n";
	  } else {
		  print "<!-- problem fetching main/txtmsg file(s) -->\n";
		  print "<!-- main  content length=".strlen($content1)."\n-->\n";
		  print "<!-- txtmsg content length=".strlen($content2)."\n-->\n";
		  print "<!-- cache not saved to $cacheName. -->\n";
	  }
}

list($content1,$content2,$content3) = explode('||||||',$html);

$MAIN = json_decode($content1,true);
$FTM  = json_decode($content2,true);
$radarMsgs = json_decode($content3,true);


#print "<!--\n MAIN=".var_export($MAIN,true)."\n -->\n";
#print "<!--\n  FTM=".var_export($FTM,true)."\n -->\n";

#print "\n\n<!-- ################################################################# -->\n\n";

# Set timezone in PHP5/PHP4 manner
  if (!function_exists('date_default_timezone_set')) {
	putenv("TZ=" . $ourTZ);
#	$Status .= "<!-- using putenv(\"TZ=$ourTZ\") -->\n";
    } else {
	date_default_timezone_set("$ourTZ");
#	$Status .= "<!-- using date_default_timezone_set(\"$ourTZ\") -->\n";
   }

  if(strlen($html) < 250) {
	  print "<!-- unable to process radar-status.. insufficient data -->\n";
	  return;
  }
  
   $lastUTCdate = $MAIN['latency']['levelTwoLastReceivedTime'];
//   print "<!-- lastUTCdate\n".print_r($lastUTCdate,true)."-->\n";
   $t=strtotime($lastUTCdate);
   $UTCdate = time();
   $LCLdate = date($timeFormat,$UTCdate);

   print "<!-- \nUTCdate    =$UTCdate (".gmdate('Y-m-d h:i:s',$UTCdate).")\n" .
                "lastUTCdate=$t (".gmdate('Y-m-d h:i:s',$t).")\n -->\n";
   $age = $UTCdate - $t;
//   if ($age < 0) { $age += (60*60*24); } // account for one day extra downtime if need be
   $ageHMS = gmdate('H:m:s',$age);
   print "<!-- age=$age seconds ($ageHMS) -->\n";
   #preg_match_all('|<!--:(.*):-->|is',$rec,$matches);
   $curStatus = $MAIN['rda']['properties']['status'];

   $statColor = '#33FF33'; # Assume normal color = green
   if($age >=  5*60) {$statColor = '#FFFF00';} # delayed yellow
   if($age >= 30*60) {$statColor = '#FF0000';} # inop RED

   if ($statColor <> '#33FF33') {
     $curStatus .= ' - Data not recent';
   }



// Output the status
  $divStarted = false;
  if (isset($statColor) and (!$noMsgIfActive or $statColor != '#33FF33') ) {
  print "<div $boxStyle>\n";
	$divStarted = true;
  $pAge = ($showHMSAge)?sec2hmsRS($age)." h:m:s":"$age secs";
  print "<p>NEXRAD Radar $myRadar status: <span style=\"background-color: $statColor; padding: 0 5px;\">$curStatus</span> [last data $pAge ago]<br/>as of $LCLdate</p>\n";
  
  if (isset($radarMsgs[$myRadar])) {
     $imsg = 0;
     foreach ($radarMsgs[$myRadar] as $timestamp => $msg) {
       $imsg++;
       if($imsg > $showMsgCnt) {break;}
       $msg = htmlspecialchars($msg);
       $msg = preg_replace('|\n|is',"<br/>\n",$msg);
       print "<p>Message date: " . date($timeFormat,$timestamp) . "<br/>\n";
       print $msg . "</p>\n";
     }
  } 
  
  
  $niceFileName = preg_replace('!&!is','&amp;',$fileName);
  print "<p><small><a href=\"https://www.weather.gov/nl2/NEXRADView\">NWS WSR-88D NEXRADView</a></small></p>\n";
  } // end suppress if radar active and $noMsgIfActive == true
 elseif (isset($statColor) ){
 
  print "<!-- NEXRAD Radar $myRadar status: $curStatus [last data $age secs ago] as of $LCLdate -->\n";
  if (isset($radarMsgs[$myRadar])) {
     foreach ($radarMsgs[$myRadar] as $timestamp => $msg) {
       $msg = htmlspecialchars($msg);
       print "<!-- Message date: " . date($timeFormat,$timestamp) . "\n";
       print $msg . " -->\n";
     }
  } 

 
  } elseif (!isset($statColor) and $age == '') {
   # no data but found
    print "<!-- status display suppressed due to data not available -->\n";
  } else {
	 print "<p>NEXRAD radar $myRadar status not found.</p>\n";
  }
	if($divStarted) { print "</div>\n"; }

// print footer of page if needed    
// --------------- customize HTML if you like -----------------------
if (! $includeMode ) {   
?>

</body>
</html>

<?php
}

// ----------------------------functions ----------------------------------- 
 
function RS_fetchUrlWithoutHanging($url) {
// get contents from one URL and return as string 
  global $Debug, $needCookie;
  
  $overall_start = time();
  
   // Set maximum number of seconds (can have floating-point) to wait for feed before displaying page without feed
   $numberOfSeconds=6;   

// Thanks to Curly from ricksturf.com for the cURL fetch functions

  $data = '';
  $domain = parse_url($url,PHP_URL_HOST);
  $theURL = str_replace('nocache','?'.$overall_start,$url);        // add cache-buster to URL if needed
  $Debug .= "<!-- curl fetching '$theURL' -->\n";
  $ch = curl_init();                                           // initialize a cURL session
  curl_setopt($ch, CURLOPT_URL, $theURL);                         // connect to provided URL
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                 // don't verify peer certificate
  curl_setopt($ch, CURLOPT_USERAGENT, 
    'Mozilla/5.0 (radar-status.php - saratoga-weather.org)');

  curl_setopt($ch,CURLOPT_HTTPHEADER,                          // request LD-JSON format
     array (
         "Accept: application/ld+json"
     ));

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $numberOfSeconds);  //  connection timeout
  curl_setopt($ch, CURLOPT_TIMEOUT, $numberOfSeconds);         //  data timeout
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);              // return the data transfer
  curl_setopt($ch, CURLOPT_NOBODY, false);                     // set nobody
  curl_setopt($ch, CURLOPT_HEADER, true);                      // include header information
//  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);              // follow Location: redirect
//  curl_setopt($ch, CURLOPT_MAXREDIRS, 1);                      //   but only one time
  if (isset($needCookie[$domain])) {
    curl_setopt($ch, $needCookie[$domain]);                    // set the cookie for this request
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);             // and ignore prior cookies
    $Debug .=  "<!-- cookie used '" . $needCookie[$domain] . "' for GET to $domain -->\n";
  }

  $data = curl_exec($ch);                                      // execute session

  if(curl_error($ch) <> '') {                                  // IF there is an error
   $Debug .= "<!-- curl Error: ". curl_error($ch) ." -->\n";        //  display error notice
  }
  $cinfo = curl_getinfo($ch);                                  // get info on curl exec.
/*
curl info sample
Array
(
[url] => http://saratoga-weather.net/clientraw.txt
[content_type] => text/plain
[http_code] => 200
[header_size] => 266
[request_size] => 141
[filetime] => -1
[ssl_verify_result] => 0
[redirect_count] => 0
  [total_time] => 0.125
  [namelookup_time] => 0.016
  [connect_time] => 0.063
[pretransfer_time] => 0.063
[size_upload] => 0
[size_download] => 758
[speed_download] => 6064
[speed_upload] => 0
[download_content_length] => 758
[upload_content_length] => -1
  [starttransfer_time] => 0.125
[redirect_time] => 0
[redirect_url] =>
[primary_ip] => 74.208.149.102
[certinfo] => Array
(
)

[primary_port] => 80
[local_ip] => 192.168.1.104
[local_port] => 54156
)
*/
  $Debug .= "<!-- HTTP stats: " .
    " RC=".$cinfo['http_code'] .
    " dest=".$cinfo['primary_ip'] ;
    $RC = $cinfo['http_code'];
	if(isset($cinfo['primary_port'])) { 
	  $Debug .= " port=".$cinfo['primary_port'] ;
	}
	if(isset($cinfo['local_ip'])) {
	  $Debug .= " (from sce=" . $cinfo['local_ip'] . ")";
	}
	$Debug .= 
	"\n      Times:" .
    " dns=".sprintf("%01.3f",round($cinfo['namelookup_time'],3)).
    " conn=".sprintf("%01.3f",round($cinfo['connect_time'],3)).
    " pxfer=".sprintf("%01.3f",round($cinfo['pretransfer_time'],3));
	if($cinfo['total_time'] - $cinfo['pretransfer_time'] > 0.0000) {
	  $Debug .=
	  " get=". sprintf("%01.3f",round($cinfo['total_time'] - $cinfo['pretransfer_time'],3));
	}
    $Debug .= " total=".sprintf("%01.3f",round($cinfo['total_time'],3)) .
    " secs -->\n";

  //$Debug .= "<!-- curl info\n".print_r($cinfo,true)." -->\n";
  curl_close($ch);                                              // close the cURL session
  //$Debug .= "<!-- raw data\n".$data."\n -->\n"; 
  $stuff = explode("\r\n\r\n",$data); // maybe we have more than one header due to redirects.
  $content = (string)array_pop($stuff); // last one is the content
  $headers = (string)array_pop($stuff); // next-to-last-one is the headers
  if($cinfo['http_code'] <> '200') {
    $Debug .= "<!-- headers returned:\n".$headers."\n -->\n"; 
  }
  return (array($content,$RC));                                                 // return headers+contents

 
}    // end ECF_fetch_URL

// ------------------------------------------------------------------

function RS_fetch_microtime()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}

//------------------------------------------------------------------------------------------


//
  function sec2hmsRS ($sec, $padHours = false) 
  {

    // holds formatted string
    $hms = "";
    if (! is_numeric($sec)) { return($sec); }
    // there are 3600 seconds in an hour, so if we
    // divide total seconds by 3600 and throw away
    // the remainder, we've got the number of hours
    $hours = intval(intval($sec) / 3600); 

    // add to $hms, with a leading 0 if asked for
    $hms .= ($padHours) 
          ? str_pad($hours, 2, "0", STR_PAD_LEFT). ':'
          : $hours. ':';
     
    // dividing the total seconds by 60 will give us
    // the number of minutes, but we're interested in 
    // minutes past the hour: to get that, we need to 
    // divide by 60 again and keep the remainder
    $minutes = intval(fmod($sec / 60,60)); 

    // then add to $hms (with a leading 0 if needed)
    $hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT). ':';

    // seconds are simple - just divide the total
    // seconds by 60 and keep the remainder
    $seconds = intval($sec % 60); 

    // add to $hms, again with a leading 0 if needed
    $hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);

    // done!
    return $hms;
    
  }

   
// --------------end of functions ---------------------------------------
