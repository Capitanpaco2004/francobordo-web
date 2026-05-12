
<?php

if ($insurance_amount == 0) {
    return;
}
?>

<div class="box-insurance boxStyle">
    <div class="titu1"><?php echo TEXT_SHIPPING_INSURANCE_TITLE; ?></div>
    <label for="choose_insurance">
        <span class="check"></span>
        <span><?php echo sprintf(TEXT_SHIPPING_INSURANCE_CHOICE, $insurance_amount); ?></span>
    </label>
</div>
