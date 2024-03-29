<?php
// ###########################################################################
// dbutils.php:  Utilities for PHP Database Development with MySQL
// ===========================================================================
// Version 2020-02-01.  See README.md
// ###########################################################################

function updateDatabase($action = 'check', $newSchema = false) {

  function applyChange($apply, $change) {
    if ($apply) {
      return 'Applying : ' . $change . "\r\n";
      sqx($change);
    } else {
      return $change . ";\r\n";
    }
  }

  function describeColumn($data) {
    $opts = explode(':', $data);
    $typedata = explode('(', $opts[0]);
    $type = strtolower($typedata[0]);
    $sq = $opts[0];
    if (''.@$opts[1] == 'NO') {
      $sq .= ' NOT NULL';
    }
    $default = str_replace('~', ':', $opts[3]);
    if (($type == 'varchar')
    || ($type == 'char')
    || ($type == 'date')
    || ($type == 'time')
    || ($type == 'datetime')
    || ($type == 'timestamp')
    || ($type == 'enum')
    ) {
      $sq .= " DEFAULT '" . mes($default) . "'";
    } elseif ($opts[3] != '') {
      $sq .= ' DEFAULT ' . $default;
    }
    if ($opts[4] == 'auto_increment') {
      $sq .= ' AUTO_INCREMENT';
    }
    return $sq;
  }

  $out = '';
  $fdbname = sqf('SELECT database() AS dbname');
  $dbname = ''.@(string)$fdbname['dbname'];
  $qst = 'SHOW TABLES';
  $tables = array();
  $indices = array();
  $struct = '';
  $renameTables = array();
  $renameContent = array();

  while ($tab = sq($qst)) {
    foreach ($tab as $k => $table) {
      $qs = 'DESCRIBE `' . $table . '`';
      $tables[$table] = array();
      while ($fr = sq($qs)) {
        $field = $fr['Field'];
        $def = $fr['Type']
        . ':' . $fr['Null'] . ':' . $fr['Key']
        . ':' . str_replace(':', '~', $fr['Default']) . ':' . $fr['Extra'];
        $tables[$table][$field] = $def;
        $struct .= $table . ':' . $field . ':' . $def . "\r\n";
      }
      $qs = 'SHOW INDEXES FROM `' . $table . '`';
      $keys = array();
      $keyTypes = array();
      while ($fx = sq($qs)) {
        $keyname = $fx['Key_name'];
        if (!isset($keys[$keyname])) {
          $keys[$keyname] = array();
          $keyTypes[$keyname] = '';
          if (0+(int)@$fx['Non_unique'] == 0) {
            $keyTypes[$keyname] = 'UNIQUE';
          }
        }
        $keys[$keyname][$fx['Seq_in_index']] = $fx['Column_name'];
      }
      foreach ($keys as $kn => $kd) {
        if (!isset($indices[$table])) {
          $indices[$table] = array();
        }
        $index = $keyTypes[$kn] .':' . implode(',', $kd);
        $struct .= $table . '::' . $kn . ':' . $index . "\r\n";
        $indices[$table][$kn] = $index;
      }
    }
  }

  if (!isset($tables['global'])) {
    $out .= "# Missing `global` table.  Has the server installation been completed?\r\n";
    return array(100, $out);
  }

  if (!isset($tables['global']['schema'])) {
    $schema = 'none';
  } else {
    $fglob = selecta('global', 1);
    $schema = $fglob['schema'];
  }

  if ($newSchema) {
    $newTables = array();
    $newIndices = array();
    $lines = explode("\n", $newSchema);
    $foundVer = false;
    foreach ($lines as $line) {
      $line = trim($line);
      if ((strlen($line) != 0) &&
      (substr($line, 0, 1) != '#')) {
        $parts = explode(':', $line);
        if (count($parts) >= 3) {
          $table = ''.(string)@$parts[0];
          if ($table == '') {
            if ($parts[1] == 'schema') {
              if ($foundVer) {
                $out .= "# ERROR: Schema specified twice?\r\n";
                return array(101, $out);
              }
              $newSchema = $parts[2];
              $foundVer = true;
            } elseif ($parts[1] == 'rename') {
              if (($parts[3] == '') && ($parts[4] == '')) {
                $renameTables[$parts[2]] = $parts[4];
              } else {
                $renameContent[$parts[2] . ':' . $parts[3]] = $parts[4];
              }
            }
          } else {
            if (!isset($newTables[$table])) {
              $newTables[$table] = array();
            }
            if ($parts[1] == '') {
              $keyname = ''.(string)@$parts[2];
              $newIndices[$table][$keyname] = $parts[3] . ':' . $parts[4];
            } else {
              $field = ''.(string)@$parts[1];
              $newTables[$table][$field] = $parts[2] . ':' . $parts[3] . ':' . $parts[4] . ':' . $parts[5] . ':' . $parts[6];
            }
          }
        }
        //
      }
    }

    $apply = false;
    if ($foundVer) {
      $out .= '# Operating on database: ' . $dbname . "\r\n";
      $out .= '# Existing Schema: ' . $schema . "\r\n";
      $out .= '# Into New Schema: ' . $newSchema . "\r\n";
      $upgradeError = false;

      if ($schema != $newSchema) {
        if ($action == 'apply') {
          $apply = true;
          $out .= "# Applying changes.\r\n";
        } else {
          $out .= "# This is only a preflight test.\r\n";
        }
      } else {
        if ($action == 'reapply') {
          $apply = true;
          $out .= "# Re-applying changes in spite of same schema identifier.\r\n";
        } else {
          $out .= "# Schema is already up-to-date.  Checking consistency.\r\n";
        }
      }

      foreach ($renameTables as $kr => $kn) {
        if (isset($tables[$kr])) {
          if (!isset($tables[$kn])) {
            if (strtoupper($kn) != strtoupper($kr)) {
              $out .= '# Renaming table ' . $kr . ' to ' . $kn . "\r\n";
              $tables[$kn] = $tables[$kr];
              $indices[$kn] = $indices[$kr];
              $out .= applyChange($apply, 'RENAME TABLE `' . $kr . '` TO `' . $kn . '`');
              unset($tables[$kr]);
              unset($indices[$k]);
            }
          } else {
            $out .= '# I am supposed to rename table ' . $kr . ' to ' . $kn . ', but ' . $kn . ' already exists!' . "\r\n";
            $upgradeError = true;
          }
        }
      }

      $createTables = array();
      $deleteTables = array();
      foreach ($newTables as $tn => $td) {
        if (!isset($tables[$tn])) {
          $createTables[$tn] = true;
        }
      }
      foreach ($tables as $tn => $td) {
        if (!isset($newTables[$tn])) {
          $deleteTables[$tn] = true;
          $qs = 'SHOW TABLES LIKE "' . $tn . '"';
          $found = false;
          if ($fs = sqf($qs)) {
            $qs = 'SELECT count(TRUE) AS `rows` FROM `' . $tn . '` WHERE TRUE';
            try {
              $fcount = sqf($qs);
            } catch (Exception $e) {
              $fcount['rows'] = -1;
            }
            $rows = 0+(int)@$fcount['rows'];
            $out .= '# I am supposed to delete table ' . $tn . ' which has ' . $rows . ' ';
            if ($rows == 1) { $out .= 'row'; } else { $out .= 'rows'; }
            $out .= ".\r\n";
            $out .= applyChange($apply, 'DROP TABLE `' . $tn . '`');
          } else {
            $out .= '# I am supposed to delete table ' . $tn . ' which does not exist.';
            $out .= "\r\n";
          }
          unset($tables[$tn]);
          unset($indices[$tn]);
        }
      }

      foreach ($createTables as $tn => $td) {
        if (!isset($tables[$tn])) {
          $sq = 'CREATE TABLE `' . $tn . "` (\r\n";
          $inds = '';
          $first = true;
          foreach ($newTables[$tn] as $field => $data) {
            $opts = explode(':', $data);
            if (!$first) { $sq .= ",\r\n"; }
            $first = false;
            $sq .= '  `' . $field . '` ';
            $sq .= describeColumn($data);
            if ($opts[2] == 'PRI') {
              $inds .= ",\r\n" . '  PRIMARY KEY (`' . $field . '`)';
            }
          }
          foreach ($newIndices[$tn] as $key => $data) {
            if ($key != 'PRIMARY') {
              $inds .= ",\r\n" . '  INDEX `' . $key . '` (';
              $parts = explode(':', $data);
              $fields = explode(',', $parts[1]);
              $ff = true;
              foreach ($fields as $field) {
                if (!$ff) { $inds .= ', '; };
                $ff = false;
                $inds .= '`' . $field . '`';
              }
              $inds .= ')';
            }
          }
          $sq .= $inds;
          $sq .= "\r\n) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci\r\n";
          $out .= applyChange($apply, $sq);
        }
      }

      foreach ($renameContent as $rk => $name) {
        $parts = explode(':', $rk);
        $table = $parts[0];
        $field = $parts[1];
        if ($table != '') {
          if ($field != '') {
            if (isset($tables[$tn][$name])) {
              $out .= '# I am supposed to rename column ' . $table . '.' . $field . ' to ' . $name . ' but it alraedy exists.';
              $out .= "\r\n";
            } else {
              $sq = 'ALTER TABLE `' . $table . '` RENAME COLUMN `' . $field . '` TO `' . $name . '`';
              $out .= applyChange($apply, $sq);
              $tables[$tn][$name] = $tables[$tn][$field];
              unset($tables[$tn][$field]);
            }
          }
        }
      }

      foreach ($newTables as $tn => $td) {
        $otd = $tables[$tn];
        $position = 'FIRST';
        foreach ($td as $field => $opts) {
          if (isset($otd[$field])) {
            $oopts = $otd[$field];
            if ($opts != $oopts) {
              $out .= '# Field options for ' . $tn . '.' . $field . ' differ:' . "\r\n";
              $out .= '#   ' . $oopts . ' => ' . $opts . "\r\n";
            }
          } else {
            $out .= '# Field missing: ' . $tn . '.' . $field . "\r\n";
            $sq = 'ALTER TABLE `' . $tn . '` ADD COLUMN `' . $field . '` ';
            $sq .= describeColumn($opts);
            $sq .= ' ' . $position;
            $out .= applyChange($apply, $sq);
          }
          $position = 'AFTER `' . $field . '`';
        }
        foreach ($otd as $field => $oopts) {
          $found = false;
          if (!isset($td[$field])) {
            $out .= '# I need to delete ' . $field . ' from ' . $tn . ".\r\n";
            $sq = 'ALTER TABLE `' . $tn . '` DROP COLUMN `' . $field . '`';
            $out .= applyChange($apply, $sq);
          }
        }
      }
      if ($apply && ($newSchema != $schema)) {
        try {
          update('global', array('id' => array('TRUE')), array('schema' => $newSchema));
        } catch (Exception $e) {
          $out .= "This database is missing the `schema` field in the `global` table.\r\n";
        }
      }
    } else {
      $out .= "Version not specified.\r\n";
      return array(102, $out);
    }

  } else {
    $out .= '# Generated by updateDatabase Tool' . "\r\n";
    $out .= "\r\n";
    $out .= ':database:' . $dbname . "\r\n";
    $out .= ':schema:' . $schema . "\r\n";
    $out .= $struct;
  }
  return array(0, $out);
}
