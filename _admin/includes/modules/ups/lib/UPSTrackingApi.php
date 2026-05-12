<?php
/**
 * Created by PhpStorm.
 * User: djbuch
 * Date: 01/10/2015
 * Time: 15:16
 */
require_once 'UPSBaseApi.php';

class UPSTrackingApi extends UPSBaseApi
{

    const IS_DELIVERED = 1;
    const IS_IN_TRANSIT = 2;

    private $shipmentStatuses = array(
        '000' => array('description' => 'Status Not Available', 'internal_state' => self::IS_IN_TRANSIT),
        '003' => array('description' => 'Order Processed: Ready for UPS', 'internal_state' => self::IS_IN_TRANSIT),
        '005' => array('description' => 'In Transit', 'internal_state' => self::IS_IN_TRANSIT),
        '006' => array('description' => 'On Vehicle for Delivery', 'internal_state' => self::IS_IN_TRANSIT),
        '007' => array('description' => 'Shipment Information Voided', 'internal_state' => self::IS_IN_TRANSIT),
        '010' => array('description' => 'In Transit: On Time', 'internal_state' => self::IS_IN_TRANSIT),
        '011' => array('description' => 'Delivered', 'internal_state' => self::IS_DELIVERED),
        '012' => array('description' => 'Clearance in Progress', 'internal_state' => self::IS_IN_TRANSIT),
        '013' => array('description' => 'Exception', 'internal_state' => self::IS_IN_TRANSIT),
        '014' => array('description' => 'Clearance Completed', 'internal_state' => self::IS_IN_TRANSIT),
        '016' => array('description' => 'Held in Warehouse', 'internal_state' => self::IS_IN_TRANSIT),
        '017' => array('description' => 'Held for Customer Pickup', 'internal_state' => self::IS_IN_TRANSIT),
        '018' => array('description' => 'Delivery Change Requested: Hold for Pickup', 'internal_state' => self::IS_IN_TRANSIT),
        '019' => array('description' => 'Held for Future Delivery', 'internal_state' => self::IS_IN_TRANSIT),
        '020' => array('description' => 'Held for Future Delivery Requested', 'internal_state' => self::IS_IN_TRANSIT),
        '021' => array('description' => 'On Vehicle for Delivery Today', 'internal_state' => self::IS_IN_TRANSIT),
        '022' => array('description' => 'First Attempt Made', 'internal_state' => self::IS_IN_TRANSIT),
        '023' => array('description' => 'Second Attempt Made', 'internal_state' => self::IS_IN_TRANSIT),
        '024' => array('description' => 'Final Attempt Made', 'internal_state' => self::IS_IN_TRANSIT),
        '025' => array('description' => 'Transferred to Local Post Office for Delivery', 'internal_state' => self::IS_IN_TRANSIT),
        '026' => array('description' => 'Delivered by Local Post Office', 'internal_state' => self::IS_IN_TRANSIT),
        '027' => array('description' => 'Delivery Address Change Requested', 'internal_state' => self::IS_IN_TRANSIT),
        '028' => array('description' => 'Delivery Address Changed', 'internal_state' => self::IS_IN_TRANSIT),
        '029' => array('description' => 'Exception: Action Required', 'internal_state' => self::IS_IN_TRANSIT),
        '030' => array('description' => 'Local Post Office Exception', 'internal_state' => self::IS_IN_TRANSIT),
        '032' => array('description' => 'Adverse Weather May Cause Delay', 'internal_state' => self::IS_IN_TRANSIT),
        '033' => array('description' => 'Return to Sender Requested', 'internal_state' => self::IS_IN_TRANSIT),
        '034' => array('description' => 'Returned to Sender', 'internal_state' => self::IS_IN_TRANSIT),
        '035' => array('description' => 'Returning to Sender', 'internal_state' => self::IS_IN_TRANSIT),
        '036' => array('description' => 'Returning to Sender: In Transit', 'internal_state' => self::IS_IN_TRANSIT),
        '037' => array('description' => 'Returning to Sender: On Vehicle for Delivery', 'internal_state' => self::IS_IN_TRANSIT),
        '038' => array('description' => 'Picked Up', 'internal_state' => self::IS_IN_TRANSIT),
        '039' => array('description' => 'In Transit by Post Office', 'internal_state' => self::IS_IN_TRANSIT),
        '040' => array('description' => 'Delivered to UPS Access Point Awaiting Customer Pickup', 'internal_state' => self::IS_IN_TRANSIT),
        '041' => array('description' => 'Service Upgrade Requested', 'internal_state' => self::IS_IN_TRANSIT),
        '042' => array('description' => 'Service Upgraded', 'internal_state' => self::IS_IN_TRANSIT),
        '043' => array('description' => 'Voided Pickup', 'internal_state' => self::IS_IN_TRANSIT),
        '044' => array('description' => 'In Transit to UPS', 'internal_state' => self::IS_IN_TRANSIT),
        '045' => array('description' => 'Order Processed: In Transit to UPS', 'internal_state' => self::IS_IN_TRANSIT),
        '046' => array('description' => 'Delay', 'internal_state' => self::IS_IN_TRANSIT),
        '047' => array('description' => 'Delay', 'internal_state' => self::IS_IN_TRANSIT),
        '048' => array('description' => 'Delay', 'internal_state' => self::IS_IN_TRANSIT),
        '049' => array('description' => 'Delay: Action Required', 'internal_state' => self::IS_IN_TRANSIT),
        '050' => array('description' => 'Address Information Required', 'internal_state' => self::IS_IN_TRANSIT),
        '051' => array('description' => 'Delay: Emergency Situation or Severe Weather', 'internal_state' => self::IS_IN_TRANSIT),
        '052' => array('description' => 'Delay: Severe Weather', 'internal_state' => self::IS_IN_TRANSIT),
        '053' => array('description' => 'Delay: Severe Weather, Recovery in Progress', 'internal_state' => self::IS_IN_TRANSIT),
        '054' => array('description' => 'Delivery Change Requested', 'internal_state' => self::IS_IN_TRANSIT),
        '055' => array('description' => 'Rescheduled Delivery', 'internal_state' => self::IS_IN_TRANSIT),
        '056' => array('description' => 'Service Upgrade Requested', 'internal_state' => self::IS_IN_TRANSIT),
        '057' => array('description' => 'In Transit to a UPS Access Point', 'internal_state' => self::IS_IN_TRANSIT),
        '058' => array('description' => 'Clearance Information Required', 'internal_state' => self::IS_IN_TRANSIT),
        '059' => array('description' => 'Damage Reported', 'internal_state' => self::IS_IN_TRANSIT),
        '060' => array('description' => 'Delivery Attempted', 'internal_state' => self::IS_IN_TRANSIT),
        '061' => array('description' => 'Delivery Attempted: Adult Signature Required', 'internal_state' => self::IS_IN_TRANSIT),
        '062' => array('description' => 'Delivery Attempted: Funds Required', 'internal_state' => self::IS_IN_TRANSIT),
        '063' => array('description' => 'Delivery Change Completed', 'internal_state' => self::IS_IN_TRANSIT),
        '064' => array('description' => 'Delivery Refused', 'internal_state' => self::IS_IN_TRANSIT),
        '065' => array('description' => 'Pickup Attempted', 'internal_state' => self::IS_IN_TRANSIT),
        '066' => array('description' => 'Post Office Delivery Attempted', 'internal_state' => self::IS_IN_TRANSIT),
        '067' => array('description' => 'Returned to Sender by Post Office', 'internal_state' => self::IS_IN_TRANSIT),
        '068' => array('description' => 'Sent to Lost and Found', 'internal_state' => self::IS_IN_TRANSIT),
        '069' => array('description' => 'Package Not Claimed', 'internal_state' => self::IS_IN_TRANSIT),
    );

