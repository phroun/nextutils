<?php
// ###########################################################################
// utils.php:  Utilities for PHP Web Applications Development
// ===========================================================================
// Version 2015-07-13
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
// generateRandomString($length) - Random alphanumeric string.
// xmlentities($x) - Convert HTML named entities into XHTML/XML entities.
// getBaseURL() - Return the relative base URL of the site.
//
// ###########################################################################

$baseutils_errors_visible = false;

function logline($s) {
  global $INSTANCE; // for concurrency
  global $MESSAGELOGFN;
  
  if ($MESSAGELOGFN == '') { // try messages if no other log file specified:
    $MESSAGELOGFN = '/var/log/messages';
  }
  if (ini_get('error_log') == '') {
    ini_set('error_log', $MESSAGELOGFN);
  }

  $sri = '';
  if (''.@$INSTANCE > '') {
    $sri = $INSTANCE . '@';
  }
  date_default_timezone_set(@date_default_timezone_get());
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
    $dec = (int)@$d;
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

  if (mb_strlen($hex) == 6) { // only read major digits if 6-digit value

    $r = hexDigitToDec(substr($hex, 0, 1));
    $g = hexDigitToDec(substr($hex, 2, 1));
    $b = hexDigitToDec(substr($hex, 4, 1));
    if (($r < 0) || ($g < 0) || ($b < 0)) {
      $r = 15; $g = 15; $b = 15;
    }

  } elseif (mb_strlen($hex) == 3) {

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
  } elseif (mb_strlen($dtval) == 10) { // date only
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
  } elseif (mb_strlen($dtval) == 10) { // date only
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

function tzOffset($tz = '', $ignoredst = false) {
  if ($tz != '') {
    $oldtz = date_default_timezone_get();
    date_default_timezone_set($tz);
  }
  $tzoff = floor(date('Z')/60);
  if ((int)@date('I') == 1) {
    if ($ignoredst) {
      $tzoff -= 60; // make up for DST
    }
  }
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
  return ((int)@$int != 0);
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

function generateRandomString($length = 10) {
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $charactersLength = strlen($characters);
  $randomString = '';
  for ($i = 0; $i < $length; $i++) {
    $randomString .= $characters[rand(0, $charactersLength - 1)];
  }
  return $randomString;
}

function xmlentities($s) {
  $entities = array(
    '&nbsp;'     => '&#160;',  # no-break space = non-breaking space, U+00A0 ISOnum
    '&iexcl;'    => '&#161;',  # inverted exclamation mark, U+00A1 ISOnum
    '&cent;'     => '&#162;',  # cent sign, U+00A2 ISOnum
    '&pound;'    => '&#163;',  # pound sign, U+00A3 ISOnum
    '&curren;'   => '&#164;',  # currency sign, U+00A4 ISOnum
    '&yen;'      => '&#165;',  # yen sign = yuan sign, U+00A5 ISOnum
    '&brvbar;'   => '&#166;',  # broken bar = broken vertical bar, U+00A6 ISOnum
    '&sect;'     => '&#167;',  # section sign, U+00A7 ISOnum
    '&uml;'      => '&#168;',  # diaeresis = spacing diaeresis, U+00A8 ISOdia
    '&copy;'     => '&#169;',  # copyright sign, U+00A9 ISOnum
    '&ordf;'     => '&#170;',  # feminine ordinal indicator, U+00AA ISOnum
    '&laquo;'    => '&#171;',  # left-pointing double angle quotation mark = left pointing guillemet, U+00AB ISOnum
    '&not;'      => '&#172;',  # not sign, U+00AC ISOnum
    '&shy;'      => '&#173;',  # soft hyphen = discretionary hyphen, U+00AD ISOnum
    '&reg;'      => '&#174;',  # registered sign = registered trade mark sign, U+00AE ISOnum
    '&macr;'     => '&#175;',  # macron = spacing macron = overline = APL overbar, U+00AF ISOdia
    '&deg;'      => '&#176;',  # degree sign, U+00B0 ISOnum
    '&plusmn;'   => '&#177;',  # plus-minus sign = plus-or-minus sign, U+00B1 ISOnum
    '&sup2;'     => '&#178;',  # superscript two = superscript digit two = squared, U+00B2 ISOnum
    '&sup3;'     => '&#179;',  # superscript three = superscript digit three = cubed, U+00B3 ISOnum
    '&acute;'    => '&#180;',  # acute accent = spacing acute, U+00B4 ISOdia
    '&micro;'    => '&#181;',  # micro sign, U+00B5 ISOnum
    '&para;'     => '&#182;',  # pilcrow sign = paragraph sign, U+00B6 ISOnum
    '&middot;'   => '&#183;',  # middle dot = Georgian comma = Greek middle dot, U+00B7 ISOnum
    '&cedil;'    => '&#184;',  # cedilla = spacing cedilla, U+00B8 ISOdia
    '&sup1;'     => '&#185;',  # superscript one = superscript digit one, U+00B9 ISOnum
    '&ordm;'     => '&#186;',  # masculine ordinal indicator, U+00BA ISOnum
    '&raquo;'    => '&#187;',  # right-pointing double angle quotation mark = right pointing guillemet, U+00BB ISOnum
    '&frac14;'   => '&#188;',  # vulgar fraction one quarter = fraction one quarter, U+00BC ISOnum
    '&frac12;'   => '&#189;',  # vulgar fraction one half = fraction one half, U+00BD ISOnum
    '&frac34;'   => '&#190;',  # vulgar fraction three quarters = fraction three quarters, U+00BE ISOnum
    '&iquest;'   => '&#191;',  # inverted question mark = turned question mark, U+00BF ISOnum
    '&Agrave;'   => '&#192;',  # latin capital letter A with grave = latin capital letter A grave, U+00C0 ISOlat1
    '&Aacute;'   => '&#193;',  # latin capital letter A with acute, U+00C1 ISOlat1
    '&Acirc;'    => '&#194;',  # latin capital letter A with circumflex, U+00C2 ISOlat1
    '&Atilde;'   => '&#195;',  # latin capital letter A with tilde, U+00C3 ISOlat1
    '&Auml;'     => '&#196;',  # latin capital letter A with diaeresis, U+00C4 ISOlat1
    '&Aring;'    => '&#197;',  # latin capital letter A with ring above = latin capital letter A ring, U+00C5 ISOlat1
    '&AElig;'    => '&#198;',  # latin capital letter AE = latin capital ligature AE, U+00C6 ISOlat1
    '&Ccedil;'   => '&#199;',  # latin capital letter C with cedilla, U+00C7 ISOlat1
    '&Egrave;'   => '&#200;',  # latin capital letter E with grave, U+00C8 ISOlat1
    '&Eacute;'   => '&#201;',  # latin capital letter E with acute, U+00C9 ISOlat1
    '&Ecirc;'    => '&#202;',  # latin capital letter E with circumflex, U+00CA ISOlat1
    '&Euml;'     => '&#203;',  # latin capital letter E with diaeresis, U+00CB ISOlat1
    '&Igrave;'   => '&#204;',  # latin capital letter I with grave, U+00CC ISOlat1
    '&Iacute;'   => '&#205;',  # latin capital letter I with acute, U+00CD ISOlat1
    '&Icirc;'    => '&#206;',  # latin capital letter I with circumflex, U+00CE ISOlat1
    '&Iuml;'     => '&#207;',  # latin capital letter I with diaeresis, U+00CF ISOlat1
    '&ETH;'      => '&#208;',  # latin capital letter ETH, U+00D0 ISOlat1
    '&Ntilde;'   => '&#209;',  # latin capital letter N with tilde, U+00D1 ISOlat1
    '&Ograve;'   => '&#210;',  # latin capital letter O with grave, U+00D2 ISOlat1
    '&Oacute;'   => '&#211;',  # latin capital letter O with acute, U+00D3 ISOlat1
    '&Ocirc;'    => '&#212;',  # latin capital letter O with circumflex, U+00D4 ISOlat1
    '&Otilde;'   => '&#213;',  # latin capital letter O with tilde, U+00D5 ISOlat1
    '&Ouml;'     => '&#214;',  # latin capital letter O with diaeresis, U+00D6 ISOlat1
    '&times;'    => '&#215;',  # multiplication sign, U+00D7 ISOnum
    '&Oslash;'   => '&#216;',  # latin capital letter O with stroke = latin capital letter O slash, U+00D8 ISOlat1
    '&Ugrave;'   => '&#217;',  # latin capital letter U with grave, U+00D9 ISOlat1
    '&Uacute;'   => '&#218;',  # latin capital letter U with acute, U+00DA ISOlat1
    '&Ucirc;'    => '&#219;',  # latin capital letter U with circumflex, U+00DB ISOlat1
    '&Uuml;'     => '&#220;',  # latin capital letter U with diaeresis, U+00DC ISOlat1
    '&Yacute;'   => '&#221;',  # latin capital letter Y with acute, U+00DD ISOlat1
    '&THORN;'    => '&#222;',  # latin capital letter THORN, U+00DE ISOlat1
    '&szlig;'    => '&#223;',  # latin small letter sharp s = ess-zed, U+00DF ISOlat1
    '&agrave;'   => '&#224;',  # latin small letter a with grave = latin small letter a grave, U+00E0 ISOlat1
    '&aacute;'   => '&#225;',  # latin small letter a with acute, U+00E1 ISOlat1
    '&acirc;'    => '&#226;',  # latin small letter a with circumflex, U+00E2 ISOlat1
    '&atilde;'   => '&#227;',  # latin small letter a with tilde, U+00E3 ISOlat1
    '&auml;'     => '&#228;',  # latin small letter a with diaeresis, U+00E4 ISOlat1
    '&aring;'    => '&#229;',  # latin small letter a with ring above = latin small letter a ring, U+00E5 ISOlat1
    '&aelig;'    => '&#230;',  # latin small letter ae = latin small ligature ae, U+00E6 ISOlat1
    '&ccedil;'   => '&#231;',  # latin small letter c with cedilla, U+00E7 ISOlat1
    '&egrave;'   => '&#232;',  # latin small letter e with grave, U+00E8 ISOlat1
    '&eacute;'   => '&#233;',  # latin small letter e with acute, U+00E9 ISOlat1
    '&ecirc;'    => '&#234;',  # latin small letter e with circumflex, U+00EA ISOlat1
    '&euml;'     => '&#235;',  # latin small letter e with diaeresis, U+00EB ISOlat1
    '&igrave;'   => '&#236;',  # latin small letter i with grave, U+00EC ISOlat1
    '&iacute;'   => '&#237;',  # latin small letter i with acute, U+00ED ISOlat1
    '&icirc;'    => '&#238;',  # latin small letter i with circumflex, U+00EE ISOlat1
    '&iuml;'     => '&#239;',  # latin small letter i with diaeresis, U+00EF ISOlat1
    '&eth;'      => '&#240;',  # latin small letter eth, U+00F0 ISOlat1
    '&ntilde;'   => '&#241;',  # latin small letter n with tilde, U+00F1 ISOlat1
    '&ograve;'   => '&#242;',  # latin small letter o with grave, U+00F2 ISOlat1
    '&oacute;'   => '&#243;',  # latin small letter o with acute, U+00F3 ISOlat1
    '&ocirc;'    => '&#244;',  # latin small letter o with circumflex, U+00F4 ISOlat1
    '&otilde;'   => '&#245;',  # latin small letter o with tilde, U+00F5 ISOlat1
    '&ouml;'     => '&#246;',  # latin small letter o with diaeresis, U+00F6 ISOlat1
    '&divide;'   => '&#247;',  # division sign, U+00F7 ISOnum
    '&oslash;'   => '&#248;',  # latin small letter o with stroke, = latin small letter o slash, U+00F8 ISOlat1
    '&ugrave;'   => '&#249;',  # latin small letter u with grave, U+00F9 ISOlat1
    '&uacute;'   => '&#250;',  # latin small letter u with acute, U+00FA ISOlat1
    '&ucirc;'    => '&#251;',  # latin small letter u with circumflex, U+00FB ISOlat1
    '&uuml;'     => '&#252;',  # latin small letter u with diaeresis, U+00FC ISOlat1
    '&yacute;'   => '&#253;',  # latin small letter y with acute, U+00FD ISOlat1
    '&thorn;'    => '&#254;',  # latin small letter thorn, U+00FE ISOlat1
    '&yuml;'     => '&#255;',  # latin small letter y with diaeresis, U+00FF ISOlat1
    '&fnof;'     => '&#402;',  # latin small f with hook = function = florin, U+0192 ISOtech
    '&Alpha;'    => '&#913;',  # greek capital letter alpha, U+0391
    '&Beta;'     => '&#914;',  # greek capital letter beta, U+0392
    '&Gamma;'    => '&#915;',  # greek capital letter gamma, U+0393 ISOgrk3
    '&Delta;'    => '&#916;',  # greek capital letter delta, U+0394 ISOgrk3
    '&Epsilon;'  => '&#917;',  # greek capital letter epsilon, U+0395
    '&Zeta;'     => '&#918;',  # greek capital letter zeta, U+0396
    '&Eta;'      => '&#919;',  # greek capital letter eta, U+0397
    '&Theta;'    => '&#920;',  # greek capital letter theta, U+0398 ISOgrk3
    '&Iota;'     => '&#921;',  # greek capital letter iota, U+0399
    '&Kappa;'    => '&#922;',  # greek capital letter kappa, U+039A
    '&Lambda;'   => '&#923;',  # greek capital letter lambda, U+039B ISOgrk3
    '&Mu;'       => '&#924;',  # greek capital letter mu, U+039C
    '&Nu;'       => '&#925;',  # greek capital letter nu, U+039D
    '&Xi;'       => '&#926;',  # greek capital letter xi, U+039E ISOgrk3
    '&Omicron;'  => '&#927;',  # greek capital letter omicron, U+039F
    '&Pi;'       => '&#928;',  # greek capital letter pi, U+03A0 ISOgrk3
    '&Rho;'      => '&#929;',  # greek capital letter rho, U+03A1
    '&Sigma;'    => '&#931;',  # greek capital letter sigma, U+03A3 ISOgrk3
    '&Tau;'      => '&#932;',  # greek capital letter tau, U+03A4
    '&Upsilon;'  => '&#933;',  # greek capital letter upsilon, U+03A5 ISOgrk3
    '&Phi;'      => '&#934;',  # greek capital letter phi, U+03A6 ISOgrk3
    '&Chi;'      => '&#935;',  # greek capital letter chi, U+03A7
    '&Psi;'      => '&#936;',  # greek capital letter psi, U+03A8 ISOgrk3
    '&Omega;'    => '&#937;',  # greek capital letter omega, U+03A9 ISOgrk3
    '&alpha;'    => '&#945;',  # greek small letter alpha, U+03B1 ISOgrk3
    '&beta;'     => '&#946;',  # greek small letter beta, U+03B2 ISOgrk3
    '&gamma;'    => '&#947;',  # greek small letter gamma, U+03B3 ISOgrk3
    '&delta;'    => '&#948;',  # greek small letter delta, U+03B4 ISOgrk3
    '&epsilon;'  => '&#949;',  # greek small letter epsilon, U+03B5 ISOgrk3
    '&zeta;'     => '&#950;',  # greek small letter zeta, U+03B6 ISOgrk3
    '&eta;'      => '&#951;',  # greek small letter eta, U+03B7 ISOgrk3
    '&theta;'    => '&#952;',  # greek small letter theta, U+03B8 ISOgrk3
    '&iota;'     => '&#953;',  # greek small letter iota, U+03B9 ISOgrk3
    '&kappa;'    => '&#954;',  # greek small letter kappa, U+03BA ISOgrk3
    '&lambda;'   => '&#955;',  # greek small letter lambda, U+03BB ISOgrk3
    '&mu;'       => '&#956;',  # greek small letter mu, U+03BC ISOgrk3
    '&nu;'       => '&#957;',  # greek small letter nu, U+03BD ISOgrk3
    '&xi;'       => '&#958;',  # greek small letter xi, U+03BE ISOgrk3
    '&omicron;'  => '&#959;',  # greek small letter omicron, U+03BF NEW
    '&pi;'       => '&#960;',  # greek small letter pi, U+03C0 ISOgrk3
    '&rho;'      => '&#961;',  # greek small letter rho, U+03C1 ISOgrk3
    '&sigmaf;'   => '&#962;',  # greek small letter final sigma, U+03C2 ISOgrk3
    '&sigma;'    => '&#963;',  # greek small letter sigma, U+03C3 ISOgrk3
    '&tau;'      => '&#964;',  # greek small letter tau, U+03C4 ISOgrk3
    '&upsilon;'  => '&#965;',  # greek small letter upsilon, U+03C5 ISOgrk3
    '&phi;'      => '&#966;',  # greek small letter phi, U+03C6 ISOgrk3
    '&chi;'      => '&#967;',  # greek small letter chi, U+03C7 ISOgrk3
    '&psi;'      => '&#968;',  # greek small letter psi, U+03C8 ISOgrk3
    '&omega;'    => '&#969;',  # greek small letter omega, U+03C9 ISOgrk3
    '&thetasym;' => '&#977;',  # greek small letter theta symbol, U+03D1 NEW
    '&upsih;'    => '&#978;',  # greek upsilon with hook symbol, U+03D2 NEW
    '&piv;'      => '&#982;',  # greek pi symbol, U+03D6 ISOgrk3
    '&bull;'     => '&#8226;', # bullet = black small circle, U+2022 ISOpub
    '&hellip;'   => '&#8230;', # horizontal ellipsis = three dot leader, U+2026 ISOpub
    '&prime;'    => '&#8242;', # prime = minutes = feet, U+2032 ISOtech
    '&Prime;'    => '&#8243;', # double prime = seconds = inches, U+2033 ISOtech
    '&oline;'    => '&#8254;', # overline = spacing overscore, U+203E NEW
    '&frasl;'    => '&#8260;', # fraction slash, U+2044 NEW
    '&weierp;'   => '&#8472;', # script capital P = power set = Weierstrass p, U+2118 ISOamso
    '&image;'    => '&#8465;', # blackletter capital I = imaginary part, U+2111 ISOamso
    '&real;'     => '&#8476;', # blackletter capital R = real part symbol, U+211C ISOamso
    '&trade;'    => '&#8482;', # trade mark sign, U+2122 ISOnum
    '&alefsym;'  => '&#8501;', # alef symbol = first transfinite cardinal, U+2135 NEW
    '&larr;'     => '&#8592;', # leftwards arrow, U+2190 ISOnum
    '&uarr;'     => '&#8593;', # upwards arrow, U+2191 ISOnum
    '&rarr;'     => '&#8594;', # rightwards arrow, U+2192 ISOnum
    '&darr;'     => '&#8595;', # downwards arrow, U+2193 ISOnum
    '&harr;'     => '&#8596;', # left right arrow, U+2194 ISOamsa
    '&crarr;'    => '&#8629;', # downwards arrow with corner leftwards = carriage return, U+21B5 NEW
    '&lArr;'     => '&#8656;', # leftwards double arrow, U+21D0 ISOtech
    '&uArr;'     => '&#8657;', # upwards double arrow, U+21D1 ISOamsa
    '&rArr;'     => '&#8658;', # rightwards double arrow, U+21D2 ISOtech
    '&dArr;'     => '&#8659;', # downwards double arrow, U+21D3 ISOamsa
    '&hArr;'     => '&#8660;', # left right double arrow, U+21D4 ISOamsa
    '&forall;'   => '&#8704;', # for all, U+2200 ISOtech
    '&part;'     => '&#8706;', # partial differential, U+2202 ISOtech
    '&exist;'    => '&#8707;', # there exists, U+2203 ISOtech
    '&empty;'    => '&#8709;', # empty set = null set = diameter, U+2205 ISOamso
    '&nabla;'    => '&#8711;', # nabla = backward difference, U+2207 ISOtech
    '&isin;'     => '&#8712;', # element of, U+2208 ISOtech
    '&notin;'    => '&#8713;', # not an element of, U+2209 ISOtech
    '&ni;'       => '&#8715;', # contains as member, U+220B ISOtech
    '&prod;'     => '&#8719;', # n-ary product = product sign, U+220F ISOamsb
    '&sum;'      => '&#8721;', # n-ary sumation, U+2211 ISOamsb
    '&minus;'    => '&#8722;', # minus sign, U+2212 ISOtech
    '&lowast;'   => '&#8727;', # asterisk operator, U+2217 ISOtech
    '&radic;'    => '&#8730;', # square root = radical sign, U+221A ISOtech
    '&prop;'     => '&#8733;', # proportional to, U+221D ISOtech
    '&infin;'    => '&#8734;', # infinity, U+221E ISOtech
    '&ang;'      => '&#8736;', # angle, U+2220 ISOamso
    '&and;'      => '&#8743;', # logical and = wedge, U+2227 ISOtech
    '&or;'       => '&#8744;', # logical or = vee, U+2228 ISOtech
    '&cap;'      => '&#8745;', # intersection = cap, U+2229 ISOtech
    '&cup;'      => '&#8746;', # union = cup, U+222A ISOtech
    '&int;'      => '&#8747;', # integral, U+222B ISOtech
    '&there4;'   => '&#8756;', # therefore, U+2234 ISOtech
    '&sim;'      => '&#8764;', # tilde operator = varies with = similar to, U+223C ISOtech
    '&cong;'     => '&#8773;', # approximately equal to, U+2245 ISOtech
    '&asymp;'    => '&#8776;', # almost equal to = asymptotic to, U+2248 ISOamsr
    '&ne;'       => '&#8800;', # not equal to, U+2260 ISOtech
    '&equiv;'    => '&#8801;', # identical to, U+2261 ISOtech
    '&le;'       => '&#8804;', # less-than or equal to, U+2264 ISOtech
    '&ge;'       => '&#8805;', # greater-than or equal to, U+2265 ISOtech
    '&sub;'      => '&#8834;', # subset of, U+2282 ISOtech
    '&sup;'      => '&#8835;', # superset of, U+2283 ISOtech
    '&nsub;'     => '&#8836;', # not a subset of, U+2284 ISOamsn
    '&sube;'     => '&#8838;', # subset of or equal to, U+2286 ISOtech
    '&supe;'     => '&#8839;', # superset of or equal to, U+2287 ISOtech
    '&oplus;'    => '&#8853;', # circled plus = direct sum, U+2295 ISOamsb
    '&otimes;'   => '&#8855;', # circled times = vector product, U+2297 ISOamsb
    '&perp;'     => '&#8869;', # up tack = orthogonal to = perpendicular, U+22A5 ISOtech
    '&sdot;'     => '&#8901;', # dot operator, U+22C5 ISOamsb
    '&lceil;'    => '&#8968;', # left ceiling = apl upstile, U+2308 ISOamsc
    '&rceil;'    => '&#8969;', # right ceiling, U+2309 ISOamsc
    '&lfloor;'   => '&#8970;', # left floor = apl downstile, U+230A ISOamsc
    '&rfloor;'   => '&#8971;', # right floor, U+230B ISOamsc
    '&lang;'     => '&#9001;', # left-pointing angle bracket = bra, U+2329 ISOtech
    '&rang;'     => '&#9002;', # right-pointing angle bracket = ket, U+232A ISOtech
    '&loz;'      => '&#9674;', # lozenge, U+25CA ISOpub
    '&spades;'   => '&#9824;', # black spade suit, U+2660 ISOpub
    '&clubs;'    => '&#9827;', # black club suit = shamrock, U+2663 ISOpub
    '&hearts;'   => '&#9829;', # black heart suit = valentine, U+2665 ISOpub
    '&diams;'    => '&#9830;', # black diamond suit, U+2666 ISOpub
    '&quot;'     => '&#34;',   # quotation mark = APL quote, U+0022 ISOnum
    '&amp;'      => '&#38;',   # ampersand, U+0026 ISOnum
    '&lt;'       => '&#60;',   # less-than sign, U+003C ISOnum
    '&gt;'       => '&#62;',   # greater-than sign, U+003E ISOnum
    '&OElig;'    => '&#338;',  # latin capital ligature OE, U+0152 ISOlat2
    '&oelig;'    => '&#339;',  # latin small ligature oe, U+0153 ISOlat2
    '&Scaron;'   => '&#352;',  # latin capital letter S with caron, U+0160 ISOlat2
    '&scaron;'   => '&#353;',  # latin small letter s with caron, U+0161 ISOlat2
    '&Yuml;'     => '&#376;',  # latin capital letter Y with diaeresis, U+0178 ISOlat2
    '&circ;'     => '&#710;',  # modifier letter circumflex accent, U+02C6 ISOpub
    '&tilde;'    => '&#732;',  # small tilde, U+02DC ISOdia
    '&ensp;'     => '&#8194;', # en space, U+2002 ISOpub
    '&emsp;'     => '&#8195;', # em space, U+2003 ISOpub
    '&thinsp;'   => '&#8201;', # thin space, U+2009 ISOpub
    '&zwnj;'     => '&#8204;', # zero width non-joiner, U+200C NEW RFC 2070
    '&zwj;'      => '&#8205;', # zero width joiner, U+200D NEW RFC 2070
    '&lrm;'      => '&#8206;', # left-to-right mark, U+200E NEW RFC 2070
    '&rlm;'      => '&#8207;', # right-to-left mark, U+200F NEW RFC 2070
    '&ndash;'    => '&#8211;', # en dash, U+2013 ISOpub
    '&mdash;'    => '&#8212;', # em dash, U+2014 ISOpub
    '&lsquo;'    => '&#8216;', # left single quotation mark, U+2018 ISOnum
    '&rsquo;'    => '&#8217;', # right single quotation mark, U+2019 ISOnum
    '&sbquo;'    => '&#8218;', # single low-9 quotation mark, U+201A NEW
    '&ldquo;'    => '&#8220;', # left double quotation mark, U+201C ISOnum
    '&rdquo;'    => '&#8221;', # right double quotation mark, U+201D ISOnum
    '&bdquo;'    => '&#8222;', # double low-9 quotation mark, U+201E NEW
    '&dagger;'   => '&#8224;', # dagger, U+2020 ISOpub
    '&Dagger;'   => '&#8225;', # double dagger, U+2021 ISOpub
    '&permil;'   => '&#8240;', # per mille sign, U+2030 ISOtech
    '&lsaquo;'   => '&#8249;', # single left-pointing angle quotation mark, U+2039 ISO proposed
    '&rsaquo;'   => '&#8250;', # single right-pointing angle quotation mark, U+203A ISO proposed
    '&euro;'     => '&#8364;', # euro sign, U+20AC NEW
    '&apos;'     => '&#39;',   # apostrophe = APL quote, U+0027 ISOnum, XHTML only
  );
  $parts = explode('&', $s);
  $rs = @$parts[0];
  unset($parts[0]);
  foreach ($parts as $part) {
    $tp = explode(';', $part);
    if (count($tp) == 2) {
      $index = '&' . $tp[0] . ';';
      if (isset($entities[$index])) {
        $rs .= $entities[$index];
      } else {
        $rs .= '&' . $tp[0] . ';';
      }
      $rs .= $tp[1];
    } else {
      $rs .= $tp[0];
    }
  }
  return $rs;
}

function getBaseURL() {
  $pn = ''.@$_SERVER['REQUEST_URI'];
  $firstslash = substr($pn, 0, 1) == '/';
  $lastslash = substr($pn, strlen($pn) - 1, 1) == '/';
  if (!$lastslash) {
    $opn = $pn;
    $pn = basename($pn);
    $pn = substr($opn, 0, strlen($opn) - strlen($pn));
    $nowfirst = substr($pn, 0, 1) == '/';
    if ($firstslash && !$nowfirst) {
      $pn = '/' . $pn;
    }
  }
  return $pn;
}

function baseutils_errorHandler($errno, $errstr, $errfile, $errline) {
  global $baseutils_errors_visible;

  if (!(error_reporting() & $errno)) {
    // This error code is not included in error_reporting
    return;
  }

  $errtype = 'UNKNOWN';
  switch ($errno) {
    case E_USER_ERROR: $errtype = 'ERROR'; break;
    case E_USER_WARNING: $errtype = 'WARNING'; break;
    case E_USER_NOTICE: $errtype = 'NOTICE'; break;
  }

  $s = explode("\r", $errstr);
  foreach ($s as $line) {
    $line = trim($line);
    if ($line != 'Above error reporting') {
      if ($baseutils_errors_visible) {
        echo "\r\n" . $errtype . ' (' . $errfile . ':' . $errline . '): ' . $line;
      }
      logline($errtype . ' (' . $errfile . ':' . $errline . '): ' . $line);
    }
  }
  if ($baseutils_errors_visible) {
    echo "\r\n";
  }
  if ($errtype == 'ERROR') {
    exit(1);
  }
  return true;
}
