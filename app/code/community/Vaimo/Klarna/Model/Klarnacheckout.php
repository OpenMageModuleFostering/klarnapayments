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

class Vaimo_Klarna_Model_Klarnacheckout extends Vaimo_Klarna_Model_Klarnacheckout_Abstract
{
    protected $_api = NULL;

    public function __construct($setStoreInfo = true, $moduleHelper = NULL, $coreHttpHelper = NULL, $coreUrlHelper = NULL, $customerHelper = NULL)
    {
        parent::__construct($setStoreInfo, $moduleHelper, $coreHttpHelper, $coreUrlHelper, $customerHelper);
        $this->_getHelper()->setFunctionNameForLog('klarnacheckout');
    }

    /**
     * Function added for Unit testing
     *
     * @param $apiObject
     */
    public function setApi($apiObject)
    {
        $this->_api = $apiObject;
    }

    /**
     * Will return the API object, it set, otherwise null
     */
    public function getApi()
    {
        return $this->_api;
    }

    /**
     * Could have been added to getApi, but I made it separate for Unit testing
     *
     * @param $storeId
     * @param $method
     * @param $functionName
     */
    protected function _initApi($storeId, $method, $functionName)
    {
        if (!$this->getApi()) {
            $this->setApi(Mage::getModel('klarna/api')->getApiInstance($storeId, $method, $functionName));
        }
    }

    /**
     * Init funcition
     *
     * @todo If storeid is null, we need to find first store where Klarna is active, not just trust that default store has it active...
     */
    protected function _init($functionName)
    {
        $this->_getHelper()->setFunctionNameForLog($this->_getHelper()->getFunctionNameForLog() . '-' . $functionName);
        $this->_initApi($this->_getStoreId(), $this->getMethod(), $functionName);
        $this->_api->init($this->getKlarnaSetup());
        $this->_api->setTransport($this->_getTransport());
    }


    public function getKlarnaOrderHtml($checkoutId = null, $createIfNotExists = false, $updateItems = false)
    {
        $this->_init(Vaimo_Klarna_Helper_Data::KLARNA_API_CALL_KCODISPLAY_ORDER);
        $this->_api->initKlarnaOrder($checkoutId, $createIfNotExists, $updateItems);
        return $this->_api->getKlarnaCheckoutGui();
    }
    
    /**
     * When we call this function, order is already done and complete. We can then cache
     * the information we get from Klarna so when we call initKlarnaOrder again (from
     * phtml files) we can use the cached order instead of fetching it again.
     * 
     * @param string $checkoutId
     * 
     * @return string
     */
    public function getCheckoutStatus($checkoutId = null)
    {
        $this->_init(Vaimo_Klarna_Helper_Data::KLARNA_API_CALL_KCODISPLAY_ORDER);
        $this->_api->setKlarnaOrderSessionCache(true);
        $this->_api->initKlarnaOrder($checkoutId);
        return $this->_api->getKlarnaCheckoutStatus();
    }
    
    /*
     * Not happy with this, but I guess we can't solve it in other ways.
     *
     */
    public function getActualKlarnaOrder()
    {
        return $this->_api->getActualKlarnaOrder();
    }
    
    /*
     * Will return the klarna order or null, if it doesn't find it
     * Not used by this module, but as a service for others.
     *
     */
    public function getKlarnaOrderRaw($checkoutId)
    {
        $this->_init(Vaimo_Klarna_Helper_Data::KLARNA_API_CALL_KCODISPLAY_ORDER);
        return $this->_api->getKlarnaOrderRaw($checkoutId);
    }
    
    public function validateQuote($checkoutId)
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $this->_loadQuoteByKey($checkoutId, 'klarna_checkout_id');

        if (!$quote->getId()) {
            $this->_getHelper()->logDebugInfo('validateQuote could not get quote');
            return 'could not get quote';
        }

        if (!$quote->hasItems()) {
            $this->_getHelper()->logDebugInfo('validateQuote has no items');
            return 'has no items';
        }

