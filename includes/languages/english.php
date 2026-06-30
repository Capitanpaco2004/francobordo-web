<?php
/*
$Id: english.php 1743 2007-12-20 18:02:36Z hpdl $

osCommerce, Open Source E-Commerce Solutions
http://www.oscommerce.com

Copyright (c) 2007 osCommerce

Released under the GNU General Public License
 */

// look in your $PATH_LOCALE/locale directory for available locales
// or type locale -a on the server.
// Examples:
// on RedHat try 'en_US'
// on FreeBSD try 'en_US.ISO_8859-1'
// on Windows try 'en', or 'English'
@setlocale(LC_TIME, 'en_US.ISO_8859-1');

define('DATE_FORMAT_SHORT', '%m/%d/%Y'); // this is used for strftime()
define('DATE_FORMAT_LONG', '%A %d %B, %Y'); // this is used for strftime()
define('DATE_FORMAT', 'm/d/Y'); // this is used for date()
define('DATE_TIME_FORMAT', DATE_FORMAT_SHORT . ' %H:%M:%S');

////
// Return date in raw format
// $date should be in format mm/dd/yyyy
// raw date is in format YYYYMMDD, or DDMMYYYY
// if USE_DEFAULT_LANGUAGE_CURRENCY is true, use the following currency, instead of the applications default currency (used when changing language)
define('LANGUAGE_CURRENCY', 'USD');

// Global entries for the <html> tag
define('HTML_PARAMS', 'dir="ltr" lang="en"');
define('LANGUAGE_LOCALE', 'en-US');

// charset for web pages and emails
define('CHARSET', 'iso-8859-1');

// page title
define('TITLE', STORE_NAME);

// header text in includes/header.php
define('BOX_ALL_CATEGORIES', 'All');
define('BOX_HEADER_ADDFAVORITE', 'Add to favorites');
define('HEADER_TITLE_CREATE_ACCOUNT', 'Create an Account');
define('HEADER_TITLE_MY_ACCOUNT', 'My Account');
define('HEADER_TITLE_CART_CONTENTS', 'Cart Contents');
define('HEADER_TITLE_CHECKOUT', 'Checkout');
define('HEADER_TITLE_TOP', 'Home');
define('HEADER_TITLE_CATALOG', 'Catalog');
define('HEADER_TITLE_LOGOFF', 'Log Off');
define('HEADER_TITLE_LOGIN', 'Log In');

// footer text in includes/footer.php
define('FOOTER_TEXT_REQUESTS_SINCE', 'requests since');

// text for gender
define('MALE', 'Male');
define('FEMALE', 'Female');
define('MALE_ADDRESS', 'Mr.');
define('FEMALE_ADDRESS', 'Ms.');

// text for date of birth example
define('DOB_FORMAT_STRING', 'mm/dd/yyyy');

// categories box text in includes/boxes/categories.php
define('BOX_HEADING_CATEGORIES', 'Categories');

// manufacturers box text in includes/boxes/manufacturers.php
define('BOX_HEADING_MANUFACTURERS', 'Brands');

// whats_new box text in includes/boxes/whats_new.php
define('BOX_HEADING_WHATS_NEW', 'What\'s New?');

// quick_find box text in includes/boxes/quick_find.php
define('BOX_HEADING_SEARCH', 'Quick Find');
define('BOX_SEARCH_TEXT', 'Use keywords to find the product you are looking for.');
define('BOX_SEARCH_ADVANCED_SEARCH', 'Advanced Search');

// specials box text in includes/boxes/specials.php
define('BOX_HEADING_SPECIALS', 'Specials');

// reviews box text in includes/boxes/reviews.php
define('BOX_HEADING_REVIEWS', 'Reviews');
define('BOX_REVIEWS_WRITE_REVIEW', 'Write a review on this product!');
define('BOX_REVIEWS_NO_REVIEWS', 'There are currently no product reviews');
define('BOX_REVIEWS_TEXT_OF_5_STARS', '%s of 5 Stars!');

// shopping_cart box text in includes/boxes/shopping_cart.php
define('BOX_HEADING_SHOPPING_CART', 'Shopping Cart');
define('BOX_SHOPPING_CART_EMPTY', '0 items');

// order_history box text in includes/boxes/order_history.php
define('BOX_HEADING_CUSTOMER_ORDERS', 'Order History');

// best_sellers box text in includes/boxes/best_sellers.php
define('BOX_HEADING_BESTSELLERS', 'Bestsellers');
define('BOX_HEADING_BESTSELLERS_IN', 'Bestsellers in<br />&nbsp;&nbsp;');

// notifications box text in includes/boxes/products_notifications.php
define('BOX_HEADING_NOTIFICATIONS', 'Notifications');
define('BOX_NOTIFICATIONS_NOTIFY', 'Notify me of updates to <strong>%s</strong>');
define('BOX_NOTIFICATIONS_NOTIFY_REMOVE', 'Do not notify me of updates to <strong>%s</strong>');

// manufacturer box text
define('BOX_HEADING_MANUFACTURER_INFO', 'Brand Info');
define('BOX_MANUFACTURER_INFO_HOMEPAGE', '%s Homepage');
define('BOX_MANUFACTURER_INFO_OTHER_PRODUCTS', 'Other products');

// languages box text in includes/boxes/languages.php
define('BOX_HEADING_LANGUAGES', 'Languages');

// currencies box text in includes/boxes/currencies.php
define('BOX_HEADING_CURRENCIES', 'Currencies');

// information box text in includes/boxes/information.php
define('BOX_HEADING_INFORMATION', 'Information');
define('BOX_INFORMATION_PRIVACY', 'Privacy Notice');
define('BOX_INFORMATION_CONDITIONS', 'Conditions of Use');
define('BOX_INFORMATION_SHIPPING', 'Shipping & Returns');
define('BOX_INFORMATION_CONTACT', 'Contact Us');
define('BOX_INFORMATION_MY_POINTS_HELP', 'Point Program FAQ'); // Points/Rewards Module V2.1rc2a

// tell a friend box text in includes/boxes/tell_a_friend.php
define('BOX_HEADING_TELL_A_FRIEND', 'Tell A Friend');
define('BOX_TELL_A_FRIEND_TEXT', 'Tell someone you know about this product.');

// checkout procedure text
define('CHECKOUT_BAR_DELIVERY', 'Delivery Information');
define('CHECKOUT_BAR_PAYMENT', 'Payment Information');
define('CHECKOUT_BAR_CONFIRMATION', 'Confirmation');
define('CHECKOUT_BAR_FINISHED', 'Finished!');