    /**
     * @param $tracking_number
     * @param bool $only_last_activty (optional, if false all the activity of item will be returned)
     * @return int see class constants to get possible results
     * @throws Exception
     */
    public function doTrack($tracking_number)
    {
        if (trim($tracking_number) == "") {
            throw new Exception("UPSTrackingApi - doTrack : traking_number cannot be empty");
        }

        $this->setEndPointURL('webservices/Track');
        $this->setWSDL(dirname(__FILE__)."/Tracking/Track.wsdl");
        $this->setOperation("ProcessTrack");

        $request = array(
            'Request' => array(
                'RequestOption' => '0',
                'TransactionReference' => array(
                    'CustomerContext' => 'TRK_' . $tracking_number
                )
            ),
            'InquiryNumber' => $tracking_number,
            'TrackingOption' => '02'
        );

        $this->setRequest($request);
        $this->send();
        $response = $this->getResponse();
        if ($response === false) {
            return false;
        }

        if ($response->Response->ResponseStatus->Code != '1') {
            throw new Exception("UPSTrackingApi - doTrack : an error occured " .
                $response->Response->ResponseStatus->Code . " - " . $response->Response->ResponseStatus->Description);
        }

        if (isset($this->shipmentStatuses[$response->Shipment->CurrentStatus->Code])) {
            return $this->shipmentStatuses[$response->Shipment->CurrentStatus->Code]["internal_state"];
        } else {
            throw new Exception("UPSTrackingApi - doTrack : unknown CurrentStatus");
        }
    }
}
