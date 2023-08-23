<?php
    /**
     * Parses $GLOBALS['argv'] for parameters and assigns them to an array.
     *
     * Supports:
     * -abc
     * -e
     * -e<value>
     * -e=<value>
     * -e <value>
     * --long-param
     * --long-param=<value>
     * --long-param <value>
     * -- end of params, begin positional arguments
     * <value>
     *
     * @param array $params Arguments to parse if not the default $GLOBALS['argv']
     * @param array $noopt List of parameters without values
     */
    function parseParameters($params = false, $noopt = array()) {
        $result = array();
        if ($params === false) {
          $params = $GLOBALS['argv'];
        }
        $pname = '';
        // could use getopt() here (since PHP 5.3.0), but it doesn't work relyingly
        $complete = false;
        $result[0] = array($params[0]);
        array_shift($params);
        $result['*'] = array();
        $value = true; // true=already recorded a value
        foreach ($params as $tmp => $p) {
            $param = false;
            $pval = '';
            if (!$complete) {
                if (substr($p, 0, 2) == '--') {
                    $param = true;
                    $value = false;
                    $pname = $p;
                    // long-opt (--<param>)
                    if ($pname == '--') {
                      $param = true;
                      $value = true;
                      $complete = true;
                    } else {
                        if (strpos($p, '=') !== false) {
                            // value specified inline (--<param>=<value>)
                            $pv = explode('=', $p, 2);
                            $pname = $pv[0];
                            $pval = $pv[1];
                            $value = true;
                        }
                    }
                } else if (substr($p, 0, 1) == '-') {
                    $param = true;
                    $value = false;
                    $pname = substr($p, 1);
                    if (strlen($pname) > 1) {
                        while (in_array(substr($pname, 0, 1), $noopt, true)) {
                          $first = substr($pname, 0, 1);
                          $pname = substr($pname, 1);
                          if (!isset($result[$first])) {
                            $result[$first] = array();
                          }
                        }
                        $pval = substr($pname, 1);
                        $pname = substr($pname, 0, 1);
                        if (strlen($pval) > 0) {
                            if (substr($pval, 0, 1) == '=') {
                              $pval = substr($pval, 1);
                            }
                        }
                        $value = true;
                    }
                } else { // no dashes
                    if (!$value) {
                        $result[$pname][] = $p;
                        $value = true;
                    } else {
                        $result['*'][] = $p;
                    }
                }
                if ($param && (!$value) && (in_array($pname, $noopt, true))) {
                    if (!isset($result[$pname])) {
                        $result[$pname] = array();
                    }
                    $param = false;
                    $value = true; // no option
                }
                if (!$complete) { // (avoid adding the -- itself)
                    if ($param) {
                        if (!isset($result[$pname])) {
                            $result[$pname] = array();
                        }
                        if ($value) {
                            $result[$pname][] = $pval;
                            $value = true;
                        }
                    } else if (!$value) {
                        $result['*'][] = $p;
                    }
                }
            } else {
              $result['*'][] = $p;
            }
        } // loop
        return $result;
    }

