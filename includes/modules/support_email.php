<?
/*




*/

/* this section covers the very first confirmationemail to a customer,
to say that their ticket has been recieved */
define('EMAIL_SUBJECT_OPEN', 'Support sent to ' . STORE_NAME);
define('EMAIL_TEXT_TICKET_OPEN', 'TICKET ID -<b><i>' . $ticket_id . '</b></i>' . "\n\n");
define('EMAIL_THANKS_OPEN', 'Thank you for subbmitting your support request to <b>' . STORE_NAME . '</b>.' . "\n\n");
define('EMAIL_TEXT_OPEN', 'You\'re ticket has now been sent to the relevant department for investigation.' . "\n\n" . 'If you need to contact us regarding this matter before we have replied, please quote the above <i>ticket number</i> so as we may keep track of all relevant correspondance.' . "\n\n");
define('EMAIL_CONTACT_OPEN', 'For help with any of our online services, please email the store-owner: ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n\n");
define('EMAIL_WARNING_OPEN', '<b>Note:</b> This email address was given to us by someone using it to submit a support request. If you did not send this request, please send a email to ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n");

/* this section covers the confirmation email sent after a ticket has been edited by a customer,
to say that their ticket has been updated */




/* this section covers the confirmation email sent after a ticket has been edited by a customer,
to the assigned administrator, to say that hte ticket has been edited */




/* this section covers the confirmation email sent after a ticket has been closed by a customer,
to say that their ticket has been updated */



/* this section covers the confirmation email sent after a ticket has been re-opened by a customer,
to say that their ticket has been updated */


