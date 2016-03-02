<?php
// ###########################################################################
// dbutils.php:  Utilities for PHP Database Development with MySQL
// ===========================================================================
// Version 2016-02-13.  See README.md
// ###########################################################################

$dbutils_txcount = 0;
$dbutils_history_callback = false;
$dbutils_show_errors = false;
$dbutils_link = NULL;
$dbutils_readonly = true;
$dbutils_selstack = array();
$dbutils_tx_failure_exception = false;
$dbiutils_die_if_malformed = false; // allow qsafe() legacy behavior

function dbiutils_stack_trace($error_msg, $stack_trace_level) {
  $stack = debug_backtrace();
  $first = true;
  $n = 0;
  for ($i = $stack_trace_level + 1; $i < count($stack); $i++) {
    $frame = $stack[$i];
    if (!$frame) {
      break;
    }
    if ($first) {
      $error_msg .= ' in call to ' . $frame['function'] . '() at ';
    } else {
      $n++;
      $error_msg .= "\r\n ... Stack Trace(" . $n . '):  During call to ' . $frame['function'] . '() invoked at ';
    }
    $first = false;
    $error_msg .= $frame['file'] . ':' . $frame['line'];
  }
  $error_msg .= " \r\nAbove error reporting";
  return trigger_error($error_msg, E_USER_WARNING);
}

function dbiutils_assert_connection($stack_trace_level = 0) {
  global $dbutils_link;
  if (!is_object($dbutils_link)) {
    dbiutils_stack_trace('No connection specified (use setDataLink or dbutils_connect)', $stack_trace_level + 1);
    return false;
  }
  if (get_class($dbutils_link) != 'mysqli') {
    dbiutils_stack_trace('No connection specified (use setDataLink or dbutils_connect)', $stack_trace_level + 1);
    return false;
  }
  
  return true;
}

