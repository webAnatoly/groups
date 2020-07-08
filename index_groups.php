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

//    echo "<pre>";
//    print_r(getGroup($allProducts, $_GET['group']));
//    echo "</pre>";

    $categories = getCategories((int) $_GET['group']);
//    $categories = array_reverse($categories);
    $categories = $categories;


    $parentCats = getParentCategories($_GET['group']);

    echo "<ul>";
    foreach(array_reverse($parentCats) as $li) {
        echo $li;
    }
    echo "</ul>";

    var_dump($categories);

//    echo "<ul>";
//    foreach($categories as $li) {
//        echo $li;
//    }
//    echo "</ul>";

    $subProducts = getGroup($allProducts, $_GET['group']);

//    echo createUl($subProducts);

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