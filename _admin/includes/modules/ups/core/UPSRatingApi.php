<?php
/**
 * Created by PhpStorm.
 * User: djbuch
 * Date: 01/10/2015
 * Time: 15:17
 */
require_once 'UPSBaseApi.php';

class UPSRatingApi extends UPSBaseApi
{
    /**
     * Send a rating request, and returns the result. The currency returned is the currency of the origin country
     * ! you need to check the currency to determine if you need to convert it !
     * @param string $shipper_name
     * @param string $shipper_number
     * @param array $shipper_address address formated by UPSBaseApi::formatAddress
     * @param string $shipto_name
     * @param array $shipto_address address formated by UPSBaseApi::formatAddress
     * @param float $weight
     * @param string $serviceCode
     * @param bool $negotiatedPrices if true response will return account based negociated prices
     * @param array $accessorials formated by UPSBaseApi::formatAccessorials
     * @param null $accesspoint_name
     * @param null $accesspoint_address formated by UPSBaseApi::formatAddress
     * @param string $ShipmentIndicationType
     * @param null|array $dimensions
     * @param string $weightUnitCode KGS (default) or LBS
     * @param string $dimensionUnitCode CM (default) or IN
     * @param bool $LargePackageIndicator false (default), true if large package
     * @param null $shipfrom_name if null $shipper_name is used
     * @param null $shipfrom_address if null $shipper_address is used, else address formated by UPSBaseApi::formatAddress
     * @param string $PickupType
     * @param string $CustomerClassification
     * @return array|bool false if no response exists, else array('amount' (float), 'currency' (string))
     * @throws Exception
     */
    public function doRate(
        $shipper_name,
        $shipper_number,
        $shipper_address,
        $shipto_name,
        $shipto_address,
        $weight,
        $serviceCode = '11',
        $negotiatedPrices = false,
        $accessorials = array(),
        $accesspoint_name = null,
        $accesspoint_address = null,
        $ShipmentIndicationType = '02',
        $dimensions = null,
        $weightUnitCode = 'KGS',
        $dimensionUnitCode = 'CM',
        $LargePackageIndicator = false,
        $shipfrom_name = null,
        $shipfrom_address = null,
        $PickupType = '01',
        $CustomerClassification = '01'
    ) {
        if ($shipfrom_name === null) {
            $shipfrom_name = $shipper_name;
        }
        if ($shipfrom_address === null) {
            $shipfrom_address = array_merge($shipper_address);
        }

        //round weight depending on unit
        if ($weight <= 0) {
            throw new Exception("UPSRatingApi - doRate : weight cannot be zero");
        }
        if ($weightUnitCode == "LBS") {
            $weight = ceil(floatval($weight));
        } else {
            $weight = ceil(floatval($weight)*2)/2;
        }

        $this->setEndPointURL('webservices/Rate');
        $this->setWSDL(dirname(__FILE__)."/Rating/RateWS.wsdl");
        $this->setOperation("ProcessRate");

        $request = array(
            'Request' => array(
                'RequestOption' => 'Rate'
            ),
            'Shipment' => array(
                'PickupType' => array(
                    'Code' => $PickupType,
                ),
                'CustomerClassification' => array(
                    'Code' => $CustomerClassification,
                ),
                'Shipper' => array(
                    'Name' => $shipper_name,
                    'ShipperNumber' => $shipper_number,
                    'Address' => $shipper_address,
                ),
                'ShipTo' => array(
                    'Name' => $shipto_name,
                    'Address' => $shipto_address,
                ),
                'ShipFrom' => array(
                    'Name' => $shipfrom_name,
                    'Address' => $shipfrom_address,
                ),
                'Service' => array(
                    'Code' => $serviceCode,
                ),
                'Package' => array(
                    array(
                        'PackagingType' => array(
                            'Code' => '02',//always 02 as stated in the requirements
                        ),

                        'PackageWeight' => array(
                            'Weight' => $weight,
                            'UnitOfMeasurement' => array(
                                'Code' => $weightUnitCode,
                            )
                        )
                    )
                ),
                'ShipmentServiceOptions' => array()
            )
        );

        if ($negotiatedPrices) {
            $request['Shipment']['ShipmentServiceOptions']['NegotiatedRatesIndicator'] = '';
        }

        if (isset($accessorials["DeclaredValue"]) && is_array($accessorials["DeclaredValue"])) {
            $request['Shipment']['ShipmentServiceOptions']['DeclaredValue'] = $accessorials["DeclaredValue"];
        }

        if (isset($accessorials["Signature"]) && $accessorials["Signature"] !== false) {
            $request['Shipment']['ShipmentServiceOptions']['DeliveryConfirmation'] = array('DCISType' => $accessorials["Signature"]);
        }

        if ($accesspoint_address !== null && $accesspoint_name !== null) {
            if (isset($accessorials["AccessPointCOD"]) && is_array($accessorials["AccessPointCOD"])) {
                $ShipmentIndicationType = '01';
                $request['Shipment']['ShipmentServiceOptions']['AccessPointCOD'] = $accessorials["AccessPointCOD"];
            }
            if (isset($accessorials['ToAddresseeOnly']) && $accessorials['ToAddresseeOnly']) {
                $ShipmentIndicationType = '01';
                $request['Shipment']['ShipmentServiceOptions']['DeliverToAddresseeOnlyIndicator'] = '';
            }
            $request['Shipment']['ShipmentIndicationType'] = array(
                'Code' => $ShipmentIndicationType
            );
            $request['Shipment']['AlternateDeliveryAddress'] = array(
                'Name' => $accesspoint_name,
                'Address' => $accesspoint_address
            );
        }

        if (is_array($dimensions) && count($dimensions) == 3) {
            $request['Shipment']['Package'][0]['Dimensions'] = array(
                'Length' => $dimensions[0],
                'Width' => $dimensions[1],
                'Height' => $dimensions[2],
                'UnitOfMeasurement' => array(
                    'Code' => $dimensionUnitCode,
                )
            );
        }

        if ($LargePackageIndicator) {
            $request['Shipment']['LargePackageIndicator'] = '';
        }




        $this->setRequest($request);
        $this->send();
        $response = $this->getResponse();
        if ($response === false) {
            return false;
        }

        if ($response->Response->ResponseStatus->Code != '1') {
            throw new Exception("UPSRatingApi - doRate : an error occured " .
                $response->Response->ResponseStatus->Code . " - " . $response->Response->ResponseStatus->Description);
        }

        if (isset($response->RatedShipment->NegotiatedRateCharges->TotalCharge)) {
            $amount = floatval($response->RatedShipment->NegotiatedRateCharges->TotalCharge->MonetaryValue);
            $currency = $response->RatedShipment->NegotiatedRateCharges->TotalCharge->CurrencyCode;
        } else {
            $amount = floatval($response->RatedShipment->TotalCharges->MonetaryValue);
            $currency = $response->RatedShipment->TotalCharges->CurrencyCode;
        }

        return array('amount' => $amount, 'currency' => $currency);
    }

}
