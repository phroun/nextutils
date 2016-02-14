<?php

include('baseutils.php');
include('dbiutils.php');

echo 'included';

$baseutils_errors_visible = false;
set_error_handler('baseutils_errorHandler');

function things() {
  echo 'set error handler';

  sqlWriteStatement('');

  echo 'write statement';

  echo mes('This is a thing');

  echo 'mes';
}

things();
