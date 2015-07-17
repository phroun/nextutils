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
// qsafe() -- make a safe query string
// selecta($table, [$fields,] $keyvalues, [$clauses]) -- just get one
// select($table, [$fields,] $keyvalues, [$clauses])
// selectRow() -- iterate over the result set obtained by select()
// selectClose() -- used in conjunction with select() and selectRow
//
// Functions mostly for internal use:
//
// q(...) - Raw version of sq()
// arraytosafe() - Used to stack up parameters into a string.
//
// ###########################################################################

$dbutils_txcount = 0;
$dbutils_history_callback = false;
$dbutils_show_errors = false;
$dbutils_link = NULL;
$dbutils_readonly = true;
$dbutils_selstack = array();

function mes($s) {
  global $dbutils_link;
  return mysqli_real_escape_string($dbutils_link, $s);
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
      $this->moreResults = array();
      $this->moreErrors = array();
      mysqli_multi_query($dbutils_link, implode(';', $queryString));
      $this->result = mysqli_use_result($dbutils_link);
      $this->error = mysqli_error($dbutils_link);
      while (mysqli_more_results($dbutils_link) && mysqli_next_result($dbutils_link)) {
        $this->moreResults[] = mysqli_use_result($dbutils_link);
        $this->moreErrors[] = mysqli_error($dbutils_link);
      }
    } else {
      $sample = strtolower(trim(substr($queryString, 1, 7)));
      $this->moreResults = array();
      $this->moreErrors = array();
      $this->result = mysqli_query($dbutils_link, $queryString);
      $this->error = mysqli_error($dbutils_link);
      if ($sample == 'insert') {
        $this->insert_id = mysqli_insert_id($dbutils_link);
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
      $this->count = mysqli_num_rows($this->result);
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
      mysqli_free_result($this->result);
    }
  }
  public function fetch() {
    if ($this->result) {
      $f = mysqli_fetch_assoc($this->result);
      if ($f) {
        $this->n++;
      } else {
        if ($this->result) {
          mysqli_free_result($this->result);
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
    $literal = (''.(0+@$name) === ''.@$name);

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
        $sql .= 0+@$val;
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
  if ( (!isset($keyvalues['id'])) || (0+@$keyvalues['id'] != 0) ) {
    // if the key value is based on an ID that is nonzero, or the key value is based on something other than ID:
    
    $sql = 'SELECT * FROM `' . $table . '` WHERE ' . arraytosafe($keyvalues, true);
    if ($f = sqf($sql)) {
      $i = 0+@$f['id'];
      $r = true;
      $sql = 'UPDATE `' . $table . '` SET ' . arraytosafe($allvalues) . ' WHERE id = ' . $i;
      mysqli_query($dbutils_link, $sql);
      if ($dbutils_show_errors) {
        echo mysqli_error($dbutils_link);
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
        $sql .= 0+@$val;
      }
      $first = false;
    }
    $sql .= ')';
    mysqli_query($dbutils_link, $sql);
    $error = mysqli_error($dbutils_link);
    if (($error > '') && $dbutils_show_errors) {
      echo $error . "\r\n";
    }
    $i = mysqli_insert_id($dbutils_link);
    $updateorinsert_inserted = true;
  }
  if ($dbutils_history_callback !== false) {
    $dbutils_history_callback($table, $keyvalues);
  }  
  txCommit();
  return $i;
}

function update($table, $keyvalues, $values = array()) {
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
  mysqli_query($dbutils_link, $sql);
  $error = mysqli_error($dbutils_link);
  if ($error > '') {
    if ($dbutils_show_errors) {
      echo $error . "\r\n";
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
      $sql .= '"' . mysqli_real_escape_string($dbutils_link, $val) . '"';
    } elseif (gettype($val) == 'array') {
      $sql .= $val[0]; // raw expression
    } else {
      $sql .= 0+@$val;
    }
    $first = false;
  }
  $sql .= ')';
  @mysqli_query($dbutils_link, $sql);
  $error = @mysqli_error($dbutils_link);
  $mii = 0;
  if (trim($error) == '') {
    $mii = @mysqli_insert_id($dbutils_link);
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
    $keyvalues = array('id' => 0+@$keyvalues);
  }
  if (count($keyvalues) > 0) {
    $sql = 'DELETE FROM `' . $table . '` '
    . ' WHERE ' . arraytosafe($keyvalues, true);
    if ($limit > 0) {
      $sql .= ' LIMIT ' . $limit;
    }
    mysqli_query($dbutils_link, $sql);
    $e = mysqli_error($dbutils_link);
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
    $keyvalues = array('id' => 0+@$keyvalues);
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
      unset($GLOBALS['dbutils_selstack'][$qq][1]);
      return $f;  
    } else {
      $f = sq($dbutils_selstack[$qq][0]);
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
    unset($dbutils_selstack[$qq]);
  }
}

function txBegin() {
  global $dbutils_txcount;
  global $dbutils_link;
  assertDataNonRO('txBegin');
  if (0+@$dbutils_txcount == 0) {
    mysqli_query($dbutils_link, 'START TRANSACTION');
  }
  $dbutils_txcount++;
}

function txCommit() {
  global $dbutils_txcount;
  global $dbutils_link;
  assertDataNonRO('txCommit');
  $dbutils_txcount--;
  if (0+@$dbutils_txcount == 0) {
    mysqli_query($dbutils_link, 'COMMIT');
  }
}

function txCancel() {
  global $dbutils_txcount;
  global $dbutils_link;
  assertDataNonRO('txCancel');
  $dbutils_txcount--;
  if (0+@$dbutils_txcount <= 0) {
    mysqli_query($dbutils_link, 'ROLLBACK');
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

function dbutils_connect($host, $user, $pass, $base = '', $graceful = false) {
  $port = ini_get('mysqli.default_port');
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
  $dbconn = mysqli_connect($host, $user, $pass, $base, $port);
  if (mysqli_connect_errno()) {
    $error = mysqli_connect_error();
    $error = 'DB connection failure: ' . $error;
    die();
  } else {
    setDataLink($dbconn);
    $error = mysqli_error($dbconn);
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
        for ($x = 0; $ < mb_strlen($v); $x++) {
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
        }
      } else {
        if ($nexttype == 1) {
          $s .= '"' . mes($v) . '"';
        } elseif ($nexttype == 2) {
          $s .= (0+@$v);
        } else {
          echo 'Malformed query.';
          die();
        }
      }
    }
  }
  return $s;
}