// pull down default text
define('PULL_DOWN_DEFAULT', 'Please Select');
define('PULL_DOWN_CITY', 'Select city');
define('PULL_DOWN_STATE', 'Select state');
define('PULL_DOWN_COUNTRY', 'Select country');
define('TYPE_BELOW', 'Type Below');

// javascript messages
define('JS_ERROR', 'Errors have occured during the process of your form.\n\nPlease make the following corrections:\n\n');

define('JS_REVIEW_TEXT', '* The \'Review Text\' must have at least ' . REVIEW_TEXT_MIN_LENGTH . ' characters.\n');
define('JS_REVIEW_RATING', '* You must rate the product for your review.\n');

define('JS_ERROR_NO_PAYMENT_MODULE_SELECTED', '* Please select a payment method for your order.\n');

define('JS_ERROR_SUBMITTED', 'This form has already been submitted. Please press Ok and wait for this process to be completed.');

define('ERROR_NO_PAYMENT_MODULE_SELECTED', 'Please select a payment method for your order.');

define('CATEGORY_COMPANY', 'Company Details');
define('CATEGORY_PERSONAL', 'Your Personal Details');
define('CATEGORY_ADDRESS', 'Your Address');
define('CATEGORY_CONTACT', 'Your Contact Information');
define('CATEGORY_OPTIONS', 'Options');
define('CATEGORY_PASSWORD', 'Your Password');

define('ENTRY_COMPANY', 'Company Name:');
define('ENTRY_COMPANY_ERROR', '');
define('ENTRY_COMPANY_TEXT', '');
define('ENTRY_COMPANY_TAX_ID', 'Company\'s tax id number:');
define('ENTRY_COMPANY_TAX_ID_ERROR', '');
define('ENTRY_COMPANY_TAX_ID_TEXT', '');
define('ENTRY_GENDER', 'Gender:');
define('ENTRY_GENDER_ERROR', 'Please select your Gender.');
define('ENTRY_GENDER_TEXT', '*');
define('ENTRY_FIRST_NAME', 'First Name:');
define('ENTRY_FIRST_NAME_ERROR', 'Your First Name must contain a minimum of ' . ENTRY_FIRST_NAME_MIN_LENGTH . ' characters.');
define('ENTRY_FIRST_NAME_TEXT', '*');
define('ENTRY_LAST_NAME', 'Last Name:');
define('ENTRY_LAST_NAME_ERROR', 'Your Last Name must contain a minimum of ' . ENTRY_LAST_NAME_MIN_LENGTH . ' characters.');
define('ENTRY_LAST_NAME_TEXT', '*');
define('ENTRY_DATE_OF_BIRTH', 'Date of Birth:');
define('ENTRY_DATE_OF_BIRTH_ERROR', 'Your Date of Birth must be in this format: MM/DD/YYYY (eg 05/21/1970)');
define('ENTRY_DATE_OF_BIRTH_TEXT', '* (eg. 05/21/1970)');
define('ENTRY_EMAIL_ADDRESS', 'E-Mail Address:');
define('ENTRY_EMAIL_ADDRESS_ERROR', 'Your E-Mail Address must contain a minimum of ' . ENTRY_EMAIL_ADDRESS_MIN_LENGTH . ' characters.');
define('ENTRY_EMAIL_ADDRESS_CHECK_ERROR', 'Your E-Mail Address does not appear to be valid - please make any necessary corrections.');
define('ENTRY_EMAIL_ADDRESS_ERROR_EXISTS', 'Your E-Mail Address already exists in our records - please log in with the e-mail address or create an account with a different address.');
define('ENTRY_EMAIL_ADDRESS_TEXT', '*');
define('ENTRY_STREET_ADDRESS', 'Street Address:');
define('ENTRY_STREET_ADDRESS_ERROR', 'Your Street Address must contain a minimum of ' . ENTRY_STREET_ADDRESS_MIN_LENGTH . ' characters.');
define('ENTRY_STREET_ADDRESS_TEXT', '*');
define('ENTRY_SUBURB', 'Suburb:');
define('ENTRY_SUBURB_ERROR', '');
define('ENTRY_SUBURB_TEXT', '');
define('ENTRY_POST_CODE', 'Post Code:');
define('ENTRY_POST_CODE_ERROR', 'Your Post Code must contain a minimum of ' . ENTRY_POSTCODE_MIN_LENGTH . ' characters.');
define('ENTRY_POST_CODE_TEXT', '*');
define('ENTRY_CITY', 'City:');
define('ENTRY_CITY_ERROR', 'Your City must contain a minimum of ' . ENTRY_CITY_MIN_LENGTH . ' characters.');
define('ENTRY_CITY_TEXT', '*');
define('ENTRY_STATE', 'State/Province:');
define('ENTRY_STATE_ERROR', 'Your State must contain a minimum of ' . ENTRY_STATE_MIN_LENGTH . ' characters.');
define('ENTRY_STATE_ERROR_SELECT', 'Please select a state from the States pull down menu.');
define('ENTRY_STATE_TEXT', '*');
define('ENTRY_COUNTRY', 'Country:');
define('ENTRY_COUNTRY_ERROR', 'You must select a country from the Countries pull down menu.');
define('ENTRY_COUNTRY_TEXT', '*');
define('ENTRY_TELEPHONE_NUMBER', 'Telephone Number:');
define('ENTRY_TELEPHONE_NUMBER_ERROR', 'Your Telephone Number must contain a minimum of ' . ENTRY_TELEPHONE_MIN_LENGTH . ' characters.');
define('ENTRY_TELEPHONE_NUMBER_TEXT', '*');
define('ENTRY_FAX_NUMBER', 'Fax Number:');
define('ENTRY_FAX_NUMBER_ERROR', '');
define('ENTRY_FAX_NUMBER_TEXT', '');
define('ENTRY_NEWSLETTER', 'Newsletter:');
define('ENTRY_NEWSLETTER_TEXT', '');
define('ENTRY_NEWSLETTER_YES', 'Subscribed');
define('ENTRY_NEWSLETTER_NO', 'Unsubscribed');
define('ENTRY_NEWSLETTER_ERROR', '');
define('ENTRY_PASSWORD', 'Password:');
define('ENTRY_PASSWORD_ERROR', 'Your Password must contain a minimum of ' . ENTRY_PASSWORD_MIN_LENGTH . ' characters.');
define('ENTRY_PASSWORD_ERROR_NOT_MATCHING', 'The Password Confirmation must match your Password.');
define('ENTRY_PASSWORD_TEXT', '*');
define('ENTRY_PASSWORD_CONFIRMATION', 'Password Confirmation:');
define('ENTRY_PASSWORD_CONFIRMATION_TEXT', '*');
define('ENTRY_PASSWORD_CURRENT', 'Current Password:');
define('ENTRY_PASSWORD_CURRENT_TEXT', '*');
define('ENTRY_PASSWORD_CURRENT_ERROR', 'Your Password must contain a minimum of ' . ENTRY_PASSWORD_MIN_LENGTH . ' characters.');
define('ENTRY_PASSWORD_NEW', 'New Password:');
define('ENTRY_PASSWORD_NEW_TEXT', '*');
define('ENTRY_PASSWORD_NEW_ERROR', 'Your new Password must contain a minimum of ' . ENTRY_PASSWORD_MIN_LENGTH . ' characters.');
define('ENTRY_PASSWORD_NEW_ERROR_NOT_MATCHING', 'The Password Confirmation must match your new Password.');
define('PASSWORD_HIDDEN', '--HIDDEN--');

