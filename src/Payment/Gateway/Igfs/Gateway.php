<?php

namespace Payment\Gateway\Igfs;

class Gateway implements \Payment\GatewayInterface
{
    private $serverUrl;
    private $test;
    private $dTid = '';
    private $dKsig = '';
    private $allowedCurrencies = array('AUD','CAD','CHF','DKK','GBP','JPY',
    'SEK','EUR','NOK','RUB','USD','AED','BRL','HKD','KWD','MXN','MYR',
    'SAR','SGD','THB','TWD');
    private $allowedLanguages = array('IT','EN','FR','DE');
    private $sellingLocations = null;

    // Extra informations
    const DEFAULT_INFO1 = '';
    const DEFAULT_INFO2 = '';
    const DEFAULT_INFO3 = '';
    const DEFAULT_INFO4 = '';
    const DEFAULT_INFO5 = '';
    
    // Default credentials 
    const DEFAULT_TID = '06231955';
    const DEFAULT_KSIG = 'xHosiSb08fs8BQmt9Yhq3Ub99E8=';
    
    // Acquirer types
    const ACQUIRER_POSITIVI = 'bnlpositivity';
    const ACQUIRER_PARIBAS = 'bnlparibas';
    
    // Endpoints
    const URL = 'https://merchant.s2s.bnlpositivity.it/BNL_CG_SERVICES/services/';
    const URL_TEST = 'https://merchant.s2stest.bnlpositivity.it/BNL_CG_SERVICES/services/';

    const DEFAULT_LANGUAGE = 'EN';

     /**
     * 
     * @return object
     *
     * @throws \Exception
     */
     public function __construct ($test){
        $this->test = $test;
        if($test){
            $this->serverUrl =self::URL_TEST;
            $this->dTid = self::DEFAULT_TID;
            $this->dKsig = self::DEFAULT_KSIG;
        }
        else{
            $this->serverUrl = self::URL;
        }
     }

    /**
     * 
     * Transaction initializer. Create the Redirect URL.
     * 
     * @param array $params
     * @return array|object
     * @throws ConnectionException
     * @throws IgfsException
     */
    public function init(array $params = [])
    {
        $initObj = new Init\IgfsCgInit(); 
        $unique = IgfsUtils::getValue($params, 'orderReference');
        $url= IgfsUtils::getValue($params,'baseURL','');

        $initObj->serverURL = $this->serverUrl;
        if($this->test){
            $initObj->disableCheckSSLCert();
        }

        $paymentMethod = IgfsUtils::getValue($params,'paymentMethod');
        if($paymentMethod ==="findomestic"){
            $initObj->tid = IgfsUtils::getValue($params,'terminalIdFindomestic');
        }else{
            $initObj->tid = IgfsUtils::getValue($params,'terminalId',$this->dTid,false).$this->getInstrumentCode($paymentMethod);
        }
        
        $initObj->shopID = $unique;
        $initObj->amount = str_replace('.', '', number_format(IgfsUtils::getValue($params, 'amount', '0'), 2, '.', ''));
        $initObj->currencyCode =IgfsUtils::getValue($params,'currency','EUR');
        $initObj->kSig = IgfsUtils::getValue($params,'hashMessage',$this->dKsig,false);
        $initObj->notifyURL = IgfsUtils::appendParameter($url.IgfsUtils::getValue($params,'notifyUrl',''), 'token='.urlencode($unique));
        $initObj->errorURL = IgfsUtils::appendParameter($url.IgfsUtils::getValue($params,'errorUrl',''), 'token='.urlencode($unique));
        $initObj->callbackURL = IgfsUtils::appendParameter($url.IgfsUtils::getValue($params,'callbackUrl',''), 'token='.urlencode($unique));
        $initObj->addInfo1 = substr(IgfsUtils::getValue($params,'addInfo1',self::DEFAULT_INFO1),0,256);
        $initObj->addInfo2 = substr(IgfsUtils::getValue($params,'addInfo2',self::DEFAULT_INFO2),0,256);
        $initObj->addInfo3 = substr(IgfsUtils::getValue($params,'addInfo3',self::DEFAULT_INFO3),0,256);
        $initObj->addInfo4 = substr(IgfsUtils::getValue($params,'addInfo4',self::DEFAULT_INFO4),0,256);
        $initObj->addInfo5 = substr(IgfsUtils::getValue($params,'addInfo5',self::DEFAULT_INFO5),0,256);
        $initObj->trType =IgfsUtils::getValue($params, 'transactionType', 'AUTH');
        $initObj->description =IgfsUtils::getValue($params, 'description');
        $initObj->shopUserRef =IgfsUtils::getValue($params, 'shopUserRef');
        $initObj->shopUserName =IgfsUtils::getValue($params, 'shopUserName');
        $initObj->langID = strtoupper(IgfsUtils::normalizeLanguage(IgfsUtils::getValue($params, 'language', self::DEFAULT_LANGUAGE)));
        $initObj->payInstrToken = IgfsUtils::getValue($params, 'payInstrToken');
        $initObj->regenPayInstrToken = IgfsUtils::getValue($params, 'regenPayInstrToken');

        $initObj->execute();
        return array(
            'returnCode' => $initObj->rc,
            'message' => $initObj->errorDesc,
            'error' => $initObj->rc !== 'IGFS_000',
            'paymentID' => $initObj->paymentID,
            'orderReference' => $initObj->shopID,
            'notifyURL' => $initObj->notifyURL,
            'redirectURL' => $initObj->redirectURL,
        );
    }

