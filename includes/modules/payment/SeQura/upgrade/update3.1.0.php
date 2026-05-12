<?php
tep_db_query("update " . TABLE_CONFIGURATION . " set 
configuration_title = 'Sandbox',
configuration_key = 'MODULE_PAYMENT_SEQURA_SANDBOX',
configuration_description = '',
set_function = 'tep_cfg_select_option(array(\'True\', \'False\'), ',
configuration_value = case
    when configuration_value like 'https://live.%' then 'False'
    when configuration_value like 'https://sandbox.%' then 'True'
end
WHERE configuration_key = 'MODULE_PAYMENT_SEQURA_ENDPOINT'");