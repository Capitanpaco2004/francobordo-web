<?php
	define('NAVBAR_TITLE', 'Create an Account');
	define( 'NAVBAR_TITLE_1', 'Create an Account' );
	define( 'NAVBAR_TITLE_2', 'Process' );
	define('HEADING_TITLE', 'My Account Information');
	define('TEXT_ORIGIN_LOGIN', '<font color="#FF0000"><small><strong>NOTE:</strong></font></small> If you already have an account with us, please login at the <a href="%s"><u>login page</u></a>.');
	define( 'TEXT_PROFESIONAL_WARNING', '<font color="#FF0000"><small><strong>NOTE:</strong></font></small> <b>You are registering as a professional, You will not be able to access our prices as a professional until it is validated by our staff that this account meets the professional requirements of the nautical sector. This validation can take up to 48 hours.</b><br /><b>We only register as professionals those companies or freelancers whose activity is the sale of nautical accessories and maintenance or repair of boats or ships.</b>' );
	define('EMAIL_SUBJECT', 'Welcome to ' . STORE_NAME);
	define('EMAIL_GREET_MR', 'Dear Mr. ' . stripslashes($_POST['lastname']) . ',' . "\n\n");
	define('EMAIL_GREET_MS', 'Dear Ms. ' . stripslashes($_POST['lastname']) . ',' . "\n\n");
	define('EMAIL_GREET_NONE', 'Dear ' . stripslashes($_POST['firstname']) . ',' . "\n\n");
	define('EMAIL_WELCOME', 'We welcome you to <strong>' . STORE_NAME . '</strong>.' . "\n\n");
	define('EMAIL_TEXT', 'You can now take part in the <strong>various services</strong> we have to offer you. Some of these services include:' . "\n\n" . '<li><strong>Permanent Cart</strong> - Any products added to your online cart remain there until you remove them, or check them out.' . "\n" . '<li><strong>Address Book</strong> - We can now deliver your products to another address other than yours! This is perfect to send birthday gifts direct to the birthday-person themselves.' . "\n" . '<li><strong>Order History</strong> - View your history of purchases that you have made with us.' . "\n" . '<li><strong>Products Reviews</strong> - Share your opinions on products with our other customers.' . "\n\n");
	define('EMAIL_CONTACT', 'For help with any of our online services, please email the store-owner: ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n\n");
	define('EMAIL_WARNING', '<strong>Note:</strong> This email address was given to us by one of our customers. If you did not signup to be a member, please send an email to ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n");
	define( 'CREATE_ACCOUNT_TITLE_SOY', 'I am' );
	define( 'CREATE_ACCOUNT_PARTICULAR', 'Particular' );
	define( 'CREATE_ACCOUNT_EMPRESA', 'Company, self-employed or organization' );
	define( 'CREATE_ACCOUNT_RE', 'I take advantage of the equivalence surcharge' );

	define( 'RGPD_WINDOW_MODAL_TITLE_DOB', 'Are you over 16?' );
	define( 'RGPD_WINDOW_MODAL_SUBTITLE_DOB', 'Due to the new European Data Protection Law, you must be 16 years or older as stable Article 8 of the RGPD, to be able to continue on this site you must accept these terms before registering as a customer.' );
	define( 'RGPD_WINDOW_MODAL_DOB_DENEGATE', 'I\'m not older' );
	define( 'RGPD_WINDOW_MODAL_DOB_ACCEPT', 'Accept and continue' );

	define('ENTRY_DATE_OF_BIRTH_OLD_ERROR', 'Due to the European Data Protection Act, you must be 16 years or older as stable Article 8 of the RGPD, in order to register as a customer on this site you must comply with these terms.');
