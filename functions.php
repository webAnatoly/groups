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
                $result[$group_row['name']]['meta']['id_parent'] = $group_row['id_parent'];

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
 * Функция обходит многомерный массив и на его основе создает многомерный ul список.
 * @param array $data
 * @param int $stop_lvl уровень вложенности до которого нужно отображать дерево категорий, по умолчанию отображается всё дерево
 * @param int $current_lvl вспомогательный параметр, хранящий текущи уровень вложенности дерева
 * @param string $str вспомогательный параметр для рекурсии
 * @return string
 */
function createUl(array $data = array(), int $stop_lvl = 999, string $str = "", int $current_lvl = 0) {

    $result = "<ul>" . $str;

    foreach ($data as $key=>$value) {

        if (is_array($value) && $key !== 'meta') {

            if($current_lvl > $stop_lvl) {
                // Если текущий уровень вложенности больше стоп уровня, то ничего не выводим.
                continue;
            };

            $result .= '<li><a href="index_groups.php?group='
                . $value['meta']['id_group'] . '">' . $key . '</a><span> - '
                . countChildProducts($value) . '</span>' // Подсчет кол-ва товаров в текущей категории и в дочерних
                . '<span>' . ' current_level: ' . $current_lvl . '</span>'
                . createUl($value, $stop_lvl, $str, $current_lvl += 1) . '</li>'; // Рекурсивный вызов для вложенных массивов
            $current_lvl -= 1;

        } else if ($key === 'meta') {
            continue; // массив с метаданными не включаем в список
        } else {
            $result .= '<li>' . $value . '</li>';
        }
    }

    return $result . "</ul>";
}

/**
 * Выбирает из многомерного массива все продукты в один одномерный список, оборачивает каждый продукт в <li> тег.
 * @param array $data
 * @return string строка вида <li>продукт</li><li>продукт</li>
 */
function createSimpleLi(array $data = array())
{

    $result = "";
    if (empty($data)) {
        return "";
    }

    foreach ($data as $key=>$value) {
        if (is_array($value) && $key !== 'meta') {
            $result .= createSimpleLi($value);
        } else if($key === "meta") { // массив с метаданными пропускаем
            continue;
        } else {
            $result .= "<li>" . $value . "</li>";
        }
    }

    return $result;
}

/**
 * Функция подсчета кол-ва товаров в массиве и во всех вложенных массивах
 * @param array $products многомерный массив с продуктами,
 * где ключ массива это название категории и в каждой категории кроме продуктов должен лежать служебный массив "meta"
 *
 * @param int $counter вспомогательный параметр для рекурсивного подсчета кол-ва всех продуктов.
 *
 * @return int $counter общее кол-во продуктов во всех вложенных массивах
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