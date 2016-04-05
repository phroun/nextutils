<?php

$x = 2;

echo 'hello' . 0+@$x;

echo "includes\r\n";
include('baseutils.php');
include('dbiutils.php');

$baseutils_errors_visible = false;

echo "set error handler\r\n";
set_error_handler('baseutils_errorHandler');

function things() {

  echo "write statement\r\n";
  sqlWriteStatement('');

  echo "mes\r\n";
  echo mes('This is a thing');

}

things();
