<?php
/**
 * Copyright (c) 2009-2014 Vaimo AB
 *
 * Vaimo reserves all rights in the Program as delivered. The Program
 * or any portion thereof may not be reproduced in any form whatsoever without
 * the written consent of Vaimo, except as provided by licence. A licence
 * under Vaimo's rights in the Program may be available directly from
 * Vaimo.
 *
 * Disclaimer:
 * THIS NOTICE MAY NOT BE REMOVED FROM THE PROGRAM BY ANY USER THEREOF.
 * THE PROGRAM IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE PROGRAM OR THE USE OR OTHER DEALINGS
 * IN THE PROGRAM.
 *
 * @category    Vaimo
 * @package     Vaimo_Klarna
 * @copyright   Copyright (c) 2009-2014 Vaimo AB
 */

/*
 * This class is the base for all calculations and tests required
 * Payment methods will use Mage::getModel on the appropriate class and then set known information
 * For example, assign and reserve part of the code sets the quote, capture sets the order, refund sets the payment
 * When the record is set, this class will fill other variables of any known information, such as address, payment methods, store id, currency and language
 * As soon as that is done, you can use this class to do tests and set values that are retreived later in the code
 * This class does not have any database connection in itself, that's why it's extending Varian_Object rather than any Magento class
 *
 */

class Vaimo_Klarna_Model_Klarna_Tools_Abstract extends Varien_Object
{
    /*
     * The following variables are set depending on what child it is
     * Setting payment will automatically also set the order, as it is known in the payment object
     */
    protected $_quote = NULL;
    protected $_order = NULL;
    protected $_invoice = NULL;
    protected $_payment = NULL;
    protected $_creditmemo = NULL;

    /*
     * Info instance is set for example when doing a refund, it also tries to set the credit memo and order, if they are known
     */
    protected $_info_instance = NULL;

    /*
     * The current payment method
     */
    protected $_method = NULL;

    /*
     * Store id that should be used while loading settings etc
     * It's set to Mage::app()->getStore()->getId() initially
     * But is then changed as soon as one of the record variables above is set
     */
    protected $_storeId = NULL;

    /*
     * Country, Language and Currency code of the current store or of the current record
     */
    protected $_countryCode = '';
    protected $_languageCode = NULL;
    protected $_currencyCode = NULL;

    /*
     * Both addresses are set when the records above are set
     */
    protected $_shippingAddress = NULL;
    protected $_billingAddress = NULL;

    /*
     * Contains the name of the function currently inheriting this class, only used for logs
     */
    protected $_functionName = NULL;
    
    protected $_pclassTypes = NULL;

    /**
     * The encoding used by the platform
     *
     * @var string
     */
    public static $platformEncoding = 'UTF-8';

    /**
     * The encoding expected by Klarna
     *
     * @var string
     */
    public static $klarnaEncoding = 'ISO-8859-1';

