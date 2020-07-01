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
