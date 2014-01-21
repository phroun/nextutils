<?php
// ###########################################################################
// utils.php:  Utilities for PHP Web Applications Development
// ===========================================================================
// Version 2013-06-26
//
// Contributors:
//
// * Jeff Day
//
// Quick Usage Guide:
//
// hts($s) - alias for htmlspecialchars, use for output of data fields.
// logline($s) - Log a line to $MESSAGELOGFN with timestamp.
// setTimezoneByOffset($offset) - Set PHP default timezone.
// hexDigitToDec($d) - Convert single hex digit into decimal equivalent.
// luminanceOfHex($hex) - Convert 3 or 6 digit hex code into luminance score.
// selected($comp) - Output ' selected="selected"' if passed true.
// checked($comp) - Output ' checked="checked"' if passed true.
// dtLocal($dtval) - Convert YYYY-MM-DD HH:II:SS from UTC to local time.
// dtUTC($dtval) - Convert YYYY-MM-DD HH:II:SS from local time to UTC.
// tzOffset() - Return current timezone offset in +00:00 format.
// formatDuration($duration, $usedays) - Format duration given in seconds
//   to be human readable, and if 48 hours or more, switches to "x days"
//   format.
// boolToInt($bool) - Returns -1 for true or 0 for false.
// intToBool($int) - Forces to numeric and returns true for nonzero values.
//
// ###########################################################################

function logline($s) {
  global $INSTANCE; // for concurrency
  global $MESSAGELOGFN;
  if ($MESSAGELOGFN == '') { // try messages if no other log file specified:
    $MESSAGELOGFN = '/var/log/messages';
  }
  $sri = '';
  if (''.@$INSTANCE > '') {
    $sri = $INSTANCE . '@';
  }
  error_log('[' . $sri . date('Y-m-d H:i:s') . '] ' . $s . "\n", 3, $MESSAGELOGFN);
}

function setTimezoneByOffset($offset) {
  date_default_timezone_set('UTC');
  $testTimestamp = time();
  $testLocaltime = localtime($testTimestamp,true);
  $testHour = $testLocaltime['tm_hour'];

  $abbrarray = timezone_abbreviations_list();
  foreach ($abbrarray as $abbr) {
    foreach ($abbr as $city) {
      $val = false;
      if (($city['timezone_id'] != 'Factory')
      &&  (''.@$city['timezone_id'] > '')) {
        if (isset($city['timezone_id'])) {
          $val = date_default_timezone_set($city['timezone_id']);
          if ($val) {
            $testLocaltime = localtime($testTimestamp,true);
            $hour = $testLocaltime['tm_hour'];
            $testOffset =  $hour - $testHour;
            if (($testOffset == $offset) || ($testOffset==$offset+24)) {
              return true;
            }
          }
        }
      }
    }
  }
  date_default_timezone_set('UTC');
  return false;
}

function hexDigitToDec($d) {
  $d = strtolower($d);
  $dec = -1;
  if (($d >= '0') && ($d <= '9')) {
    $dec = 0+@$d;
  } else {
    if ($d == 'a') { $dec = 10; }
    if ($d == 'b') { $dec = 11; }
    if ($d == 'c') { $dec = 12; }
    if ($d == 'e') { $dec = 13; }
    if ($d == 'd') { $dec = 14; }
    if ($d == 'f') { $dec = 15; }
  }
  return $dec;
}

function luminanceOfHex($hex) {

  if (strlen($hex) == 6) { // only read major digits if 6-digit value

    $r = hexDigitToDec(substr($hex, 0, 1));
    $g = hexDigitToDec(substr($hex, 2, 1));
    $b = hexDigitToDec(substr($hex, 4, 1));
    if (($r < 0) || ($g < 0) || ($b < 0)) {
      $r = 15; $g = 15; $b = 15;
    }

  } elseif (strlen($hex) == 3) {

    $r = hexDigitToDec(substr($hex, 0, 1));
    $g = hexDigitToDec(substr($hex, 1, 1));
    $b = hexDigitToDec(substr($hex, 2, 1));
    if (($r < 0) || ($g < 0) || ($b < 0)) {
      $r = 15; $g = 15; $b = 15;
    }
  } else {
    $r = 15; $g = 15; $b = 15;
  }
  $lum =  0.2126*$r + 0.7152*$g + 0.0722*$b;
  return $lum;
}

