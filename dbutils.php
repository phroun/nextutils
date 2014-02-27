<?php
// ###########################################################################
// dbutils.php:  Utilities for PHP Database Development with MySQL
// ===========================================================================
// Version 2013-05-05
//
// To use the update or insertorupdate functions, tables must have an integer
// autonumber column called "id" as the primary key.
//
// Contributors:
//
// * Jeff Day
//
// Quick Usage Guide:
//
// mes($s) - Sanitize a string value using mysql_real_escape_string.
// sq($q, ...) - Turn string q into result set and return first result, or if
//   q is already a result set, return next result. If last result, the result
//   set will be freed and disposed of automatically.
// insert($table, $values) - returns id of the inserted record
// update($table, $keyvalues, $values) - returns true if successful
// updateorinsert($table, $keyvalues, $values, $insertonlyvalues) - returns
//   id of the updated or inserted record.
// updateorinsert_inserted() - Returns true if prior updateorinsert invocation
//   resulted in the insertion of a new record.
// deleteFrom($table, $keyvalues)
// txBegin(), txCommit(), txCancel()
//
// Functions mostly for internal use:
//
// q(...) - Raw version of sq()
// arraytosafe() - Used to stack up parameters into a string.
//
// ###########################################################################

$dbutils_txcount = 0;
$dbutils_history_callback = false;

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
    if (@$q['result']) {
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

function arraytosafe($values, $useand = false, $where = false) {
  $first = true;
  $sql = '';
  foreach ($values as $name => $val) {
    $literal = (substr($name, 0, 1) == '&');

    if ($where||!$literal) {
      if (!$first) {
        if ($useand) {
          $sql .= ' AND ';
        } else {
          $sql .= ', ';
        }
      }
    }

    if ($literal) {
      if ($where) {
        $sql .= ' ' . substr($name, 1);
      }
    } else {
      $sql .= ' `' . $name . '` = ';
      if (gettype($val) == 'string') {
        $sql .= '"' . mes($val) . '"';
      } elseif (gettype($val) == 'array') {
        $sql .= $val[0];
      } else {
        $sql .= 0+@$val;
      }
    }

    if ($where||!$literal) {
      $first = false;
    }
  }
  return $sql;
}

function updateorinsert_inserted() {
  global $updateorinsert_inserted;
  return $updateorinsert_inserted;
}

function updateorinsert($table, $keyvalues, $values = array(), $insertonlyvalues = false, $showerrors = true) {
  global $updateorinsert_inserted;
  global $dbutils_history_callback;
  $r = false;
  $updateorinsert_inserted = false;
  txBegin();
  if (isset($keyvalues['id']) && (0+@$keyvalues['id'] == 0)) {
    $sql = 'SELECT * FROM `' . $table . '` WHERE false'; // inserting, so we short circuit this
  } else {
    $sql = 'SELECT * FROM `' . $table . '` WHERE ' . arraytosafe($keyvalues, true);
  }
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
    if ($insertonlyvalues !== false) {
      $allvalues = array_merge($allvalues, $insertonlyvalues);
    }
    if (isset($allvalues['id'])) { // allow inserting to autogenerate the id field
      if ($allvalues['id'] == 0) {
        unset($allvalues['id']);
      }
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
  if ($dbutils_history_callback !== false) {
    $dbutils_history_callback($table, $keyvalues);
  }  
  txCommit();
  return $i;
}

function update($table, $keyvalues, $values = array(), $showerrors = true) {
  global $dbutils_history_callback;
  $r = false;
  if ($dbutils_history_callback !== false) {
    txBegin();
  }
  $sql = 'UPDATE `' . $table . '` SET ';
  $sql .= arraytosafe($values);
  $sql .= ' WHERE ' . arraytosafe($keyvalues, true);
  mysql_query($sql);
  $e = mysql_error();
  if ($e > '') {
    if ($showerrors) {
      echo $e;
    }
  } else {
    $r = true;
  }
  if ($dbutils_history_callback !== false) {
    $dbutils_history_callback($table, $keyvalues);
    txCommit();
  }
  return $r;
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

function deleteFrom($table, $keyvalues = array(), $showerrors = true, $limit = 0) {
  if (!is_array($keyvalues)) {
    $keyvalues = array('id' => 0+@$keyvalues);
  }
  if (count($keyvalues) > 0) {
    $sql = 'DELETE FROM `' . $table . '` '
    . ' WHERE ' . arraytosafe($keyvalues, true);
    if ($limit > 0) {
      $sql .= ' LIMIT ' . $limit;
    }
    mysql_query($sql);
    $e = mysql_error();
    if ($e > '') {
      if ($showerrors) {
        echo $e;
      }
      return false;
    } else {
      return true;
    }
  } else {
    $e = 'No key/value given for delete.';
    return false;
  }
}

function getSelectFrom($table, $fields, $keyvalues = array(), $clauses = '', $showerrors = true) {
  $r = false;
  $qs = false;
  if (!is_array($keyvalues)) {
    $keyvalues = array('id' => 0+@$keyvalues);
  }
  if (count($keyvalues) > 0) {
    $qs = 'SELECT ' . $fields . ' FROM `' . $table . '` '
    . ' WHERE (' . arraytosafe($keyvalues, true) . ') ' . $clauses;
  } else {
    $e = 'No key/value given for delete.';
    $r = false;
  }
  return $qs;
}

function txBegin() {
  global $dbutils_txcount;
  if (0+@$dbutils_txcount == 0) {
    mysql_query('START TRANSACTION');
  }
  $dbutils_txcount++;
}

function txCommit() {
  global $dbutils_txcount;
  $dbutils_txcount--;
  if (0+@$dbutils_txcount == 0) {
    mysql_query('COMMIT');
  }
}

function txCancel() {
  global $dbutils_txcount;
  $dbutils_txcount--;
  if (0+@$dbutils_txcount <= 0) {
    mysql_query('ROLLBACK');
  } else {
    die(); // something pretty bad happened
  }
}
