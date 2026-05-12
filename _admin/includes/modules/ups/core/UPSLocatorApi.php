<?php
/**
 * Created by PhpStorm.
 * User: djbuch
 * Date: 01/10/2015
 * Time: 15:17
 */
require_once 'UPSBaseApi.php';

class UPSLocatorApi extends UPSBaseApi {

    protected $endpointUrl = 'https://onlinetools.ups.com/ups.app/xml/Locator';

    /**
     * Find all UPS Access Points
     * @param string $addressLine
     * @param string $city
     * @param string $postalCode
     * @param string $countryCode
     * @param string $maxListSize
     * @param string $searchRadius
     * @param string $distanceUnit
     * @return array|bool false if error or not found, else an array of UPS Access Points defined as following
     *                     Array (
     *                        [LocationID] =>
     *                        [PublicAccessPointID] =>
     *                        [lat] =>
     *                        [lng] =>
     *                        [name] =>
     *                        [address] =>
     *                        [postalCode] =>
     *                        [city] =>
     *                        [countryCode] =>
     *                        [distance] =>
     *                        [imageURL] =>
     *                        [holidays] =>
     *                        [openingHours] => array indexed by a number (1 = Sunday, 7 = Saturday), in each element you can find :
     *                                  - allDay (boolean) set to true if the Access Point is opened 24hours
     *                                  - closed (boolean) set to true if the Access Point is closed
     *                                  - open and close two arrays of opening and closing hours in military format HHMM
     *                     )
     */
    public function findUPSAccessPoint($addressLine, $city, $postalCode, $countryCode, $maxListSize = "10", $searchRadius = "30", $distanceUnit = 'KM') {

            $strXml = '<?xml version="1.0"?>
                <AccessRequest xml:lang="en-US">
                    <AccessLicenseNumber>' . $this->accessKey . '</AccessLicenseNumber>
                    <UserId>' . $this->userId . '</UserId>
                    <Password>' . $this->passwd . '</Password>
                </AccessRequest>
                <?xml version="1.0"?>
                <LocatorRequest>
                    <!-- REQUEST OPTION INFORMATION -->
                    <Request>
                        <RequestAction>Locator</RequestAction>
                        <RequestOption>64</RequestOption>
                    </Request>
                    <!--  ORIGIN ADDRESS INFORMATION -->
                    <OriginAddress>
                        <AddressKeyFormat>
                            <AddressLine>'.substr($addressLine, 0, 100).'</AddressLine>
                            <PoliticalDivision2>'.$city.'</PoliticalDivision2>
                            <PostcodePrimaryLow>'.$postalCode.'</PostcodePrimaryLow>
                            <CountryCode>'.$countryCode.'</CountryCode>
                        </AddressKeyFormat>
                    </OriginAddress>
                    <!--  REQUIRED INFORMATION -->
                    <Translate>
                        <LanguageCode>ENG</LanguageCode>
                    </Translate>
                    <UnitOfMeasurement>
                        <Code>'.$distanceUnit.'</Code>
                    </UnitOfMeasurement>
                    <!--  LOCATION TYPE SEARCH CRITERIA -->
                    <LocationSearchCriteria>
                        <SearchOption>
                            <OptionType>
                                <Code>01</Code>
                            </OptionType>
                            <OptionCode>
                                <Code>018</Code>
                            </OptionCode>
                        </SearchOption>
                        <!--  SERVICE SEARCH CRITERIA -->
                        <MaximumListSize>
                            '.$maxListSize.'
                        </MaximumListSize>
                        <SearchRadius>
                            '.$searchRadius.'
                        </SearchRadius>
                        <AccessPointSearch>
                            <!-- only active and available points -->
                            <AccessPointStatus>01</AccessPointStatus>
                        </AccessPointSearch>
                    </LocationSearchCriteria>
                </LocatorRequest>';

            $rsrcCurl = curl_init($this->endpointUrl);

            curl_setopt($rsrcCurl, CURLOPT_HEADER, 0);
            curl_setopt($rsrcCurl, CURLOPT_POST, 1);
            curl_setopt($rsrcCurl, CURLOPT_TIMEOUT, 60);
            curl_setopt($rsrcCurl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($rsrcCurl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($rsrcCurl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($rsrcCurl, CURLOPT_POSTFIELDS, $strXml);

            $strResult = curl_exec($rsrcCurl);
            if ($this->testMode) {
                /* save soap request and response to file in test mode for debuging */
                $fw = fopen(dirname(__FILE__)."/../tests/".get_class($this)."-request.xml", 'w+');
                fwrite($fw, $strXml);
                fclose($fw);

                $fw = fopen(dirname(__FILE__)."/../tests/".get_class($this)."-response.xml", 'w+');
                fwrite($fw, $strResult);
                fclose($fw);
            }
            $objResult = new SimpleXMLElement($strResult);

            curl_close($rsrcCurl);

            if ((string)$objResult->Response->ResponseStatusCode == '1') {
                $accessPoints = array();
                foreach ($objResult->SearchResults->DropLocation as $location) {
                    $accessPoint = array(
                        'LocationID' => (string)$location->LocationID,
                        'PublicAccessPointID' => (string)$location->AccessPointInformation->PublicAccessPointID,
                        'lat' => (string)$location->Geocode->Latitude,
                        'lng' => (string)$location->Geocode->Longitude,
                        'name' => (string)$location->AddressKeyFormat->ConsigneeName,
                        'address' => (string)$location->AddressKeyFormat->AddressLine,
                        'postalCode' => (string)$location->AddressKeyFormat->PostcodePrimaryLow,
                        'city' => (string)$location->AddressKeyFormat->PoliticalDivision2,
                        'countryCode' => (string)$location->AddressKeyFormat->CountryCode,
                        'distance' => (string)$location->Distance->Value.' '.(string)$location->Distance->UnitOfMeasurement->Code,
                        'imageURL' => (string)$location->AccessPointInformation->ImageURL,
                        'holidays' => (isset($location->NonStandardHoursOfOperation) ? (string)$location->NonStandardHoursOfOperation : '')
                    );

                    $openingHours = array();
                    //add opening hours
                    foreach ($location->OperatingHours->StandardHours->DayOfWeek as $day) {
                        if(isset($day->Open24HoursIndicator)) {
                            $openingHours[(string)$day->Day] = array(
                                'allDay' => true,
                            );
                        } elseif(isset($day->ClosedIndicator)) {
                            $openingHours[(string)$day->Day] = array(
                                'closed' => true,
                            );
                        } else {
                            $openingHours[(string)$day->Day] = array(
                                'open' => (array)$day->OpenHours,
                                'close' => (array)$day->CloseHours
                            );
                        }
                    }

                    $accessPoint['openingHours'] = $openingHours;

                    $accessPoints[] = $accessPoint;
                }
                return $accessPoints;
            } else {
                return false;
            }
    }

    /**
     * Format military time
     * @param string $time
     * @param string $minuteSeparator
     * @return string
     */
    public static function formatMilitaryTime($time, $minuteSeparator = ":") {
        if (strlen($time) == 1 && $time == "0") {
            return "00".$minuteSeparator."00";
        } else {
            $time = str_pad($time, 4, "0", STR_PAD_LEFT);
            return substr($time, 0, 2).$minuteSeparator.substr($time, 2);
        }
    }
}
