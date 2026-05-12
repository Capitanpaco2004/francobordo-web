<?php global $showTax; ?>
<div class="box-ttal row ax amiddle" data-order-total>

	<?php foreach ($totalizations as $class => $values): ?>
        <?php foreach ($values as $value): ?>
            <div class="checkout-detail col a06 <?php echo $class; ?>" data-total="<?php echo $class; ?>" data-value="<?php echo $value['value']; ?>"><?php echo $value['title']; ?></div>
            <div class="checkout-detail col a06 <?php echo $class; ?>"><?php echo $value[(!$showTax && isset($value['text_tax']) ? 'text_tax' : 'text')]; ?></div>
        <?php endforeach;?>
    <?php endforeach;?>

    <div class="col a06 ttal-s aself-top" data-total="ot_total" data-value="<?php echo $totalValue; ?>">Total:</div>
    <div class="col a06 ttal-n"><?php echo $totalText; ?></div>

    <p class="col a12 showDetail"><i></i> <a href="javascript:void(0);">Ver desglose</a></p>

</div>

