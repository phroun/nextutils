<?php
// ###########################################################################
// dbutils.php:  Utilities for PHP Database Development with MySQL
// ===========================================================================
// Version 2015-07-20.  See README.md
// ###########################################################################

$dbutils_txcount = 0;
$dbutils_history_callback = false;
$dbutils_show_errors = false;
$dbutils_link = NULL;
$dbutils_readonly = true;
$dbutils_selstack = array();

function mes($s) {
  global $dbutils_link;
  return mysql_real_escape_string($s, $dbutils_link);
}

class Q {
  private
    $sql,
    $result,
    $moreResults,
    $moreErrors,
    $n,
    $cached_count,
    $count,
    $error,
    $insert_id;
  public function __construct($queryString) {
    global $dbutils_show_errors;
    global $dbutils_link;
    $this->n = 0;
    $this->insert_id = false;
    $this->cached_count = false;
    $this->sql = $queryString;
    if (!$dbutils_link) {
      if ($dbutils_show_errors) {
        echo "No link when trying: " . $queryString . "\r\n";
      } else {
        die();
      }
    }
    if (is_array($queryString)) {
      echo "Not supported.  Upgrade to mysqli required.";
      die();
    } else {
      $sample = strtolower(trim(substr($queryString, 1, 7)));
      $this->moreResults = array();
      $this->moreErrors = array();
      $this->result = mysql_query($queryString, $dbutils_link);
      $this->error = mysql_error($dbutils_link);
      if ($sample == 'insert') {
        $this->insert_id = mysql_insert_id($dbutils_link);
      }
    }
    if (''.@$this->error > '') {
      $this->result = null;
      if ($dbutils_show_errors) {
        echo $this->error . "\r\n";
        die();
      }
    }
  }
  public function count() {
    if (!$this->cached_count) {
      $this->count = mysql_num_rows($this->result);
      $this->cached_count = true;
    }
    return $this->count;
  }
  public function error() {
    return $this->error;
  }
  public function insert_id() {
    return $this->insert_id;
  }
  public function get_sql() {
    return $this->sql;
  }
  public function get_cursor() {
    return $this->n;
  }
  public function free() {
    if ($this->result) {
      mysql_free_result($this->result);
    }
  }
  public function fetch() {
    if ($this->result) {
      $f = mysql_fetch_assoc($this->result);
      if ($f) {
        $this->n++;
      } else {
        if ($this->result) {
          mysql_free_result($this->result);
          $this->result = null; // apparently this is more important than such things were in the non-class version of the code :)
        }
      }
      return $f;
    } else {
      return false;
    }
  }
}
//  look for usages of 'n'  "n"  "error" 'error' "insert_id" 'insert_id' "count" 'count'

function sqlWriteStatement($q) {
  $r = true;
  $q = mb_strtoupper($q);
  if (trim(mb_substr($q, 0, 7)) == 'SELECT') {
    $r = false;
  } elseif (trim(mb_substr($q, 0, 5)) == 'SHOW') {
    $r = false;
  } elseif (trim(mb_substr($q, 0, 9)) == 'DESCRIBE') {
    $r = false;
  } elseif (trim(mb_substr($q, 0, 8)) == 'EXPLAIN') {
    $r = false;
  }
  return $r;
}

function assertDataNonRO($s) {
  global $dbutils_readonly;
  if ($dbutils_readonly) {
    echo "\r\n\r\n";
    echo 'ASSERTION (readonly == false) FAILED: ' . $s;
    echo "\r\n\r\n";
    die();
  }
}

function qf(&$q) {
  if (is_object($q)) {
    $q = null; // no need to call free because null
  }
}

function sq(&$query, $showerrors = true) {
  global $dbutils_show_errors;
  $dbutils_show_errors = $showerrors;
  if (!is_object($query)) {
    if (is_array($query)) {
      foreach ($query as $key => $val) {
        if (sqlWriteStatement($val)) {
          assertDataNonRO('sq:' . $val);
        }
      }
    } else {
      if (sqlWriteStatement($query)) {
        assertDataNonRO('sq:' . $query);
      }
    }
    $query = new Q($query);
  }
  return $query->fetch();
}

function sqf($queryString, $showerrors = true) {
  global $dbutils_show_errors;
  $dbutils_show_errors = $showerrors;
  if (is_array($queryString)) {
    foreach ($queryString as $key => $val) {
      if (sqlWriteStatement($val)) {
        assertDataNonRO('sqf:' . $val);
      }    
    }
  } else {
    if (sqlWriteStatement($queryString)) {
      assertDataNonRO('sqf:' . $queryString);
    }
  }
  $q = new Q($queryString);
  $r = $q->fetch();
  $q = null;
  return $r;
}