define('ENTRY_NIF', 'DNI/NIF:');
define('ENTRY_NO_NIF_ERROR', 'You must enter your ID/NIF.');
define('ENTRY_FORMATO_NIF_ERROR', 'The ID/NIF must have 5 characters. In the case of the NIF, fill in with leading zeros if necessary.');
define('ENTRY_LETRA_NIF_ERROR', 'The letter of the ID is incorrect.');
define('ENTRY_NIF_TEXT', '*');
define('ENTRY_NIF_EXAMPLE', '(for example: 01234567L):');
define('FORM_REQUIRED_INFORMATION', '* Required information');

// constants for use in tep_prev_next_display function
define('TEXT_RESULT_PAGE', 'Result Pages:');
define('TEXT_DISPLAY_NUMBER_OF_PRODUCTS', 'Displaying <strong>%d</strong> to <strong>%d</strong> (of <strong>%d</strong> products)');
define('TEXT_DISPLAY_NUMBER_OF_ORDERS', 'Displaying <strong>%d</strong> to <strong>%d</strong> (of <strong>%d</strong> orders)');
define('TEXT_DISPLAY_NUMBER_OF_REVIEWS', 'Displaying <strong>%d</strong> to <strong>%d</strong> (of <strong>%d</strong> reviews)');
define('TEXT_DISPLAY_NUMBER_OF_PRODUCTS_NEW', 'Displaying <strong>%d</strong> to <strong>%d</strong> (of <strong>%d</strong> new products)');
define('TEXT_DISPLAY_NUMBER_OF_SPECIALS', 'Displaying <strong>%d</strong> to <strong>%d</strong> (of <strong>%d</strong> specials)');

define('PREVNEXT_TITLE_FIRST_PAGE', 'First Page');
define('PREVNEXT_TITLE_PREVIOUS_PAGE', 'Previous Page');
define('PREVNEXT_TITLE_NEXT_PAGE', 'Next Page');
define('PREVNEXT_TITLE_LAST_PAGE', 'Last Page');
define('PREVNEXT_TITLE_PAGE_NO', 'Page %d');
define('PREVNEXT_TITLE_PREV_SET_OF_NO_PAGE', 'Previous Set of %d Pages');
define('PREVNEXT_TITLE_NEXT_SET_OF_NO_PAGE', 'Next Set of %d Pages');
define('PREVNEXT_BUTTON_FIRST', '&lt;&lt;FIRST');
define('PREVNEXT_BUTTON_PREV', '[&lt;&lt;&nbsp;Prev]');
define('PREVNEXT_BUTTON_NEXT', '[Next&nbsp;&gt;&gt;]');
define('PREVNEXT_BUTTON_LAST', 'LAST&gt;&gt;');

define('IMAGE_BUTTON_ADD_ADDRESS', 'Add Address');
define('IMAGE_BUTTON_ADDRESS_BOOK', 'Address Book');
define('IMAGE_BUTTON_BACK', 'Back');
define('IMAGE_BUTTON_BUY_NOW', 'Buy Now');
define('IMAGE_BUTTON_CHANGE_ADDRESS', 'Change Address');
define('IMAGE_BUTTON_CHECKOUT', 'Checkout');
define('IMAGE_BUTTON_CONFIRM_ORDER', 'Confirm Order');
define('IMAGE_BUTTON_CONTINUE', 'Continue');
define('IMAGE_BUTTON_WAIT', 'Wait...');
define('IMAGE_BUTTON_CONTINUE_SHOPPING', 'Continue Shopping');
define('IMAGE_BUTTON_DELETE', 'Delete');
define('IMAGE_BUTTON_EDIT_ACCOUNT', 'Edit Account');
define('IMAGE_BUTTON_HISTORY', 'Order History');
define('IMAGE_BUTTON_LOGIN', 'Sign In');
define('IMAGE_BUTTON_IN_CART', 'Add to Cart');
define('IMAGE_BUTTON_NOTIFICATIONS', 'Notifications');
define('IMAGE_BUTTON_QUICK_FIND', 'Quick Find');
define('IMAGE_BUTTON_REMOVE_NOTIFICATIONS', 'Remove Notifications');
define('IMAGE_BUTTON_REVIEWS', 'Reviews');
define('IMAGE_BUTTON_SEARCH', 'Search');
define('IMAGE_BUTTON_SHIPPING_OPTIONS', 'Shipping Options');
define('IMAGE_BUTTON_TELL_A_FRIEND', 'Tell a Friend');
define('IMAGE_BUTTON_UPDATE', 'Update');
define('IMAGE_BUTTON_UPDATE_CART', 'Update Cart');
define('IMAGE_BUTTON_WRITE_REVIEW', 'Write Review');

define('SMALL_IMAGE_BUTTON_DELETE', 'Delete');
define('SMALL_IMAGE_BUTTON_EDIT', 'Edit');
define('SMALL_IMAGE_BUTTON_VIEW', 'View');

define('ICON_ARROW_RIGHT', 'more');
define('ICON_CART', 'In Cart');
define('ICON_ERROR', 'Error');
define('ICON_SUCCESS', 'Success');
define('ICON_WARNING', 'Warning');

define('TEXT_GREETING_PERSONAL', 'Welcome back <span class="greetUser">%s!</span>');
define('TEXT_GREETING_PERSONAL_RELOGON', '<small>If you are not %s, please <a href="%s"><u>log yourself in</u></a> with your account information.</small>');
define('TEXT_GREETING_GUEST', 'Welcome <span class="greetUser">Guest!</span> Would you like to <a href="%s"><u>log yourself in</u></a>? Or would you prefer to <a href="%s"><u>create an account</u></a>?');

define('TEXT_SORT_PRODUCTS', 'Sort products ');
define('TEXT_DESCENDINGLY', 'descendingly');
define('TEXT_ASCENDINGLY', 'ascendingly');
define('TEXT_BY', ' by ');