    /*
     * Languages supported by Klarna
     */
    protected $_supportedLanguages = array(
                                    'da', // Danish
                                    'de', // German
                                    'en', // English
                                    'fi', // Finnish
                                    'nb', // Norwegian
                                    'nl', // Dutch
                                    'sv', // Swedish
                                    );


    
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setStoreInformation();
    }

    /**
     * Encode the string to the klarnaEncoding
     *
     * @param string $str  string to encode
     * @param string $from from encoding
     * @param string $to   target encoding
     *
     * @return string
     */
    protected function _encode($str, $from = null, $to = null)
    {
        if ($from === null) {
            $from = self::$platformEncoding;
        }
        if ($to === null) {
            $to = self::$klarnaEncoding;
        }
        return iconv($from, $to, $str);
    }

    /**
     * Decode the string to the platformEncoding
     *
     * @param string $str  string to decode
     * @param string $from from encoding
     * @param string $to   target encoding
     *
     * @return string
     */
    protected function _decode($str, $from = null, $to = null)
    {
        if ($from === null) {
            $from = self::$klarnaEncoding;
        }
        if ($to === null) {
            $to = self::$platformEncoding;
        }
        return iconv($from, $to, $str);
    }

    /**
     * Will set current store language, but there is a language override in the Klarna payment setting.
     * Language is sent to the Klarna API
     * The reason for the override is for example if you use the New Norwegian language in the site (nn as code),
     * Klarna will not allow that code, so we have the override
     *
     * @return void
     */
    protected function _setDefaultLanguageCode()
    {
        if ($this->_getConfigData('klarna_language')) {
            $this->_languageCode = $this->_getConfigData('klarna_language');
        } else {
            $localeCode = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $this->_getStoreId() );
            $this->_languageCode = $this->_getLocaleCode($localeCode);
        }
        if (!in_array($this->_languageCode, $this->_supportedLanguages)) {
            $this->_languageCode = 'en';
        }
    }
    
    /*
     * Gets the Default country of the store
     *
     * @return string
     */
    protected function _getDefaultCountry()
    {
        return strtoupper(Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_COUNTRY, $this->_getStoreId()));
    }

    /*
     * Sets the default currency to that of this store id
     *
     * @return void
     */
    protected function _setDefaultCurrencyCode()
    {
        $currencyCode = Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_DEFAULT, $this->_getStoreId() );
        $this->_currencyCode = strtoupper($currencyCode);
    }

    /*
     * Sets the default country to that of this store id
     *
     * @return void
     */
    protected function _setDefaultCountry()
    {
        $this->_countryCode = $this->_getDefaultCountry();
    }

    /*
     * Sets the store of this class and then updates the default values
     *
     * @return void
     */
    public function setStoreInformation($storeId = NULL)
    {
        if ($storeId === NULL) {
            $this->_storeId = Mage::app()->getStore()->getId();
        } else {
            $this->_storeId = $storeId;
        }
        $this->_setDefaultLanguageCode();
        $this->_setDefaultCurrencyCode();
        $this->_setDefaultCountry();
    }
    
    /**
     * Parse a locale code into a language code Klarna can use.
     *
     * @param string $localeCode The Magento locale code to parse
     *
     * @return string
     */
    protected function _getLocaleCode($localeCode)
    {
        $result = preg_match("/([a-z]+)_[A-Z]+/", $localeCode, $collection);
        if ($result !== 0) {
            return $collection[1];
        }
        return null;
    }

    /*
     * Once we have a record in one of the record variables, we update the addresses and then we set the country to
     * that of the shipping address or billing address, if shipping is empty
     *
     * @return void
     */
    protected function _updateCountry()
    {
        if ($this->_shippingAddress && $this->_shippingAddress->getCountry()) {
            $this->_countryCode = strtoupper($this->_shippingAddress->getCountry());
        } elseif ($this->_billingAddress && $this->_billingAddress->getCountry()) {
            $this->_countryCode = strtoupper($this->_billingAddress->getCountry());
        }
    }
    
    /**
     * Set current shipping address
     *
     * @param Mage_Customer_Model_Address_Abstract $address
     *
     * @return void
     */
    protected function _setShippingAddress($address)
    {
        $this->_shippingAddress = $address;
        $this->_updateCountry();
    }

    /**
     * Set current billing address
     *
     * @param Mage_Customer_Model_Address_Abstract $address
     *
     * @return void
     */
    protected function _setBillingAddress($address)
    {
        $this->_billingAddress = $address;
        $this->_updateCountry();
    }

    /**
     * Set current addresses from quote and updates this class currency
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return void
     */
    protected function _setAddressesFromQuote($quote)
    {
        $this->_setShippingAddress($quote->getShippingAddress());
        $this->_setBillingAddress($quote->getBillingAddress());
        $this->_currencyCode = $quote->getQuoteCurrencyCode();
    }

    /**
     * Set current addresses from order and updates this class currency
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return void
     */
    protected function _setAddressesFromOrder($order)
    {
        $this->_setShippingAddress($order->getShippingAddress());
        $this->_setBillingAddress($order->getBillingAddress());
        $this->_currencyCode = $order->getOrderCurrencyCode();
    }

    /*
     * Sets the function name, which is used in logs. This is set in each class construct
     *
     * @param string $functionName
     *
     * @return void
     */
    protected function _setFunctionName($functionName)
    {
        $this->_functionName = $functionName;
    }

    /*
     * Sets the payment method, either directly from the top class or when the appropriate record object is set
     *
     * @param string
     *
     * @return void
     */
    public function setMethod($method)
    {
        $this->_method = $method;
    }

    /*
     * Sets the order of this class plus updates what is known on the order, such as payment method, store and address
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return void
     */
    public function setOrder($order)
    {
        $this->_order = $order;
        if (!$this->getQuote() && $order->getQuote()) {
            $this->_quote = $order->getQuote();
        }
        $this->setMethod($this->getOrder()->getPayment()->getMethod());
        $this->setStoreInformation($this->getOrder()->getStoreId());
        $this->_setAddressesFromOrder($order);
    }
    
    /*
     * Sets the quote of this class plus updates what is known on the quote, store and address
     * Method can also be set by this function, if it is known
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param string Payment method
     *
     * @return void
     */
    public function setQuote($quote, $method = NULL)
    {
        $this->_quote = $quote;
        if ($method) {
            $this->setMethod($method);
        }
        $this->setStoreInformation($this->getQuote()->getStoreId());
        $this->_setAddressesFromQuote($quote);
    }
    
    /*
     * Sets the invoice of this class plus updates current store
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     *
     * @return void
     */
    public function setInvoice($invoice)
    {
        $this->_invoice = $invoice;
        $this->setStoreInformation($this->getInvoice()->getStoreId());
    }
    
    /*
     * Sets the creditmemo of this class
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     *
     * @return void
     */
    public function setCreditmemo($creditmemo)
    {
        $this->_creditmemo = $creditmemo;
    }
    
    /*
     * Sets the payment of this class plus updates what is known of other varibles, such as order, creditmemo and invoice
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     *
     * @return void
     */
    public function setPayment($payment)
    {
        $this->_payment = $payment;
        if ($this->getPayment()) {
            $this->setOrder($this->getPayment()->getOrder());
            if ($this->getPayment()->getCreditmemo()) {
                $this->setCreditmemo($this->getPayment()->getCreditmemo());
                if ($this->getCreditmemo()->getInvoice()) {
                    $this->setInvoice($this->getCreditmemo()->getInvoice());
                }
            }
        }
    }
    
    /*
     * Sets the info instance of this class plus updates what is known of other varibles, such as order and creditmemo
     *
     * @param Mage_Payment_Model_Info $info
     *
     * @return void
     */
    public function setInfoInstance($info)
    {
        $this->_info_instance = $info;
        if ($this->getInfoInstance()) {
            $this->setCreditmemo($this->getInfoInstance()->getCreditmemo());
            if ($this->getCreditmemo()) {
                $this->setOrder($this->getCreditmemo()->getOrder());
            }
        }
    }

    /*
     * Sets the pclasstype variable, which is an array containing what pclass types are acceptable with the current payment method
     *
     * @param array $types
     *
     * @return void
     */
    public function setPClassTypes($types)
    {
        $this->_pclassTypes = $types;
    }

    /**
     * Check if consent is needed
     *
     * @return boolean
     */
    public function needConsent()
    {
        switch ($this->_getCountryCode()) {
            case 'DE':
            case 'AT':
                return true;
            default:
                return false;
        }
    }

    /**
     * Check if an asterisk is needed
     *
     * @return boolean
     */
    public function needAsterisk()
    {
        return false;
    }

    /**
     * Check if gender is needed
     *
     * @return boolean
     */
    public function needGender()
    {
        switch ($this->_getCountryCode()) {
            case 'NL':
            case 'DE':
            case 'AT':
                return true;
            default:
                return false;
        }
    }

    /**
     * Check if date of birth is needed
     *
     * @return boolean
     */
    public function needDateOfBirth()
    {
        switch ($this->_getCountryCode()) {
            case 'NL':
            case 'DE':
            case 'AT':
                return true;
            default:
                return false;
        }
    }
    
    /**
     * Norway has special rules regarding the details of the payment plan you are selecting
     *
     * @return boolean
     */
    public function needExtraPaymentPlanInformaton()
    {
        switch ($this->_getCountryCode()) {
            case 'NO':
                return true;
            default:
                return false;
        }
    }

    /**
     * Return the fields a street should be split into.
     *
     * @return array
     */
    protected function _getSplit()
    {
        switch ($this->_getCountryCode()) {
            case 'DE':
                return array('street', 'house_number');
            case 'NL':
                return array('street', 'house_number', 'house_extension');
            default:
                return array('street');
        }
    }

    /*
     * Is the sum below the limit allowed for the given _country?
     * This contains a hardcoded value for NL.
     * Meaning, if a customer shops for over 250 EUR, it won't be allowed to use any part payment option...
     * I'm leaving this as hardcoded... But should get a better solution...
     *
     * @param float  $sum    Sum to check
     * @param string $method payment method
     *
     * @return boolean
     */
    public function isBelowAllowedHardcodedLimit($sum)
    {
        if ($this->_getCountryCode() !== 'NL') {
            return true;
        }

        if ($this->getMethod() === Vaimo_Klarna_Helper_Data::KLARNA_METHOD_INVOICE) {
            return true;
        }

        if (((double)$sum) <= 250.0) { // Hardcoded
            return true;
        }

        return false;
    }

    /**
     * Do we need to call getAddresses
     *
     * @return boolean
     */
    public function useGetAddresses()
    {
        switch ($this->_getCountryCode()) {
            case 'SE':
                return true;
            default:
                return false;
        }
    }

    /**
     * Are Company Purchases supported?
     *
     * @return boolean
     */
    public function isCompanyAllowed()
    {
        switch ($this->_getCountryCode()) {
            case 'NL':
            case 'DE':
            case 'AT':
                return false;
            default:
                return true;
        }
    }

    public function getAvailableMethods()
    {
        $res = array();
        $this->setMethod(Vaimo_Klarna_Helper_Data::KLARNA_METHOD_INVOICE);
        if ($this->_getConfigData('active')) {
            $res[] = $this->getMethod();
        }
        $this->setMethod(Vaimo_Klarna_Helper_Data::KLARNA_METHOD_ACCOUNT);
        if ($this->_getConfigData('active')) {
            $res[] = $this->getMethod();
        }
        $this->setMethod(Vaimo_Klarna_Helper_Data::KLARNA_METHOD_SPECIAL);
        if ($this->_getConfigData('active')) {
            $res[] = $this->getMethod();
        }
        return $res;
    }

    /**
     * Check if shipping and billing should be the same
     *
     * @return boolean
     */
    public function shippingSameAsBilling()
    {
        if ($this->_getConfigData('allow_separate_address')) {
            $res = false;
        } else {
            $res = true;
        }
        /*
        switch ($this->_getCountryCode()) {
            case 'NL':
            case 'DE':
                $res = true;
                break;
            default:
                $res = false;
                break;
        }
        */
        if (!$res) {
            if ($this->getQuote()) {
                $shipping = $this->getQuote()->isVirtual() ? null : $this->getQuote()->getShippingAddress();
                if ($shipping && $shipping->getSameAsBilling()) {
                    $res = true;
                }
            }
        }
        return $res;
    }

    /**
     * Check if current country is allowed
     *
     * @return boolean
     */
    public function isCountryAllowed()
    {
        if ($this->_getCountryCode() != $this->_getDefaultCountry()) {
            return false;
        }
        return true;
    }

    /*
     * Function to read correct payment method setting
     *
     * @param string $field
     *
     * @return string
     */
    protected function _getConfigData($field)
    {
        if ($this->getMethod() && $this->_getStoreId()!==NULL) {
            $res = Mage::getStoreConfig('payment/' . $this->getMethod() . '/' . $field, $this->_getStoreId());
        } else {
          $res = NULL;
        }
        return $res;
    }
    
    /*
     * Returns this class country code
     *
     * @return string
     */
    protected function _getCountryCode()
    {
        return $this->_countryCode;
    }
    
    /*
     * Returns this class language code
     *
     * @return string
     */
    protected function _getLanguageCode()
    {
        return $this->_languageCode;
    }
    
    /*
     * Returns this class currency code
     *
     * @return string
     */
    protected function _getCurrencyCode()
    {
        return $this->_currencyCode;
    }
    
    /*
     * Returns this class store id
     *
     * @return int
     */
    protected function _getStoreId()
    {
        return $this->_storeId;
    }
    
    /*
     * Returns this class payment method
     *
     * @return string
     */
    // Can probably be protected...
    public function getMethod()
    {
        return $this->_method;
    }
    
    /*
     * Returns the order set in this class
     *
     * @return Mage_Sales_Model_Order
     */
    // Can probably be protected...
    public function getOrder()
    {
        return $this->_order;
    }
    
    /*
     * Returns the creditmemo set in this class
     *
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    // Can probably be protected...
    public function getCreditmemo()
    {
        return $this->_creditmemo;
    }
    
    /*
     * Returns the invoice set in this class
     *
     * @return Mage_Sales_Model_Order_Invoice
     */
    // Can probably be protected...
    public function getInvoice()
    {
        return $this->_invoice;
    }
    
    /*
     * Returns the payment set in this class
     *
     * @return Mage_Sales_Model_Order_Payment
     */
    // Can probably be protected...
    public function getPayment()
    {
        return $this->_payment;
    }
    
    /*
     * Returns the info instance set in this class
     *
     * @return Mage_Payment_Model_Info
     */
    // Can probably be protected...
    public function getInfoInstance()
    {
        return $this->_info_instance;
    }
    
    /*
     * Returns the quote set in this class
     *
     * @return Mage_Sales_Model_Quote
     */
    // Can probably be protected...
    public function getQuote()
    {
        return $this->_quote;
    }

    /*
     * Returns the billing address set in this class
     *
     * @return Mage_Customer_Model_Address_Abstract
     */
    public function getBillingAddress()
    {
        return $this->_billingAddress;
    }

    /*
     * Returns the shipping address set in this class
     *
     * @return Mage_Customer_Model_Address_Abstract
     */
    public function getShippingAddress()
    {
        return $this->_shippingAddress;
    }
    
    /*
     * Returns the function name set by the constructors in this class
     *
     * @return string
     */
    protected function _getFunctionName()
    {
        return $this->_functionName;
    }
    
    /*
     * Returns the current payment methods title, as set in Klarna Payment settings
     *
     * @return string
     */
    public function getMethodTitle()
    {
        $res = Mage::helper('klarna')->__($this->_getConfigData('title'));
        if ($this->getQuote() && $this->getMethod()==Vaimo_Klarna_Helper_Data::KLARNA_METHOD_INVOICE) {
            $fee = Mage::helper('klarna')->getVaimoKlarnaFeeInclVat($this->getQuote());
            if ($fee) {
                $res .= ' (' . Mage::app()->getStore($this->getQuote()->getStoreId())->formatPrice($fee, false) . ')';
            }
        }
        return $res;
    }

    /*
     * Return allowed pclass types for current payment method
     *
     * @return array
     */
    protected function _getPClassTypes()
    {
        return $this->_pclassTypes;
    }

    /*
     * A function that returns a few setup values unique to the current active session
     * If currently selected method is not setup it will default to Invoice method and try again
     * It uses recursion, but can only call itself once
     *
     * @return Varien_Object
     */
    public function getKlarnaSetup()
    {
        try {
            if (!$this->_getConfigData("active")) {
                Mage::throwException($this->helper('klarna')->__('Current payment method not available'));
            }
            $res = new Varien_Object(
                array(
                    'merchant_id' => $this->_getConfigData("merchant_id"),
                    'country_code' => $this->_getCountryCode(),
                    'language_code' => $this->_getLanguageCode(),
                    'locale_code' => $this->_getLanguageCode() . "_" . $this->_getCountryCode(),
                    'currency_code' => $this->_getCurrencyCode(),
                    'host' => $this->_getConfigData("host"),
                    )
                );
        } catch( Exception $e ) {
            if ($this->getMethod()!=Vaimo_Klarna_Helper_Data::KLARNA_METHOD_INVOICE) {
                $this->setMethod(Vaimo_Klarna_Helper_Data::KLARNA_METHOD_INVOICE);
                $res = $this->getKlarnaSetup();
            } else {
                $res = new Varien_Object();
                $this->logKlarnaException($e);
            }
        }
        return $res;
    }

    /*
     * Log function that does the writing to log file
     *
     * @param string $filename  What file to write to, will be placed in site/var/klarna/ folder
     * @param string $msg       Text to log
     *
     * @return void
     */
    protected function _log($filename, $msg)
    {
        $logDir  = Mage::getBaseDir('var') . DS . 'klarna' . DS;
        $logFile = $logDir . $filename;

        try {
            if (!is_dir($logDir)) {
                mkdir($logDir);
                chmod($logDir, 0777);
            }
            if( file_exists($logFile) ){
                $fp = fopen( $logFile, "a" );
            } else {
                $fp = fopen( $logFile, "w" );
            }
            if( !$fp ) return null;
            fwrite( $fp, date("Y/m/d H:i:s") . ' ' . $this->_getFunctionName() . ': ' . $msg . "\n" );
            fclose( $fp );
        } catch( Exception $e ) {
            return;
        }
    }
    
    /*
     * Log function that logs all Klarna API calls and replies, this to see what functions are called and what reply they get
     *
     * @param string $comment Text to log
     *
     * @return void
     */
    protected function _logKlarnaApi($comment)
    {
        $this->_log('api.log', $comment);
    }
    
    /*
     * Log function used for various debug log information, array is optional
     *
     * @param string $info  Header of what is being logged
     * @param array $arr    The array to be logged
     *
     * @return void
     */
    protected function _logDebugInfo($info, $arr = NULL)
    {
        if (!$arr) {
            $this->_log('debug.log', $info);
        } else {
            $this->_log('debug.log', $info . "\narray( " . serialize($arr) . " )\n");
        }
    }
    
    /*
     * If there is an exception, this log function should be used
     * This is mainly meant for exceptions concerning klarna API calls, but can be used for any exception
     *
     * @param Exception $exception
     *
     * @return void
     */
    public function logKlarnaException($e)
    {
        $errstr = 'Exception:';
        if ($e->getCode()) $errstr = $errstr . ' Code: ' . $e->getCode();
        if ($e->getMessage()) $errstr = $errstr . ' Message: ' . $this->_decode($e->getMessage());
        if ($e->getLine()) $errstr = $errstr . ' Row: ' . $e->getLine();
        if ($e->getFile()) $errstr = $errstr . ' File: ' . $e->getFile();
        $this->_log('error.log', $errstr);
    }

    /*
     * Creates the path to the Klarna logotype, it depends on payment method, intended placemen and your merchant id
     *
     * @param $width the width of the logotype
     * @param $position const defined in Klarna Helper (checkout, product or frontpage)
     * @param $type optional const defined in Klarna Helper (invoice, account, both) if not provided, it will look at current payment method to figure it out
     *
     * @return string containing the full path to image
     */
    public function getKlarnaLogotype($width, $position, $type = NULL)
    {
        $res = "";
        if (!$type) {
            $type = Vaimo_Klarna_Helper_Data::KLARNA_LOGOTYPE_TYPE_INVOICE;
            switch ($this->getMethod()) {
                case Vaimo_Klarna_Helper_Data::KLARNA_METHOD_ACCOUNT:
                    $type = Vaimo_Klarna_Helper_Data::KLARNA_LOGOTYPE_TYPE_ACCOUNT;
                    break;
                case Vaimo_Klarna_Helper_Data::KLARNA_METHOD_SPECIAL:
                    if ($this->_getConfigData('cdn_logotype_override')) {
                        $res = $this->_getConfigData('cdn_logotype_override');
                        $res .= '" width="' . $width; // Adding width to the file location like this is ugly, but works fine
                        return $res;
                    }
                    $type = Vaimo_Klarna_Helper_Data::KLARNA_LOGOTYPE_TYPE_ACCOUNT;
                    break;
            }
        }
        switch ($position) {
            case Vaimo_Klarna_Helper_Data::KLARNA_LOGOTYPE_POSITION_FRONTEND:
                if ($type==Vaimo_Klarna_Helper_Data::KLARNA_LOGOTYPE_TYPE_BOTH) {
                    $res = 'https://cdn.klarna.com/public/images/' . $this->_getCountryCode() . '/badges/v1/' . $type . '/' . $this->_getCountryCode() . '_' . $type . '_badge_banner_blue.png?width=' . $width . '&eid=' . $this->_getConfigData('merchant_id');
                } else {
                    $res = 'https://cdn.klarna.com/public/images/' . $this->_getCountryCode() . '/badges/v1/' . $type . '/' . $this->_getCountryCode() . '_' . $type . '_badge_std_blue.png?width=' . $width . '&eid=' . $this->_getConfigData('merchant_id');
                }
                break;
            case Vaimo_Klarna_Helper_Data::KLARNA_LOGOTYPE_POSITION_PRODUCT:
                $res = 'https://cdn.klarna.com/public/images/' . $this->_getCountryCode() . '/logos/v1/' . $type . '/' . $this->_getCountryCode() . '_' . $type . '_logo_std_blue-black.png?width=' . $width . '&eid=' . $this->_getConfigData('merchant_id');
                break;
            case Vaimo_Klarna_Helper_Data::KLARNA_LOGOTYPE_POSITION_CHECKOUT:
                $res = 'https://cdn.klarna.com/public/images/' . $this->_getCountryCode() . '/badges/v1/' . $type . '/' . $this->_getCountryCode() . '_' . $type . '_badge_std_blue.png?width=' . $width . '&eid=' . $this->_getConfigData('merchant_id');
                break;
        }
        return $res;
    }

}