function arraytosafe($values, $useand = false) {
  $first = true;
  $sql = '';
  foreach ($values as $name => $val) {
    $literal = (''.((int)@$name) === ''.@$name);

//    if (!$literal) {
      if (!$first) {
        if ($useand) {
          $sql .= ' AND ';
        } else {
          $sql .= ', ';
        }
      }
//    }

    if ($literal) {
      $sql .= $val;
    } else {
      $sql .= ' `' . $name . '` = ';
      if (gettype($val) == 'string') {
        $sql .= '"' . mes($val) . '"';
      } elseif (gettype($val) == 'array') {
        $sql .= $val[0];
      } else {
        $sql .= (int)@$val;
      }
    }

//    if (!$literal) {
      $first = false;
//    }
  }
  return $sql;
}

function updateorinsert_inserted() {
  global $updateorinsert_inserted;
  return $updateorinsert_inserted; // this function is highly useful
}

function updateorinsert($table, $keyvalues, $values = array(), $insertonlyvalues = false) {
  global $dbutils_show_errors;
  global $dbutils_history_callback;
  global $updateorinsert_inserted;
  global $dbutils_link;
  $i = false;
  $r = false;
  $allvalues = array_merge($keyvalues, $values);
  assertDataNonRO('updateorinsert:' . $table);

  $updateorinsert_inserted = false;
  txBegin();
  if ( (!isset($keyvalues['id'])) || ((int)@$keyvalues['id'] != 0) ) {
    // if the key value is based on an ID that is nonzero, or the key value is based on something other than ID:
    
    $sql = 'SELECT * FROM `' . $table . '` WHERE ' . arraytosafe($keyvalues, true);
    if ($f = sqf($sql)) {
      $i = (int)@$f['id'];
      $r = true;
      $sql = 'UPDATE `' . $table . '` SET ' . arraytosafe($allvalues) . ' WHERE id = ' . $i;
      mysql_query($sql, $dbutils_link);
      if ($dbutils_show_errors) {
        echo mysql_error($dbutils_link);
      }
    }
  }

  if (!$r) {
    if ($insertonlyvalues !== false) {
      $allvalues = array_merge($allvalues, $insertonlyvalues);
    }
    if (isset($allvalues['id'])) { // allow inserting to autogenerate the id field
      if ($allvalues['id'] == 0) {
        unset($allvalues['id']);
      }
    }
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', array_keys($allvalues)) . '`) VALUES (';
    $first = true;
    foreach ($allvalues as $name => $val) {
      if (!$first) { $sql .= ', '; }
      if (gettype($val) == 'string') {
        $sql .= '"' . mes($val) . '"';
      } elseif (gettype($val) == 'array') {
        $sql .= $val[0]; // raw expression
      } else {
        $sql .= (int)@$val;
      }
      $first = false;
    }
    $sql .= ')';
    mysql_query($sql, $dbutils_link);
    $error = mysql_error($dbutils_link);
    if (($error > '') && $dbutils_show_errors) {
      echo $error . "\r\n";
    }
    $i = mysql_insert_id($dbutils_link);
    $updateorinsert_inserted = true;
  }
  if ($dbutils_history_callback !== false) {
    $dbutils_history_callback($table, $keyvalues);
  }  
  txCommit();
  return $i;
}

function update($table, $keyvalues, $values = array(), $clauses = '') {
  global $dbutils_history_callback;
  global $dbutils_show_errors;
  global $dbutils_link;
  assertDataNonRO('update:' . $table);
  $r = false;
  if ($dbutils_history_callback !== false) {
    txBegin();
  }
  $sql = 'UPDATE `' . $table
  . '` SET ' . arraytosafe($values)
  . ' WHERE ' . arraytosafe($keyvalues, true);
  if (is_array($clauses)) {
    $clauses = qsafe($clauses);
  }
  if ($clauses > '') {
    $sql .= ' ' . $clauses;
  }              
  mysql_query($sql, $dbutils_link);
  $error = mysql_error($dbutils_link);
  if ($error > '') {
    if ($dbutils_show_errors) {
      echo $error . "\r\n";
    }
  } else {
    $r = mysql_affected_rows($dbutils_link);
  }
  if ($dbutils_history_callback !== false) {
    $dbutils_history_callback($table, $keyvalues);
    txCommit();
  }
  return $r;
}

function insert($table, $values) {
  global $dbutils_show_errors;
  global $dbutils_link;
  assertDataNonRO('insert:' . $table);
  $sql = 'INSERT INTO `' . $table . '` (`'
  . implode('`, `', array_keys($values))
  . '`) VALUES (';

  $first = true;
  foreach ($values as $name => $val) {
    if (!$first) { $sql .= ', '; }
    if (gettype($val) == 'string') {
      $sql .= '"' . mysql_real_escape_string($val, $dbutils_link) . '"';
    } elseif (gettype($val) == 'array') {
      $sql .= $val[0]; // raw expression
    } else {
      $sql .= (int)@$val;
    }
    $first = false;
  }
  $sql .= ')';
  @mysql_query($sql, $dbutils_link);
  $error = @mysql_error($dbutils_link);
  $mii = 0;
  if (trim($error) == '') {
    $mii = @mysql_insert_id($dbutils_link);
  }
  if ($dbutils_show_errors && (''.@$error > '')) {
    echo $error . "\r\n";
  }
  return $mii;
}

function deleteFrom($table, $keyvalues = array(), $limit = 0) {
  global $dbutils_show_errors;
  global $dbutils_link;
  assertDataNonRO('deleteFrom:' . $table);
  if (!is_array($keyvalues)) {
    $keyvalues = array('id' => (int)@$keyvalues);
  }
  if (count($keyvalues) > 0) {
    $sql = 'DELETE FROM `' . $table . '` '
    . ' WHERE ' . arraytosafe($keyvalues, true);
    if ($limit > 0) {
      $sql .= ' LIMIT ' . $limit;
    }
    mysql_query($sql, $dbutils_link);
    $e = mysql_error($dbutils_link);
    if ($e > '') {
      if ($dbutils_show_errors) {
        echo $e;
      }
      return false;
    } else {
      return true;
    }
  } else {
    if ($dbutils_show_errors) {
      echo 'No key/value given for delete.' . "\r\n";
    }
    return false;
  }
}

function getSelectFrom($table, $fields, $keyvalues = array(), $clauses = '') {
  global $dbutils_show_errors;
  $qs = false;
  if (is_array($fields)) { // allow fields to be optional
    $clauses = $keyvalues;
    $keyvalues = $fields;
    $fields = '*';
  }
  if (($clauses == '') && is_array($keyvalues) && (count($keyvalues) == 0)) {
    // allow overloading for simplest form:  select('employee', 23)
    if (is_numeric($fields)) {
      $keyvalues = $fields;
      $fields = '*';
    }
  }
  if (!is_array($keyvalues)) {
    $keyvalues = array('id' => (int)@$keyvalues);
  }
  if (is_array($clauses)) {
    $clauses = qsafe($clauses);
  }
  if (count($keyvalues) > 0) {
    $qs = 'SELECT ' . $fields . ' FROM `' . $table . '` '
    . ' WHERE (' . arraytosafe($keyvalues, true) . ') ' . $clauses;
  } else {
    if ($dbutils_show_errors) {
      echo 'No key/value given for select.' . "\r\n";
    }
    die();
  }
  return $qs;
}

function select($table, $fields, $keyvalues = array(), $clauses = '') {
  global $dbutils_selstack;

  $cleaning = true;
  while ($cleaning) {
    $cleaning = false;
    $qq = count($dbutils_selstack) - 1;
    if ($qq >= 0) {
      if (!$dbutils_selstack[$qq][2]) {
        qf($dbutils_selstack[$qq][0]);
        unset($GLOBALS['dbutils_selstack'][$qq]);
        $GLOBALS['dbutils_selstack'] = array_values($GLOBALS['dbutils_selstack']);
        $cleaning = true;
      }
    }
  }
  
  $q = getSelectFrom($table, $fields, $keyvalues, $clauses);
  $f = sq($q);
  $dbutils_selstack[] = array($q, $f, false);
  return $f;
}

function selecta($table, $fields, $keyvalues = array(), $clauses = '') {
  $f = select($table, $fields, $keyvalues, $clauses);
  selectClose();
  return $f;
}

function selectRow() {
  global $dbutils_selstack;
  $qq = count($dbutils_selstack) - 1;
  if ($qq >= 0) {
    $dbutils_selstack[$qq][2] = true;
    if (isset($dbutils_selstack[$qq][1])) {
      $f = $dbutils_selstack[$qq][1];
      unset($GLOBALS['dbutils_selstack'][$qq][1]); // remove preloaded item
      return $f;
    } else {
      $f = sq($GLOBALS['dbutils_selstack'][$qq][0]);
      return $f;
    }
  } else {
    return false;
  }
}

function selectClose() {
  global $dbutils_selstack;
  $qq = count($dbutils_selstack) - 1;
  if ($qq >= 0) {
    qf($dbutils_selstack[$qq][0]);
    unset($GLOBALS['dbutils_selstack'][$qq]);
    $GLOBALS['dbutils_selstack'] = array_values($GLOBALS['dbutils_selstack']);
  }
}

function txBegin() {
  global $dbutils_txcount;
  global $dbutils_link;
  assertDataNonRO('txBegin');
  if ((int)@$dbutils_txcount == 0) {
    mysql_query('START TRANSACTION', $dbutils_link);
  }
  $dbutils_txcount++;
}

function txCommit() {
  global $dbutils_txcount;
  global $dbutils_link;
  assertDataNonRO('txCommit');
  $dbutils_txcount--;
  if ((int)@$dbutils_txcount == 0) {
    mysql_query('COMMIT', $dbutils_link);
  }
}

function txCancel() {
  global $dbutils_txcount;
  global $dbutils_link;
  assertDataNonRO('txCancel');
  $dbutils_txcount--;
  if ((int)@$dbutils_txcount <= 0) {
    mysql_query('ROLLBACK', $dbutils_link);
  } else {
    die(); // something pretty bad happened
  }
}

function setDataLink($link, $readonly = false) {
  global $dbutils_link;
  global $dbutils_readonly;
  $dbutils_link = $link;
  $dbutils_readonly = $readonly;
}

function insertorupdate($table, $keyvalues, $values = array(), $insertonlyvalues = false) {
  return updateorinsert($table, $keyvalues, $values, $insertonlyvalues);
}

function sqx($query, $showerrors = true) {
  global $dbutils_show_errors;
  $dbutils_show_errors = @$showerrors;
  if (!is_object($query)) {
    if (is_array($query)) {
      foreach ($query as $key => $val) {
        if (sqlWriteStatement($val)) {
          assertDataNonRO('sq:' . $val);
        }
      }
    } else {
      if (sqlWriteStatement($query)) {
        assertDataNonRO('sq:' . $query);
      }
    }
    $query = new Q($query);
  }
  return $query->error();
}

function dbutils_close($link) {
  mysql_close($link);
}

function dbutils_connect($host, $user, $pass, $base = '', $graceful = false) {
  $port = ini_get('mysql.default_port');
  $parts = explode(']', $host);
  if (count($parts) > 1) {
    $port = str_replace(':', '', $parts[1]);
    $host = str_replace('[', '', $parts[0]);
  } else {
    $parts = explode(':', $host);
    if (count($parts) > 1) {
      $port = $parts[1];
      $host = $parts[0];
    }
  }
  $error = '';
  $dbconn = mysql_connect($host . ':' . $port, $user, $pass, true);
  if (!$dbconn) {
    $error = 'DB connection failure: ' . mysql_error();
  } else {
    setDataLink($dbconn);
    mysql_select_db($base, $dbconn);
    $error = mysql_error($dbconn);
    if ($error > '') {
      $error = 'DB access failure: ' . $error;
    }
  }
  if ($error > '') {
    if ($graceful) {
      return $error;
    } else {
      echo "\r\n" . $error . "\r\n";
      die();
    }
  } else {
    return $dbconn;
  }
}

function qsafe($qs) {
  $s = '';
  $nexttype = 0;
  $frag = false;
  if (!is_array($qs)) {
    $qs = func_get_args();
  }
  if (is_array($qs)) {
    foreach ($qs as $k => $v) {
      $frag = !$frag;
      if ($frag) {
        $nexttype = 0;
        $nt = mb_substr($v, mb_strlen($v) - 1, 1);
        $bad = false;
        for ($x = 0; $x < mb_strlen($v); $x++) {
          $ch = mb_substr($v, $x, 1);
          if ( ($ch == '"')
          || ($ch == "'")
          || (($ch >= '0') && ($ch <= '9')) ) {
            $bad = true;
          }
        }
        if ($bad) {
          echo 'Malformed query..';
          die();
        }
        if ($nt == '$') {
          $nexttype = 1;
        } elseif ($nt == '#') {
          $nexttype = 2;
        } else {
          $nt = '';
        }
        $s .= mb_substr($v, 0, mb_strlen($v) - strlen($nt));
      } else {
        if ($nexttype == 1) {
          $s .= '"' . mes($v) . '"';
        } elseif ($nexttype == 2) {
          $s .= ((int)@$v);
        } else {
          echo 'Malformed query.';
          die();
        }
      }
    }
  }
  return $s;
}
