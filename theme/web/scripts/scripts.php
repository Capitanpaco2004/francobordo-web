<?php
// Librerias
use util\event;
use util\minify\Minify;

?>
<link rel="stylesheet" href="//ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/themes/smoothness/jquery-ui.min.css" />
<?php

// Minify
echo Minify::getInstance()->css();

event::getInstance()->execute( 'header_add_meta' );
