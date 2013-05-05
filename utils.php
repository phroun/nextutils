<?php

if (''.@$_SESSION['timezone'] > '') {
  setTimezoneByOffset($_SESSION['timezone']);
} else {
  date_default_timezone_set('UTC');
} // this gets overridden by user, if specified

function logline($s) {
  global $INSTANCE; // for concurrency
  global $MESSAGELOGFN;
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
      if (''.@$city['timezone_id'] > '') {
        date_default_timezone_set($city['timezone_id']);
      }
      $testLocaltime = localtime($testTimestamp,true);
      $hour = $testLocaltime['tm_hour'];
      $testOffset =  $hour - $testHour;
      if ($testOffset == $offset) {
        return true; // America/Santa_Isabel == Pacific
      }
    }
  }
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
  if (strlen($hex) == 3) {
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

function formatDuration($duration) {
  $so = '';
  if ($duration >= 0) {
    $durationmins = floor($duration / 60);
    $durationsecs = $duration - ($durationmins * 60);
    $durationhours = floor($durationmins / 60);
    if ($durationhours >= 48) {
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
    rerurn (0);
  }
}

function intToBool($int) {
  return (0+@$int != 0);
}
