<?php

function mes($s) {
  return mysql_real_escape_string($s);
}

function q(&$q, $showerrors = true, $getnumrows = false) {
  if (!is_array($q)) {
    $s = $q;
    $q = array();
    $q['sql'] = $s;
    $r = mysql_query($s);
    $q['error'] = mysql_error();
    if (''.@$q['error'] == '') {
      $q['result'] = $r;
      $q['waiting'] = true;
      $q['n'] = 0;
    }
    if ($getnumrows) {
      $q['count'] = mysql_num_rows($r);
    }
    if (strtolower(substr($s, 0, 6)) == 'insert') {
      $q['insert_id'] = mysql_insert_id();
    }
  } else {
    $q['error'] = 'Already queried.';
  }
  if ($showerrors) {
    echo $q['error'];
  }
  return $q;
}

function qf(&$q) {
  if (is_array($q)) {
    if ($q['result']) {
      mysql_free_result($q['result']);
    }
    $q = null;
  }
  return true;
}

function sq(&$q, $showerrors = true, $getnumrows = false, $keepq = false) {
  if (!is_array($q)) {
    $q = q($q, $showerrors, $getnumrows);
  }
  if (@$q['waiting']) {
    if ($f = mysql_fetch_assoc($q['result'])) {
      $q['n']++;
      return $f;
    } else {
      mysql_free_result($q['result']);
      if (!$keepq) {
        $q = null;
      }
      return false;
    }
  }
}

function arraytosafe($values, $useand = false) {
  $first = true;
  $sql = '';
  foreach ($values as $name => $val) {
    if (!$first) {
      if ($useand) {
        $sql .= ' AND ';
      } else {
        $sql .= ', ';
      }
    }
    $sql .= ' `' . $name . '` = ';
    if (gettype($val) == 'string') {
      $sql .= '"' . mes($val) . '"';
    } elseif (gettype($val) == 'array') {
      $sql .= $val[0];
    } else {
      $sql .= 0+@$val;
    }
    $first = false;
  }
  return $sql;
}

function updateorinsert_inserted() {
  global $updateorinsert_inserted;
  return $updateorinsert_inserted;
}

function updateorinsert($table, $keyvalues, $values = array(), $insertonly = false, $showerrors = true) {
  global $updateorinsert_inserted;
  $updateorinsert_inserted = false;
  mysql_query('START TRANSACTION');
  $sql = 'SELECT * FROM `' . $table . '` WHERE ' . arraytosafe($keyvalues, true);
  $allvalues = array_merge($keyvalues, $values);
  $q = mysql_query($sql);
  if ($f = mysql_fetch_assoc($q)) {
    $sql = 'UPDATE `' . $table . '` SET ';
    $sql .= arraytosafe($allvalues);
    $i = 0+@$f['id'];
    $sql .= ' WHERE id = ' . $i;
    mysql_query($sql);
    if ($showerrors) {
      echo mysql_error();
    }
  } else {
    if ($insertonly !== false) {
      $allvalues = array_merge($allvalues, $insertonly);
    }
    $sql = 'INSERT INTO `' . $table . '` (';
    $first = true;
    foreach ($allvalues as $name => $val) {
      if (!$first) { $sql .= ', '; }
      $sql .= '`' . $name . '`';
      $first = false;
    }
    $sql .= ') VALUES (';
    $first = true;
    foreach ($allvalues as $name => $val) {
      if (!$first) { $sql .= ', '; }
      if (gettype($val) == 'string') {
        $sql .= '"' . mes($val) . '"';
      } elseif (gettype($val) == 'array') {
        $sql .= $val[0]; // raw expression
      } else {
        $sql .= 0+@$val;
      }
      $first = false;
    }
    $sql .= ')';
    mysql_query($sql);
    if ($showerrors) {
      echo mysql_error();
    }
    $i = mysql_insert_id();
    $updateorinsert_inserted = true;
  }
  mysql_query('COMMIT');
  return $i;
}

function update($table, $keyvalues, $values = array(), $showerrors = true) {
  $sql = 'UPDATE `' . $table . '` SET ';
  $sql .= arraytosafe($values);
  $i = 0+@$f['id'];
  $sql .= ' WHERE ' . arraytosafe($keyvalues, true);
  mysql_query($sql);
  if ($showerrors) {
    echo mysql_error();
  }
  return true;
}

function insert($table, $values, $showerrors = true) {
  $sql = 'INSERT INTO `' . $table . '` (';
  $first = true;
  foreach ($values as $name => $val) {
    if (!$first) { $sql .= ', '; }
    $sql .= '`' . $name . '`';
    $first = false;
  }
  $sql .= ') VALUES (';
  $first = true;
  foreach ($values as $name => $val) {
    if (!$first) { $sql .= ', '; }
    if (gettype($val) == 'string') {
      $sql .= '"' . mes($val) . '"';
    } elseif (gettype($val) == 'array') {
      $sql .= $val[0]; // raw expression
    } else {
      $sql .= 0+@$val;
    }
    $first = false;
  }
  $sql .= ')';
  mysql_query($sql);
  if ($showerrors) {
    echo mysql_error();
  }
  $i = mysql_insert_id();
  return $i;
}
