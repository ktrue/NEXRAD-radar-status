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

    $Version = "radar-status.php V1.16 - 04-Feb-2019";
//  error_reporting(E_ALL);  // uncomment to turn on full error reporting
// script available at http://saratoga-weather.org/scripts.php
//  
// you may copy/modify/use this script as you see fit,
// no warranty is expressed or implied.
//
// Customized for: NOAA radar status from
//   http://weather.noaa.gov/monitor/radar/
//
//
// output: creates XHTML 1.0-Strict HTML page (default)
// Options on URL:
//      inc=Y    -- returns only the body code for inclusion
//                         in other webpages.  Omit to return full HTML.
// example URL:
//  http://your.website/radar-status.php?inc=Y
//  would return data without HTML header/footer 
//
// Usage:
//  you can use this webpage standalone (customize the HTML portion below)
//  or you can include it in an existing page:
//  no parms:  $doIncludeRS = true; include("radar-status.php"); 
//  parms:    include("http://your.website/radar-status.php?inc=Y");
//
//
// settings:  
//  change myRadar to your local NEXRAD radar site ID.
//    other settings are optional
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
  $cacheName = "radar-status.txt";  // used to store the file so we don't have to
//                          fetch it each time
  $refetchSeconds = 60;     // refetch every nnnn seconds
  
  $showHMSAge = true; // =false for number of seconds, =true for H:M:S age display
// end of settings

// Constants
// don't change $fileName or script may break ;-)
  $fileName = 'https://radar3pub.ncep.noaa.gov/';
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
if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
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
  $noMsgIfActive = (strtolower($_REQUEST['show']) != 'active');
}

