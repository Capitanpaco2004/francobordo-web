<?php
/**
 * Created by PhpStorm.
 * User: djbuch
 * Date: 07/10/2015
 * Time: 16:13
 */

class UpsIOHelper
{

    /**
     * @param array $orders array of id_order
     * @param int $type one of the const EXPORT_WORLDSHIP, EXPORT_UPS, EXPORT_PDF
     */
    public static function exportOrders(array $orders, $type = UpsExports::EXPORT_WORLDSHIP, $debug = false)
    {
        if ($type === UpsExports::EXPORT_PDF) {
            // TCPDF cargado via Composer autoload (tecnickcom/tcpdf)

	        if($debug){
				$accessKey = '7CF75FD7BD75A775';
	   			$username = 'AGENCEW360';
	   			$password = '*UPS*777*';
			}
			else{
				$accessKey = Tools::getConfigValue('MODULE_SHIPPING_UPS_ACCESS_KEY');
	   			$username = Tools::getConfigValue('MODULE_SHIPPING_UPS_USERNAME');
	   			$password = Tools::getConfigValue('MODULE_SHIPPING_UPS_PASSWORD');
			}
            $shippingApi = new UPSShippingApi(
                $accessKey,
                $username,
                $password,
                $debug
            );
			ob_clean();
           
            $ups_PDF = new TCPDF('P', 'mm', array(100,150));
            $ups_PDF->SetMargins(0.1, 0.1);
            $ups_PDF->SetAutoPageBreak(false, 0);

            foreach ($orders as $id_order) {
            	$order = new order((int)$id_order);
        		if (is_object($order)) {
                    $shippingInfo = self::getShippingInfo($shippingApi, $order, $id_order);
                  	if ($shippingInfo !== false) {
                        $data = base64_decode($shippingInfo['gif_base64']);
                        $source = imagecreatefromstring($data);
                        $rotate = imagerotate($source, 270, 0);
                        ob_start();
                        imagegif($rotate);
                        $data = ob_get_contents(); // read from buffer
                        ob_end_clean(); // delete buffer
                        imagedestroy($source);
                        imagedestroy($rotate);
                        $ups_PDF->AddPage();
                        $ups_PDF->Image('@' . $data, '', '', 100);
                        $upsExport = new UpsExports();
                        $upsExport->type = (string)$type;
                        $upsExport->orders = array($id_order);
                        $upsExport->image = $shippingInfo['gif_base64'];
                        $upsExport->save();
                        //self::setTrackingNumber($order, $shippingInfo['tracking_number']);
                    }
                }
            }
            $ups_PDF->Output('PDF-shipping-mark-'.$upsExport->id_export.'.pdf', 'D');
        } else {
            $upsExport = new UpsExports();
            $upsExport->type = (string)$type;
            $upsExport->orders = $orders;
            $upsExport->save();

            $handle = fopen(dirname(__FILE__)."/../exports/export-".$upsExport->id_export.".csv", "w+");
            $first = true;
            foreach ($orders as $id_order) {
                $csv = self::getOrderCSV($id_order, $type);
                if ($csv !== false) {
                    if ($first && $type == UpsExports::EXPORT_WORLDSHIP) {
                        fputcsv($handle, array_keys($csv),',', '"');
                    }
                    fputcsv($handle, array_values($csv), ',', '"');
                }
                $first = false;
            }
            fclose($handle);

            $upsExport->date_add = date("Y-m-d h:i:s");
            $upsExport->save();

            $_GET["filename"] = "export-".$upsExport->id_export.".csv";
            include dirname(__FILE__)."/../download.php";
        }
    }