function selected($comp) {
  $s = '';
  if ($comp) {
    $s = ' selected="selected"';
  }
  return $s;
}

function checked($comp) {
  $s = '';
  if($comp) {
    $s = ' checked="checked"';
  }
  return $s;
}

function dtLocal($dtval) {
  if ($dtval == '0000-00-00 00:00:00') { // zero
    return $dtval;
  } elseif (strlen($dtval) == 10) { // date only
    return $dtval;
  } elseif ($dtval == '') { // null
    return $dtval;
  } else { // full date & time
    $mzone = date_default_timezone_get();
    date_default_timezone_set('UTC');
    $stamp = strtotime($dtval);
    date_default_timezone_set($mzone);
    return date('Y-m-d H:i:s', $stamp);
  }
}

function dtUTC($dtval) {
  if ($dtval == '0000-00-00 00:00:00') { // zero
    return $dtval;
  } elseif (strlen($dtval) == 10) { // date only
    return $dtval;
  } elseif ($dtval == '') { // null
    return $dtval;
  } else { // full date & time
    $stamp = strtotime($dtval);
    $mzone = date_default_timezone_get();
    date_default_timezone_set('UTC');
    $rval = date('Y-m-d H:i:s', $stamp);
    date_default_timezone_set($mzone);
    return $rval;
  }
}

function tzOffset($tz = '') {
  if ($tz != '') {
    $oldtz = date_default_timezone_get();
    date_default_timezone_set($tz);
  }
  $tzoff = floor(date('Z')/60);
  $sign = ($tzoff < 0);
  if ($sign) { $tzoff *= -1; }
  $tzhr = floor($tzoff / 60);
  $tzmin = $tzoff - ($tzhr*60);
  if ($sign) {
    $tzs = '-';
  } else {
    $tzs = '+';
  }
  $tzs .= str_pad($tzhr,2,'0',STR_PAD_LEFT) . ':' . str_pad($tzmin,2,'0',STR_PAD_LEFT);
  if ($tz != '') {
    date_default_timezone_set($oldtz);
  }
  return $tzs;
}

function formatDuration($duration, $usedays = true) {
  $so = '';
  if ($duration >= 0) {
    $durationmins = floor($duration / 60);
    $durationsecs = $duration - ($durationmins * 60);
    $durationhours = floor($durationmins / 60);
    if (($durationhours >= 48) && $usedays) {
      $durationdays = floor($durationhours / 24);
      $so .= $durationdays . ' days';
    } else {
      $durationmins = $durationmins - ($durationhours * 60);
      if ($durationsecs < 10) { $durationsecs = '0' . $durationsecs; }
      if ($durationhours > 0) {
        if ($durationmins < 10) { $durationmins = '0' . $durationmins; }
        $so .= $durationhours . ':';
      }
      $so .= $durationmins . ':' . $durationsecs;
    }
  }
  return $so;
}

function boolToInt($bool) {
  if ($bool) {
    return (-1);
  } else {
    return (0);
  }
}

function intToBool($int) {
  return (0+@$int != 0);
}

function size_readable($size, $max = null, $system = 'si', $retstring = '%01.2f %s') {
  // Pick units
  $systems['si']['prefix'] = array('B', 'K', 'MB', 'GB', 'TB', 'PB');
  $systems['si']['size']   = 1000;
  $systems['bi']['prefix'] = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');
  $systems['bi']['size']   = 1024;
  $sys = isset($systems[$system]) ? $systems[$system] : $systems['si'];

  // Max unit to display
  $depth = count($sys['prefix']) - 1;
  if ($max && false !== $d = array_search($max, $sys['prefix'])) {
    $depth = $d;
  }

  // Loop
  $i = 0;
  while ($size >= $sys['size'] && $i < $depth) {
    $size /= $sys['size'];
    $i++;
  }

  return sprintf($retstring, $size, $sys['prefix'][$i]);
}

function hts($s) {
  return htmlspecialchars($s);
}