define('TEXT_REVIEW_BY', 'by %s');
define('TEXT_REVIEW_WORD_COUNT', '%s words');
define('TEXT_REVIEW_RATING', 'Rating: %s [%s]');
define('TEXT_REVIEW_DATE_ADDED', 'Date Added: %s');
define('TEXT_NO_REVIEWS', 'There are currently no product reviews.');

define('TEXT_NO_NEW_PRODUCTS', 'There are currently no products.');

define('TEXT_UNKNOWN_TAX_RATE', 'Unknown tax rate');

define('TEXT_REQUIRED', '<span class="errorText">Required</span>');
define('DEFAULT_COUNTRY', '223');

define('ERROR_TEP_MAIL', '<font face="Verdana, Arial" size="2" color="#ff0000"><strong><small>TEP ERROR:</small> Cannot send the email through the specified SMTP server. Please check your php.ini setting and correct the SMTP server if necessary.</strong></font>');
define('WARNING_INSTALL_DIRECTORY_EXISTS', 'Warning: Installation directory exists at: ' . dirname($_SERVER['SCRIPT_FILENAME']) . '/install. Please remove this directory for security reasons.');
define('WARNING_CONFIG_FILE_WRITEABLE', 'Warning: I am able to write to the configuration file: ' . dirname($_SERVER['SCRIPT_FILENAME']) . '/includes/configure.php. This is a potential security risk - please set the right user permissions on this file.');
define('WARNING_SESSION_DIRECTORY_NON_EXISTENT', 'Warning: The sessions directory does not exist: ' . tep_session_save_path() . '. Sessions will not work until this directory is created.');
define('WARNING_SESSION_DIRECTORY_NOT_WRITEABLE', 'Warning: I am not able to write to the sessions directory: ' . tep_session_save_path() . '. Sessions will not work until the right user permissions are set.');
define('WARNING_SESSION_AUTO_START', 'Warning: session.auto_start is enabled - please disable this php feature in php.ini and restart the web server.');
define('WARNING_DOWNLOAD_DIRECTORY_NON_EXISTENT', 'Warning: The downloadable products directory does not exist: ' . DIR_FS_DOWNLOAD . '. Downloadable products will not work until this directory is valid.');

define('TEXT_CCVAL_ERROR_INVALID_DATE', 'The expiry date entered for the credit card is invalid. Please check the date and try again.');
define('TEXT_CCVAL_ERROR_INVALID_NUMBER', 'The credit card number entered is invalid. Please check the number and try again.');
define('TEXT_CCVAL_ERROR_UNKNOWN_CARD', 'The first four digits of the number entered are: %s. If that number is correct, we do not accept that type of credit card. If it is wrong, please try again.');
define('REDEEM_SYSTEM_ERROR_POINTS_NOT', 'Points value are not enough to cover the cost of your purchase. Please select another payment method');
define('REDEEM_SYSTEM_ERROR_POINTS_OVER', 'REDEEM POINTS ERROR ! Points value can not be over the total value. Please Re enter points');
define('REFERRAL_ERROR_SELF', 'Sorry you can not refer yourself.');
define('REFERRAL_ERROR_NOT_VALID', 'The referral email does not appear to be valid - please make any necessary corrections.');
define('REFERRAL_ERROR_NOT_FOUND', 'The referral email address you entered was not found.');
define('TEXT_POINTS_BALANCE', 'Points Status');
define('TEXT_POINTS', 'Points :');
define('TEXT_VALUE', 'Value:');
define('REVIEW_HELP_LINK', ' Write a Review and earn <strong>%s</strong> worth of points.<br />Please check the %s for more information.');

define('FOOTER_TEXT_BODY', 'Copyright &copy; ' . date('Y') . ' <a href="' . tep_href_link(FILENAME_DEFAULT) . '">' . STORE_NAME . '</a><br />Powered by <a href="http://www.oscommerce.com" target="_blank">osCommerce</a>');

define('MINIMUM_ORDER_NOTICE', 'Minimum order amount for %s is %d. Your cart has been updated to reflect this.');
define('QUANTITY_BLOCKS_NOTICE', '%s can only by ordered in multiples of %d. Your cart has been updated to reflect this.');
define('MATC_CONDITION_AGREEMENT', 'I have read and accept the <a href="%s" target="_blank"><strong><u>Terms and Conditions of Use</u></strong></a> of this site:');
define('MATC_HEADING_CONDITIONS', 'Aceptar terminos y condiciones de uso');
define('MATC_ERROR', 'Tienes que aceptar los terminos y condiciones de uso para continuar.');

define('BOX_HEADING_CUSTOMER_TESTIMONIALS', 'Opinion');
define('BOX_HEADING_FEATURED', 'Featured Products');
define('BOX_INFORMATION_CUSTOMER_TESTIMONIALS', 'Opinion');
define('TABLE_HEADING_TESTIMONIALS_ID', 'ID');
define('TABLE_HEADING_TESTIMONIALS_NAME', 'Name');
define('TABLE_HEADING_TESTIMONIALS_DESCRIPTION', 'Opinion');
define('TEXT_TESTIM_BY', 'Writed by:');
define('IMAGE_BUTTON_INSERT', 'Insert:');

define('BOX_INFORMATION_ALLPRODS', 'All products');
define('BOX_INFORMATION_RSS', 'RSS');
define('IMAGE_BUTTON_RP_BUY_NOW', 'Buy');
define('MY_ACCOUNT_DELETE', 'Account Delete');

define('ENTRY_DISCOUNT_COUPON_ERROR', 'The coupon entered is not valid.');
define('ENTRY_DISCOUNT_COUPON_ERROR2', 'You cant\'t used discount coupons.');
define('ENTRY_DISCOUNT_COUPON_AVAILABLE_ERROR', 'El cupón introducido ha superado el numero de veces de uso.');
define('ENTRY_DISCOUNT_COUPON_USE_ERROR', 'Our records indicate that you have used this coupon %s time(s). You can not use the code over %s time(s).');
define('ENTRY_DISCOUNT_COUPON_MIN_PRICE_ERROR', 'The total minimum purchase for this coupon is %s');
define('ENTRY_DISCOUNT_COUPON_MIN_QUANTITY_ERROR', 'The minimum number of products required for this coupon is %s');
define('ENTRY_DISCOUNT_COUPON_EXCLUSION_ERROR', 'Some or all products in your cart are excluded.');
define('ENTRY_DISCOUNT_COUPON', 'Discount Coupon Code');
define('ENTRY_DISCOUNT_COUPON_SHIPPING_CALC_ERROR', 'Shipping charges calculated have changed.');
define('ENTRY_DISCOUNT_COUPON_ERROR_MAX_ORDER', 'The coupon value (%s €) exceeds the total order amount (%s €). Please add more items to the cart to use the coupon.');
// CATALOG_PRODUCTS_WITH_IMAGES_mod
define('BOX_CATALOG_PRODUCTS_WITH_IMAGES', 'Printable Catalog');
define('BOX_CATALOG_PRODUCTS_WITH_IMAGES_FULL', 'Full Printable Catalog');
define('IMAGE_BUTTON_UPSORT', 'Sort Asending');
define('IMAGE_BUTTON_DOWNSORT', 'Sort Desending');

