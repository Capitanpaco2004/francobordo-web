<div class="list-products">
<?php
while ($aProducto = eachProducts()) {
    echo _product(array('CLASS' => 'prdct-vrtl', 'VISTA' => false));
}
?>
</div>