<?php

require "includes/application_top.php";

$sql = 'SELECT prp.pop_products_id_master, pd.products_name, prp.pop_products_id_slave FROM products_related_products prp LEFT JOIN products_description pd ON pd.products_id = prp.pop_products_id_master AND pd.language_id = 3';
$sql = tep_db_query($sql);
$related = array();

while ($product = tep_db_fetch_array($sql)) {
    if ($product['products_name'] != '') {
        $related[$product['products_name']]['pop_products_id_slave'][] = $product['pop_products_id_slave'];
        $related[$product['products_name']]['pop_products_id_master'] = $product['pop_products_id_master'];
    }
}

foreach ($related as $name => $value) {

    $related_groups_id = 0;

    $sql = sprintf(
        'SELECT related_groups_id FROM related_products_groups WHERE idproducts = "%d"',
        $value['pop_products_id_master']
    );
    $sql = tep_db_query($sql);

    if (tep_db_num_rows($sql)) {
        $related = tep_db_fetch_array($sql);
        $related_groups_id = $related['related_groups_id'];
    } else {
        tep_db_perform(
            'related_products_groups',
            array(
                'related_title' => $name,
                'related_status' => 1,
                'idproducts' => $value['pop_products_id_master'],
                'created_by_script' => 1,
                'idcategories' => '',
                'idbrands' => '',
            )
        );

        $related_groups_id = tep_db_insert_id();
    }

    if ($related_groups_id > 0) {

        tep_db_perform(
            'related_products_related',
            array(
                'idproducts' => implode(',', array_unique($value['pop_products_id_slave'])),
                'created_by_script' => 1,
                'idcategories' => '',
                'idbrands' => '',
            )
        );

        $related_related_id = tep_db_insert_id();

        if ($related_related_id > 0) {
            $sql = sprintf(
                'SELECT * FROM related_groups_to_related WHERE related_groups_id = %d',
                $related_groups_id
            );
            $sql = tep_db_query($sql);

            if (!tep_db_num_rows($sql)) {
                tep_db_perform(
                    'related_groups_to_related',
                    array(
                        'related_groups_id' => $related_groups_id,
                        'related_related_id' => $related_related_id,
                        'related_status' => 1,
                        'created_by_script' => 1,
                    )
                );
            } else {
                tep_db_perform(
                    'related_groups_to_related',
                    array(
                        'related_related_id' => $related_related_id,
                        'related_status' => 1,
                        'created_by_script' => 1,
                    ),
                    'update',
                    'related_groups_id = ' . $related_groups_id
                );
            }
        }
    }
}
