# <a name="NEXRADStatus" id="NEXRADStatus"></a>PHP for current NEXRAD Radar station status display

This script will read (and cache for 60 seconds) api.weather.gov/radar/stations/{id}, 
extract the current status for a selected NEXRAD Station name, and format and display any Free Text messages associated with that station. 
The default for the script is to not display anything if the selected station is currently 'Green'(Operate), 
and return a message if the station data is 'old' or has 'no data'. 
I'm using this on my radar page above the GRLevel3 heading to report status on Station KMUX.

Settings:

```php
// settings:  
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
```

<dl>
<dt><strong>$myRadar</strong></dt>
<dd>This is the 4-character NEXRAD Radar station name. <br>
If used in the AJAX/PHP template, the setting will be replaced by the contents of $SITE['GR3radar'].</dd>
<dt><strong>$noMsgIfActive</strong></dt>
<dd>If set = false, then status and messages will be displayed in a box of style $boxStyle.<br />
  If set = true, then status of active will not display, and only 'problem' messages will be displayed in a box of $boxStyle.<br>
  If used in the AJAX/PHP template, the setting will be replaced by the contents of $SITE['showradarstatus']
</dd>
<dt><strong>$ourTZ</strong></dt>
<dd>Set to your current timezone (PST8PDT, MST7MDT, CST6CDT, EST5EDT or appropriate TZ for your locale)<br>
  If used in the AJAX/PHP template, the setting will be replaced by the contents of $SITE['tz']
</dd>
<dt><strong>$timeFormat</strong></dt>
<dd>This is the template used to format the dates displayed. <br />
  Default is 'D, d-M-Y g:ia T' which produces dates like 'Mon, 21-Jan-2008 12:29pm PST'<br>
  If used in the AJAX/PHP template, the setting will be replaced by the contents of $SITE['timeFormat']
</dd>
<dt><strong>$boxStyle</strong></dt>
<dd>This is the style specification for the box surrounding the output of the script. It will not be used if the $noMsgIfActive = true; and the selected NEXRAD station is 'active'.<br />
  Default is 
'style=&quot;border: dashed 1px black; background-color:#FFFFCC; margin: 5px; padding: 0 5px;&quot;'</dd>
<dt><strong>$cacheFileDir</strong></dt>
<dd>This is the relative file path to the directory used to store the $cacheName file.<br>
  The default is './' for the current directory.  If used in the AJAX/PHP template, this setting will be replaced by the contents of $SITE['cacheFileDir']
</dd>
<dt><strong>$cacheName</strong></dt>
<dd>This is the name of the cache file used to store the HTML page from the NWS Radar Status website.<br>Make sure this file is writable by the PHP script. <br><strong>NOTE:</strong> the '.json' will be replaced with '-$myRadar.json' so there is a unique cache for each radar.</dd>
<dt><strong>$refetchSeconds</strong></dt>
<dd>This specifies the lifetime for the cached page. After this number of seconds, a new page will be fetched and cached. </dd>
<dt><strong>$showMsgCnt</strong></dt>
<dd>This specifies the maximum number of messages about this radar to appear when the radar data is stale.  Default is latest 2 messages.  Not all radar outages have associated messages.</dd>
</dl>


## Usage

Include the following code in the page where you'd like the output to appear:

```php
<?php include("radar-status.php"); ?>
```

## Samples of the output ( With $noMsgIfActive = false: )

This is displayed if $noMsgIfActive=false; only. It is not displayed otherwise

Displayed if Yellow condition - data is not 'recent'.

Displayed if Red condition - 'No Data'
