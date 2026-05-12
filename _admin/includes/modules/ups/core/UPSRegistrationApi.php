<?php
/**
 * Created by PhpStorm.
 * User: djbuch
 * Date: 01/10/2015
 * Time: 15:17
 */
require_once 'UPSBaseApi.php';

class UPSRegistrationApi extends UPSBaseApi
{
    private $possibleResponses = array(
        '010' => array('Account is added to user\'s profile successfully',true),
        '011' => array('Account cannot be added to user\'s profile due to system problem, try account management later',false),
        '012' => array('Account is existing in your profile currently',true),
        '013' => array('Account cannot be added to your profile due to exceeding the max number allowed',false),
        '040' => array('Account with invoice is authorized and added to your valid account list successfully',true),
        '041' => array('Account is authorized but cannot be added to you valid account list due to system problem, try account management again',false),
        '042' => array('Account has already been authorized and in your valid account list before your request today',true),
        '043' => array('Authorization is not performed since the account is not on the EBS country list',false),
        '045' => array('Account can not be added to user\'s profile because invoice information is not present',false),
    );


    /**
     * Register new user
     * @param string $username
     * @param string $password
     * @param string $companyName
     * @param string $customerName
     * @param string $customerTitle
     * @param array $address address formated by UPSBaseApi::formatAddress
     * @param string $phoneNumber
     * @param string $email
     * @param string $endUserIP
     * @return array|bool false if no response exists, else if username is used array('usernameExists' (bool), 'suggestedUsername' (string)) you can then retry with the suggestedUsername
     *         else array('username' (string), 'password' (string))
     * @throws Exception
     */
    public function doRegistration(
        $username,
        $password,
        $companyName,
        $customerName,
        $customerTitle,
        $address,
        $phoneNumber,
        $email,
        $endUserIP
    ) {
        //use developpers access keys
        $this->userId = "AGENCEW360";
        $this->accessKey = "7CF75FD7BD75A775";
        $this->passwd = "*UPS*777*";

        $this->setEndPointURL('webservices/Registration');
        $this->setWSDL(dirname(__FILE__)."/Registration/RegistrationWebService.wsdl");
        $this->setOperation("ProcessRegister");

        $request = array(
            'Request' => array(
                'RequestOption' => 'N'
            ),
            'Username' => $username,
            'Password' => $password,
            'CompanyName' => $companyName,
            'CustomerName' => $customerName,
            'Title' => $customerTitle,
            'Address' =>$address,
            'PhoneNumber' => $phoneNumber,
            'EmailAddress' => $email,
            'NotificationCode' => '00',
            'SuggestUsernameIndicator' => 'Y',
            'EndUserIPAddress' => $endUserIP
        );

        $this->setRequest($request);
        $this->send();
        $response = $this->getResponse();
        if ($response === false) {
            return false;
        }

        if ($response->Response->ResponseStatus->Code != '1') {
            throw new Exception("UPSRegistrationApi - doRegistration : an error occured " .
                $response->Response->ResponseStatus->Code . " - " . $response->Response->ResponseStatus->Description);
        }

        if ($response->SuggestedUsername != '') {
            return array('usernameExists'=>true, 'suggestedUsername'=>$response->SuggestedUsername);
        }
        return array('username' => $username, 'password' => $password);
    }


    /**
     * @param string $accountName
     * @param string $accountNumber
     * @param string $postalCode
     * @param string $countryCode
     * @param string $invoiceNumber of last invoice if less than 45 days
     * @param string $invoiceDate of last invoice in military format : 20111015
     * @param string $currencyCode of last invoice
     * @param string $invoiceAmount of last invoice
     * @param string $controlID of last invoice if provided in the invoice
     * @return bool true if account was added successfully
     * @throws Exception
     */
    public function doAddAccount(
        $accountName,
        $accountNumber,
        $postalCode,
        $countryCode,
        $invoiceNumber = null,
        $invoiceDate = null,
        $currencyCode = null,
        $invoiceAmount = null,
        $controlID = null
    ) {
        $this->setEndPointURL('webservices/Registration');
        $this->setWSDL(dirname(__FILE__)."/Registration/RegistrationWebService.wsdl");
        $this->setOperation("ProcessManageAccount");

        $request = array(
            'Request' => array(
                'RequestOption' => 'N'
            ),
            'AccountStatusCheckRequired' => '',
            'ShipperAccount' => array(
                'AccountName' => $accountName,
                'AccountNumber' => $accountNumber,
                'PostalCode' => $postalCode,
                'CountryCode' => $countryCode,
             )
        );

        if ($invoiceNumber !== null) {
            $request['ShipperAccount']['InvoiceInfo'] = array(
                'InvoiceNumber' =>  $invoiceNumber,
                'InvoiceDate' => $invoiceDate,
                'CurrencyCode' => $currencyCode,
                'InvoiceAmount' => $invoiceAmount,
                'ControlID' => $controlID
            );
        }

        $this->setRequest($request);
        $this->send();
        $response = $this->getResponse();
        if ($response === false) {
            return false;
        }

        if ($response->Response->ResponseStatus->Code != '1') {
            throw new Exception("UPSRegistrationApi - doAddAccount : an error occured " .
                $response->Response->ResponseStatus->Code . " - " . $response->Response->ResponseStatus->Description);
        }
        return $this->possibleResponses[(string)(isset($response->ShipperAccountStatus->Code) ? $response->ShipperAccountStatus->Code : $response->ShipperAccountStatus[0]->Code)][1];
    }
}