function mes($s) {
  global $dbutils_link;
  if (dbiutils_assert_connection(0)) {
    return mysqli_real_escape_string($dbutils_link, $s);
  } else {
    return false;
  }
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
  public function __construct($queryString, $stack_trace_level = 0) {
    global $dbutils_show_errors;
    global $dbutils_link;
    $this->n = 0;
    $this->insert_id = false;
    $this->cached_count = false;
    $this->sql = $queryString;
    if (!dbiutils_assert_connection($stack_trace_level)) {
      if (!$dbutils_show_errors) {
        throw new Exception('No link when trying: ' . $queryString); // Exception is to emulate previous die() behavior for compatibility
      } else {
        dbiutils_stack_trace('No link when trying: ' . $queryString, 0+@$stack_trace_level);
        // Letting this continue for compatibility
      }
    }
    if (is_array($queryString)) {

      $qc = 0;
      foreach ($queryString as $key => $val) {
        $qc++;
        if (''.@$val > '') {
          if (sqlWriteStatement($val)) {
            assertDataNonRO('sq:' . $val);
          }
        } else {
          dbiutils_stack_trace('No SQL statement given in array (item #' . $qc . ')', 0+@$stack_trace_level);
        }
      }
      if ($qc == 0) {
        dbiutils_stack_trace('No SQL statement given in array', 0+@$stack_trace_level);
      }

      $this->moreResults = array();
      $this->moreErrors = array();
      $combinedQueryString = implode(';', $queryString);
      mysqli_multi_query($dbutils_link, $combinedQueryString);
      $this->result = mysqli_use_result($dbutils_link);
      $this->error = mysqli_error($dbutils_link);
      while (mysqli_more_results($dbutils_link) && mysqli_next_result($dbutils_link)) {
        $this->moreResults[] = mysqli_use_result($dbutils_link);
        $this->moreErrors[] = mysqli_error($dbutils_link);
      }
    } else {
      $combinedQueryString = ''.@$queryString;

      if (''.@$queryString == '') {
        dbiutils_stack_trace('No SQL statement given', 0+@$stack_trace_level);
      }    
    
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
      throw new Exception('MySQL: ' . $this->error . '  [QUERY: ' . $combinedQueryString . ']');
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

function sqlWriteStatement($q = '') {
  if ($q == '') {
    dbiutils_stack_trace('No SQL statement given', 0);
  }
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
    throw new Exception('ASSERTION (readonly == false) FAILED: ' . $s);    
  }
}

function qf(&$q) {
  if (is_object($q)) {
    $q = null; // no need to call free because null
  }
}

function sq(&$query, $showerrors = true, $stack_trace_level = 0) {
  global $dbutils_show_errors;
  $dbutils_show_errors = $showerrors;
  if (!dbiutils_assert_connection(0)) {
    return false;
  }
  if (!is_object($query)) {
    if (is_array($query)) {
      $qc = 0;
      foreach ($query as $key => $val) {
        $qc++;
        if (''.@$val > '') {
          if (sqlWriteStatement($val)) {
            assertDataNonRO('sq:' . $val);
          }
        } else {
          dbiutils_stack_trace('No SQL statement given in array (item #' . $qc . ')', $stack_trace_level);
        }
      }
      if ($qc == 0) {
        dbiutils_stack_trace('No SQL statement given in array', $stack_trace_level);
      }
    } else {
      if (''.@$query == '') {
        dbiutils_stack_trace('No SQL statement given', $stack_trace_level);
      }
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
    $qc = 0;
    foreach ($queryString as $key => $val) {
      $qc++;
      if (''.@$val > '') {
        if (sqlWriteStatement($val)) {
          assertDataNonRO('sq:' . $val);
        }
      } else {
        dbiutils_stack_trace('No SQL statement given in array (item #' . $qc . ')', 0);
      }
    }
    if ($qc == 0) {
      dbiutils_stack_trace('No SQL statement given in array', 0);
    }
    foreach ($queryString as $key => $val) {
      if (sqlWriteStatement($val)) {
        assertDataNonRO('sqf:' . $val);
      }    
    }
  } else {
    if (''.@$queryString == '') {
      dbiutils_stack_trace('No SQL statement given', 0);
    }
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
      if (''.@$name == '') {
        dbiutils_stack_trace('Missing name in values array', 0);
      }
      $sql .= ' `' . $name . '` = ';
      if (gettype($val) == 'string') {
        $sql .= '"' . mes($val) . '"';
      } elseif (gettype($val) == 'array') {
        if (!is_string($val[0])) {
          dbiutils_stack_trace('When specified as an array (inline SQL fragment) value contained therein must be a string', 0);
        } else {
          $sql .= ''.@$val[0];
        }
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

function updateorinsert($table = '', $keyvalues = array(), $values = array(), $insertonlyvalues = false, $stack_trace_level = 0) {
  global $dbutils_show_errors;
  global $dbutils_history_callback;
  global $updateorinsert_inserted;
  global $dbutils_link;
  $i = false;
  $r = false;
  $allvalues = array_merge($keyvalues, $values);
  assertDataNonRO('updateorinsert:' . $table);

  if (''.@$table == '') {
    dbiutils_stack_trace('No table specified', $stack_trace_level);
    return false;
  }
  
  if (!is_array($keyvalues)) {
    dbiutils_stack_trace('Match criteria must be specified as an array', $stack_trace_level);
    return false;
  }
  
  if (count($keyvalues) == 0) {
    dbiutils_stack_trace('No match criteria specified', $stack_trace_level);
    return false;
  }

  if (!dbiutils_assert_connection(0)) {
    return false;
  }

  $updateorinsert_inserted = false;
  txBegin();
  if ( (!isset($keyvalues['id'])) || (0+@$keyvalues['id'] != 0) ) {
    // if the key value is based on an ID that is nonzero, or the key value is based on something other than ID:
    
    $whereclause = arraytosafe($keyvalues, true);
    if (''.@$whereclause == '') {
      dbiutils_stack_trace('Invalid match criteria', $stack_trace_level);
      txCancel();
      return false;
    }
    $sql = 'SELECT * FROM `' . $table . '` WHERE ' . $whereclause;
    if ($f = sqf($sql)) {
      $i = 0+@$f['id'];
      $r = true;
      $valuestoset = arraytosafe($allvalues);
      if ($valuestoset == '') {
        dbiutils_stack_trace('No values specified', $stack_trace_level);
        txCancel();
        return false;
      }
      $sql = 'UPDATE `' . $table . '` SET ' . $valuestoset . ' WHERE id = ' . $i;
      mysqli_query($dbutils_link, $sql);
      $errortext = mysqli_error($dbutils_link);
      if ($errortext > '') {
        if ($dbutils_show_errors) {
          dbiutils_stack_trace('MySQL: ' . $errortext . ' [QUERY: ' . $sql . ']', $stack_trace_level);
        }
        txCancel();
        return false;
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
    if (count($allvalues) == 0) {
      dbiutils_stack_trace('No values specified', $stack_trace_level);
      txCancel();
      return false;
    }
    $fieldnames = implode('`, `', array_keys($allvalues));
    if (''.@$fieldnames == '') {
      dbiutils_stack_trace('Error compiling list of field names for insert', $stack_trace_level);
      txCancel();
      return false;
    }
    $sql = 'INSERT INTO `' . $table . '` (`' . $fieldnames . '`) VALUES (';
    $first = true;
    foreach ($allvalues as $name => $val) {
      if (!$first) { $sql .= ', '; }
      if (gettype($val) == 'string') {
        $sql .= '"' . mes(''.@$val) . '"';
      } elseif (gettype($val) == 'array') {
        if (!is_string($val[0])) {
          dbiutils_stack_trace('When specified as an array (inline SQL fragment) value contained therein must be a string', $stack_trace_level);
        } else {
          $sql .= ''.@$val[0]; // raw expression
        }
      } else {
        $sql .= 0+@$val;
      }
      $first = false;
    }
    $sql .= ')';
    mysqli_query($dbutils_link, $sql);
    $error = mysqli_error($dbutils_link);
    if ($error > '') {
      if ($dbutils_show_errors) {
        dbiutils_stack_trace('MySQL: ' . $error . ' [QUERY: ' . $sql . ']', $stack_trace_level);
      }
      txCancel();
      return false;
    }
    $i = 0 + @mysqli_insert_id($dbutils_link);
    if ($i == 0) {
      dbiutils_stack_trace('No inserted record id was returned', $stack_trace_level);
      txCancel();
      return false;
    }
    $updateorinsert_inserted = true;
  }
  if ($dbutils_history_callback !== false) {
    @$dbutils_history_callback($table, $keyvalues);
  }
  if (txCommit()) {
    return $i;
  } else {
    return false;
  }
}

function update($table = '', $keyvalues = array(), $values = array(), $clauses = '', $stack_trace_level = 0) {
  global $dbutils_history_callback;
  global $dbutils_show_errors;
  global $dbutils_link;

  if (''.@$table == '') {
    dbiutils_stack_trace('No table specified', $stack_trace_level);
    return false;
  }
  
  if (!is_array($keyvalues)) {
    dbiutils_stack_trace('Match criteria must be specified as an array', $stack_trace_level);
    return false;
  }
  
  if (count($keyvalues) == 0) {
    dbiutils_stack_trace('No match criteria specified', $stack_trace_level);
    return false;
  }

  if (!dbiutils_assert_connection(0)) {
    return false;
  }

  assertDataNonRO('update:' . $table);
  $r = false;

  $valuestoset = arraytosafe($values);
  if ($valuestoset == '') {
    dbiutils_stack_trace('No values specified', $stack_trace_level);
    return false;
  }
  if ($dbutils_history_callback !== false) {
    txBegin();
  }

  $sql = 'UPDATE `' . $table
  . '` SET ' . $valuestoset
  . ' WHERE ' . arraytosafe($keyvalues, true);
  if (is_array($clauses)) {
    $clauses = ''.@qsafe($clauses, 1+@$stack_trace_level);
  }
  if ($clauses > '') {
    $sql .= ' ' . $clauses;
  }
  mysqli_query($dbutils_link, $sql);
  $error = mysqli_error($dbutils_link);
  if ($error > '') {
    if ($dbutils_show_errors) {
      dbiutils_stack_trace('MySQL: ' . $error . ' [QUERY: ' . $sql . ']', $stack_trace_level);
    }
    if ($dbutils_history_callback !== false) {
      txCancel();
    }
    return false;
  }

  $r = mysqli_affected_rows($dbutils_link);
  if ($dbutils_history_callback !== false) {
    $dbutils_history_callback($table, $keyvalues);
    if (!txCommit()) {
      // if the commit fails, the history callback's changes should be rolled back too.
      return false;
    }
  }
  return $r;
}

function insert($table = '', $values = array(), $stack_trace_level = 0) {
  global $dbutils_show_errors;
  global $dbutils_link;

  if (''.@$table == '') {
    dbiutils_stack_trace('No table specified', $stack_trace_level);
    return false;
  }

  if (!is_array($values)) {
    dbiutils_stack_trace('Insert values must be specified as an array', $stack_trace_level);
    return false;
  }
  
  if (count($values) == 0) {
    dbiutils_stack_trace('No insert values specified', $stack_trace_level);
    return false;
  }

  if (!dbiutils_assert_connection(0)) {
    return false;
  }

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
      if (!is_string($val[0])) {
        dbiutils_stack_trace('When specified as an array (inline SQL fragment) value contained therein must be a string', $stack_trace_level);
      } else {
        $sql .= $val[0]; // raw expression
      }
    } else {
      $sql .= 0+@$val;
    }
    $first = false;
  }
  $sql .= ')';
  @mysqli_query($dbutils_link, $sql);
  $error = @mysqli_error($dbutils_link);
  if ($error > '') {
    if ($dbutils_show_errors) {
      dbiutils_stack_trace('MySQL: ' . $error . ' [QUERY: ' . $sql . ']', $stack_trace_level);
    }
    return false;
  }
  return 0+@mysqli_insert_id($dbutils_link);
}

function deleteFrom($table, $keyvalues = array(), $limit = 0, $stack_trace_level = 0) {
  global $dbutils_show_errors;
  global $dbutils_link;

  assertDataNonRO('deleteFrom:' . $table);
  if (!is_array($keyvalues)) {
    $keyvalues = array('id' => 0+@$keyvalues);
  }

  if (''.@$table == '') {
    dbiutils_stack_trace('No table specified', $stack_trace_level);
    return false;
  }

  if (!is_array($keyvalues)) {
    dbiutils_stack_trace('Match criteria must be specified as an array', $stack_trace_level);
    return false;
  }
  
  if (count($keyvalues) == 0) {
    dbiutils_stack_trace('No match criteria specified', $stack_trace_level);
    return false;
  }

  if (!dbiutils_assert_connection(0)) {
    return false;
  }

  $sql = 'DELETE FROM `' . $table . '` '
  . ' WHERE ' . arraytosafe($keyvalues, true);
  if ($limit > 0) {
    $sql .= ' LIMIT ' . $limit;
  }
  mysqli_query($dbutils_link, $sql);
  $error = mysqli_error($dbutils_link);
  if ($error > '') {
    if ($dbutils_show_errors) {
      dbiutils_stack_trace('MySQL: ' . $error . ' [QUERY: ' . $sql . ']', $stack_trace_level);
    }
    return false;
  }
  return true;
}

function getSelectFrom($table, $fields, $keyvalues = array(), $clauses = '', $stack_trace_level = 0) {
  global $dbutils_show_errors;

  if (''.@$table == '') {
    dbiutils_stack_trace('No table specified', $stack_trace_level);
    return false;
  }

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
    $clauses = qsafe($clauses, 1+@$stack_trace_level);
  }
  if (count($keyvalues) > 0) {
    $qs = 'SELECT ' . $fields . ' FROM `' . $table . '` '
    . ' WHERE (' . arraytosafe($keyvalues, true) . ') ' . $clauses;
  } else {
    dbiutils_stack_trace('No match criteria specified', $stack_trace_level);
    return false; // changed from die() to be more consistent
  }
  return $qs;
}

function select($table, $fields, $keyvalues = array(), $clauses = '', $stack_trace_level = 0) {
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
  
  $q = getSelectFrom($table, $fields, $keyvalues, $clauses, 1+@$stack_trace_level);
  $f = sq($q, true, 1+@$stack_trace_level);
  $dbutils_selstack[] = array($q, $f, false);
  return $f;
}

function selecta($table, $fields, $keyvalues = array(), $clauses = '', $stack_trace_level = 0) {
  $f = select($table, $fields, $keyvalues, $clauses, 1+@$stack_trace_level);
  selectClose();
  return $f;
}

function selectRow($stack_trace_level = 0) {
  global $dbutils_selstack;
  $qq = count($dbutils_selstack) - 1;
  if ($qq >= 0) {
    $dbutils_selstack[$qq][2] = true;
    if (isset($dbutils_selstack[$qq][1])) {
      $f = $dbutils_selstack[$qq][1];
      unset($GLOBALS['dbutils_selstack'][$qq][1]); // remove preloaded item
      return $f;
    } else {
      $f = sq($GLOBALS['dbutils_selstack'][$qq][0], true, 1+@$stack_trace_level);
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
  global $dbutils_tx_failure_exception;
  assertDataNonRO('txBegin');
  if (0+@$dbutils_txcount == 0) {
    if (!mysqli_query($dbutils_link, 'START TRANSACTION')) {
      if ($dbutils_tx_failure_exception) {
        throw new Exception('MySQL failed to start transaction: ' . @mysqli_error($dbutils_link));
      } else {
        dbiutils_stack_trace('MySQL failed to start transaction: ' . @mysqli_error($dbutils_link), 0);
        die(); // anything else would leave a dangerous non-transactional mode where unintended queries might be executed
      }
    }
  }
  $dbutils_txcount++;
}

function txCommit() {
  global $dbutils_txcount;
  global $dbutils_link;
  global $dbutils_tx_failure_exception;
  assertDataNonRO('txCommit');
  $dbutils_txcount--;
  if (0+@$dbutils_txcount == 0) {
    $r = mysqli_query($dbutils_link, 'COMMIT');
    if ($r) {
      return true;
    } else {
      if ($dbutils_tx_failure_exception) {
        throw new Exception('MySQL failed to commit transaction: ' . @mysqli_error($dbutils_link));
      } else {
        dbiutils_stack_trace('MySQL failed to commit transaction: ' . @mysqli_error($dbiutils_link), 0);
        return false;
      }
    }
  } else {
    return true; // there was no transaction open so this is okay per our definition (extra commits are fine)
  }
}

function txCancel() {
  global $dbutils_txcount;
  global $dbutils_link;
  global $dbutils_tx_failure_exception;
  assertDataNonRO('txCancel');
  $dbutils_txcount--;
  if (0+@$dbutils_txcount <= 0) {
    if (!mysqli_query($dbutils_link, 'ROLLBACK')) {
      if ($dbutils_tx_failure_exception) {
        throw new Exception('MySQL failed to rollback transaction: ' . @mysqli_error($dbutils_link));
      } else {
        dbiutils_stack_trace('MySQL failed to rollback transaction: ' . @mysqli_error($dbutils_link), 0);
        die(); // anything else would leave a dangerous open transaction mode where assumed to be committed queries would be left uncommitted.
      }
    }
    return true;
  } else {
    if ($dbutils_tx_failure_exception) {
      throw new Exception('No known transaction is active');
    } else {
      dbiutils_stack_trace('No known transaction is active', 0);
      die(); // something pretty bad happened
    }
  }
}

function setDataLink($link, $readonly = false) {
  global $dbutils_link;
  global $dbutils_readonly;
  $dbutils_link = $link;
  $dbutils_readonly = $readonly;
}

function insertorupdate($table, $keyvalues, $values = array(), $insertonlyvalues = false) {
  return updateorinsert($table, $keyvalues, $values, $insertonlyvalues, 1);
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
    $query = new Q($query, 1);
  }
  return $query->error();
}

function dbutils_close($link) {
  @mysqli_close($link);
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
      throw new Exception($error); // was die();
    }
  } else {
    return $dbconn;
  }
}

function qsafe($qs, $stack_trace_level = 0) {
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
          dbiutils_stack_trace('Malformed query', $stack_trace_level);
          if ($dbiutils_die_if_malformed) {
            die();
          } else {
            return false;
          }
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
          $s .= (0+@$v);
        } else {
          dbiutils_stack_trace('Malformed query', $stack_trace_level);
          if ($dbiutils_die_if_malformed) {
            die();
          } else {
            return false;
          }
        }
      }
    }
  }
  return $s;
}
