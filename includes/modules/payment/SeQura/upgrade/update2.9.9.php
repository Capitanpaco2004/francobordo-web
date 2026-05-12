<?php
tep_db_query("update " . TABLE_CONFIGURATION . " set 
configuration_title = 'Assets key',
configuration_key = 'MODULE_PAYMENT_SEQURA_ASSETS_KEY',
configuration_value = SUBSTRING(configuration_value FROM -24 for 10),
configuration_description = ''
WHERE configuration_key = 'MODULE_PAYMENT_SEQURA_PP_COST_URL'");