    /**
     * Generated a CSV for an order
     * @param int $id_order
     * @param int $variant one of the const EXPORT_WORLDSHIP, EXPORT_UPS
     * @return bool|array false if not available, else asso array header => value, use array_values to get only values
     */
    public static function getOrderCSV($id_order, $variant = UpsExports::EXPORT_WORLDSHIP)
    {
        if ($variant !== UpsExports::EXPORT_WORLDSHIP && $variant !== UpsExports::EXPORT_UPS) {
            return false;
        }
        $order = new order((int)$id_order);
        if (is_object($order)) {
            $upsSelectedService = UpsSelectedService::getSelectedServiceByOrderID((int)$id_order);
        }
        
        if ($upsSelectedService) {
            $shipping_address = $order->delivery['street_address']. ' ' . $order->delivery['suburb'];
            $shipping_country = Tools::getCountryCodeFromCountryName($order->delivery['country']);
            $shipper_country = Tools::getCountryCode($upsSelectedService->getService()->getAccount()->id_country);
            $order_total_weight = round($upsSelectedService->order_weight, 3)/1000;
            $weight_unit = 'g';
            
            if ($variant === UpsExports::EXPORT_UPS) {
                $csv_body = array(
                    'Contact Name' => substr($order->delivery['name'],0,35), /* Required : Yes (for an international movement or a UPS Next Day Air Early service) - Name of recipient (# entered in front of the first field results in an error)*/
                    'Company or Name' => substr((trim($order->delivery['company']) == '' ? $order->delivery['name'] : $order->delivery['company']),0,35), /* Required : Yes - Company or name of recipient*/
                    'Country' => substr($shipping_country,0,2), /* Required : Yes - Recipient's country. See Country Code table for valid codes.*/
                    'Address 1' => substr($order->delivery['street_address'],0,35), /* Required : Yes - Address 1 for recipient (required)*/
                    'Address 2' => substr($order->delivery['suburb'],0,35), /* Required : No - Address 2 for recipient (optional)*/
                    'Address 3' => substr('',0,35), /* Required : No - Address 3 for recipient (optional)*/
                    'City' => substr($order->delivery['city'],0,30), /* Required : Yes - Recipient's city*/
                    'State/Province/Other' => substr(isset($shipping_state) ? $shipping_state->iso_code : '',0,30), /* Required : Conditional - Required for certain destination countries. See State/Province code table for valid codes.*/
                    'Postal Code' => substr($order->delivery['postcode'],0,10), /* Required : Conditional - Required for certain destination countries.*/
                    'Telephone' => substr($order->customer['telephone'],0,15), /* Required : Conditional - Required for international destinations and UPS Next Day Air Early service.*/
                    'Extension' => substr('',0,5), /* Required : No - Recipient's telephone extension*/
                    'Residential Indicator' => substr('1',0,1), /* Required : No - 1=Residential; 0=Commercial*/
                    'E-mail address' => substr($order->customer['email_address'],0,50), /* Required : No - Recipient's e-mail address*/
                    'Packaging Type' => substr('2',0,2), /* Required : Yes - See Packaging Type table for valid codes.*/
                    'Customs Value' => substr('',0,15), /* Required : Conditional - Required from US to CA and US to PR movements. Currency defaults to USD. Decimals allowed.*/
                    'Weight' => substr((($upsSelectedService->order_weight && $upsSelectedService->order_weight > 0) ? number_format($upsSelectedService->order_weight, 2, ",", "") : '1,00'),0,5), /* Required : Conditional - Required for a packaging type of Other Packaging = 2. Weights are accepted optionally for a packaging type of Letter/Envelope. Use commas in numeric fields, such as weights (10,0) they would have to encase that field in double - quotation marks.*/
                    'Length' => substr('',0,4), /* Required : No - Defaults to inches for US/PR; centimeters everywhere else. CA has choice between inches and centimeters.*/
                    'Width' => substr('',0,4), /* Required : No - Defaults to inches for US/PR; centimeters everywhere else. CA has choice between inches and centimeters.*/
                    'Height' => substr('',0,4), /* Required : No - Defaults to inches for US/PR; centimeters everywhere else. CA has choice between inches and centimeters.*/
                    'Unit of Measure' => 'kgs', /* Required : No - Defaults to pounds/lbs. (US/PR) or kilograms/kgs. CA has choice between lbs./kgs.*/
                    'Description of Goods' => substr('PS_2.0',0,35), /* Required : Conditional - Conditionally required when Ship To/Ship From countries are not the same.*/
                    'Documents of No Commercial Value' => substr('0',0,1), /* Required : No - Indicates the shipment does not contain any dutiable items.*/
                    'GNIFC (Goods not in Free Circulation)' => substr('0',0,1), /* Required : Conditional - Required for movements within the EU when the goods are dutiable.*/
                    'Declared Value' => substr(number_format(min($upsSelectedService->order_amount, 1000), 0, ",", ""), 0, 7), /* Required : No - Must be less than 1000 USD or local equivalent if entered. Denominaton will default to the currency of the shipment origin country. UPS's liability for loss or damage to a package. No decimals allowed.*/
                    'Service' => substr($upsSelectedService->getService()->service_code,0,2), /* Required : Yes - See Service table for valid values.*/
                    'Delivery Confirmation' => substr(($upsSelectedService->signature == UPSBaseApi::SIGNATURE ? 'S' : ($upsSelectedService->signature == UPSBaseApi::SIGNATURE_ADULT ? 'A' : 'N')),0,1), /* Required : No - See Delivery Confirmation table for valid values.*/
                    'Shipper Release/Deliver Wthout Signature' => substr('',0,1), /* Required : No - A shipper may request to UPS release a package on the first delivery attempt without a signature. UPS will not be liable to shippers or third parties for any damages arising from the release of the package. (Not supported outside of US)*/
                    'Return of Document' => substr('',0,1), /* Required : No - Only used for Poland to Poland movements.*/
                    'Deliver on Saturday' => substr('',0,1), /* Required : No - Available with select services. Refer to service guide for additional information.*/
                    'UPS Carbon Neutral' => substr('',0,1), /* Required : No - Request the option to offset the climate impact of your shipment.*/
                    'Large Package' => substr('',0,1), /* Required : No - Applied when length plus girth (2 * width) + (2* height) combined exceeds 130 inches, but is less than 165 inches. Surcharge accessed. (Outside of US, large package limit is 419cms.)*/
                    'Additional Handling' => substr('',0,4), /* Required : No - For US: Applied when an article is encased in an outside shipping container made of metal or wood, packages exceed 70 lbs. or 32 kg., longest side exceeds 150cm. Refer to service guide for additional information. Outside of US: If weight exceeds 32 kg. or the longest side exceeds 150cm then additional handling will be automatically supplied.*/
                    'Reference 1' => substr((int)$id_order,0,35), /* Required : No - Used to record information about a package.*/
                    'Reference 2' => substr('',0,35), /* Required : No - Used to record information about a package.*/
                    'Reference 3' => substr('',0,35), /* Required : No - Only used in UPS CampusShip package level packaging (US, PR, and CA).*/
                    'E-mail Notification 1 - Address' => substr($order->customer['email_address'],0,50), /* Required : No - E-mail address that will receive the specified notification (ship, exception, or delivery).*/
                    'E-mail Notification 1 - Ship' => substr('1',0,1), /* Required : No - E-mail notification sent when the package is shipped.*/
                    'E-mail Notification 1 - Exception' => substr('1',0,1), /* Required : No - E-mail notification sent when there has been an exception in delivering the package.*/
                    'E-mail Notification 1 - Delivery' => substr('1',0,1), /* Required : No - E-mail notification sent upon package delivery.*/
                    'E-mail Notification 2 - Address' => substr('',0,50), /* Required : No - E-mail address that will receive the specified notification (ship, exception, or delivery).*/
                    'E-mail Notification 2 - Ship' => substr('',0,1), /* Required : No - E-mail notification sent when the package is shipped.*/
                    'E-mail Notification 2 - Exception' => substr('',0,1), /* Required : No - E-mail notification sent when there has been an exception in delivering the package.*/
                    'E-mail Notification 2 - Delivery' => substr('',0,1), /* Required : No - E-mail notification sent upon package delivery.*/
                    'E-mail Notification 3 - Address' => substr('',0,50), /* Required : No - E-mail address that will receive the specified notification (ship, exception, or delivery).*/
                    'E-mail Notification 3 - Ship' => substr('',0,1), /* Required : No - E-mail notification sent when the package is shipped.*/
                    'E-mail Notification 3 - Exception' => substr('',0,1), /* Required : No - E-mail notification sent when there has been an exception in delivering the package.*/
                    'E-mail Notification 3 - Delivery' => substr('',0,1), /* Required : No - E-mail notification sent upon package delivery.*/
                    'E-mail Notification 4 - Address' => substr('',0,50), /* Required : No - E-mail address that will receive the specified notification (ship, exception, or delivery).*/
                    'E-mail Notification 4 - Ship' => substr('',0,1), /* Required : No - E-mail notification sent when the package is shipped.*/
                    'E-mail Notification 4 - Exception' => substr('',0,1), /* Required : No - E-mail notification sent when there has been an exception in delivering the package.*/
                    'E-mail Notification 4 - Delivery' => substr('',0,1), /* Required : No - E-mail notification sent upon package delivery.*/
                    'E-mail Notification 5 - Address' => substr('',0,50), /* Required : No - E-mail address that will receive the specified notification (ship, exception, or delivery).*/
                    'E-mail Notification 5 - Ship' => substr('',0,1), /* Required : No - E-mail notification sent when the package is shipped.*/
                    'E-mail Notification 5 - Exception' => substr('',0,1), /* Required : No - E-mail notification sent when there has been an exception in delivering the package.*/
                    'E-mail Notification 5 - Delivery' => substr('',0,1), /* Required : No - E-mail notification sent upon package delivery.*/
                    'E-mail Message' => substr('',0,150), /* Required : No - Additional messaging or special instructions to be provided in the e-mail notification.*/
                    'E-mail Failure Address' => substr('',0,50), /* Required : No - E-mail address that will receive a notification if the e-mail notification to the recipient failed delivery.*/
                    'Location ID' => substr((string)$upsSelectedService->location_id,0,10), /* Required : No - Unique identifier for an alternate delivery location such as a UPS Access Point*/
                    'Notification Media Type' => substr('03',0,2), /* Required : No - See Media Types table for valid values.*/
                    'Notification Language' => substr('',0,6), /* Required : No - A list of valid country/language pairs. Default value based on origin/destination of shipment. See Notification Language table for valid values*/
                    'Notification Address' => substr($order->customer['email_address'],0,50), /* Required : No - Email address or mobile number used to receive delivery notification for packages delivered to an alternate delivery location.*/
                    'ADL Failure Address' => '',
                    'ADL COD Value' => substr(($upsSelectedService->access_point_cod ? number_format($upsSelectedService->order_amount, 2, ",", "") : ''),0,10), /* Required : No - Monetary amount to be collected at the UPS Access Point, must be specified in the currency of the UPS Access Point . Decimals allowed.*/
                    'ADL Deliver to Addressee' => substr($upsSelectedService->to_addressee_only ? '1' : '0',0,1), /* Required : No - 0 = option not requested 1 = Deliver To address only option requested*/
                    'ADL Shipper Media Type' => substr('',0,2), /* Required : No - See Media Types table for valid values.*/
                    'ADL Shipper Language' => substr('',0,6), /* Required : No - A list of valid country/language pairs. Default value based on origin/destination of shipment. See Notification Language table for valid values.*/
                    'ADL Shipper Notification' => substr('',0,50) /* Required : No - Email address or mobile number used for the shipper to receive notification when the package has arrived at the Access Point and when it has been picked up*/
                );
            } else {
                $csv_body = array(
                    'ShipmentInformationShipperNumber' => $upsSelectedService->getService()->getAccount()->account_number,
                    'ShipmentInformationDescriptionofGoods' => substr('PS_2.0',0,35), /* Required : Conditional - Conditionally required when Ship To/Ship From countries are not the same.*/
                    'ShipmentInformationDeclaredValueOption' => $upsSelectedService->declared_value == 1  ? 'Y' : 'N',
                    'ShipmentInformationDeclaredValueShipperPaid' => $upsSelectedService->declared_value == 1  ? 'Y' : 'N',
                    'ShipmentInformationDeclaredValueAmount' => $upsSelectedService->declared_value == 1  ? min($upsSelectedService->order_amount, 5000) : '0',
                    'ShipmentInformationServiceType' => substr($upsSelectedService->getService()->service_code,0,2), /* Required : Yes - See Service table for valid values.*/
                    'ShipmentInformationDeliveryConfirmationOption' => substr(($upsSelectedService->signature >= UPSBaseApi::SIGNATURE ? 'Y' : 'N'),0,1), /* Required : No - See Delivery Confirmation table for valid values.*/
                    'ShipmentInformationDeliveryConfirmationAdultSignatureRequired' => substr(($upsSelectedService->signature == UPSBaseApi::SIGNATURE_ADULT ? 'Y' : 'N'),0,1), /* Required : No - See Delivery Confirmation table for valid values.*/
                    'ShipmentInformationReference1' => substr($order->id,0,35), /* Required : No - Used to record information about a package.*/
                    'ShipmentInformationReference2' => substr('',0,35), /* Required : No - Used to record information about a package.*/
                    'ShipmentInformationActualWeight' => substr((($upsSelectedService->order_weight && $upsSelectedService->order_weight > 0) ? number_format($upsSelectedService->order_weight, 2, ",", "") : '1,00'),0,5), /* Required : Conditional - Required for a packaging type of Other Packaging = 2. Weights are accepted optionally for a packaging type of Letter/Envelope. Use commas in numeric fields, such as weights (10,0) they would have to encase that field in double - quotation marks.*/
                    //aucune info'UnitofMeasure' => substr(($weight_unit == 'kg' || $weight_unit == 'g' ? 'kgs':'lbs'),0,3), /* Required : No - Defaults to pounds/lbs. (US/PR) or kilograms/kgs. CA has choice between lbs./kgs.*/
                    'ShipmentInformationPackageType' => substr('CP',0,2), /* Required : Yes - See Packaging Type table for valid codes.*/
                    'ShipmentInformationQVNOption' => 'Y',
                    'ShipmentInformationQVNInTransitNotification1Option' => substr('Y',0,1), /* Required : No - E-mail notification sent when the package is shipped.*/
                    'ShipmentInformationQVNShipNotification1Option' => substr('Y',0,1), /* Required : No - E-mail notification sent when the package is shipped.*/
                    'ShipmentInformationQVNDeliveryNotification1Option' => substr('Y',0,1), /* Required : No - E-mail notification sent when the package is shipped.*/
                    'ShipmentInformationQVNExceptionNotification1Option' => substr('Y',0,1), /* Required : No - E-mail notification sent when the package is shipped.*/
                    'ShipmentInformationNotificationRecipient1CompanyorName' => substr((trim($order->delivery['company']) == '' ? $order->delivery['name'] : $order->delivery['company']),0,35), /* Required : No - E-mail notification sent when there has been an exception in delivering the package.*/
                    'ShipmentInformationNotificationRecipient1ContactName' => substr($order->delivery['name'],0,35), /* Required : No - E-mail notification sent upon package delivery.*/
                    'ShipmentInformationNotificationRecipient1Email' => substr($order->customer['email_address'],0,50),
                    'ShipmentInformationDelivertoAddresseeOnlyOption' => substr($upsSelectedService->to_addressee_only ? '1' : '0',0,1), /* Required : No - 0 = option not requested 1 = Deliver To address only option requested*/

                    'ShipToAttention' => substr($order->delivery['name'],0,35), /* Required : Yes (for an international movement or a UPS Next Day Air Early service) - Name of recipient (# entered in front of the first field results in an error)*/
                    'ShipToCompanyorName' => substr((trim($order->delivery['company']) == '' ? $order->delivery['name'] : $order->delivery['company']),0,35), /* Required : Yes - Company or name of recipient*/
                    'ShipToCountry' => substr($shipping_country,0,2), /* Required : Yes - Recipient's country. See Country Code table for valid codes.*/
                    'ShipToAddress1' => substr($order->delivery['street_address'],0,35), /* Required : Yes - Address 1 for recipient (required)*/
                    'ShipToAddress2' => substr($order->delivery['suburb'],0,35), /* Required : No - Address 2 for recipient (optional)*/
                    'ShipToCity' => substr($shipping_address->city,0,30), /* Required : Yes - Recipient's city*/
                    'ShipToState' => substr(isset($shipping_state) ? $shipping_state : '',0,30), /* Required : Conditional - Required for certain destination countries. See State/Province code table for valid codes.*/
                    'ShipToPostalCode' => substr($order->delivery['postcode'],0,10), /* Required : Conditional - Required for certain destination countries.*/
                    'ShipToTelephone' => substr($order->customer['telephone'],0,15), /* Required : Conditional - Required for international destinations and UPS Next Day Air Early service.*/
                    'ShipToResidentialIndicator' => substr('Y',0,1), /* Required : No - 1=Residential; 0=Commercial*/
                    'ShipToEmailAddress' => substr($order->customer['email_address'],0,50), /* Required : No - Recipient's e-mail address*/
                    //pas encore dispo sur WS'LocationID' => substr((string)$upsSelectedService->location_id,0,10), /* Required : No - Unique identifier for an alternate delivery location such as a UPS Access Point*/
                    //pas encore dispo sur WS'ADLCODValue' => substr(($upsSelectedService->access_point_cod ? number_format($upsSelectedService->order_amount, 2, ",", "") : ''),0,10), /* Required : No - Monetary amount to be collected at the UPS Access Point, must be specified in the currency of the UPS Access Point . Decimals allowed.*/

                    'ShipFromAttention' => substr($upsSelectedService->getService()->getAccount()->shipper_attention_name,0,35), /* Required : Yes (for an international movement or a UPS Next Day Air Early service) - Name of recipient (# entered in front of the first field results in an error)*/
                    'ShipFromCompanyorName' => substr($upsSelectedService->getService()->getAccount()->shipper_name,0,35), /* Required : Yes - Company or name of recipient*/
                    'ShipFromCountry' => substr($shipper_country,0,2), /* Required : Yes - Recipient's country. See Country Code table for valid codes.*/
                    'ShipFromAddress1' => substr($upsSelectedService->getService()->getAccount()->address_line_1,0,35), /* Required : Yes - Address 1 for recipient (required)*/
                    'ShipFromAddress2' => substr($upsSelectedService->getService()->getAccount()->address_line_2,0,35), /* Required : No - Address 2 for recipient (optional)*/
                    'ShipFromCity' => substr($upsSelectedService->getService()->getAccount()->city,0,30), /* Required : Yes - Recipient's city*/
                    'ShipFromState' => substr(isset($shipper_state) ? $shipper_state : '',0,30), /* Required : Conditional - Required for certain destination countries. See State/Province code table for valid codes.*/
                    'ShipFromPostalCode' => substr($upsSelectedService->getService()->getAccount()->postal_code,0,10), /* Required : Conditional - Required for certain destination countries.*/
                    'ShipFromTelephone' => substr($upsSelectedService->getService()->getAccount()->phone_number,0,15), /* Required : Conditional - Required for international destinations and UPS Next Day Air Early service.*/
                    'ShipFromTaxIDNumber' => $upsSelectedService->getService()->getAccount()->dni_number,
                    'ShipFromTaxIDType' => 'OTHER'
                );
            }

            return $csv_body;
        }
        return false;
    }


