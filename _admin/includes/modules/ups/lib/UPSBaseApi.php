<?php
/**
 * Created by PhpStorm.
 * User: djbuch
 * Date: 01/10/2015
 * Time: 14:31
 */

class UPSBaseApi
{

    protected $accessKey = '';
    protected $userId = '';
    protected $passwd = '';
    protected $endpointUrl = '';
    protected $wsdl = '';
    protected $operation = '';
    protected $testMode = false;

    const SIGNATURE       = 1;
    const SIGNATURE_ADULT = 2;

    private $request = array();
    private $response = false;

    public function __construct($accessKey, $userId, $passwd, $testMode = false)
    {
        $this->accessKey = $accessKey;
        $this->userId = $userId;
        $this->passwd = $passwd;
        $this->testMode = $testMode;
    }

    protected function setEndPointURL($url)
    {
        $this->endpointUrl = $this->testMode ? "https://wwwcie.ups.com/".$url : "https://onlinetools.ups.com/".$url;
    }

    protected function setOperation($operation)
    {
        $this->operation = $operation;
    }

    protected function setWSDL($wsdl)
    {
        $this->wsdl = $wsdl;
    }

    protected function setRequest($request)
    {
        $this->request = $request;
    }

    public function send()
    {
        $mode = array
        (
            'soap_version' => 'SOAP_1_1',  // use soap 1.1 client
            'trace' => 1
        );

        /*  initialize soap client */
        $client = new SoapClient($this->wsdl, $mode);

        /* set endpoint url */
        $client->__setLocation($this->endpointUrl);

        /* create soap header */
        $header = new SoapHeader('http://www.ups.com/XMLSchema/XOLTWS/UPSS/v1.0', 'UPSSecurity', array(
            'UsernameToken' => array(
                'Username' => $this->userId,
                'Password' => $this->passwd
            ),
            'ServiceAccessToken' => array(
                'AccessLicenseNumber' => $this->accessKey
            )
        ));
        $client->__setSoapHeaders($header);

        /* get response */
        try {
            $this->response = $client->__soapCall($this->operation, array($this->request));
        } catch (Exception $e) {
            if(isset($e->detail->Errors->ErrorDetail->PrimaryErrorCode)) {
                $exc = new Exception($e->detail->Errors->ErrorDetail->PrimaryErrorCode->Description, $e->detail->Errors->ErrorDetail->PrimaryErrorCode->Code);
            } else {
                $exc = new Exception("Unknow error");
            }
            throw $exc;
        }

        if ($this->testMode) {
            /* save soap request and response to file in test mode for debuging */
            $fw = fopen(dirname(__FILE__)."/../tests/".get_class($this)."-request.xml", 'w+');
            fwrite($fw, $client->__getLastRequest());
            fclose($fw);

            $fw = fopen(dirname(__FILE__)."/../tests/".get_class($this)."-response.xml", 'w+');
            fwrite($fw, $client->__getLastResponse());
            fclose($fw);
        }
    }

    public function getResponse()
    {
        if (is_object($this->response)) {
            return $this->response;
        }
        return false;
    }

    /**
     * Formats address for some API requests (see requests docs)
     * @param $addressLine1
     * @param $addressLine2
     * @param $addressLine3
     * @param $city
     * @param $stateCode
     * @param $postalCode
     * @param $countryCode
     * @param null $residentialAddressIndicator
     * @param null $POBoxIndicator
     * @return array
     */
    public static function formatAddress($addressLine1, $addressLine2, $addressLine3, $city, $stateCode, $postalCode, $countryCode, $residentialAddressIndicator = null, $POBoxIndicator = null)
    {
        $addressLines = array();
        if ($addressLine1 != '') {
            $addressLines[] = $addressLine1;
            if ($addressLine2 != '') {
                $addressLines[] = $addressLine2;
                if ($addressLine3 != '') {
                    $addressLines[] = $addressLine3;
                }
            }
        }

        $address = array(
            'AddressLine' => $addressLines,
            'City' => $city,
            'StateProvinceCode' => $stateCode,
            'PostalCode' => $postalCode,
            'CountryCode' => $countryCode
        );
        if ($residentialAddressIndicator !== null) {
            $address['ResidentialAddressIndicator'] = $residentialAddressIndicator;
        }
        if ($POBoxIndicator !== null) {
            $address['POBoxIndicator'] = $POBoxIndicator;
        }
        return $address;
    }

