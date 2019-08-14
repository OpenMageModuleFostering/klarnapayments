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
 *
 * This is the only file in the module that loads and uses the Klarna library folder
 * It should never be instantiated by itself, it can, but for readability one should not
 * No Klarna specific variables, constants or functions should be used outside this class
 *
 */
require_once Mage::getBaseDir('lib') . '/Klarna/transport/xmlrpc-3.0.0.beta/lib/xmlrpc.inc';
require_once Mage::getBaseDir('lib') . '/Klarna/Klarna.php';
require_once Mage::getBaseDir('lib') . '/Klarna/pclasses/mysqlstorage.class.php';


class Vaimo_Klarna_Model_Klarna_Api extends Vaimo_Klarna_Model_Klarna_Tools_Api
{
    protected $_klarnaApi = NULL;

    protected static $_session_key = 'klarna_address';

    public function __construct($klarnaApi = null, $payment = null)
    {
        parent::__construct();
        $this->_setFunctionName('api');

        $this->_payment = $payment;

        $this->_klarnaApi = $klarnaApi;
        if ($this->_klarnaApi == null) {
            $this->_klarnaApi = new Klarna();
        }
    }

    /**
     * Init funcition
     *
     * @todo If storeid is null, we need to find first store where Klarna is active, not just trust that default store has it active...
     */
    protected function _init($functionName)
    {
        $this->_setFunctionName($this->_getFunctionName() . '-' . $functionName);
        $klarnaSetup = $this->getKlarnaSetup();
        $host = $klarnaSetup->getHost();
        if ($host == 'LIVE') {
            $mode = Klarna::LIVE;
        } else {
            $mode = Klarna::BETA;
        }
        $this->_klarnaApi->config(
            $klarnaSetup->getMerchantId(),
            $this->_getConfigData("shared_secret"),
            $klarnaSetup->getCountryCode(),
            $klarnaSetup->getLanguageCode(),
            $klarnaSetup->getCurrencyCode(),
            $mode,              // Live / Beta
            'mysql',            // pcStorage
            $this->_getPCURI(), // pclasses.json
            true,               // ssl
            true                // candice
            );

        if (method_exists('Mage', 'getEdition')) {
            $magentoEdition = Mage::getEdition();
        } else {
            if (class_exists("Enterprise_UrlRewrite_Model_Redirect", false)) {
                $magentoEdition = "Enterprise";
            } else {
                $magentoEdition = "Community";
            }
        }
        $magentoVersion = Mage::getVersion();
        $module = (string)Mage::getConfig()->getNode()->modules->Vaimo_Klarna->name;
        $version = (string)Mage::getConfig()->getNode()->modules->Vaimo_Klarna->version;
        $this->_klarnaApi->setVersion('PHP_' . 'Magento ' . $magentoEdition . '_' . $magentoVersion . '_' . $module . '_' . $version);
    }

    public function reserve($amount)
    {
        try {
            $this->_init('reserveAmount');
            $this->_setAdditionalInformation($this->getPayment()->getAdditionalInformation());
            $items = $this->getPayment()->getKlarnaItemList();
            $this->_createGoodsList($items);
            $this->_setGoodsListReserve();
            $this->_setAddresses();
            $this->_logKlarnaApi('Call with personal ID ' . $this->_getPNO());

            $this->_klarnaApi->setEstoreInfo($this->getOrder()->getIncrementId());
            $result = $this->_klarnaApi->reserveAmount(
                $this->_getPNO(),
                $this->_getGender(),
                $this->getOrder()->getTotalDue(),
                KlarnaFlags::NO_FLAG,
                $this->_getPaymentPlan()
            );

            $res = array(
                Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID => $result[0],
                Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS => $this->_klarnaReservationStatusToCode($result[1])
            );

            $this->_logKlarnaApi('Response ' . $res[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS] . ' - ' . $res[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID]);
            if ($res[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS]==Vaimo_Klarna_Helper_Data::KLARNA_STATUS_PENDING) {
                if ($this->_getConfigData("pending_status_action")) {
                    $this->cancel($res[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID]);
                    Mage::throwException(Mage::helper('klarna')->__('Unable to pay with Klarna, please choose another payment method'));
                }
            }

            $this->_sendMethodEvent('vaimo_paymentmethod_order_reserved', $this->getOrder()->getTotalDue());

            $this->_cleanAdditionalInfo();

        } catch (KlarnaException $e) {
            $this->_logKlarnaApi('Response Error Code = ' . $e->getCode());
            $this->logKlarnaException($e);
            Mage::throwException($this->_decode($e->getMessage()));
        }
        return $res;
    }
    