    public static function getShippingInfo(UPSShippingApi $shippingApi, $order, $id_order)
    {
        global $currency;
        try {
	     	$upsSelectedService = UpsSelectedService::getSelectedServiceByOrderID((int)$id_order);
	        
	     	if ($upsSelectedService) {
                $shipping_address = $order->delivery['street_address']. ' ' . $order->delivery['suburb'];
            	$shipping_country = Tools::getCountryCodeFromCountryName($order->delivery['country']);
            	$shipper_country = Tools::getCountryCode($upsSelectedService->getService()->getAccount()->id_country);
            	$order_total_weight = round($upsSelectedService->order_weight, 3)/1000;
            	$weight_unit = 'g';
            
               return $shippingApi->doShipment(
                    $upsSelectedService->getService()->getAccount()->shipper_name,
                    $upsSelectedService->getService()->getAccount()->account_number,
                    $upsSelectedService->getService()->getAccount()->shipper_attention_name,
                    $upsSelectedService->getService()->getAccount()->dni_number,
                    UPSShippingApi::formatAddress(
                        $upsSelectedService->getService()->getAccount()->address_line_1,
                        $upsSelectedService->getService()->getAccount()->address_line_2,
                        '',

                        $upsSelectedService->getService()->getAccount()->city,
                        (isset($shipper_state) ? $shipper_state->iso_code : ''),
                        $upsSelectedService->getService()->getAccount()->postal_code,
                        $shipper_country
                    ),
                    $upsSelectedService->getService()->getAccount()->phone_number,
                    substr((trim($order->delivery['company']) == '' ? $order->delivery['name'] : $order->delivery['company']), 0, 35),
                    substr($order->delivery['name'], 0, 35),
                    UPSShippingApi::formatAddress(
                        $order->delivery['street_address'],
                        $order->delivery['suburb'],
                        '',
                        $order->delivery['city'],
                        isset($shipping_state) ? $shipping_state : '',
                        $order->delivery['postcode'],
                        $shipping_country
                    ),
                    $order->customer['telephone'],
                    $order_total_weight,
                    $upsSelectedService->getService()->service_code,
                    "PS_2.0",
                    false,
                    UPSShippingApi::formatAccessorials(
                        ($upsSelectedService->declared_value?
                            array ('CurrencyCode' => strtoupper($currency),'MonetaryValue'=>min($upsSelectedService->order_amount, 5000))
                        : false),
                        ($upsSelectedService->access_point_cod?
                            array ('CurrencyCode' => strtoupper($currency),'MonetaryValue'=>$upsSelectedService->order_amount)
                            : false),
                        $upsSelectedService->to_addressee_only,
                        $upsSelectedService->signature
                    ),
                    $upsSelectedService->name,
                    $upsSelectedService->location_id,
                    ($upsSelectedService->name !== null && trim($upsSelectedService->name) != '' ?
                        UPSShippingApi::formatAddress(
                            $upsSelectedService->address,
                            '',
                            '',
                            $upsSelectedService->city,
                            '',
                            $upsSelectedService->postal_code,
                            $upsSelectedService->country_code
                        )
                    : null),
                    '02',
                    null,
                    ($weight_unit == 'kg' || $weight_unit == 'g' ? 'KGS':'LBS'),'CM',false, null, null,null,null,
                    $upsSelectedService->getService()->getAccount()->pickup_type
                );
            }
            return false;
        } catch(Exception $e) {
            return false;
        }
    }