define('TABLE_HEADING_REFERRAL', 'Recomendado por');
define('TEXT_REFERRAL_REFERRED', 'Si algún amigo, familiar o conocido le ha recomendado nuestra tienda por favor, introduzca su dirección de email aqui: ');
define('TABLE_HEADING_FEATURED_PRODUCTS', 'Featured Products');
define('TABLE_HEADING_FEATURED_PRODUCTS_CATEGORY', 'Featured Products in %s');

define('VISUAL_VERIFY_CODE_CHARACTER_POOL', 'abcdefghkmnpstwxyABCDEFGHJKMNPRSTWXY23456789FJWNVB63HDLAJAF'); //no zeros or O
define('VISUAL_VERIFY_CODE_CATEGORY', '<br />Anti-Spam Security Check (Case SEnSiTive)<br />');
define('VISUAL_VERIFY_CODE_ENTRY_ERROR', 'The security code you entered did not match the one displayed. Please try again.');
define('VISUAL_VERIFY_CODE_ENTRY_TEXT', '*');

define('VISUAL_VERIFY_CODE_TEXT_INSTRUCTIONS', 'Escriba el c&oacute;digo de seguridad:');
define('VISUAL_VERIFY_CODE_BOX_IDENTIFIER', '(refrescar p&aacute;gina para renovar)');
define('ENTRY_REMEMBER_ME', 'Remember me');

define('MESSAGE_WAIT','Please wait...');
define('TEXT_PRICE_BREAKS', 'From');
define('TEXT_ON_SALE', 'On sale');

define('FREE_SHIPPING_TITLE', 'Free Shipping!');
define('FREE_SHIPPING_DESCRIPTION', 'Free shipping');


// Filtro
define('FILTRO_FILTRO', 'Brands:');
define('FILTRO_ORDENAR', 'Order:');
define('FILTRO_NUMERO', 'No. Articles:');
define('FILTRO_NO_EXISTEN', 'No existen productos que correspondan con el filtro seleccionado.');

// Paginador
define('PAGINADOR_MOSTRAR', 'Showing %d of %d products');
define('PAGINADOR_MAS', 'See more products');

define('TABLE_HEADING_IMAGEN', 'Images');


define('TEXT_ERROR_SHIPPING', 'Lo sentimos, pero es necesario para continuar con tu pedido seleccionar una forma de envío disponible de la siguiente lista.');

// Politicas
define('EMAIL_POLITICA', 'En cumplimiento del Reglamento (UE) 2016/679 (RGPD) y la Ley Org&aacute;nica 3/2018, de 5 de diciembre (LOPDGDD), le informamos de que sus datos personales son tratados por Francobordo como responsable del tratamiento, con la finalidad de gestionar su pedido y la relaci&oacute;n comercial. Puede ejercer sus derechos de acceso, rectificaci&oacute;n, supresi&oacute;n, oposici&oacute;n, limitaci&oacute;n y portabilidad escribiendo a info@francobordo.com o llamando al 916 528 858. M&aacute;s informaci&oacute;n en nuestra Pol&iacute;tica de Privacidad: https://www.francobordo.com/politica-de-privacidad-i-15.html');
define('PIE_EMAIL', 'Calle San Rafael nº 8. Alcobendas. 28108 MADRID<br>+34 916 528 858 info@francobordo.com<br>Copyright &copy; ' . date('Y') . '   www.francobordo.com');

//begin Supportticketsystem
define('BOX_HEADING_SUPPORT', 'Soporte');
//end Supportticketsystem

// XSell (English)
define('TEXT_XSELL_PRODUCTS', 'We Also Recommend');

//+ Insurance 2.03
define('TEXT_SHIPPING_INSURANCE_TITLE', 'Shipping Insurance');
define('TEXT_SHIPPING_INSURANCE_CHOICE', 'Would you like to insure your shipment for <strong>%s</strong>?');
define('TEXT_SHIPPING_INSURANCE_DISCLAIMER', '(We recommend you insure your shipment. Uncheck this option if you want to insure your shipment.)');
//- Insurance 2.03

//BOF Bundled Products
define('IMAGE_BUTTON_OUT_OF_STOCK', 'Out of Stock');
define('TEXT_BUNDLE_ONLY', 'Not Sold Separately');
//EOF Bundled Products

define('TEXT_SHOW_ALL', 'See all');

// Añadido traduccion //
define('TEXT_LOGIN_IN', 'Login');
define('TEXT_LOGIN_REGISTER', 'REGISTER');
define('TEXT_NEWS', 'Novelty');
define('TEXT_SPECIALS', 'Specials');
define('TEXT_INFORMATION', 'Information');
/**
 * #XCC-313-91043
 */
define('TEXT_AFFILIADOS', 'Affiliates');
define('TEXT_CONTACT', 'Contact');
define('TEXT_CONTACT_US', 'Contact us');
define('TEXT_REMEMBER_PASS', 'Remember password');
define('TEXT_MY_ACCOUNT', 'My account');
define('TEXT_MY_ORDERS', 'My orders');
define('TEXT_MY_WISHLIST', 'My wishlist');
define('TEXT_MY_POINTS', 'My points');
define('TEXT_EXIT', 'Logoff');
define('TEXT_SEARCH', 'Search');
define('TEXT_FILTER_MANUFACTURERS', 'Filter by brand');
define('TEXT_SEE_ALL', 'see all');
define('TEXT_NAUTICA', 'Nautical');
define('TEXT_PESCA', 'Fishing');
define('TEXT_TIEMPO_LIBRE', 'Outdoor');
define('TEXT_SUBMARINISMO', 'Diving');
define('TEXT_PRIVACIDAD', 'Privacy Policy');
define('TEXT_BOLETIN', 'subscribe to our newsletter.');
define('TEXT_BOLETIN_INFO', 'Be the first to hear about all our news');
define('TEXT_SUBSCRIBE', 'Subscribe');
define('TEXT_DISTRIBUIDOR', 'Professionals Area');
define('TEXT_DISTRIBUIDOR_INFO', 'Register and take advantage of discounts and benefits to be Official Distributor of Francobordo');
define('TEXT_DISTRIBUIDOR_REGISTRO', 'Start register');
define('TEXT_FOOTER1', 'FRANCOBORDO.COM | YOUR NAUTICAL, FISHING, OUTDORR AND DIVING STORE');
define('TEXT_FOOTER2', 'FRANCOBORDO MADRID: Calle San Rafael 8, Alcobendas, 28100 MADRID');
define('TEXT_FOOTER3', 'STORE HOURS:<br/>from 10:00 to 20:00 Monday to friday<br/>Saturdays from 10:00 to 14:00');
define('TEXT_DEVELOPED', 'Developed by:');
define('TEXT_OLVIDO', 'Forgot your password?');
define('TEXT_ACOGERSE', 'I take the equivalence charge');
define('TEXT_PRODUCT', 'product');
define('TEXT_VISTA', 'View');
define('TEXT_NUM_MOSTRAR', 'Number of products to display on this page');
define('TEXT_READ_MORE', 'read+');
define('TEXT_DESCRIPTION', 'Description');
define('TEXT_ESPECIFICACIONES', 'Specifications');
define('TEXT_COMMENTS', 'Comments');
define('TEXT_RELATED', 'Related');
define('TEXT_SHARE', 'Share on');
define('TEXT_OPTIONAL_RELATED', 'Accessories and related');
define('TEXT_PRICE', 'Prices');
define('TEXT_CAPTCHA', 'Write what you see below:');
define('TEXT_SELECT_STORE', 'Select store for pickup:');