        if ($quote->getHasError()) {
            $result = array('has error');
            /** @var Mage_Core_Model_Message_Error $error */
            foreach ($quote->getErrors() as $error) {
                $result[] = $error->getText();
            }
            $this->_getHelper()->logDebugInfo('validateQuote errors: ' . implode(" ", $result));
            return implode("\n", $result);
        }

        if (!$quote->validateMinimumAmount()) {
            $this->_getHelper()->logDebugInfo('validateQuote below minimum amount');
            return 'minimum amount';
        }

        $quote->reserveOrderId()->save();
        $this->_getHelper()->logDebugInfo('validateQuote reserved order id: ' . $quote->getReservedOrderId());

        return true;
    }

    public function createOrder($checkoutId = null)
    {
        $this->_init(Vaimo_Klarna_Helper_Data::KLARNA_API_CALL_KCOCREATE_ORDER);
        if (!$this->_api->initKlarnaOrder($checkoutId)) {
            $this->_getHelper()->logDebugInfo('createOrder could not get klarna order');
            return 'could not get klarna order';
        }

        $quote = $this->_api->loadQuote();
        if (!$quote) {
            $this->_getHelper()->logDebugInfo('createOrder could not get quote');
            return 'could not get quote';
        }
        $this->setQuote($quote);

        $varienOrder = $this->_api->initVarienOrder();
        if (!$varienOrder) {
            $this->_getHelper()->logDebugInfo('createOrder could not create varienOrder');
            return 'could not create varienOrder';
        }

        $billingStreetAddress  = $varienOrder->getBillingAddress('street_address');
        $billingStreetAddress2  = $varienOrder->getBillingAddress('street_address2');
        $billingStreetName     = $varienOrder->getBillingAddress('street_name');
        $billingStreetNumber   = $varienOrder->getBillingAddress('street_number');
        $shippingStreetAddress = $varienOrder->getShippingAddress('street_address');
        $shippingStreetAddress2 = $varienOrder->getShippingAddress('street_address2');
        $shippingStreetName    = $varienOrder->getShippingAddress('street_name');
        $shippingStreetNumber  = $varienOrder->getShippingAddress('street_number');

        if (!$billingStreetAddress && $billingStreetName && $billingStreetNumber) {
            $streetAddress = $varienOrder->getBillingAddress();
            $streetAddress['street_address'] = $billingStreetName . ' ' . $billingStreetNumber;
            $varienOrder->setBillingAddress($streetAddress);
        }
        if ($billingStreetAddress2) {
            $streetAddress = $varienOrder->getBillingAddress();
            $streetAddress['street_address'] = array($streetAddress['street_address'], $billingStreetAddress2);
            $varienOrder->setBillingAddress($streetAddress);
        }

        if (!$shippingStreetAddress && $shippingStreetName && $shippingStreetNumber) {
            $streetAddress = $varienOrder->getShippingAddress();
            $streetAddress['street_address'] = $shippingStreetName . ' ' . $shippingStreetNumber;
            $varienOrder->setShippingAddress($streetAddress);
        }
        if ($shippingStreetAddress2) {
            $streetAddress = $varienOrder->getShippingAddress();
            $streetAddress['street_address'] = array($streetAddress['street_address'], $shippingStreetAddress2);
            $varienOrder->setShippingAddress($streetAddress);
        }

        if ($varienOrder->getStatus() != 'checkout_complete') {
            $this->_getHelper()->logDebugInfo('createOrder status not complete');
            return 'status not complete';
        }

        $orderId = $this->_findAlreadyCreatedOrder($quote->getId());
        if ($orderId>0) {
            $this->_getHelper()->logDebugInfo('createOrder order already created ' . $orderId);
            return 'order already created';
        }
        $isNewCustomer = false;

        if ($quote->getCustomerId()) {
            $customer = $this->_loadCustomer($quote->getCustomerId());
            $quote->setCustomer($customer);
            $quote->setCheckoutMethod('customer');
        } else {
            /** @var $customer Mage_Customer_Model_Customer */
            $customer = $this->_loadCustomerByEmail($varienOrder->getBillingAddress('email'), $quote->getStore());
            if ($customer->getId()) {
                $quote->setCustomer($customer);
                $quote->setCheckoutMethod('customer');
            } else {
                $quote->setCheckoutMethod('register');
                $isNewCustomer = true;
            }
        }

        $billingAddress = $quote->getBillingAddress();
        $customerAddressId = 0;

        if ($customerAddressId) {
            $customerAddress = $this->_loadCustomerAddress($customerAddressId);
            if ($customerAddress->getId()) {
                if ($customerAddress->getCustomerId() != $this->getQuote()->getCustomerId()) {
                    throw new Exception('Customer Address is not valid');
                }

                $billingAddress->importCustomerAddress($customerAddress)->setSaveInAddressBook(0);
            }
        } else {
            $billingAddress->setFirstname($varienOrder->getBillingAddress('given_name'));
            $billingAddress->setLastname($varienOrder->getBillingAddress('family_name'));
            $billingAddress->setCareOf($varienOrder->getBillingAddress('care_of'));
            $billingAddress->setStreet($varienOrder->getBillingAddress('street_address'));
            $billingAddress->setPostcode($varienOrder->getBillingAddress('postal_code'));
            $billingAddress->setCity($varienOrder->getBillingAddress('city'));
            $billingAddress->setCountryId(strtoupper($varienOrder->getBillingAddress('country')));
            $billingAddress->setEmail($varienOrder->getBillingAddress('email'));
            $billingAddress->setTelephone($varienOrder->getBillingAddress('phone'));
            $billingAddress->setSaveInAddressBook(1);
        }

//        $this->_validateCustomerData($data);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setFirstname($varienOrder->getShippingAddress('given_name'));
        $shippingAddress->setLastname($varienOrder->getShippingAddress('family_name'));
        $shippingAddress->setCareOf($varienOrder->getShippingAddress('care_of'));
        $shippingAddress->setStreet($varienOrder->getShippingAddress('street_address'));
        $shippingAddress->setPostcode($varienOrder->getShippingAddress('postal_code'));
        $shippingAddress->setCity($varienOrder->getShippingAddress('city'));
        $shippingAddress->setCountryId(strtoupper($varienOrder->getShippingAddress('country')));
        $shippingAddress->setEmail($varienOrder->getShippingAddress('email'));
        $shippingAddress->setTelephone($varienOrder->getShippingAddress('phone'));

        if ($this->getConfigData('packstation_enabled')) {
            $shippingAddress->setSameAsBilling(0);
        } else {
            $shippingAddress->setSameAsBilling(1);
        }
        $shippingAddress->setSaveInAddressBook(0);

        $quote->getBillingAddress()->setShouldIgnoreValidation(true);
        $quote->getShippingAddress()->setShouldIgnoreValidation(true);

        $quote->setTotalsCollectedFlag(true);
        $quote->save();

        switch ($quote->getCheckoutMethod()) {
            case 'register':
                $this->_prepareNewCustomerQuote($quote);
                break;
            case 'customer':
                $this->_prepareCustomerQuote($quote);
                break;
        }

        $service = $this->_getServiceQuote($quote);
        $service->submitAll();

        if ($isNewCustomer) {
            try {
                $this->_involveNewCustomer($quote);
            } catch (Exception $e) {
                $this->_getHelper()->logKlarnaException($e);
            }
        }

        $quote->save();

        $reservation = $varienOrder->getReservation();
        if ($varienOrder->getOrderId()) {
            $reservation = $varienOrder->getOrderId();
        }

        // Update Order
        /** @var $order Mage_Sales_Model_Order */
        $order = $this->_loadOrderByKey($quote->getId());
        $payment = $order->getPayment();

        if ($varienOrder->getReference()) {
            $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_REFERENCE, $varienOrder->getReference());
        } else if ($varienOrder->getKlarnaReference()) {
            $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_REFERENCE, $varienOrder->getKlarnaReference());
        }

        if ($reservation) {
            $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_RESERVATION_ID, $reservation);
        }

        $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_FORM_FIELD_PHONENUMBER, $varienOrder->getBillingAddress('phone'));
        $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_FORM_FIELD_EMAIL, $varienOrder->getBillingAddress('email'));

        $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_HOST, $this->getConfigData("host") );
        $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_MERCHANT_ID, $this->getConfigData("merchant_id") );


        $payment->setTransactionId($reservation)
            ->setIsTransactionClosed(0)
            ->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_APPROVED);
        if ($transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH)) {
            $transaction->save();
        }
        $payment->save();

        // send new order email
        if ($order->getCanSendNewEmailFlag()) {
            try {
                $order->sendNewOrderEmail();
            } catch (Exception $e) {
                $this->_getHelper()->logKlarnaException($e);
            }
        }

        // Subscribe customer to newsletter
        try {
            if ($quote->getKlarnaCheckoutNewsletter()) {
                $this->_addToSubscription($varienOrder->getBillingAddress('email'));
            }
        } catch(Exception $e) {
            $this->_getHelper()->logKlarnaException($e);
        }

        $this->_getHelper()->dispatchMethodEvent($order, Vaimo_Klarna_Helper_Data::KLARNA_DISPATCH_RESERVED, $order->getTotalDue(), $this->getMethod());

        $this->_getHelper()->logDebugInfo('createOrder successfully created order with no: ' . $order->getIncrementId());

        $this->_api->updateKlarnaOrder($order);

        return $order;
    }
    
    public function getKlarnaCheckoutEnabled()
    {
        $remoteAddr = $this->_getCoreHttpHelper()->getRemoteAddr();
        $message = $remoteAddr . ' ' . $this->_getCoreUrlHelper()->getCurrentUrl();

        if (!$this->getConfigData('active')) {
            return false;
        }

        if ($this->_getCustomerHelper()->isLoggedIn() && !$this->getConfigData('allow_when_logged_in')) {
            return false;
        }
        $allowedCustomerGroups = $this->getConfigData('allow_customer_group');
        if (isset($allowedCustomerGroups)) {
            $allowedCustomerGroups = explode(',', $allowedCustomerGroups);

            if (!in_array(Vaimo_Klarna_Helper_Data::KLARNA_CHECKOUT_ALLOW_ALL_GROUP_ID, $allowedCustomerGroups)) {
                $customerGroupId = $this->_getCustomerSession()->getCustomerGroupId();
                if (!in_array($customerGroupId, $allowedCustomerGroups)) {
                    return false;
                }
            }
        }
        if ($allowedIpRange = $this->getConfigData('allowed_ip_range')) {
            $ipParts = explode('.', $remoteAddr);

            if (is_array($ipParts) && count($ipParts) >= 4) {
                $lastDigit = intval($ipParts[3]);
            } else {
                $lastDigit = 0;
            }

            list ($allowIpFrom, $allowIpTo) = explode('-', $allowedIpRange, 2);

            if ($lastDigit >= (int)$allowIpFrom && $lastDigit <= (int)$allowIpTo) {
                return true;
            } else {
                return false;
            }
        }

        return true;
    }

    public function checkNewsletter()
    {
        // set newsletter subscribe based on settings
        if ($this->getQuote()->getKlarnaCheckoutNewsletter() == null) {
            $type = (int)$this->getConfigData('enable_newsletter');
            $checked = (bool)$this->getConfigData('newsletter_checked');

            if (($type == Vaimo_Klarna_Helper_Data::KLARNA_CHECKOUT_NEWSLETTER_SUBSCRIBE && $checked)
                || ($type == Vaimo_Klarna_Helper_Data::KLARNA_CHECKOUT_NEWSLETTER_DONT_SUBSCRIBE && !$checked)) {
                $this->getQuote()->setKlarnaCheckoutNewsletter(1);
            } else {
                $this->getQuote()->setKlarnaCheckoutNewsletter(0);
            }
            $this->getQuote()->save();
        }

        return $this;
    }

    protected function _getTransport()
    {
        return $this;
    }
    
}
