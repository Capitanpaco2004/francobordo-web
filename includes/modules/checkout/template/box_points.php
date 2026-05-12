<?php

if (USE_POINTS_SYSTEM == 'true' && USE_REDEEM_SYSTEM == 'true') {
    //echo points_selection();
    $cart_show_total = $cart->show_total();
    echo points_selection($cart_show_total);
    if (tep_not_null(USE_REFERRAL_SYSTEM) && (tep_count_customer_orders() == 0)) {
        echo referral_input();
    }
}
