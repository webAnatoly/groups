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
    $allProducts = getAllCatProducts();

    echo "<pre>";
    print_r(getGroup($allProducts, $_GET['group']));
    echo "</pre>";

    $subProducts = getGroup($allProducts, $_GET['group']);

    echo createUl($subProducts);

    echo "<ul>";
    echo createSimpleLi($subProducts);
    echo "</ul>";

} else {
    $products = getAllCatProducts();
    echo createUl($products, 0);
    echo "<h3>Пока не выбрана ни одна группа – выводится список всех товаров.</h3>";
    echo "<ul>";
    echo createSimpleLi($products);
    echo "</ul>";
}