if (isset($_REQUEST['nexrad']) ) { // for testing

  $myRadar = substr(strtoupper($_REQUEST['nexrad']),0,4);
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

// refresh cached copy of page if needed
// fetch/cache code by Tom at carterlake.org
$cacheName = $cacheFileDir . $cacheName;
$Debug = '';
if (file_exists($cacheName) and filemtime($cacheName) + $refetchSeconds > time()) {
      print "<!-- using Cached version of $cacheName -->\n";
      $html = implode('', file($cacheName));
    } else {
      print "<!-- loading $cacheName from ${fileName}rcvxmit.sites.public.html -->\n";
      $html = RS_fetchUrlWithoutHanging($fileName.'rcvxmit.sites.public.html',false);
	  print $Debug;
	  $Debug = '';
	  list($hdr1,$content1) = explode("\r\n\r\n",$html);
	  
      print "<!-- appending $cacheName from ${fileName}ftm.txt -->\n";
	  $html2 = RS_fetchUrlWithoutHanging($fileName.'ftm.txt',false);
	  print $Debug;
	  $Debug = '';
	  list($hdr2,$content2) = explode("\r\n\r\n",$html2);
	  
	  if(strlen($content1) > 100 and strlen($content2) > 100) {
		$fp = fopen($cacheName, "w");
		if ($fp) {
		  $write = fputs($fp, $html);
		  $write = fputs($fp,"\n||||||\n");
		  $write = fputs($fp,$hdr2."\n<pre>\n".$content2."</pre>\n");
		   fclose($fp);  
		   print "<!-- cache written to $cacheName. -->\n";
		} else {
		   print "<!-- unable to save cache to $cacheName. -->\n";
		}
		$html .= "\n||||||\n"."\n<pre>\n".$content2."</pre>\n";
	  } else {
		  print "<!-- problem fetching main/txtmsg file(s) -->\n";
		  print "<!-- main  content length=".strlen($content1).", headers\n".$hdr1."\n-->\n";
		  print "<!-- txtmsg content length=".strlen($content2).", headers\n".$hdr2."\n-->\n";
		  print "<!-- cache not saved to $cacheName. -->\n";
	  }
}

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
  
  // extract the updated date/time
//  print "<pre>\n";
  preg_match_all('|Last-Modified: (.*)\r|Uis',$html,$matches);
//  print_r($matches);
  if(!isset($matches[1][0])) { // no Last-Modified header.. look for the status line
    // <b>Status as of 08.03.2016 wed 21:05:01 utc
    preg_match_all('|Status as of (\S+)\s+\S+\s(\S+) utc|Uis',$html,$matches);
	$t = explode('.',$matches[1][0]);
	
	$UDate = $t[2].'-'.$t[0].'-'.$t[1] . ' ' . $matches[2][0] . ' GMT';
	//print "<!-- UDate matches \n" . print_r($matches,true)." -->\n";
	$UDate = gmdate('r',strtotime($UDate));
  } else {
    $UDate = $matches[1][0];
  }
 // $UDate = 'Fri, 05 Jun 2015 00:14:01 GMT'
  $UDp = explode(" ",$UDate);
  //print "<!-- UDp\n" .print_r($UDp,true)." -->\n";
 /*
Array $UDp is now:
(
    [0] => Tue,
    [1] => 08
    [2] => Mar
    [3] => 2016
    [4] => 21:40:01
    [5] => +0000
)
*/
  $UTCdate = strtotime($UDate);
  $LCLdate = date($timeFormat,$UTCdate);
  //print "<!--LCLdate '$LCLdate' UDate='$UDate' UTCdate=$UTCdate -->\n";
  
/*  The data looks like this:
<td ALIGN=CENTER BGCOLOR="#FF0000" class = "  white "  id= whitelink><b>
KMUX</a></b><br>23:28:17</td> <!--:No Data:-->
<td ALIGN=CENTER BGCOLOR="#33FF33" class = " black"  id= blacklink><b>
KMVX</a></b><br>00:15:17</td>

then looked like

<td align=center bgcolor="#FF0000" class=" white " id=whitelink><b>KAMX</a></b><br>04:06:04</td> <!--:No Data:-->
<td align=center bgcolor="#FFFF00" class="black" id=blacklink><b>KAPX</a></b><br>04:26:04</td>
<td align=center bgcolor="#33FF33" class="black" id=blacklink><b>KARX</a></b><br>04:42:27</td>
<td align=center bgcolor="#FF0000" class=" white " id=whitelink><b>KATX</a></b><br>02:33:21</td> <!--:No Data:-->

now looks like

<td align=center bgcolor="#0000FF" class="white" id=whitelink><b>KVNX</b><br>19:03:46<br>07/06/15</td>
<td align=center bgcolor="#33FF33" class="black" id=blacklink><b>KVTX</b><br>20:49:11<br>07/06/15</td>

*/
  preg_match_all('|<td.*bgcolor="([^"]+)"\s+class="([^"]+)"[^>]*>(.*)</td>|Uis',$html,$matches);
//  print "<!-- matches\n".print_r($matches,true)." -->\n";
  $status = $matches[1];
  $recs = $matches[3];
/*
            [104] => <b>KMUX</b><br>20:50:16<br>07/06/15
            [105] => <b>KMVX</b><br>20:46:59<br>07/06/15
*/
  
  print "<!-- ".count($recs)." records found -->\n";

  foreach ($recs as $n => $rec) {
   if (! preg_match("|$myRadar|",$rec)) {continue;}
   
   $statColor = $status[$n];
   $statColor = str_replace('#0000FF','#FF0000',$statColor);
//   print "statColor='$statColor'\n";
   preg_match_all('|<b>(.*)</b><br>(.*)<br>(.*)|is',$rec,$matches);
//   print "<!-- radar\n".print_r($matches,true)."-->\n";

   $statRadar = $matches[1][0];
   $lastUTCtime = $matches[2][0];
   $td = explode('/',$matches[3][0]);
//   print "<!-- td\n".print_r($td,true)."-->\n";
   
   $lastUTCdate = '20'.$td[2].'-'.$td[0].'-'.$td[1].' '.$lastUTCtime.' GMT';
//   print "<!-- lastUTCdate\n".print_r($lastUTCdate,true)."-->\n";
   $t=strtotime($lastUTCdate);
   print "<!-- \nUTCdate    =$UTCdate (".gmdate('Y-m-d h:i:s',$UTCdate).")\n" .
                "lastUTCdate=$t (".gmdate('Y-m-d h:i:s',$t).")\n -->\n";
   $age = $UTCdate - $t;
//   if ($age < 0) { $age += (60*60*24); } // account for one day extra downtime if need be
   $ageHMS = gmdate('H:m:s',$age);
   preg_match_all('|<!--:(.*):-->|is',$rec,$matches);
   $curStatus = 'Active';
   if ($statColor <> '#33FF33') {
     $curStatus = 'Data not recent';
   }
   if (isset($matches[1][0])) {
     $curStatus = $matches[1][0];
   }
   
//   print "statRadar='$statRadar' lastUTCtime='$lastUTCtime' curStatus='$curStatus'\n";
   
//   print "$prec";
//   print "$rec";
   
    break;
//	print "$rec";  // this shouldn't print ever
  }



  // extract the messages
 preg_match('|<pre[^>]*>(.*)</pre>|Usi',$html,$matches);
 $messages = $matches[1];
 // now split up the messages and process
 $messages = preg_replace('|NOUS|Us','||NOUS',$messages) . '|'; // add message delimiters
 $messages = preg_replace('|ÿÿ|Uis',"\n||NOUSnn",$messages); // remove garbage characters.
 $messages = preg_replace('|ð|Uis','',$messages);  // remove garbage characters
 preg_match_all('!\|NOUS(.*)\|!Us',$messages,$matches); // split the messages
 $messages = $matches[1];  // now have array of messages in order
 
 $radarMsgs = array();  // for storing the messages in a 'cleansed' format by Radar key, then date
 foreach ($messages as $n => $msg) {
 
 /* a $msg looks like this:
 66 KMTR 200618
FTMMUX
MESSAGE DATE:  JAN 20 2008 06:17:55
KMUX SAN FRANCISCO RADAR IS EXPERIENCING INTERMITTENT DATA FLOW
INTERUPTIONS. TROUBLE-SHOOTING PROCEDURES ARE CURRENTLY UNDERWAY TO
DETERMINE THE PROBLEM AND RESTORE NORMAL OPERATIONS.
*/
   $msgline = explode("\n",$msg);  // get 'em separated into individual lines.
   $t = explode(' ',trim($msgline[0]));
   $thisRadar = $t[1];
   $thisTD = $t[2];
   if (substr($thisRadar,1,3) != substr($msgline[1],3,3)) { // sometimes one reports for another
     $thisRadar = substr($thisRadar,0,1) . substr($msgline[1],3,3);
   }
   
   preg_match('|date:\s+(.*)|i',trim($msgline[2]),$matches);
   $istart = 3;
   if (!isset($matches[1])) {
   // oops.. no message line
     $istart--;
 /*
 use the Updated UTC to 'fill in the blanks' from the header line
(
    [0] => Mon
    [1] => Jan
    [2] => 21
    [3] => 00:14:59
    [4] => CUT
    [5] => 2008
)
*/
     $tdate = substr($thisTD,0,2) . '-' . $UDp[1] . '-' . $UDp[5] . ' ' .
	          substr($thisTD,2,2) . ':' . substr($thisTD,4,2) . ':00 UTC';
	 $thisDate = strtotime($tdate);
	
	} else { 
   
      $thisDate = strtotime($matches[1] . ' UTC');
	}
	
   $thisMsg = '';
   for ($i=$istart;$i<count($msgline);$i++) { $thisMsg .= $msgline[$i] . "\n"; };
   
   $thisMsg = preg_replace("|\n|is",'',$thisMsg);
   $radarMsgs[$thisRadar][$thisDate] = $thisMsg; // save away for later lookup
 
 
 }
 
// print_r($radarMsgs);

/*  $radarMsgs now looks like this:
    [KVNX] => Array
        (
            [1200854014] => THE KVNX WSR-88D WILL BE DOWN FOR A BRIEF PERIOD BETWEEN 1840 AND 1900 UTC FOR R
EQUIRED MAINTENANCE.  AT WFO/OUN, 1833 UTC - 1/20/08


            [1200857882] => THE KVNX WSR-88D HAS BEEN RETURNED TO SERVICE.  AT WFO/OUN, 1935 UTC - 1/20/08  


        )

    [KMUX] => Array
        (
            [1200809875] => KMUX SAN FRANCISCO RADAR IS EXPERIENCING INTERMITTENT DATA FLOW
INTERUPTIONS. TROUBLE-SHOOTING PROCEDURES ARE CURRENTLY UNDERWAY TO
DETERMINE THE PROBLEM AND RESTORE NORMAL OPERATIONS.


            [1200890280] => KMUX SAN FRANCISCO RADAR IS CONTINUING TO EXPERIENCE INTERMITTENT
DATA FLOW INTERUPTIONS. TROUBLE-SHOOTING PROCEDURES CONTINUE.
PROBLEMS ARE LIKELY WITH TELCO CONNECTIONS AND VERIZON TECHNICIANS
WILL RESUME WITH THEIR PROCESSES ON MONDAY.


        )
*/

// Output the status

  if (isset($statColor) and (!$noMsgIfActive or $statColor != '#33FF33') ) {
  print "<div $boxStyle>\n";
  $pAge = ($showHMSAge)?sec2hmsRS($age)." h:m:s":"$age secs";
  print "<p>NEXRAD Radar $myRadar status: <span style=\"background-color: $statColor; padding: 0 5px;\">$curStatus</span> [last data $pAge ago] as of $LCLdate</p>\n";
  
  if (isset($radarMsgs[$myRadar])) {
     foreach ($radarMsgs[$myRadar] as $timestamp => $msg) {
	   $msg = htmlspecialchars($msg);
	   $msg = preg_replace('|\n|is',"<br/>\n",$msg);
	   print "<p>Message date: " . date($timeFormat,$timestamp) . "<br/>\n";
	   print $msg . "</p>\n";
     }
  } 
  
  
  $niceFileName = preg_replace('!&!is','&amp;',$fileName);
  print "<p><small><a href=\"$niceFileName\">NWS WSR-88D Transmit/Receive Status</a></small></p>\n";
  print "</div>\n";
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

 
  } else {
	 print "<p>NEXRAD radar $myRadar status not found.</p>\n";
  }


// print footer of page if needed    
// --------------- customize HTML if you like -----------------------
if (! $includeMode ) {   
?>

</body>
</html>

<?php
}

// ----------------------------functions ----------------------------------- 
 
function RS_fetchUrlWithoutHanging($url,$useFopen) {
// get contents from one URL and return as string 
  global $Debug, $needCookie;
  
  $overall_start = time();
  if (! $useFopen) {
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
         "Accept: text/html,text/plain"
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
  $i = strpos($data,"\r\n\r\n");
  $headers = substr($data,0,$i);
  $content = substr($data,$i+4);
  if($cinfo['http_code'] <> '200') {
    $Debug .= "<!-- headers returned:\n".$headers."\n -->\n"; 
  }
  return $data;                                                 // return headers+contents

 } else {
//   print "<!-- using file_get_contents function -->\n";
   $STRopts = array(
	  'http'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (radar-status.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  ),
	  'https'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (radar-status.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  )
	);
	
   $STRcontext = stream_context_create($STRopts);

   $T_start = RS_fetch_microtime();
   $xml = file_get_contents($url,false,$STRcontext);
   $T_close = RS_fetch_microtime();
   $headerarray = get_headers($url,0);
   $theaders = join("\r\n",$headerarray);
   $xml = $theaders . "\r\n\r\n" . $xml;

   $ms_total = sprintf("%01.3f",round($T_close - $T_start,3)); 
   $Debug .= "<!-- file_get_contents() stats: total=$ms_total secs -->\n";
   $Debug .= "<-- get_headers returns\n".$theaders."\n -->\n";
//   print " file() stats: total=$ms_total secs.\n";
   $overall_end = time();
   $overall_elapsed =   $overall_end - $overall_start;
   $Debug .= "<!-- fetch function elapsed= $overall_elapsed secs. -->\n"; 
//   print "fetch function elapsed= $overall_elapsed secs.\n"; 
   return($xml);
 }

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
    $minutes = intval(($sec / 60) % 60); 

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

?>