<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/groups/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/groups/functions.php';
$mysqli = new mysqli($db['host'], $db['username'], $db['passwd'], $db['dbname']);

/* check connection */
if (mysqli_connect_errno()) {
    printf('Database connection error');
    exit();
}

if (isset($_GET['group']) && is_numeric($_GET['group']) && $_GET['group'] > 0) {
    echo "do complicated works";
} else {
    echo "<pre>";
    print_r(getAllCatProducts(0));
    echo "</pre>";
}