    /**
     * 
     * Verify transaction. Receive only the status of the specific transaction.
     * 
     * @param array $params
     * @return array|object
     */
    public function verify(array $params = []){
        $verifyObj = new Init\IgfsCgVerify(); 

        $verifyObj->serverURL = $this->serverUrl;
        if($this->test){
            $verifyObj->disableCheckSSLCert();
        }
        $verifyObj->kSig = IgfsUtils::getValue($params,'hashMessage',$this->dKsig,false);
        $verifyObj->tid = IgfsUtils::getValue($params,'terminalId',$this->dTid,false).'_S';
        $verifyObj->shopID = IgfsUtils::getValue($params, 'orderReference');
        $verifyObj->langID = strtoupper(IgfsUtils::normalizeLanguage(IgfsUtils::getValue($params, 'language', self::DEFAULT_LANGUAGE)));
        $verifyObj->paymentID =IgfsUtils::getValue($params, 'paymentID', '00179695241108714733');


        $verifyObj->execute();
        return array(
            'terminalId' => $verifyObj->tid,
            'returnCode' => $verifyObj->rc,
            'message' => $verifyObj->errorDesc,
            'error' => $verifyObj->rc !== 'IGFS_000',
            'orderReference' => $verifyObj->shopID,
            'paymentID' => $verifyObj->paymentID,
            'tranID' => $verifyObj->tranID,
        );
    }

    /**
     * 
     * Transaction confirmation. 
     * Transfer a specific amount from an authorized transaction
     * 
     * @param array $params
     * @return array|object
     */
    public function confirm(array $params = []){
        $confirmObj = new tran\IgfsCgConfirm(); 

        $confirmObj->serverURL = $this->serverUrl;
        if($this->test){
            $confirmObj->disableCheckSSLCert();
        }

        $confirmObj->tid= IgfsUtils::getValue($params,'terminalId',$this->dTid,false);
        $confirmObj->kSig= IgfsUtils::getValue($params,'hashMessage',$this->dKsig,false);
        $confirmObj->shopID= IgfsUtils::getValue($params, 'orderReference');
        $confirmObj->refTranID= IgfsUtils::getValue($params, 'paymentReference');
        $confirmObj->amount= str_replace('.', '', number_format(IgfsUtils::getValue($params, 'amount', '0'), 2, '.', ''));
        
        $confirmObj->execute();
        return array(
            'terminalId' => $confirmObj->tid,
            'returnCode' => $confirmObj->rc,
            'message' => $confirmObj->errorDesc,
            'error' => $confirmObj->rc !== 'IGFS_000',
            'refTranID' => $confirmObj->refTranID,
            'tranID' => $confirmObj->tranID,
        );
    }