    /**
     * @param $order
     * @param $tracking_number
     * @return array|bool false if nothing changed (tracking number was already known) else an
     *              array : array("sent" (bool) true if mail was sent,"tracking_number", "email", "id_order");
     * @throws PrestaShopException
     */
    public static function setTrackingNumber($order,$tracking_number)
    {
        if ($order->shipping_number != $tracking_number) {
            $order->shipping_number = $tracking_number;
            if (version_compare(_PS_VERSION_, '1.5', '>')) {
                $oc = new OrderCarrier($order->getIdOrderCarrier());
                $oc->tracking_number = $tracking_number;
                $oc->update();
            }
            $order->update();

            // Send mail to customer
            $customer = new Customer((int)$order->id_customer);
            $carrier = new Carrier((int)$order->id_carrier, $order->id_lang);
            $send = false;
            if (Validate::isLoadedObject($customer) && Validate::isLoadedObject($carrier)) {
                $templateVars = array(
                    '{followup}' => str_replace('@', $order->shipping_number, $carrier->url),
                    '{firstname}' => $customer->firstname,
                    '{lastname}' => $customer->lastname,
                    '{id_order}' => $order->id,
                    '{shipping_number}' => $order->shipping_number
                );
                if (version_compare(_PS_VERSION_, '1.5', '>')) {
                    $templateVars['{order_name}'] = $order->getUniqReference();
                    if (@Mail::Send((int)$order->id_lang, 'in_transit', Mail::l('Package in transit', (int)$order->id_lang), $templateVars,
                        $order->customer['email_address'], $customer->firstname . ' ' . $customer->lastname, null, null, null, null,
                        _PS_MAIL_DIR_, true, (int)$order->id_shop)
                    ) {
                        Hook::exec('actionAdminOrdersTrackingNumberUpdate', array('order' => $order, 'customer' => $customer, 'carrier' => $carrier), null, false, true, false, $order->id_shop);
                        $send = true;
                    }
                } else {
                    if (@Mail::Send((int)$order->id_lang, 'in_transit', Mail::l('Package in transit', (int)$order->id_lang), $templateVars,
                        $order->customer['email_address'], $customer->firstname . ' ' . $customer->lastname, null, null, null, null,
                        _PS_MAIL_DIR_, true)
                    ) {
                        $send = true;
                    }
                }

            }
            return array("sent"=>$send,"tracking_number"=>$tracking_number, "email"=>$order->customer['email_address'], "id_order"=>$order->id);
        }
        return false;
    }


