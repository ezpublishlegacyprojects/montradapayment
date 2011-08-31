<?php

$Module = array( 'name' => 'Montrada Module',
                 'variable_params' => true );

$ViewList = array();

$ViewList['notificate'] = array( 'functions' => array( 'notificate' ),
                                 'script' => 'notificate.php');

$ViewList['redirector'] = array( 'functions' => array( 'redirector' ),
                                 'script' => 'redirector.php');

$FunctionList['notificate'] = array();
$FunctionList['redirector'] = array();



?>
