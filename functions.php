<?php

/**
 * Достает из базы все категории с товарами, подкатегории с товарами, складывает всё в многомерный массив и возвращает его.
 *
 * @param int $id_parent идентификатор категории
 * @param array $tmp вспомогательный параметр для рекурсивного вызова
 *
 * @return array
 */
function getAllCatProducts(int $id_parent = 0, int $stop_lvl = 999, array $tmp = array(), int $current_lvl = 0)
{

    global $mysqli;
    $result = array();

    if ($stmt = $mysqli->prepare('SELECT id, id_parent, name FROM `groups` WHERE id_parent=?')) {

        $stmt->bind_param('d', $id_parent);
        $stmt->execute();
        $groups = $stmt->get_result();

        if ($groups->num_rows > 0) {
            while ($group_row = $groups->fetch_assoc()) {

                // Условие глубины выборки. Если функции был передан $stop_lvl = 0, то функция вернет одномерный массив,
                // если $stop_lvl = 1, то двумерный и т.д. По умолчанию $stop_lvl = 999
                if (!($stop_lvl >= $current_lvl)) {
                    continue;
                }

                /* Рекурсивно вызываем эту же функцию и в качестве id_parent передаем ей id текущей категории
                $current_lvl увеличиваем на единицу и тоже передаем, а для текущего вызова $current_lvl уменьшаем обратно на единицу */
                $result[$group_row['name']] = getAllCatProducts($group_row['id'], $stop_lvl, $result, $current_lvl += 1);
                $current_lvl -= 1;

                // сохраняем id группы во вложенный массив meta, чтобы можно было его потом использовать в случае надобности
                $result[$group_row['name']]['meta']['id_group'] = $group_row['id'];
                $result[$group_row['name']]['meta']['id_parent'] = $group_row['id_parent'];
                $result[$group_row['name']]['meta']['level_in_tree'] = $current_lvl;

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

function getCategories(int $id_group = 0, $result = '')
{
    global $mysqli;
    $id = $id_group;

    // Получить группу
    if ($stmt = $mysqli->prepare('SELECT id, id_parent, name FROM `groups` WHERE id=?')) {
        $stmt->bind_param('d', $id);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($f_id, $f_id_parent, $f_name);
        $stmt->fetch();
        $result .= '<li><a href="index_groups.php?group='. $id_group .'">' . $f_name . ' <span> '. countChidren($id_group) .'</span></a></li>';
        $stmt->close();
    }

    // Получить подгруппы
    if ($stmt = $mysqli->prepare('SELECT id, id_parent, name FROM `groups` WHERE id_parent=?')) {
        $stmt->bind_param('d', $f_id);
        $stmt->execute();
        $groups = $stmt->get_result();

        if ($groups->num_rows > 0) {
            while ($group_row = $groups->fetch_assoc()) {
                $result =  getCategories((int) $group_row['id'], $result); // рекурсия
            }
        }
        $stmt->close();
    }

    return $result;
}


/**
 * Достает из базы родительские категории от текущей категории, до самой верхней родительской категории
 * @param int $id_group
 * @param array $result
 * @return array
 */
function getParentCategories(int $id_group = 0, $result = array())
{
    global $mysqli;

    if ($id_group == 0) {
        return $result;
    }

    // Получить группу
    if ($stmt = $mysqli->prepare('SELECT id, id_parent, name FROM `groups` WHERE id=?')) {
        $stmt->bind_param('d', $id_group);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($f_id, $f_id_parent, $f_name);
        $stmt->fetch();
        $result[] = '<li><a href="index_groups.php?group='. $id_group .'">' . $f_name . ' <span> '. countChidren($id_group) .'</span></a></li>';
        $stmt->close();
    }


    // Получить родительскую группу
    if (isset($f_id_parent) && $f_id_parent > 0) {
        $result = getParentCategories((int) $f_id_parent, $result); // рекурсия
    }

    return $result;
}

/**
 * Функция обходит многомерный массив и на его основе создает многомерный ul список.
 * @param array $data
 * @param int $stop_lvl уровень вложенности до которого нужно отображать дерево категорий, по умолчанию отображается всё дерево
 * @param bool $showProducts показывать ли список категорий с товарами или без. По умолчанию просто список категорий
 *
 * @param int $current_lvl вспомогательный параметр, хранящий текущи уровень вложенности дерева
 * @param string $str вспомогательный параметр для рекурсии
 * @return string
 */
function createUl(array $data = array(), int $stop_lvl = 999, bool $showProducts = false, string $str = "", int $current_lvl = 0) {

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
                . createUl($value, $stop_lvl, $showProducts, $str, $current_lvl += 1) . '</li>'; // Рекурсивный вызов для вложенных массивов
            $current_lvl -= 1;

        } else if ($key === 'meta') {
            continue; // массив с метаданными не включаем в список
        } else if ($showProducts) {
            $result .= '<li>' . $value . '</li>';
        }
    }

    return $result . "</ul>";
}

/**
 * @param array $data
 * @param int $id_group
 * @param string $catName
 * @return string возвращает имя категории
 */
function getCatName(array $data, int $id_group, string $catName = '')
{

    $catName = '';
    foreach ($data as $key=>$value) {
        if (isset($value['meta']['id_group']) && $value['meta']['id_group'] === $id_group) {
            return $key;
        } else if (is_array($value) && $key !== 'meta'){
            $catName .= getCatName($value, $id_group, $catName);
        }
    }
    return $catName;
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

/**
 * Получает id группы и рекурсивно подсчитывает кол-во товаров в группе и во всех вложенных подгруппах
 * @param int $id_group
 * @return int
 */
function countChidren ($id_group = 0)
{
    global $mysqli;
    $result = 0;

    // Посчитать кол-во продуктов для текущей группы
    if ($stmt = $mysqli->prepare('SELECT COUNT(id) FROM products WHERE id_group=?')) {

        $stmt->bind_param('d', $id_group);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $result += isset($count) ? (int) $count : 0;
        $stmt->close();
    }

    // Получить вложенные подгруппы и посчитать кол-во продуктов в них
    if ($stmt = $mysqli->prepare('SELECT id, id_parent, name FROM `groups` WHERE id_parent=?')) {

        $stmt->bind_param('d', $id_group);
        $stmt->execute();
        $groups = $stmt->get_result();

        if ($groups->num_rows > 0) {
            while ($group_row = $groups->fetch_assoc()) {
                $result += countChidren($group_row['id']);
            }
        }
        $stmt->close();
    }
    return $result;
}

/**
 * Выбирает из многомерного массива продукты по id_group
 * @param array $products
 * @param int $id_group
 * @param array $tmp вспомогательный массив для рекурсии
 * @return array
 */
function getGroup(array $products, int $id_group, array $tmp = []) {

    foreach ($products as $key=>$value) {
        if (isset($value['meta']['id_group']) && $value['meta']['id_group'] === $id_group) {
            $tmp[$key] = $value;
        } else if (is_array($value) && $key !== 'meta') {
            $tmp = getGroup($value, $id_group, $tmp);
        }
    }

    return $tmp;
}