    public static function importCsvFile($filename) {

        ini_set("auto_detect_line_endings", true);

        /*
         * get all information of CSV file
         */
        $row = 0;
        $infos = array();
        $handle = fopen($filename, "r");
        while (($data = fgetcsv($handle, 0, ',', '"')) !== FALSE) {
            for ($c = 0; $c < sizeof($data); $c++) {
                $infos[$row][$c] = $data[$c];
            }
            $row++;
        }

        /*
         * Extract all needed information
         */

        $type = 0;

        if (($keyId = array_search("Reference #1", $infos[0])) !== false) {
            $type = UpsExports::EXPORT_UPS;
            $keyShippingNumber = array_search('Shipment Tracking #', $infos[0]);
        } elseif (($keyId = array_search("ShipmentInformationReference1", $infos[0])) !== false) {
            $type = UpsExports::EXPORT_WORLDSHIP;
            $keyShippingNumber = array_search('ShipmentInformationLeadTrackingNumber', $infos[0]);
        } else {
            throw new Exception("The provided file is invalid");
        }

        unset($infos[0]); // delete csv header

        $res = array();
        foreach ($infos as $info) {
            $id_order = $info[$keyId];
            $tracking_number = $info[$keyShippingNumber];

            $order = new Order($id_order);
            if (Validate::isLoadedObject($order)) {
                $add_track = self::setTrackingNumber($order, $tracking_number);
                if ($add_track !== false)
                    $res[] = $add_track;
            }
        }
        return $res;
    }

}