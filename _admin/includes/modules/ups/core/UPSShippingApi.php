<?php
require_once 'UPSBaseApi.php';

class UPSShippingApi extends UPSBaseApi {

    /**
     * Send a ship request, and returns the tracking number and the base64 encoded GIF label.
     * @param string $shipper_name
     * @param string $shipper_number
     * @param string $shipper_attention_name
     * @param string $shipper_tax_id_number
     * @param array $shipper_address address formated by UPSBaseApi::formatAddress
     * @param array $shipper_phone_number
     * @param string $shipto_name
     * @param string $shipto_attention_name
     * @param array $shipto_address address formated by UPSBaseApi::formatAddress
     * @param string $shipto_phone_number
     * @param float $weight
     * @param string $serviceCode
     * @param string $description
     * @param bool $negotiatedPrices if true response will return account based negociated prices
     * @param array $accessorials formated by UPSBaseApi::formatAccessorials
     * @param null $accesspoint_name
     * @param null $accesspoint_id
     * @param null $accesspoint_address formated by UPSBaseApi::formatAddress
     * @param string $ShipmentIndicationType
     * @param null|array $dimensions
     * @param string $weightUnitCode KGS (default) or LBS
     * @param string $dimensionUnitCode CM (default) or IN
     * @param bool $LargePackageIndicator false (default), true if large package
     * @param null $shipfrom_name if null $shipper_name is used
     * @param null $shipfrom_attention_name if null $shipper_attention_name is used
     * @param null $shipfrom_address if null $shipper_address is used, else address formated by UPSBaseApi::formatAddress
     * @param null $shipfrom_phone_number if null $shipper_phone_number is used
     * @param string $PickupType
     * @param string $CustomerClassification
     * @return array|bool false if no response exists, else array('tracking_number' (string), 'gif_base64' (string))
     * @throws Exception
     */
    public function doShipment(
        $shipper_name,
        $shipper_number,
        $shipper_attention_name,
        $shipper_tax_id_number,
        $shipper_address,
        $shipper_phone_number,
        $shipto_name,
        $shipto_attention_name,
        $shipto_address,
        $shipto_phone_number,
        $weight,
        $serviceCode = '11',
        $description = '',
        $negotiatedPrices = false,
        $accessorials = array(),
        $accesspoint_name = null,
        $accesspoint_id = null,
        $accesspoint_address = null,
        $ShipmentIndicationType = '02',
        $dimensions = null,
        $weightUnitCode = 'KGS',
        $dimensionUnitCode = 'CM',
        $LargePackageIndicator = false,
        $shipfrom_name = null,
        $shipfrom_attention_name = null,
        $shipfrom_address = null,
        $shipfrom_phone_number = null,
        $PickupType = '01',
        $CustomerClassification = '01'
    ) {
        if ($shipfrom_name === null) {
            $shipfrom_name = $shipper_name;
        }
        if ($shipfrom_address === null) {
            $shipfrom_address = array_merge($shipper_address);
        }
        if ($shipfrom_attention_name === null) {
            $shipfrom_attention_name = $shipper_attention_name;
        }
        if ($shipfrom_phone_number === null) {
            $shipfrom_phone_number = $shipper_phone_number;
        }

        //round weight depending on unit
        if ($weight <= 0) {
            throw new Exception("UPSShippingApi - doShipment : weight cannot be zero");
        }
        echo $weight;
        if ($weightUnitCode == "LBS") {
            $weight = ceil(floatval($weight));
        } else {
            $weight = ceil(floatval($weight)*2)/2;
        }

        echo $weight;

        $this->setEndPointURL('webservices/Ship');
        $this->setWSDL(dirname(__FILE__)."/Shipping/Ship.wsdl");
        $this->setOperation("ProcessShipment");

        $request = array(
            'Request' => array(
                'RequestOption' => 'nonvalidate'
            ),
            'Shipment' => array(
                'Description' => $description,
                'PickupType' => array(
                    'Code' => $PickupType,
                ),
                'CustomerClassification' => array(
                    'Code' => $CustomerClassification,
                ),
                'Shipper' => array(
                    'Name' => $shipper_name,
                    'AttentionName' => $shipper_attention_name,
                    'TaxIdentificationNumber'=> $shipper_tax_id_number,
                    'ShipperNumber' => $shipper_number,
                    'Address' => $shipper_address,
                    'Phone' => array(
                        'Number' => $shipper_phone_number,
                    )
                ),
                'ShipTo' => array(
                    'Name' => $shipto_name,
                    'AttentionName' => $shipto_attention_name,
                    'Address' => $shipto_address,
                    'Phone' => array(
                        'Number' => $shipto_phone_number,
                    )
                ),
                'ShipFrom' => array(
                    'Name' => $shipfrom_name,
                    'AttentionName' => $shipfrom_attention_name,
                    'Address' => $shipfrom_address,
                    'Phone' => array(
                        'Number' => $shipfrom_phone_number,
                    )
                ),
                'Service' => array(
                    'Code' => $serviceCode,
                ),
                'PaymentInformation' => array(
                    'ShipmentCharge' => array(
                        'Type' => '01',
                        'BillShipper' => array(
                            'AccountNumber' => $shipper_number
                        )
                    )
                ),
                'Package' => array(
                    array(
                        'Packaging' => array(
                            'Code' => '02',//always 02 as stated in the requirements
                        ),
                        'PackageWeight' => array(
                            'Weight' => $weight,
                            'UnitOfMeasurement' => array(
                                'Code' => $weightUnitCode,
                            )
                        ),
                        'PackageServiceOptions' => array()
                    )
                ),
                'ShipmentServiceOptions' => array(),
                'LabelSpecification' => array(
                    'LabelImageFormat' => array(
                        'Code' => 'GIF'
                    )
                )
            )
        );

        if ($negotiatedPrices) {
            $request['Shipment']['ShipmentRatingOptions'] = array();
            $request['Shipment']['ShipmentRatingOptions']['NegotiatedRatesIndicator'] = '';
        }

        if (isset($accessorials["DeclaredValue"]) && is_array($accessorials["DeclaredValue"])) {
            $request['Shipment']['Package'][0]['PackageServiceOptions']['DeclaredValue'] = $accessorials["DeclaredValue"];
        }

        if (isset($accessorials["Signature"]) && $accessorials["Signature"] !== false) {
            $request['Shipment']['ShipmentServiceOptions']['DeliveryConfirmation'] = array('DCISType' => $accessorials["Signature"]+1);
        }

        if ($accesspoint_address !== null && $accesspoint_name !== null) {
            if (isset($accessorials["AccessPointCOD"]) && is_array($accessorials["AccessPointCOD"])) {
                $ShipmentIndicationType = '01';
                $request['Shipment']['Package'][0]['PackageServiceOptions']['AccessPointCOD'] = $accessorials["AccessPointCOD"];
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
                'AttentionName' => $accesspoint_name,
                'UPSAccessPointID' => $accesspoint_id,
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
            $request['Shipment']['Package'][0]['LargePackageIndicator'] = '';
        }


        $this->setRequest($request);
        $this->send();
        $response = $this->getResponse();
        if ($response === false) {
            return false;
        }

        if ($response->Response->ResponseStatus->Code != '1') {
            throw new Exception("UPSShippingApi - doShipment : an error occured " .
                $response->Response->ResponseStatus->Code . " - " . $response->Response->ResponseStatus->Description);
        }

        $tracking_number = $response->ShipmentResults->PackageResults->TrackingNumber;

        $gif_base64 = $response->ShipmentResults->PackageResults->ShippingLabel->GraphicImage;

        return array('tracking_number' => $tracking_number, 'gif_base64' => $gif_base64);
    }
   
}
