<?php
use aic\models\Model;
use aic\models\User;

// Error reporting
// error_reporting(0);   // Product environment, reporting nothing
// error_reporting(E_ERROR | E_PARSE); // Avoid E_WARNING, E_NOTICE, etc
error_reporting(E_ALL); // Development environment, reporting all

Model::setConnInfo( [
    'host' => "localhost", 
    'usename' => "root", 
    'password' => "", 
    'dbname' => "aic_rsv",
]);