    /**
     * 
     * Refund transaction. Return a specific amount back to buyer.
     * 
     * @param array $params
     * @return array|object
     */
    public function refund(array $params = []){
        
        $rfdObj = new tran\IgfsCgCredit();

        $rfdObj->serverURL = $this->serverUrl;
        $rfdObj->tid= IgfsUtils::getValue($params,'terminalId',$this->dTid,false);
        $rfdObj->kSig= IgfsUtils::getValue($params,'hashMessage',$this->dKsig,false);
        $rfdObj->shopID= IgfsUtils::getValue($params, 'orderReference');
        $rfdObj->amount= str_replace('.', '', number_format(IgfsUtils::getValue($params, 'amount', '0'), 2, '.', ''));
        $rfdObj->refTranID= IgfsUtils::getValue($params, 'paymentReference');

        $rfdObj->execute();
        return array(
            'terminalId' => $rfdObj->tid,
            'returnCode' => $rfdObj->rc,
            'message' => $rfdObj->errorDesc,
            'error' => $rfdObj->rc !== 'IGFS_000',
            'orderReference' => $rfdObj->shopID,
            'tranID' => $rfdObj->tranID,
        );
    }

    /**
     * 
     * Cancel pending transaction. Return a specific amount back to buyer.
     * 
     * @param array $params
     * @return array|object
     */
    public function cancel(array $params){
        $rfdObj = new tran\IgfsCgVoidAuth();
        
        $rfdObj->serverURL = $this->serverUrl;
        $rfdObj->tid= IgfsUtils::getValue($params,'terminalId',$this->dTid,false);
        $rfdObj->kSig= IgfsUtils::getValue($params,'hashMessage',$this->dKsig,false);
        $rfdObj->shopID= IgfsUtils::getValue($params, 'orderReference');
        $rfdObj->amount= str_replace('.', '', number_format(IgfsUtils::getValue($params, 'amount', '0'), 2, '.', ''));
        $rfdObj->refTranID= IgfsUtils::getValue($params, 'paymentReference');
        
        $rfdObj->execute();
        return array(
            'terminalId' => $rfdObj->tid,
            'orderReference' => $rfdObj->shopID,
            'tranID' => $rfdObj->tranID,
            'refTranID' => $rfdObj->refTranID,
            'returnCode' => $rfdObj->rc,
            'message' => $rfdObj->errorDesc,
            'error' => $rfdObj->rc !== 'IGFS_000',
        );
    }
    /**
     * 
     * Return all the possible payment instruments
     * 
     * @param 
     * @return array|object
     */
    public function getPaymentInstruments(){
        return array(
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'maestro' => 'Maestro',
            'diners' => 'Diners',
            'americanexpress' => 'American Express',
            'findomestic' => 'Findomestic',
            'masterpass'  => 'Masterpass',
            'mybank' => 'MyBank',
            'paypal'      => 'PayPal'
          );
    }
    /**
     * 
     * Return all the possible payment instruments for credit cards
     * 
     * @param 
     * @return array|object
     */
    public function getCcPaymentInstruments(){
        return array(
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'maestro' => 'Maestro',
            'diners' => 'Diners',
            'americanexpress' => 'American Express',
            //'findomestic' => 'Findomestic',
          );
    }
    /**
     * 
     * Return the extra characters that has to be added in the tId during the initialization
     * 
     * @param string $inst
     * @return string
     */
    private function getInstrumentCode($inst){
        $code = '_S';
        switch ($inst) {
            case 'cc':
            case 'visa':
            case 'mastercard':
            case 'maestro':
            case 'diners':
            case 'americanexpress':
                $code = '';
                break;
            case 'mybank':
                $code = 'M';
                break;
            case 'masterpass':
                $code = 'P';
                break;
            //case 'findomestic':
            //    $code = '';
            //    break;
            case 'paypal':
                $code = 'PP';
                break;
        }
        return $code;
    }
    /**
     * 
     * Return all the possible transaction types
     * 
     * @param 
     * @return array|object
     */
    public function getTransactionTypes(){
        return array(
            'PURCHASE'  => IgfsUtils::getLabelText('TRANSACTION_TYPE_PURCHASE'),
            'AUTH'      => IgfsUtils::getLabelText('TRANSACTION_TYPE_AUTH'),
            'VERIFY'    => IgfsUtils::getLabelText('TRANSACTION_TYPE_VERIFY'),
          );
    }
    /**
     * 
     * Return all the possible cheout types
     * 
     * @param 
     * @return array|object
     */
    public function getCheckoutTypes(){
        return array(
            '1'  => IgfsUtils::getLabelText('CHECK_OUT_TYPE_1'),
            '2'  => IgfsUtils::getLabelText('CHECK_OUT_TYPE_2'),
            '3'  => IgfsUtils::getLabelText('CHECK_OUT_TYPE_3'),
          );
      }
    /**
     * Get Allowed Currencies
     *
     * @return array|object
     */
    public function getCurrenciesAllowed($simple = false){
        $arr = array();
        $filePath = __DIR__ . "/../../Data/currencies.xml";
        
        $cLang = IgfsUtils::getGlobalLanguage();
        
        if (file_exists($filePath)) {
            $xmlElements = simplexml_load_file($filePath);

            if($this->allowedCurrencies!=null){
                $query = "//currency[code='".join("' or code='", $this->allowedCurrencies)."']";
                $xmlElements = $xmlElements->xpath($query);
            }

            if($simple){
                foreach($xmlElements as $currency){
                    $cDetails = array(
                        'title' => (string)$currency->{'name_' . $cLang},
                        'code' => (string)$currency->code,
                    );
    
                    array_push($arr, $cDetails);
                }
            }else{
                foreach($xmlElements as $currency){
                    $cDetails = array(
                        'title' => __((string)$currency->{'name_' . $cLang}, 'bnppay'),
                        'code' => (string)$currency->code,
                    );
    
                    array_push($arr, $cDetails);
                }
            }
        }
        return $arr;
    }
    /**
     * Get Allowed Languages
     *
     * @return array|object
     */
    public function getLanguagesAllowed(){
        $arr = array();
        $filePath = __DIR__ . "/../../Data/languages.xml";
        
        $cLang = IgfsUtils::getGlobalLanguage();

        if (file_exists($filePath)) {
            $xmlElements = simplexml_load_file($filePath);

            if($this->allowedLanguages != null){
                $query = "//language[code='".join("' or code='", $this->allowedLanguages)."']";
                $xmlElements = $xmlElements->xpath($query);
            }

            foreach($xmlElements as $lang){
                array_push($arr,array( 'code' => (string)$lang->code, 'name' => (string)$lang->{'name_' . $cLang}));
            }
        }
        
        return $arr;
    }
    
    /**
     * Return the possible acquirers
     *
     * @return array|object
     */
    public function getAcquirer(){
        return array(self::ACQUIRER_POSITIVI=> IgfsUtils::getLabelText('ACQUIRER_POSITIVI'),self::ACQUIRER_PARIBAS  => IgfsUtils::getLabelText('ACQUIRER_PARIBAS'));
    }
    
    /**
     * Get Default Terminal Id
     *
     * @return string
     */
    public function getTestTerminalId(){
        return self::DEFAULT_TID;
    }
    /**
     * Get Default Hased Password
     *
     * @return string
     */
    public function getTestHashMessage(){
        return self::DEFAULT_KSIG;
    }
    /**
     * Get Default Extra Hased Password
     *
     * @return string
     */
    public function getTesthMacPassword(){
        return null;
    }
    /**
     * Return a list with all available countries
     *
     * @return array|object
     */
    public function getSellingLocations(){
        return array();
    }
    /**
     * Return a test Id for the Findomestic payment
     *
     * @return string
     */
    public function getTestFindomesticTerminalId(){
        return 93885445;
    }
}