define('CONDITION_AGREEMENT_WARNING', 'You must accept the Privacy Policy and Conditions before proceeding');
define('EXTRA_SUBJECT_STOREOWNER', '');

// Begin: RMA Returns System
define('BOX_INFORMATION_RETURNS', 'Track a Return');
// End: RMA Returns System

define('PRODUCTS_TOGETHER_TITLE', 'Take advantage and also buy these products');

// Ajax transferencia
define('AJAX_TRANS_INFO', 'payment info:');
define('AJAX_TRANS_PLEASE', 'Please use the following information to transfer the total value of your purchase');
define('AJAX_TRANS_NAME', 'Account Name:');
define('AJAX_TRANS_BANK', 'Bank Name:');
define('AJAX_TRANS_NUMBER', 'Account Number:');
define('AJAX_TRANS_REMEMBER', 'The purchase will not be sent until no amount appears on our account');
define( 'MODULE_ORDER_TOTAL_SHIPPING_TITLE', 'Gastos de Envío');
define('MENSAJE_VACACIONES', 'Orders placed from October 8 to October 16 will not be handled until Monday October 16.<br>We move to a new installations that will allow us to better serve our customers.<br>We apologize for any inconvenience we may cause you');
define('REORDER', 'Reorder');
define('SHIPPING_PREDICTION_BUY_NOW', 'Buy now and receive it between <span>%s1</span> and <span>%s2</span>');
define('SHIPPING_PREDICTION_BUY_NOW_TOMORROW', 'Buy now and receive it tomorrow <span>%s1</span>');
define('SHIPPING_PREDICTION_BUY_NOW_PAST_TOMORROW', 'Buy now and receive it the day after tomorrow <span>%s1</span>');
define('SHIPPING_PREDICTION_BEFORE', ' before 13:30h');
define('SHIPPING_PREDICTION_NONE', 'Could not make a shipment prediction');
define('SHIPPING_PREDICTION_FROM', '\o\f');
define('SHIPPING_PREDICTION_MORE_INFO', 'More information');
define('SHIPPING_PREDICTION_MORE_INFO_DETAILS', '<p>- The delivery dates indicated here are for shipments within the peninsula, for delivery on Saturdays you will have to choose SEUR 13:30.</p>
<p>- Installation in the Balearic Islands: The indicated deadlines are valid by choosing as transport company SEUR 13:30, in the case of choosing another means of shipment to the indicated term, it will have to add one more working day.</p>
<p>- Under the Canary Islands, Ceuta, Melilla and International Destinations: You must add 5 more working days to the indicated term.</p>
<p>- In the case of choosing as a form of delivery CORREOS the delivery period will be extended from one to two more days over the above mentioned deadlines.</p>');
define('SHIPPING_PREDICTION_EXCEPT', '* Except the products on request that depend on the receipt of the same.');

define('ENTRY_CITY_ID_ERROR', 'You must select a city');

define('NOTIFICACIONES_TEXT', 'Do you want to be the first to know the best promotions of Francobordo?');
define('NOTIFICACIONES_BUTTON_YES', 'Yes');
define('NOTIFICACIONES_BUTTON_NO', 'No');

define('SPECIALS_CUENTA_ATRAS', 'This sale ends in: ');
define('SPECIALS_CUENTA_ATRAS_DIA', 'day');
define('SPECIALS_CUENTA_ATRAS_DIAS', 'days');

define('ERROR_POLITICA', 'You must read and accept the privacy policy before proceeding.');
define('LOGIN_LOGOFF', 'Log off');
define('MY_WISH', 'My wishlist');
define('MY_ACCOUNT', 'My Account');
define('SHOW_MORE_FILTERS', 'MOSTRAR MÁS FILTROS');
define('SHOW_LESS_FILTERS', 'MOSTRAR MENOS FILTROS');
define('VER_MODIFICAR', 'view or modify');
define('VER_MAS', 'view more');
define('ACCOUNT_ERROR_PASSWORD', 'The password does not match, try again.');
define('RGPD_WINDOW_MODAL_SUBTITLE', 'We have updated our Conditions and made some changes to the Data Policy. Take a few minutes to review these changes and indicate if you agree.');
define('RGPD_WINDOW_MODAL_TITLE', 'Changes in conditions and data policy');
define('RGPD_WINDOW_MODAL_ACCEPT', 'Accept and continue');
define('RGPD_CHECKBOX_TERMINO_TRADE', 'I have read and accept the terms "{TITLE}" of this site');
define('RGPD_CHECKBOX_TERMINO_TRADE_ERROR', 'You must read and accept the term "{TITLE}" before continuing.');
define('TEXT_DATE_DAY', 'Day');
define('TEXT_DATE_MONTH', 'Month');
define('TEXT_DATE_YEAR', 'Year');
define('TEXT_DATE_JAN', 'January');
define('TEXT_DATE_FEB', 'February');
define('TEXT_DATE_MAR', 'March');
define('TEXT_DATE_APR', 'April');
define('TEXT_DATE_MAY', 'May');
define('TEXT_DATE_JUN', 'June');
define('TEXT_DATE_JUL', 'July');
define('TEXT_DATE_AUG', 'August');
define('TEXT_DATE_SEP', 'September');
define('TEXT_DATE_OCT', 'October');
define('TEXT_DATE_NOV', 'November');
define('TEXT_DATE_DEC', 'December');

define('MY_DOWNLOADS', 'Download your information');
define('MY_COMMENTS', 'Comments');
define('MY_REVIEWS', 'Reviews');
define('MY_DISABLE', 'Deactivate account temporarily');
define('NOTIFICACIONES_EMAIL', 'Notifications by email');
define('USA_6_CARACTERES', 'Use 6 or more characters');
define('MY_RGPD_TITLE', 'History Acceptance of Policies');
define('MY_RGPD_TEXT', 'Has <b>aceptado</b> las <a href="{LINK}"><u>políticas y términos</u></a> generales en el día y hora {DATE}');
define('MY_RGPD_TEXT_TRADE', 'You have <b>{TYPE}</b> the term "{TERM}" | Date: {DATE} h.');
define('MY_RGPD_ACCEPT', 'accepted');
define('MY_RGPD_DENEY', 'denied');
define('RGPD_EMAIL_CUSTOMER_DELETE_NOTIFY_SUBJECT', '[Required action] Your customer account in ' . STORE_NAME . ' is going to be eliminated');
define('RGPD_EMAIL_CUSTOMER_DELETE_NOTIFY', '<span style="font-size: 24px;">Estimado {USERNAME},</span><br/><br/>Te informamos de que según la normativa de la RGPD, con fecha {DATE} <strong>sus datos van a ser eliminados de nuestro sistema de forma automática en un periodo de {DAYS} días</strong>.<br><br>Si desea conservar tus datos de cliente en nuestra tienda, necesitamos que <strong>accedas a tu cuenta antes de la fecha</strong>. Para ello puedes acceder cliqueando en el siguiente botón:<br/><br/><a href="{LINK}" style="background-color: #1d9896; border: 1px solid #1d9896; border-radius: 3px; color: #ffffff; display: inline-block; font-family: sans-serif; font-size: 16px; line-height: 44px; text-align: center; text-decoration: none; -webkit-text-size-adjust: none; mso-hide: all; padding: 10px 40px;">ACCEDER A MI CUENTA</a><br/><br/>En el caso de que no recibamos un acceso a su cuenta, llegada la fecha mencionada el sistema borrará automáticamente todos sus datos.<br/><br/>Un saludo de parte del equipo de ' . STORE_NAME);
define('RGPD_WINDOW_MODAL_TITLE_DOB', 'Are you over 16?');
define('RGPD_WINDOW_MODAL_SUBTITLE_DOB', 'Due to the new European Data Protection Law, you must be 16 years or older as stable Article 8 of the RGPD, to be able to continue on this site you must accept these terms before registering as a customer.');
define('RGPD_WINDOW_MODAL_DOB_DENEGATE', 'I\'m not older');
define('RGPD_WINDOW_MODAL_DOB_ACCEPT', 'Accept and continue');
define('ENTRY_DATE_OF_BIRTH_OLD_ERROR', 'Due to the European Data Protection Act, you must be 16 years or older as stable Article 8 of the RGPD, in order to register as a customer on this site you must comply with these terms.');
define('RGPD_ACCOUNT_DISABLE_TITLE', 'Oooh ... Your Account is Off!');
define('RGPD_ACCOUNT_DISABLE_TEXT', 'How happy we are to have you back here! I do not know if you remember, that last day <strong> {DATE} h. </ Strong>, you asked us to deactivate your account temporarily. <br> <br> With this your data has been restricted during all this time according to the rules of the RGPD in force, but do not worry! Our system can reactivate your account in just a few seconds if you wish. <br> <br> Do you dare? Click on the next button to proceed!');

define('RGPD_EMAIL_ACTIVE_SUBJECT', 'Account activated, how happy we are to see you again ' . STORE_NAME . '!');
define('RGPD_EMAIL_ACTIVE', '<span style="font-size: 24px;">¡Hi {USERNAME}!</span><br/><br/>
We confirm you through this e-mail, that with date {DATE} your account has been activated again so that you can re-use all the features of our online store. <br/> <br/> I\'m glad to see you again back! <br/> <br/> Greetings from the team ' . STORE_NAME);
define('BUTTON_DISABLE', 'Deactivate');
define('RGPD_EMAIL_DISABLE_SUBJECT', 'Account Deactivated - That you are sorry that you abandon us... ' . STORE_NAME);
define('RGPD_EMAIL_DISABLE', '<span style="font-size: 24px;">Dear {USERNAME},</span><br/><br/>
	We confirm through this e-mail, that with date {DATE} your account has been deactivated temporarily. As you already know, you can activate it again when you want with just one of the following options:<br/><br/>
	&nbsp;&nbsp;<strong>- Log in into your account:</strong> just by accessing your user account again in our online store, you can activate it.<br/>
	&nbsp;&nbsp;<strong>- Request by e-mail / telephone:</strong> If you wish, you can notify us and our team will activate it for you again.<br/><br/>
	We hope to see you again soon, it\'s been a shame that you abandoned us!<br><br>
	Greetings from ' . STORE_NAME . '\'s team ');

define('PRODUCTS_DESCATALOGADO', 'We are sorry, but <span>this product is discontinued</span> and will not be available for purchase again.');
define('PRODUCTS_DESCATALOGADO_2', 'Related products:');

define('STOCK_LEFT_SINGULAR', 'Only <strong>1 unit</strong> left');
define('STOCK_LEFT_PLURAL', '<strong>%s</strong> units left');

define('AJAX_PAYPAL_TITLE', 'IMPORTANT ANNOUNCEMENT!');
define('AJAX_PAYPAL_SUBTITLE', 'Attentive to the address of your Paypal');
define('AJAX_PAYPAL', 'You will be redirected to Paypal, please log in. Please confirm that the amount and delivery address of our website are correct before making the payment. The shipping address must match the one that appears on your Paypal profile.
<br /><br />
Once the payment is completed you will be redirected back to our website.
<br /><br />
¡Gracias!.');
define('AJAX_PAYPAL_SOBRECARGO', '<br><br><b style="color:#b75e5e;">ATTENTION!</b> This payment method has a surcharge of ');
define('VIEW_CART', 'Back to cart');

define('MAYBE_YOU_WANTED_TO_SAY', 'Maybe you wanted to say');

define( 'AJAX_BAJO_DEMANDA', 'You have included in your purchase a product <b>on request</b>, the delivery time can be <b>longer than 30 days</b>.' );

// TRADUCCIONES REDISEÑO //
define( 'TEXT_ATENDEMOS', 'We attend only' );
define( 'TEXT_HE_COMPRADO', 'I have bought other times here' );
define( 'TEXT_YA_CLIENTE', 'I\'m already a customer' );
define( 'TEXT_QUIERO_REGISTR', 'I want to register' );
define( 'TEXT_NUEVO_CLIENTE', 'New customer' );
define( 'TEXT_NUEVO_INFO', 'By creating an account at francobordo.com you can make your purchases quickly in our virtual store, check the status of your orders and check your previous operations.<br/><br/>Ahead! We were waiting for you.' );
define( 'TEXT_ACCEDER', 'Access the' );
define( 'TEXT_DISTRI_INFO', 'Register and take advantage of the discounts and advantages of being a Professional of Nautical</p><p>Join the more than 500 Nautical Professionals now' );
define( 'TEXT_REGISTRO_PROFES', 'professional register' );
define( 'TEXT_PORTES_GRATIS', 'Shipping will be <u>free</u> for those orders that exceed %s€ Take advantage of it!</span><span class="dhide">Free shipping for orders over %s€ Take advantage of it!' );
define( 'TEXT_PLACE_SEARCH', 'Write here what you are looking' );
define( 'TEXT_VOLVER', 'back to' );
define( 'TEXT_SECCION_ANT', 'previous section' );
define( 'TEXT_SECCION_TODAS', 'all sections' );
define( 'TEXT_PORTES_CARRITO', 'In this order you have FREE shipping!' );
define( 'TEXT_ESCRIBE', 'Write your' );
define( 'TEXT_AVISO_LEGAL', 'Legal Notice' );
define( 'TEXT_COOKIES', 'Cookies policy' );
define( 'TEXT_ENVIOS_DEVO', 'Shipping & Returns' );
define( 'TEXT_CONFIG_COOKIES', 'Cookies config' );
define( 'TEXT_DENOX', 'Developed by' );
define( 'TEXT_SELEC_IDIOMA', 'Select your language' );
define( 'TEXT_VER_NOVEDADES', 'see all<span class="mhide"> Novelty</span>' );
define( 'TEXT_VER_OFERTAS', 'see all<span class="mhide"> Specials</span>' );
define( 'TEXT_DESTACADOS_EN', 'Featured articles in' );
define( 'TEXT_VER_DESTACADOS', 'View all Featured ' );
define( 'TEXT_VER_DESTACADOS2', 'see all<span class="mhide"> featured</span>' );
define( 'TEXT_VER_MARCAS', 'see all brands' );
define( 'TEXT_MOSTRAR_PAG', 'Showing <b class="ctdrows">%s</b> <span class="ml-auto-mx">of <b>%s</b> articles</span>' );
define( 'TEXT_BUSCAR_MARCAR', 'Write hear the brand that you are looking for' );
define( 'TEXT_AVISEME', 'Let me know!' );
define( 'TEXT_BAJO_PEDIDO', 'on request' );
define( 'TEXT_ENTREGA_EN', 'Delivery in %s days' );
define( 'TEXT_ENTREGA_SUPR', 'It can be more than 30 days' );
define( 'TEXT_ENTREGA_24', '<b>In Stock</b>' );
define( 'TEXT_ENTREGA_24_2', '<b>In Stock</b>' );
define( 'TEXT_SIN_STOCK', 'No stock' );
define( 'TEXT_IVA', 'TAX' );
define( 'TEXT_COMPARAR', 'Compare' );
define( 'TEXT_VER_MAS_PRODUCT', 'see more products' );
define( 'TEXT_VER_ANTER', 'see previous products' );
define( 'TEXT_DEJAR_OPINION', 'Give your opinion' );
define( 'TEXT_VER_OPCIONES', 'see options' );
define( 'TEXT_ULT_UNID', 'LAST UNITS' );
define( 'TEXT_ENVIO_GRATIS', 'This article has <b>FREE SHIPPING</b>' );
define( 'TEXT_PEDIDO_MINIMO', 'ATENTION:</b> Minimum order <b>%s</b> units' );
define( 'TEXT_PUNTOS_ACUMU', 'With the purchase of this article you accumulate <b>%s points for your next purchase</b>' );
define( 'TEXT_DUDA', 'Any questions about this article?' );
define( 'TEXT_DUDA_2', 'Here we solve your doubts' );
define( 'TEXT_MEJOR_PRECIO', '<b>The best price, guaranteed!</b> Have you seen it cheaper?' );
define( 'TEXT_INFORMANOS', 'Inform us' );
define( 'TEXT_DESCARGAR', 'Download' );
define( 'TEXT_FICHA_PDF', 'File in PDF' );
define( 'TEXT_DOCUMENTACION', 'Documentation' );
define( 'TEXT_ANADIR', 'add' );
define( 'TEXT_GRATIS', 'Free!' );
define( 'TEXT_REPUESTOS', 'Do you need spare parts for this item?' );
define( 'TEXT_OTROS_ARTICULOS', 'other items in the category' );
define( 'TEXT_CLIENTES_COMPRARON', 'Customers who bought this product' );
define( 'TEXT_PRODUCTOS_DEMANDA', 'Products on request' );
define( 'TEXT_DESCUENTO_CANTIDADES', 'Quantity discounts' );
define( 'TEXT_CANTIDAD', 'Quantity' );
define( 'TEXT_PRECIO_UNIDAD', 'Price by unit' );
define( 'TEXT_COMPARAR_PRODUCTOS', 'compare products' );
define( 'AUTHORIZE_DATA', 'I authorize the processing of my data in order to manage the contracting of products or services offered by FRANCOBORDO.' );

define ('SELECT_COUNTRY_ZONE_CITY', 'You must select a province or zip code');
define( 'SELECT_COUNTRY_CITY_NOT_FOUND', 'Can\'t find your city? Click here' );
define( 'SELECT_COUNTRY_CITY_NOT_FOUND_PLACEHOLDER', 'Write the name of your city' );
define('ENTRY_IAE_ERROR', 'The file IAE must be pdf, doc, png or jpg');
define('EMAIL_NO_MODEL', 'No model');
define('TEXT_SELECCIONE', 'Select...');
define('ATTRIBUTES_TITLE_TEXT', 'Choose option:');

// TEXTOS PARA opinions
define('OPINIONS_TEXT_CUSTOMER_OPINIONS_HEADER', '<b>Opinions of our clients</b>');
define('OPINIONS_TEXT_CUSTOMER_OPINIONS', 'Customer reviews');
define('OPINIONS_TEXT_VIEW_ALL', 'view all opinions');
define('OPINIONS_TEXT_EXCELENT', 'Excelent');
define('OPINIONS_TEXT_BASED_COMMENTS', 'Based on %s comments');

// TEXTOS PARA opiniones
define('OPINIONS_TEXT_ANON_CUSTOMER', 'Anonymous Customer');
define('PROMOTIONS_TEXT_TITLE', 'Promotions');
define('TEXT_RESULTADOS', 'results');