    public function capture($amount)
    {
        try {
            $this->_init('capture');
            $this->_setAdditionalInformation($this->getPayment()->getAdditionalInformation());
            $items = $this->getPayment()->getKlarnaItemList();
            $this->_createGoodsList($items);
            $this->_setGoodsListCapture();
            $this->_setAddresses();
            
            $rno = $this->_getAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_RESERVATION_ID);
            $this->_logKlarnaApi('Call with reservation ID ' . $rno);

            $this->_klarnaApi->setEstoreInfo($this->getOrder()->getIncrementId());
            $this->_klarnaApi->setActivateInfo('orderid1', strval($this->getOrder()->getIncrementId()));

            $ocr = NULL;
            $flags = $this->_setCaptureFlags();

            $result = $this->_klarnaApi->activate($rno, $ocr, $flags);

            $res = array(
                Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS => $result[0],
                Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID => $result[1],
                Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_FEE_CAPTURED => $this->_feeAmountIncluded()
            );

            $this->_sendMethodEvent('vaimo_paymentmethod_order_captured', $this->getOrder()->getTotalDue());

            $this->_logKlarnaApi('Response ' . $res[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS] . ' - ' . $res[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID]);
        } catch (KlarnaException $e) {
            $this->_logKlarnaApi('Response Error Code = ' . $e->getCode());
            $this->logKlarnaException($e);
            Mage::throwException($this->_decode($e->getMessage()));
        }
        return $res;
    }
    
    public function refund($amount)
    {
        try {
            $this->_init('refund');
            $invno = $this->getInfoInstance()->getParentTransactionId();
            $this->_setAdditionalInformation($this->getInfoInstance()->getAdditionalInformation());
            $items = $this->getPayment()->getKlarnaItemList();
            $this->_createRefundGoodsList($items);

            switch ($this->_decideRefundMethod($amount)) {
                case self::REFUND_METHOD_FULL:
                    $this->_logKlarnaApi('Full with invoice ID ' . $invno);
                    $result = $this->_klarnaApi->creditInvoice($invno);
                    break;
                case self::REFUND_METHOD_AMOUNT:
                    $taxRate = 0;
                    foreach ($this->_getGoodsList() as $item) {
                        if (isset($item['tax'])) {
                            $taxRate = $item['tax'];
                            break;
                        }
                    }
//                    $amountExclDiscount = $amount - $this->getCreditmemo()->getDiscountAmount();
                    $this->_logKlarnaApi('Amount with invoice ID ' . $invno);
                    $result = $this->_klarnaApi->returnAmount($invno, $amount, $taxRate);
                    break;
                default: // self::REFUND_METHOD_PART
                    $this->_logKlarnaApi('Part with invoice ID ' . $invno);
                    $this->_setGoodsListRefund();
                    $result = $this->_klarnaApi->creditPart($invno);
                    break;
            }

            $res = array(
                Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS => 'OK',
                Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID => $result,
                Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_FEE_REFUNDED => $this->_feeAmountIncluded()
            );

            $this->_sendMethodEvent('vaimo_paymentmethod_order_refunded', $amount);

            $this->_logKlarnaApi('Response ' . $res[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS] . ' - ' . $res[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID]);
        } catch (KlarnaException $e) {
            $this->_logKlarnaApi('Response Error Code = ' . $e->getCode());
            $this->logKlarnaException($e);
            Mage::throwException($this->_decode($e->getMessage()));
        }
        return $res;
    }
    
    public function cancel($direct_rno = NULL)
    {
        try {
            $this->_init('cancel');
            $this->_setAdditionalInformation($this->getPayment()->getAdditionalInformation());

            if ($direct_rno) {
                $rno = $direct_rno;
            } else {
                $rno = $this->_getAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_RESERVATION_ID);
            }
            $this->_logKlarnaApi('Call with reservation ID ' . $rno);

            $result = $this->_klarnaApi->cancelReservation($rno);

            $res = array(
                Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS => $result,
            );

            $this->_sendMethodEvent('vaimo_paymentmethod_order_canceled', $this->getOrder()->getTotalDue());

            $this->_logKlarnaApi('Response ' . $res[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS]);
        } catch (KlarnaException $e) {
            $this->_logKlarnaApi('Response Error Code = ' . $e->getCode());
            $this->logKlarnaException($e);
            Mage::throwException($this->_decode($e->getMessage()));
        }
        return $res;
    }

    public function checkStatus()
    {
        try {
            $this->_init('check_status');
            $this->_setAdditionalInformation($this->getPayment()->getAdditionalInformation());
            
            $rno = $this->_getAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_RESERVATION_ID);
            $this->_logKlarnaApi('Call with reservation ID ' . $rno);

            $result = $this->_klarnaApi->checkOrderStatus($rno);

            $res = array(
                Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS => $this->_klarnaReservationStatusToCode($result),
            );

            $this->_logKlarnaApi('Response ' . $res[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS]);
        } catch (KlarnaException $e) {
            $this->_logKlarnaApi('Response Error Code = ' . $e->getCode());
            $this->logKlarnaException($e);
            Mage::throwException($this->_decode($e->getMessage()));
        }
        return $res;
    }
    
    protected function _klarnaReservationStatusToCode($status)
    {
        switch ($status) {
            case KlarnaFlags::ACCEPTED:
                $res = Vaimo_Klarna_Helper_Data::KLARNA_STATUS_ACCEPTED;
                break;
            case KlarnaFlags::PENDING:
                $res = Vaimo_Klarna_Helper_Data::KLARNA_STATUS_PENDING;
                break;
            case KlarnaFlags::DENIED:
                $res = Vaimo_Klarna_Helper_Data::KLARNA_STATUS_DENIED;
                break;
            default:
                $res = 'unknown_' . $status;
                break;
        }
        return $res;
    }
    
    /*
     * I have copied the cache function from previous Klarna module, it's only for this session
     *
     * @return array
     */
    public function getAddresses($pno)
    {

        $res = array();
        try {
            $cache = array();

            if (array_key_exists(self::$_session_key, $_SESSION)) {
                $cache = unserialize( base64_decode($_SESSION[self::$_session_key]) );
            }
            if (array_key_exists($pno, $cache)) {
                return $cache[$pno];
            }

            $this->_init('address');
            $this->_logKlarnaApi('Call with Personal ID ' . $pno);

            $result = $this->_klarnaApi->getAddresses($pno);
            foreach ($result as $klarnaAddr) {
                $res[] = array(
                    'company_name' => $this->_decode($klarnaAddr->getCompanyName()),
                    'first_name' => $this->_decode($klarnaAddr->getFirstName()),
                    'last_name' => $this->_decode($klarnaAddr->getLastName()),
                    'street' => $this->_decode($klarnaAddr->getStreet()),
                    'zip' => $this->_decode($klarnaAddr->getZipCode()),
                    'city' => $this->_decode($klarnaAddr->getCity()),
                    'house_number' => $this->_decode($klarnaAddr->getHouseNumber()),
                    'house_extension' => $this->_decode($klarnaAddr->getHouseExt()),
                    'country_code' => $klarnaAddr->getCountryCode(),
                    'id' => $this->getAddressKey($klarnaAddr)
                    );
            }


            $this->_logKlarnaApi('Response ' .'OK');

            $cache[$pno] = $res;
            $_SESSION[self::$_session_key] = base64_encode( serialize($cache) );

        } catch (KlarnaException $e) {
            $this->_logKlarnaApi('Response Error Code = ' . $e->getCode());
            $this->logKlarnaException($e);
            Mage::throwException($this->_decode($e->getMessage()));
        }
        return $res;
    }

    /**
     * Get a matching address using getAddresses
     *
     * @return array
     */
    protected function _getSelectedAddress($pno, $address_id)
    {
        try {
            $addresses = $this->getAddresses($pno);
            foreach ($addresses as $address) {
                if ($address['id']==$address_id) {
                    return $address;
                }
            }
        } catch (Mage_Core_Exception $e) {
            $this->logKlarnaException($e);
            return NULL;
        }
        return NULL;
    }

    public function reloadAllPClasses()
    {
        try {
            $countries = $this->_getKlarnaActiveStores();

            $this->_init('get-all-pclasses');

            $this->_logKlarnaApi('Call clear');
            $this->_klarnaApi->clearPClasses();
            $this->_logKlarnaApi('Call clear OK');

        } catch (KlarnaException $e) {
            $this->_logKlarnaApi('Response Error Code = ' . $e->getCode());
            $this->logKlarnaException($e);
            Mage::throwException($this->_decode($e->getMessage()));
        }

        foreach ($countries as $storeId) {
            try {
                $this->setStoreInformation($storeId);
                $this->_init('get-all-pclasses');

                $this->_logKlarnaApi('Call fetch all');
                $this->_klarnaApi->fetchPClasses($this->_getCountryCode(), $this->_getLanguageCode(), $this->_getCurrencyCode());
                $this->_logKlarnaApi('Call fetch all OK');

            } catch (KlarnaException $e) {
                $this->_logKlarnaApi('Response Error Code = ' . $e->getCode());
                $this->logKlarnaException($e);
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('klarna')->__('Fetching PClasses failed for store %s. Error: %s - %s', Mage::app()->getStore($storeId)->getName(), $e->getCode(), $e->getMessage())
                );
            }
        }
    }
    
    protected function _getAllPClasses($onlyCurrentStore = false)
    {
        try {
            $res = array();
            
            $this->_init('get-all-pclasses');

            $this->_logKlarnaApi('Call get all');
            $pclasses = $this->_klarnaApi->getAllPClasses();
            if (is_array($pclasses)) {
                foreach ($pclasses as $pclass) {
                    if ($onlyCurrentStore) {
                        if ($this->_getCountryCode()!=KlarnaCountry::getCode($pclass->getCountry())) {
                            continue;
                        }
                    }
                    $res[] = $pclass;
                }
            }
            $this->_logKlarnaApi('Response OK');
        } catch (KlarnaException $e) {
            $this->_logKlarnaApi('Response Error Code = ' . $e->getCode());
            $this->logKlarnaException($e);
            Mage::throwException($this->_decode($e->getMessage()));
        }
        return $res;
    }
    
    protected function _filterPClasses($pclasses, $page, $amount)
    {
        foreach ($pclasses as $id => $pclass) {
            $type = $pclass->getType();
            $monthlyCost = -1;

            if (!in_array($pclass->getType(), $this->_getPClassTypes())) {
                unset($pclasses[$id]);
                continue;
            }

            if ($pclass->getMinAmount()) {
                if ($amount < $pclass->getMinAmount()) {
                    unset($pclasses[$id]);
                    continue;
                }
            }


            if (in_array($type, array(KlarnaPClass::FIXED, KlarnaPClass::DELAY, KlarnaPClass::SPECIAL))) {
                if ($page == KlarnaFlags::PRODUCT_PAGE) {
                    unset($pclasses[$id]);
                    continue;
                }
            } else {
                $lowestPayment = KlarnaCalc::get_lowest_payment_for_account( $pclass->getCountry() );
                $monthlyCost = KlarnaCalc::calc_monthly_cost( $amount, $pclass, $page );
                if ($monthlyCost < 0.01) {
                    unset($pclasses[$id]);
                    continue;
                }

                if ($monthlyCost < $lowestPayment) {
                    if ($type == KlarnaPClass::CAMPAIGN) {
                        unset($pclasses[$id]);
                        continue;
                    }
                    if ($page == KlarnaFlags::CHECKOUT_PAGE && $type == KlarnaPClass::ACCOUNT) {
                        $monthlyCost = $lowestPayment;
                    }
                }
            }
        }
        return $pclasses;
    }
    
    /*
     * Same logic as function above, but it's easier to read if these functions are split...
     *
     */
    protected function _getPClassMinimum($pclasses, $page, $amount)
    {
        $minimum = NULL;
        $minval = NULL;
        foreach ($pclasses as $pclass) {
            $type = $pclass->getType();
            $monthlyCost = -1;

            if (!in_array($type, array(KlarnaPClass::FIXED, KlarnaPClass::SPECIAL))) {
                $lowestPayment = KlarnaCalc::get_lowest_payment_for_account( $pclass->getCountry() );
                $monthlyCost = KlarnaCalc::calc_monthly_cost( $amount, $pclass, $page );

                if ($monthlyCost < $lowestPayment) {
                    if ($page == KlarnaFlags::CHECKOUT_PAGE && $type == KlarnaPClass::ACCOUNT) {
                        $monthlyCost = $lowestPayment;
                    }
                }
            }

            if ($minimum === null || $minval > $monthlyCost) {
                $minimum = $pclass;
                $minval = $monthlyCost;
            }

        }
        return $minimum;
    }
    
    protected function _getDefaultPClass($pclasses)
    {
        $default = NULL;
        foreach ($pclasses as $pclass) {
            $type = $pclass->getType();

            if ($type == KlarnaPClass::ACCOUNT) {
                $default = $pclass;
            } else if ($type == KlarnaPClass::CAMPAIGN) {
                if ($default === NULL || $default->getType() != KlarnaPClass::ACCOUNT) {
                    $default = $pclass;
                }
            } else { 
                if ($default === NULL) {
                    $default = $pclass;
                }
            }
        }
        return $default;
    }
    
    protected function _PClassToArray($pclass, $amount, $default = NULL, $minimum = NULL)
    {
        $type = $pclass->getType();
        $monthlyCost = -1;

        if (!in_array($type, array(KlarnaPClass::FIXED, KlarnaPClass::SPECIAL))) {
            $lowestPayment = KlarnaCalc::get_lowest_payment_for_account( $pclass->getCountry() );
            $monthlyCost = KlarnaCalc::calc_monthly_cost( $amount, $pclass, KlarnaFlags::CHECKOUT_PAGE );

            if ($monthlyCost < $lowestPayment) {
                if ($type == KlarnaPClass::ACCOUNT) {
                    $monthlyCost = $lowestPayment;
                }
            }
        }
        $totalCost = KlarnaCalc::total_credit_purchase_cost($amount, $pclass, KlarnaFlags::CHECKOUT_PAGE);

        $pclassArr = $pclass->toArray();
        if ($default) {
            if ($pclass==$default) {
                $pclassArr['default'] = true;
            } else {
                $pclassArr['default'] = false;
            }
        } else {
            $pclassArr['default'] = NULL;
        }

        if ($minimum) {
            if ($pclass==$minimum) {
                $pclassArr['cheapest'] = true;
            } else {
                $pclassArr['cheapest'] = false;
            }
        } else {
            $pclassArr['cheapest'] = NULL;
        }
        $pclassArr['monthly_cost'] = $monthlyCost;
        $pclassArr['total_cost'] = $totalCost;
        return $pclassArr;
    }
    
    /*
     * 
     *
     */
    public function getValidCheckoutPClasses()
    {
        try {
            $amount = $this->getQuote()->getGrandTotal();
            $pclasses = $this->_getAllPClasses(true);
            $pclasses = $this->_filterPClasses($pclasses, KlarnaFlags::CHECKOUT_PAGE, $amount);
            $default = $this->_getDefaultPClass($pclasses);
            $minimum = $this->_getPClassMinimum($pclasses, KlarnaFlags::CHECKOUT_PAGE, $amount);

            $res = array();
            foreach ($pclasses as $pclass) {
                $pclassArr = $this->_PClassToArray($pclass, $amount, $default, $minimum);
                $res[] = $pclassArr;
            }
            if (sizeof($res)<=0) {
                $res = NULL;
            }
        } catch (Mage_Core_Exception $e) {
            $this->logKlarnaException($e);
            Mage::throwException($this->_decode($e->getMessage()));
        }
        return $res;
    }
    
    /*
     * 
     *
     */
    protected function _getSpecificPClass($id)
    {
        try {
        $amount = $this->getQuote()->getGrandTotal();
        $pclasses = $this->_getAllPClasses(true);

        $res = NULL;
        foreach ($pclasses as $pclass) {
            if ($pclass->getId()==$id) {
                $res = $this->_PClassToArray($pclass, $amount);
                break;
            }
        }
        } catch (Mage_Core_Exception $e) {
            $this->logKlarnaException($e);
        }
        return $res;
    }
    
    public function setPClassTypes($types)
    {
        if (!is_array($types)) {
            switch ($types) {
                case Vaimo_Klarna_Helper_Data::KLARNA_METHOD_ACCOUNT:
                    $types = array(KlarnaPClass::ACCOUNT,
                            KlarnaPClass::CAMPAIGN,
                            KlarnaPClass::FIXED
                        );
                    break;
                case Vaimo_Klarna_Helper_Data::KLARNA_METHOD_SPECIAL:
                    $types = array(KlarnaPClass::SPECIAL,
                            KlarnaPClass::DELAY
                        );
                    break;
            }
        }
        parent::setPClassTypes($types);

        return $types;
    }
    
    /*
     * 
     *
     */
    public function getDisplayAllPClasses()
    {
        $res = array();
        $pclasses = $this->_getAllPClasses(false);
        foreach ($pclasses as $pclass) {
            $pclassArr = $pclass->toArray();
            switch ($pclass->getCountry()) {
                case KlarnaCountry::SE:
                    $pclassArr['countryname']= Mage::helper('core')->__('Sweden');
                    break;
                case KlarnaCountry::NO:
                    $pclassArr['countryname']= Mage::helper('core')->__('Norway');
                    break;
                case KlarnaCountry::NL:
                    $pclassArr['countryname']= Mage::helper('core')->__('Netherlands');
                    break;
                case KlarnaCountry::DE:
                    $pclassArr['countryname']= Mage::helper('core')->__('Germany');
                    break;
                case KlarnaCountry::DK:
                    $pclassArr['countryname']= Mage::helper('core')->__('Denmark');
                    break;
                case KlarnaCountry::FI:
                    $pclassArr['countryname']= Mage::helper('core')->__('Finland');
                    break;
                default:
                    $pclassArr['countryname'] = Mage::helper('core')->__('Unknown');
                    break;
            }
            $res[] = $pclassArr;
        }
        return $res;
    }
    
    /**
     * Set the addresses on the Klarna object
     *
     * @return void
     */
    protected function _setAddresses()
    {
        $shipping = $this->toKlarna($this->getShippingAddress());
        $billing = $this->toKlarna($this->getBillingAddress());

        $email = $this->_getAdditionalInformation('email');
        $shipping->setEmail($email);
        $billing->setEmail($email);

        $this->_setReference($shipping, $billing);

        $this->_klarnaApi->setAddress(KlarnaFlags::IS_SHIPPING, $shipping);
        $this->_klarnaApi->setAddress(KlarnaFlags::IS_BILLING, $billing);

        $this->_logDebugInfo('shippingAddress', $shipping->toArray());
        $this->_logDebugInfo('billingAddress', $billing->toArray());

    }

    /**
     * Set the company reference for the purchase
     *
     * @param KlarnaAddr $shipping Klarna shipping address
     * @param KlarnaAddr $billing  Klarna billing address
     *
     * @return void
     */
    protected function _setReference($shipping, $billing)
    {
        $data = $this->_getAdditionalInformation();
        $reference = null;
        if (array_key_exists("reference", $data)) {
            $reference = $data["reference"];
        } elseif ($billing->isCompany) {
            $reference = $shipping->getFirstName() . " " . $shipping->getLastName();
        } elseif ($shipping->isCompany) {
            $reference = $billing->getFirstName() . " " . $billing->getLastName();
        }

        if (strlen($reference) == 0) {
            return;
        }
        $reference = html_entity_decode(trim($reference), ENT_COMPAT, 'ISO-8859-1');
        $this->_klarnaApi->setReference($reference, "");
        $this->_klarnaApi->setComment("Ref:{$reference}");
    }

    /**
     * Create a KlarnaAddr from a Magento address
     *
     * @param object $address The Magento address to convert
     *
     * @return KlarnaAddr
     */
    public function toKlarna($address)
    {
        if (!$address) return NULL;

        $streetArr = $address->getStreet();
        $street = $streetArr[0];
        if (count($streetArr) > 1) {
            $street .= " " . $streetArr[1];
        }

        $split = $this->_splitStreet($street);

        $houseNo = "";
        if (array_key_exists("house_number", $split)) {
            $houseNo = $split["house_number"];
        }

        $houseExt = "";
        if (array_key_exists("house_extension", $split)) {
            $houseExt = $split["house_extension"];
        }

        $klarnaAddr = new KlarnaAddr(
            "",
            $address->getTelephone(), // Telno
            "", // Cellno
            $this->_encode($address->getFirstname()),
            $this->_encode($address->getLastname()),
            "",
            $this->_encode(trim($split["street"])),
            $this->_encode($address->getPostcode()),
            $this->_encode($address->getCity()),
            $address->getCountry(),
            $this->_encode(trim($houseNo)),
            $this->_encode(trim($houseExt))
        );

        $company = $address->getCompany();
        if (strlen($company) > 0 && $this->isCompanyAllowed()) {
            $klarnaAddr->setCompanyName($this->_encode($company));
            $klarnaAddr->isCompany = true;
        } else {
            $klarnaAddr->setCompanyName('');
            $klarnaAddr->isCompany = false;
        }

        return $klarnaAddr;
    }

    /**
     * Add an article to the goods list and pad it with default values
     *
     * Keys : qty, sku, name, price, tax, discount, flags
     *
     * @param array $array The array to use
     *
     * @return void
     */
    protected function _addArticle($array)
    {
        $default = array(
            "qty" => 0,
            "sku" => "",
            "name" => "",
            "price" => 0,
            "tax" => 0,
            "discount" => 0,
            "flags" => KlarnaFlags::NO_FLAG
        );

        //Filter out null values and overwrite the default values
        $values = array_merge($default, array_filter($array));

        switch ($values['flags']) {
            case self::FLAG_ITEM_NORMAL:
                $values['flags'] = KlarnaFlags::INC_VAT;
                break;
            case self::FLAG_ITEM_SHIPPING_FEE:
                $values['flags'] = KlarnaFlags::INC_VAT | KlarnaFlags::IS_SHIPMENT;
                break;
            case self::FLAG_ITEM_HANDLING_FEE:
                $values['flags'] = KlarnaFlags::INC_VAT | KlarnaFlags::IS_HANDLING;
                break;
        }

        $this->_klarnaApi->addArticle(
            $values["qty"],
            $this->_encode($values["sku"]),
            $this->_encode($values["name"]),
            $values["price"],
            $values["tax"],
            $values["discount"],
            $values["flags"]
        );
        $this->_logDebugInfo('addArticle', $values);
    }

    /*
     * Add an article to the goods list and pad it with default values
     *
     * Keys : qty, sku, name, price, tax, discount, flags
     *
     * @param array $array The array to use
     *
     * @return void
     */
    protected function _addArtNo($array)
    {
        $default = array(
            "qty" => 0,
            "sku" => ""
        );

        //Filter out null values and overwrite the default values
        $values = array_merge($default, array_filter($array));

        $this->_klarnaApi->addArtNo(
            intval($values["qty"]),
            strval($this->_encode($values["sku"]))
            );
        $this->_logDebugInfo('addArticle', $values);
    }

    /**
     * Set the goods list for reservations
     *
     * @return void
     */
    protected function _setGoodsListReserve()
    {
        foreach ($this->_getGoodsList() as $item) {
            $this->_addArticle($item);
        }
        foreach ($this->_getExtras() as $extra) {
            $this->_addArticle($extra);
        }
    }

    /**
     * Set the goods list for Capture
     * Klarna seems to switch the order of the items in capture, so we simply add them backwards.
     *
     * @return void
     */
    protected function _setGoodsListCapture()
    {
        foreach (array_reverse($this->_getExtras()) as $extra) {
            $this->_addArtNo($extra);
        }
        foreach (array_reverse($this->_getGoodsList()) as $item) {
            $this->_addArtNo($item);
        }
    }
    
    /**
     * Set the goods list for Refund
     *
     * @return void
     */
    protected function _setGoodsListRefund()
    {
        foreach ($this->_getGoodsList() as $item) {
            $this->_addArtNo($item);
        }

        foreach ($this->_getExtras() as $extra) {
            $this->_addArtNo($extra);
        }
    }

    protected function _setCaptureFlags()
    {
        $res = NULL;
        if ($this->_getConfigData("send_klarna_email")) {
            $res = KlarnaFlags::RSRV_SEND_BY_EMAIL;

        }
        return $res;
    }
    
}
