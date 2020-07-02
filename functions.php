<?php

/**
 * Достает из базы все категории с товарами, подкатегории с товарами, складывает всё в многомерный массив и возвращает его.
 *
 * @param int $id_parent идентификатор категории
 * @param array $tmp вспомогательный параметр для рекурсивного вызова
 *
 * @return array
 */
function getAllCatProducts(int $id_parent = 0, array $tmp = array())
{

    global $mysqli;
    $result = array();

    if ($stmt = $mysqli->prepare('SELECT id, id_parent, name FROM `groups` WHERE id_parent=?')) {

        $stmt->bind_param('d', $id_parent);
        $stmt->execute();
        $groups = $stmt->get_result();

        if ($groups->num_rows > 0) {
            while ($group_row = $groups->fetch_assoc()) {

                // рекурсивно вызываем эту же функцию и в качестве id_parent передаем ей id текущей категории
                $result[$group_row['name']] = getAllCatProducts($group_row['id'], $result);

                // сохраняем id группы во вложенный массив meta, чтобы можно было его потом использовать в случае надобности
                $result[$group_row['name']]['meta']['id_group'] = $group_row['id'];

                // Для текущей категории выбрать принадлежащие ей products (если они есть)
                if ($stmt2 = $mysqli->prepare('SELECT id, id_group, name FROM `products` WHERE id_group=?')) {
                    $stmt2->bind_param('d', $group_row['id']);
                    $stmt2->execute();
                    $products = $stmt2->get_result();
                    if ($products->num_rows > 0) {
                        while ($product_row = $products->fetch_assoc()) {
                            $result[$group_row['name']][] = $product_row['name'];
                        }
                    }
                    // Сохраняем кол-во товаров в данной группе в массив meta
                    $result[$group_row['name']]['meta']['product_amount'] = (int) $products->num_rows;
                }
            }
        }
        $stmt->close();
    }
    return $result;
}

/**
 * Функция для вывода категорий
 * @param array $data многомерный массив категорий и товаров
 * @param int $level уровень вложенности, по умолчанию 0, т.е. выводятся только категории самого верхнего уровня
 * @param string $tmp вспомогательный параметр для рекурсивного вызова
 *
 * @return string возвращает <ul> список
 */
function createUl(array $data = array(), int $level = 0, string $tmp = "") {
    $result = "<ul>";

    if ($level === 0) {
        foreach ($data as $key=>$value) {
            $result .= '<li><a href="'. $_SERVER['HTTP_HOST'] . '/groups/index_groups.php?group=0' .'">' . $key . '</a></li>';
        }
    }

//    foreach ($data as $key=>$value) {
//        var_dump($key);
//        if (is_array($value) && $key !== "meta") { // если массив то рекурсивно вызываем
//            $result .= createUl($value, 0, $result);
//        } else {
//            $result .= "<li>" . $value . "</li>";
//        }
//    }

    return $result . "</ul>";
}

/**
 * Функция подсчета кол-ва товаров в массиве и во всех вложенных массивах
 * @param array $products многомерный массив с продуктами,
 * где ключ массива это название категории и в каждой категории кроме продуктов должен лежать служебный массив "meta"
 *
 * @param int $counter вспомогательный параметр для рекурсивного подсчета кол-ва всех продуктов.
 *
 * @return int $amount общее кол-во продуктов во всех вложенных массивах
 */
function countChildProducts(array $products = array(), int $counter = 0) {

    // Если пустой массив возвращаем ноль
    if (empty($products)) {
        return 0;
    }

    foreach ($products as $key=>$value) {
        if (is_array($value)) {

            // Если в подмассиве meta указано кол-во продуктов, то прибавлеем их к $counter
            if ($key === 'meta' && (isset($products['meta']['product_amount']))) {
                $counter += $products['meta']['product_amount'];
            }

            // Рекурсивный вызов
            $counter = countChildProducts($value, $counter);

        }
    }
    return $counter;
}