    /**
     * Creates a formated array for shipping accessorials
     * @param bool|array $DeclaredValue if DeclaredValue needed give an array ('CurrencyCode' (string),'MonetaryValue' (float))
     * @param bool|array $AccessPointCOD if AccessPointCOD needed give an array ('CurrencyCode' (string),'MonetaryValue' (float)) only for AccessPoint delivery
     * @param bool $ToAddresseeOnly only for AccessPoint delivery
     * @param bool $Signature, false if no signature is required, see self::SIGNATURE and self::SIGNATURE_ADULT other possible values
     * @return array
     */
    public static function formatAccessorials($DeclaredValue = false, $AccessPointCOD = false, $ToAddresseeOnly = false, $Signature = false)
    {
        $accessorials = array();
        if (is_array($DeclaredValue)) {
            $accessorials['DeclaredValue'] = $DeclaredValue;
        }
        if (is_array($AccessPointCOD)) {
            $accessorials['AccessPointCOD'] = $AccessPointCOD;
        }
        if ($ToAddresseeOnly) {
            $accessorials['ToAddresseeOnly'] = true;
        }
        if ($Signature !== false && $Signature !== null && $Signature != 0) {
            $accessorials['Signature'] = $Signature;
        }

        return $accessorials;
    }

    /**
     * @return array with key = UPS Service code, and value = array('name' (string),
     *                                                              'originCountries' (array) of country codes,
     *                                                              'destinationCountries' (array) of country codes,
     *                                                              'onlySameAsOrigin' (bool) true if the destination can only be the same as the origin countrie
     *                                                             )
     */
    public static function getUPSServices()
    {
        return array(
            '70' => array(
                'name' => 'UPS Access Point Economy',
                'originCountries' => array('BE', 'NL', 'LU', 'ES', 'FR'),
                'destinationCountries' => array('BE', 'NL', 'LU', 'ES', 'FR'),
                'onlySameAsOrigin' => true
            ),
            '11' => array(
                'name' => 'UPS Standard',
                'originCountries' => array('BE', 'NL', 'LU', 'FR', 'ES'),
                'destinationCountries' => array('BE', 'NL', 'LU', 'FR', 'ES', 'UK', 'DE', 'PL', 'IT'),
                'onlySameAsOrigin' => false
            ),
            '65' => array(
                'name' => 'UPS Express Saver',
                'originCountries' => array('BE', 'NL', 'LU', 'FR', 'ES'),
                'destinationCountries' => array('BE', 'NL', 'LU', 'FR', 'ES', 'CA', 'UK', 'MX', 'US', 'DE', 'PL', 'IT'),
                'onlySameAsOrigin' => false
            ),
            '08' => array(
                'name' => 'UPS Expedited',
                'originCountries' => array('BE', 'NL', 'LU', 'FR', 'ES'),
                'destinationCountries' => array('BE', 'NL', 'LU', 'FR', 'ES', 'CA', 'UK', 'MX', 'US', 'DE', 'PL', 'IT'),
                'onlySameAsOrigin' => false
            ),
            '07' => array(
                'name' => 'UPS Express',
                'originCountries' => array('BE', 'NL', 'LU', 'FR', 'ES'),
                'destinationCountries' => array('BE', 'NL', 'LU', 'FR', 'ES', 'CA', 'UK', 'MX', 'US', 'DE', 'PL', 'IT'),
                'onlySameAsOrigin' => false
            ),
        );
    }
}