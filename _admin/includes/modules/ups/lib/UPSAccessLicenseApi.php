<?php
/**
 * Created by PhpStorm.
 * User: djbuch
 * Date: 01/10/2015
 * Time: 15:17
 */

class UPSAccessLicenseApi {

    protected $endpointUrl = 'https://wwwcie.ups.com/ups.app/xml/License';
    protected $developerLicenseNumber = 'DCF75FCF9DD24DC5';
    protected $testMode = true;

    public function __construct($testMode = false)
    {
        $this->testMode = $testMode;
        if ($testMode) {
            $this->endpointUrl = 'https://wwwcie.ups.com/ups.app/xml/License';
        } else {
            $this->endpointUrl = 'https://onlinetools.ups.com/ups.app/xml/License';
        }
    }


    /**
     * Returns the license agreement text to be displayed to the client
     * @param string $countryCode user country code
     * @param string $languageCode FR or EN only
     * @return bool|string
     */
    public function getLicenseAgreement($countryCode, $languageCode) {

        if ($languageCode != 'FR') {
            $languageCode = 'EN';
        }

        $strXml = '<?xml version="1.0"?>
                <AccessLicenseAgreementRequest xml:lang="en-US">
                    <!--Validate combination of Request Option and Tool ID-->
                    <Request>
                        <RequestAction>AccessLicense</RequestAction>
                        <RequestOption>AllTools</RequestOption>
                    </Request>
                    <DeveloperLicenseNumber>'.$this->developerLicenseNumber.'</DeveloperLicenseNumber>
                    <AccessLicenseProfile>
                        <CountryCode>'.$countryCode.'</CountryCode>
                        <LanguageCode>'.$languageCode.'</LanguageCode>
                    </AccessLicenseProfile>
                </AccessLicenseAgreementRequest>';

        $rsrcCurl = curl_init($this->endpointUrl);

        curl_setopt($rsrcCurl, CURLOPT_HEADER, 0);
        curl_setopt($rsrcCurl, CURLOPT_POST, 1);
        curl_setopt($rsrcCurl, CURLOPT_TIMEOUT, 60);
        curl_setopt($rsrcCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($rsrcCurl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($rsrcCurl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($rsrcCurl, CURLOPT_POSTFIELDS, $strXml);

        $strResult = curl_exec($rsrcCurl);
        $objResult = new SimpleXMLElement($strResult);
        if ($this->testMode) {
            /* save soap request and response to file in test mode for debuging */
            $fw = fopen(dirname(__FILE__)."/../tests/".get_class($this)."-request.xml", 'w+');
            fwrite($fw, $strXml);
            fclose($fw);

            $fw = fopen(dirname(__FILE__)."/../tests/".get_class($this)."-response.xml", 'w+');
            fwrite($fw, $strResult);
            fclose($fw);
        }

        curl_close($rsrcCurl);

        if ((string)$objResult->Response->ResponseStatusCode == '1') {
            return (string)$objResult->AccessLicenseText;
        } else {
            return false;
        }
    }

    /**
     * Create access key to get access to the API
     * @param string $companyName required
     * @param string $addressLine1 required
     * @param string $addressLine2
     * @param string $addressLine3
     * @param string $city required
     * @param string $stateCode required
     * @param string $postalCode required
     * @param string $countryCode required
     * @param string $contactName required
     * @param string $contactTitle required
     * @param string $contactEmail required
     * @param string $contactPhone required
     * @param string $companyUrl required
     * @param string $shipperNumber
     * @param string $licenseAgreementCountryCode
     * @param string $licenseAgreementLanguageCode FR or EN only
     * @param string $licenseAgreementText required = text of the license agreement
     * @param string $cms name of cms
     * @return bool|string
     */
    public function getAccessLicense(
        $companyName,
        $addressLine1,
        $addressLine2,
        $addressLine3,
        $city,
        $stateCode,
        $postalCode,
        $countryCode,
        $contactName,
        $contactTitle,
        $contactEmail,
        $contactPhone,
        $companyUrl,
        $shipperNumber,
        $licenseAgreementCountryCode,
        $licenseAgreementLanguageCode,
        $licenseAgreementText,
        $cms
    ) {


        if ($licenseAgreementLanguageCode != 'FR') {
            $licenseAgreementLanguageCode = 'EN';
        }

        $strXml = '<?xml version = "1.0" encoding = "UTF-8" standalone = "yes"?>
                    <AccessLicenseRequest>
                        <Request>
                            <RequestOption>AllTools</RequestOption>
                            <RequestAction>AccessLicense</RequestAction>
                        </Request>
                        <CompanyName><![CDATA['.$companyName.']]></CompanyName>
                        <Address>
                            <AddressLine1><![CDATA['.$addressLine1.']]></AddressLine1>
                            '.(trim($addressLine2) != '' ? '<AddressLine2><![CDATA['.$addressLine2.']]></AddressLine2>' : '').'
                            '.(trim($addressLine3) != '' ? '<AddressLine3><![CDATA['.$addressLine3.']]></AddressLine3>' : '').'
                            <City>'.$city.'</City>
                            '.(trim($stateCode) != '' ? '<StateProvinceCode>'.$stateCode.'</StateProvinceCode>' : '<StateProvinceCode/>').'
                            <PostalCode>'.$postalCode.'</PostalCode>
                            <CountryCode>'.$countryCode.'</CountryCode>
                        </Address>
                        <PrimaryContact>
                            <Name><![CDATA['.$contactName.']]></Name>
                            <Title><![CDATA['.$contactTitle.']]></Title>
                            <EMailAddress><![CDATA['.$contactEmail.']]></EMailAddress>
                            <PhoneNumber>'.$contactPhone.'</PhoneNumber>
                        </PrimaryContact>
                        <CompanyURL><![CDATA['.$companyUrl.']]></CompanyURL>
                        '.(trim($shipperNumber) != '' ? '<ShipperNumber>'.$shipperNumber.'</ShipperNumber>' : '').'
                        <DeveloperLicenseNumber>'.$this->developerLicenseNumber.'</DeveloperLicenseNumber>
                        <AccessLicenseProfile>
                            <CountryCode>'.$licenseAgreementCountryCode.'</CountryCode>
                            <LanguageCode>'.$licenseAgreementLanguageCode.'</LanguageCode>
                            <AccessLicenseText><![CDATA['.$licenseAgreementText.']]></AccessLicenseText>
                        </AccessLicenseProfile>
                        <ClientSoftwareProfile>
                            <SoftwareInstaller>Agence Web 360</SoftwareInstaller>
                            <SoftwareProductName>Module UPS - '.$cms.'</SoftwareProductName>
                            <SoftwareProvider>Agence Web 360</SoftwareProvider>
                            <SoftwareVersionNumber>1.0</SoftwareVersionNumber>
                        </ClientSoftwareProfile>
                    </AccessLicenseRequest>';

        $rsrcCurl = curl_init($this->endpointUrl);

        curl_setopt($rsrcCurl, CURLOPT_HEADER, 0);
        curl_setopt($rsrcCurl, CURLOPT_POST, 1);
        curl_setopt($rsrcCurl, CURLOPT_TIMEOUT, 60);
        curl_setopt($rsrcCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($rsrcCurl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($rsrcCurl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($rsrcCurl, CURLOPT_POSTFIELDS, $strXml);

        $strResult = curl_exec($rsrcCurl);
        $objResult = new SimpleXMLElement($strResult);
        if ($this->testMode) {
            /* save soap request and response to file in test mode for debuging */
            $fw = fopen(dirname(__FILE__)."/../tests/".get_class($this)."-access-request.xml", 'w+');
            fwrite($fw, $strXml);
            fclose($fw);

            $fw = fopen(dirname(__FILE__)."/../tests/".get_class($this)."-access-response.xml", 'w+');
            fwrite($fw, $strResult);
            fclose($fw);
        }

        curl_close($rsrcCurl);

        if ((string)$objResult->Response->ResponseStatusCode == '1') {
            return (string)$objResult->AccessLicenseNumber;
        } else {
            return false;
        }
    }
}
