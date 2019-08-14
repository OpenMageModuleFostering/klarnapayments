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

class Vaimo_Klarna_Model_Api_Rest extends Vaimo_Klarna_Model_Api_Abstract
{
    protected $_url = NULL;

    protected $_curlHeaders;
    protected $_klarnaOrder = null;
    protected $_useKlarnaOrderSessionCache = false;
    protected $_request = NULL;

    public function init($klarnaSetup)
    {
        $this->_klarnaSetup = $klarnaSetup;
        if ($this->_klarnaSetup->getHost() == 'BETA') {
            $this->_url = 'https://api.playground.klarna.com';
        } else {
            $this->_url = 'https://api.klarna.com';
        }
    }
    
    protected function _getUrl()
    {
        return $this->_url;
    }

    public function curlHeader($ch, $str)
    {
        Mage::helper('klarna')->logDebugInfo('curlHeader rest str = ' . $str);
        if (strpos($str, ': ') !== false) {
            list($key, $value) = explode(': ', $str, 2);
            $this->_curlHeaders[$key] = trim($value);
        }

        return strlen($str);
    }

    protected function _getLocationOrderId($location = NULL)
    {
        if ($location) {
            $res = $location;
        } else {
            $res = $this->_klarnaOrder->getLocation();
        }
        $arr = explode('/', $res);
        if (is_array($arr)) {
            $res = $arr[sizeof($arr)-1];
        }
        return $res;
    }
    
    protected function _getBillingAddressData()
    {
        if (!$this->_getTransport()->getConfigData('auto_prefil')) return NULL;

        /** @var $session Mage_Customer_Model_Session */
        $session = Mage::getSingleton('customer/session');
        if ($session->isLoggedIn()) {
            $address = $session->getCustomer()->getPrimaryBillingAddress();
            return array(
                'email' => $session->getCustomer()->getEmail(),
                'postal_code' => $address ? $address->getPostcode() : '',
                'street_address' => $address ? $address->getStreet(1) : '',
                'given_name' => $address ? $address->getFirstname() : '',
                'family_name' => $address ? $address->getLastname() : '',
                'city' => $address ? $address->getCity() : '',
                'phone' => $address ? $address->getTelephone() : '',
                'country' => $address ? $address->getCountryId() : '',
            );
        }

        return array();
    }

    /**
     * Get active Klarna checkout id
     *
     * @return string
     */
    protected function _getKlarnaCheckoutId()
    {
        return $this->_getQuote()->getKlarnaCheckoutId();
    }

    /**
     * Put Klarna checkout id to quote
     *
     * @param $checkoutId string
     */
    protected function _setKlarnaCheckoutId($checkoutId)
    {
        $quote = $this->_getQuote();

        if ($quote->getKlarnaCheckoutId() != $checkoutId) {
            Mage::helper('klarna')->logDebugInfo('SET checkout id rest: ' . $checkoutId);
            Mage::helper('klarna')->logDebugInfo('Quote Id rest: ' . $quote->getId());
            $quote->setKlarnaCheckoutId($checkoutId);
            $quote->save();
        }

        Mage::getSingleton('checkout/session')->setKlarnaCheckoutId($checkoutId);
    }

    protected function _getCartItems()
    {
        $quote = $this->_getQuote();
        $items = array();
        $calculator = Mage::getSingleton('tax/calculation');

        foreach ($quote->getAllVisibleItems() as $quoteItem) {
            if ($quoteItem->getTaxPercent() > 0) {
                $taxRate = $quoteItem->getTaxPercent();
            } else {
                $taxRate = $quoteItem->getTaxAmount() / $quoteItem->getRowTotal() * 100;
            }
            $taxAmount = $calculator->calcTaxAmount($quoteItem->getRowTotalInclTax(), $taxRate, true, true);
            $items[] = array(
                'type' => 'physical',
                'reference' => $quoteItem->getSku(),
                'name' => $quoteItem->getName(),
                'quantity' => round($quoteItem->getQty()),
                'quantity_unit' => 'pcs',
                'unit_price' => round($quoteItem->getPriceInclTax() * 100),
//                'discount_rate' => round($quoteItem->getDiscountPercent() * 100),
                'tax_rate' => round($taxRate * 100),
                'total_amount' => round($quoteItem->getRowTotalInclTax() * 100),
                'total_tax_amount' => round($taxAmount * 100),
            );
        }

        foreach ($quote->getTotals() as $key => $total) {
            switch ($key) {
                case 'shipping':
                    if ($total->getValue() != 0) {
                        $amount_incl_tax = $total->getAddress()->getShippingInclTax();
                        if (false && $amount_incl_tax) {
                            $taxAmount = $total->getAddress()->getShippingTaxAmount();
                            $amount = $amount_incl_tax - $taxAmount;
                        } else {
                            $amount = $total->getAddress()->getShippingAmount();
                            $taxAmount = $total->getAddress()->getShippingTaxAmount();
                        }
                        $hiddenTaxAmount = $total->getAddress()->getShippingHiddenTaxAmount();
                        $taxRate = ($taxAmount + $hiddenTaxAmount) / $amount * 100;
                        $amount_incl_tax = $amount + $taxAmount + $hiddenTaxAmount;
                        $items[] = array(
                            'type' => 'shipping_fee',
                            'reference' => $total->getCode(),
                            'name' => $total->getTitle(),
                            'quantity' => 1,
                            'unit_price' => round(($amount_incl_tax) * 100),
                            'discount_rate' => 0,
                            'tax_rate' => round($taxRate * 100),
                            'total_amount' => round(($amount_incl_tax) * 100),
                            'total_tax_amount' => round($taxAmount * 100),
                        );
                    }
                    break;
                case 'discount':
                    if ($total->getValue() != 0) {
                        // ok, this is a bit shaky here, i know...
                        // but i don't have discount tax anywhere but in hidden_tax_amount field :(
                        // and I have to send discount also with tax rate to klarna
                        // otherwise the total tax wouldn't match
                        $taxAmount = $total->getAddress()->getHiddenTaxAmount();
                        $amount = -$total->getAddress()->getDiscountAmount() - $taxAmount;
                        $taxRate = $taxAmount / $amount * 100;
                        $items[] = array(
                            'type' => 'discount',
                            'reference' => $total->getCode(),
                            'name' => $total->getTitle(),
                            'quantity' => 1,
                            'unit_price' => -round(($amount + $taxAmount) * 100),
                            'discount_rate' => 0,
                            'tax_rate' => round($taxRate * 100),
                            'total_amount' => -round(($amount + $taxAmount) * 100),
                            'total_tax_amount' => -round($taxAmount * 100),
                        );
                    }
                    break;
                case 'giftcardaccount':
                    if ($total->getValue() != 0) {
                        $items[] = array(
                            'type' => 'discount',
                            'reference' => $total->getCode(),
                            'name' => $total->getTitle(),
                            'quantity' => 1,
                            'unit_price' => round($total->getValue() * 100),
                            'discount_rate' => 0,
                            'tax_rate' => 0,
                            'total_amount' => round($total->getValue() * 100),
                            'total_tax_amount' => 0,
                        );
                    }
                    break;
                case 'ugiftcert':
                    if ($total->getValue() != 0) {
                        $items[] = array(
                            'type' => 'discount',
                            'reference' => $total->getCode(),
                            'name' => $total->getTitle(),
                            'quantity' => 1,
                            'unit_price' => -round($total->getValue() * 100),
                            'discount_rate' => 0,
                            'tax_rate' => 0,
                            'total_amount' => round($total->getValue() * 100),
                            'total_tax_amount' => 0,
                        );
                    }
                    break;
                case 'reward':
                    if ($total->getValue() != 0) {
                        $items[] = array(
                            'type' => 'discount',
                            'reference' => $total->getCode(),
                            'name' => $total->getTitle(),
                            'quantity' => 1,
                            'unit_price' => round($total->getValue() * 100),
                            'discount_rate' => 0,
                            'tax_rate' => 0,
                            'total_amount' => round($total->getValue() * 100),
                            'total_tax_amount' => 0,
                        );
                    }
                    break;
                case 'customerbalance':
                    if ($total->getValue() != 0) {
                        $items[] = array(
                            'type' => 'discount',
                            'reference' => $total->getCode(),
                            'name' => $total->getTitle(),
                            'quantity' => 1,
                            'unit_price' => round($total->getValue() * 100),
                            'discount_rate' => 0,
                            'tax_rate' => 0,
                            'total_amount' => round($total->getValue() * 100),
                            'total_tax_amount' => 0,
                        );
                    }
                    break;
            }
        }

        return $items;
    }

    protected function _getCreateRequest()
    {
        $create = array();
        if (version_compare(Mage::getVersion(), '1.6.2', '>=')) {
            $create['purchase_country'] = Mage::helper('core')->getDefaultCountry();
        } else {
            $create['purchase_country'] = Mage::getStoreConfig('general/country/default');
        }
        $create['purchase_currency'] = $this->_getQuote()->getQuoteCurrencyCode();
        $create['locale'] = str_replace('_', '-', Mage::app()->getLocale()->getLocaleCode());

        $create['gui']['layout'] = $this->_isMobile() ? 'mobile' : 'desktop';
        if ($this->_getTransport()->getConfigData('enable_auto_focus')==false) {
            $create['gui']['options'] = array('disable_autofocus');
        }
        if ($this->_getTransport()->AllowSeparateAddress()) {
            $create['options']['allow_separate_shipping_address'] = true;
        }
        if ($this->_getTransport()->getConfigData('force_phonenumber')) {
            $create['options']['phone_mandatory'] = true;
        }
        if ($this->_getTransport()->getConfigData('packstation_enabled')) {
            $create['options']['packstation_enabled'] = true;
        }

        $this->_addUserDefinedVariables($create);

        if ($data = $this->_getBillingAddressData()) {
            $create['billing_address'] = $data;
        }

        $create['order_amount'] = round($this->_getQuote()->getGrandTotal() * 100);
        $create['order_tax_amount'] = 0;
        $create['order_lines'] = $this->_getCartItems();

        foreach ($create['order_lines'] as $line) {
            $create['order_tax_amount'] += $line['total_tax_amount'];
        }

        $pushUrl = Mage::getUrl('checkout/klarna/push?klarna_order={checkout.order.id}', array('_nosid' => true));
        if (substr($pushUrl, -1, 1) == '/') {
            $pushUrl = substr($pushUrl, 0, strlen($pushUrl) - 1);
        }

        $create['merchant_urls']['terms'] = Mage::getUrl(Mage::getStoreConfig('payment/vaimo_klarna_checkout/terms_url'));
        $create['merchant_urls']['checkout'] = Mage::getUrl('checkout/klarna');
        $create['merchant_urls']['confirmation'] = Mage::getUrl('checkout/klarna/success');
        $create['merchant_urls']['push'] = $pushUrl;

        $validateUrl = Mage::getUrl('checkout/klarna/validate?klarna_order={checkout.order.id}', array('_nosid' => true));
        if (substr($validateUrl, -1, 1) == '/') {
            $validateUrl = substr($validateUrl, 0, strlen($validateUrl) - 1);
        }
        if (substr($validateUrl, 0, 5) == 'https') {
            $create['merchant']['validation_uri'] = $validateUrl;
        }

        Mage::helper('klarna')->logDebugInfo('_getCreateRequest rest', $create);

        $request = new Varien_Object($create);
        Mage::dispatchEvent('klarnacheckout_get_create_request', array('request' => $request));

        return $request->getData();
    }

    protected function _getUpdateRequest()
    {
        $update = array();

        if ($data = $this->_getBillingAddressData()) {
            $update['billing_address'] = $data;
        }

        $update['order_amount'] = round($this->_getQuote()->getGrandTotal() * 100);
        $update['order_tax_amount'] = 0;
        $update['order_lines'] = $this->_getCartItems();

        foreach ($update['order_lines'] as $line) {
            $update['order_tax_amount'] += $line['total_tax_amount'];
        }

        Mage::helper('klarna')->logDebugInfo('_getUpdateRequest rest', $update);

        $request = new Varien_Object($update);
        Mage::dispatchEvent('klarnacheckout_get_update_request', array('request' => $request));

        return $request->getData();
    }

    protected function _createOrder()
    {
        $this->_curlHeaders = array();

        $request = $this->_getCreateRequest();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_getUrl() . '/checkout/v3/orders');
        curl_setopt($ch, CURLOPT_USERPWD, $this->_getTransport()->getConfigData('merchant_id') . ':' . $this->_getTransport()->getConfigData('shared_secret'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'curlHeader'));
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status != 201) {
            Mage::throwException('Error creating order: ' . $status);
        }

        Mage::helper('klarna')->logDebugInfo('_createOrder rest response = ' . $response . ' status = ' . $status);

        if (isset($this->_curlHeaders['Location'])) {
            return $this->_getLocationOrderId($this->_curlHeaders['Location']);
        }

        return false;
    }

    protected function _fetchOrder($checkoutId)
    {
        $ch = curl_init();
        $location = $this->_getUrl() . '/checkout/v3/orders/' . $checkoutId;
        curl_setopt($ch, CURLOPT_URL, $location);
        curl_setopt($ch, CURLOPT_USERPWD, $this->_getTransport()->getConfigData('merchant_id') . ':' . $this->_getTransport()->getConfigData('shared_secret'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'curlHeader'));
        curl_setopt($ch, CURLOPT_HEADER, false);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status != 200) {
            Mage::throwException('Error fetching order: ' . $status);
        }

        Mage::helper('klarna')->logDebugInfo('_fetchOrder rest response = ' . $response . ' status = ' . $status);

        $this->_klarnaOrder = new Varien_Object(json_decode($response, true));
        $this->_klarnaOrder->setLocation($location);
    }

    protected function _updateOrder($checkoutId)
    {
        $ch = curl_init();
        $location = $this->_getUrl() . '/checkout/v3/orders/' . $checkoutId;
        curl_setopt($ch, CURLOPT_URL, $location);
        curl_setopt($ch, CURLOPT_USERPWD, $this->_getTransport()->getConfigData('merchant_id') . ':' . $this->_getTransport()->getConfigData('shared_secret'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'curlHeader'));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->_getCreateRequest()));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status != 200) {
            Mage::throwException('Error updating order: ' . $status);
        }

        Mage::helper('klarna')->logDebugInfo('_updateOrder rest response = ' . $response . ' status = ' . $status);

        $this->_klarnaOrder = new Varien_Object(json_decode($response, true));
        $this->_klarnaOrder->setLocation($location);
    }

    protected function _retrieveOrder($orderId)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_getUrl() . '/ordermanagement/v1/orders/' . $orderId);
        curl_setopt($ch, CURLOPT_USERPWD, $this->_getTransport()->getConfigData('merchant_id') . ':' . $this->_getTransport()->getConfigData('shared_secret'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'curlHeader'));
        curl_setopt($ch, CURLOPT_HEADER, false);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Mage::helper('klarna')->logDebugInfo('_retrieveOrder rest response = ' . $response . ' status = ' . $status);

        return new Varien_Object(json_decode($response, true));
    }

    protected function _acknowledgeOrder($orderId)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_getUrl() . '/ordermanagement/v1/orders/' . $orderId . '/acknowledge');
        curl_setopt($ch, CURLOPT_USERPWD, $this->_getTransport()->getConfigData('merchant_id') . ':' . $this->_getTransport()->getConfigData('shared_secret'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'curlHeader'));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Mage::helper('klarna')->logDebugInfo('_acknowledgeOrder rest response = ' . $response . ' status = ' . $status);
    }

    protected function _updateMerchantReferences($orderId, $reference1, $reference2 = null)
    {
        $request = array(
            'merchant_reference1' => $reference1
        );

        if ($reference2) {
            $request['merchant_reference2'] = $reference2;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_getUrl() . '/ordermanagement/v1/orders/' . $orderId . '/merchant-references');
        curl_setopt($ch, CURLOPT_USERPWD, $this->_getTransport()->getConfigData('merchant_id') . ':' . $this->_getTransport()->getConfigData('shared_secret'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'curlHeader'));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Mage::helper('klarna')->logDebugInfo('_updateMerchantReferences rest response = ' . $response . ' status = ' . $status);
    }

    public function capture($orderId, $amount, $sendEmailf)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_getUrl() . '/ordermanagement/v1/orders/' . $orderId . '/captures');
        curl_setopt($ch, CURLOPT_USERPWD, $this->_getTransport()->getConfigData('merchant_id') . ':' . $this->_getTransport()->getConfigData('shared_secret'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'curlHeader'));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->_request));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Mage::helper('klarna')->logDebugInfo('capture rest response = ' . $response . ' status = ' . $status);

        if ($status != 201) {
            $response = json_decode($response, true);
            $message = 'Error capturing order: ' . $status;
            if (isset($response['error_code'])) {
                $message .= '; Code: ' . $response['error_code'];
            }
            if (isset($response['error_messages']) && is_array($response['error_messages'])) {
                foreach ($response['error_messages'] as $value) {
                    $message .= '; ' . $value;
                }
            }
            Mage::throwException($message);
        }
        
        $location = "";
        $capture_id = "";
        if (isset($this->_curlHeaders['Location'])) {
            $location = $this->_curlHeaders['Location'];
            $parts = explode('/', $location);
            $prev_part = "";
            foreach ($parts as $part) {
                if ($prev_part=='captures') {
                    $capture_id = $part;
                    break;
                }
                $prev_part = $part;
            }
        }

        $res = array(
            Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS => $status,
            Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID => $orderId,
            Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_KCO_CAPTURE_ID => $capture_id,
        );
        return $res;
    }

    public function refund($amount, $reservation_no)
    {
        $tmp = explode('/', $reservation_no);
        if (sizeof($tmp)>0) {
            $orderId = $tmp[0];
        } else {
            $orderId = $reservation_no;
        }
        $this->_setGoodsListRefund($amount);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_getUrl() . '/ordermanagement/v1/orders/' . $orderId . '/refunds');
        curl_setopt($ch, CURLOPT_USERPWD, $this->_getTransport()->getConfigData('merchant_id') . ':' . $this->_getTransport()->getConfigData('shared_secret'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'curlHeader'));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->_request));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Mage::helper('klarna')->logDebugInfo('refund rest response = ' . $response . ' status = ' . $status);

        if ($status != 204) {
            $response = json_decode($response, true);
            $message = 'Error refunding order: ' . $status;
            if (isset($response['error_code'])) {
                $message .= '; Code: ' . $response['error_code'];
            }
            if (isset($response['error_messages']) && is_array($response['error_messages'])) {
                foreach ($response['error_messages'] as $value) {
                    $message .= '; ' . $value;
                }
            }
            Mage::throwException($message);
        }
        $res = array(
            Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS => $status,
            Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID => $reservation_no,
        );
        return $res;
    }

    /**
     * Cancel an authorized order. For a cancellation to be successful, there must be no captures on the order.
     * The authorized amount will be released and no further updates to the order will be allowed.
     *
     * @param $orderId
     */
    public function cancel($orderId)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_getUrl() . '/ordermanagement/v1/orders/' . $orderId . '/cancel');
        curl_setopt($ch, CURLOPT_USERPWD, $this->_getTransport()->getConfigData('merchant_id') . ':' . $this->_getTransport()->getConfigData('shared_secret'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'curlHeader'));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Mage::helper('klarna')->logDebugInfo('cancel rest response = ' . $response . ' status = ' . $status);

        if ($status != 204) {
            $response = json_decode($response, true);
            $message = 'Error canceling order: ' . $status;
            if (isset($response['error_code'])) {
                $message .= '; Code: ' . $response['error_code'];
            }
            if (isset($response['error_messages']) && is_array($response['error_messages'])) {
                foreach ($response['error_messages'] as $value) {
                    $message .= '; ' . $value;
                }
            }
            Mage::throwException($message);
        }
        $res = array(
            Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS => $status,
            Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID => $orderId,
        );
        return $res;
    }

    /**
     * Signal that there is no intention to perform further captures.
     *
     * @param $orderId
     */
    public function release($orderId)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_getUrl() . '/ordermanagement/v1/orders/' . $orderId . '/release-remaining-authorization');
        curl_setopt($ch, CURLOPT_USERPWD, $this->_getTransport()->getConfigData('merchant_id') . ':' . $this->_getTransport()->getConfigData('shared_secret'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'curlHeader'));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Mage::helper('klarna')->logDebugInfo('release rest response = ' . $response . ' status = ' . $status);

        if ($status != 204) {
            $response = json_decode($response, true);
            $message = 'Error canceling order: ' . $status;
            if (isset($response['error_code'])) {
                $message .= '; Code: ' . $response['error_code'];
            }
            if (isset($response['error_messages']) && is_array($response['error_messages'])) {
                foreach ($response['error_messages'] as $value) {
                    $message .= '; ' . $value;
                }
            }
            Mage::throwException($message);
        }
        $res = array(
            Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS => $status,
            Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID => $orderId,
        );
        return $res;
    }

    public function setKlarnaOrderSessionCache($value)
    {
        $this->_useKlarnaOrderSessionCache = $value;
    }

    public function initKlarnaOrder($checkoutId = null, $createIfNotExists = false, $updateItems = false)
    {
        if ($checkoutId) {
            Mage::helper('klarna')->logKlarnaApi('initKlarnaOrder rest checkout id: ' . $checkoutId);
            $loadf = true;
            if ($this->_useKlarnaOrderSessionCache) {
                if ($this->_klarnaOrder) {
                    $loadf = false;
                }
            }
            if ($loadf) {
                $this->_fetchOrder($checkoutId);
            }
            $res = $this->_klarnaOrder!=NULL;
            if ($res) {
                if ($this->_getLocationOrderId()) {
                    $this->_setKlarnaCheckoutId($this->_getLocationOrderId());
                }
            }
            Mage::helper('klarna')->logKlarnaApi('initKlarnaOrder rest true');
            return $res;
        }

        if ($klarnaCheckoutId = $this->_getKlarnaCheckoutId()) {
            try {
                Mage::helper('klarna')->logKlarnaApi('initKlarnaOrder rest klarnaCheckoutId id: ' . $klarnaCheckoutId);
                if ($updateItems) {
                    $this->_updateOrder($klarnaCheckoutId);
                } else {
                    $this->_fetchOrder($klarnaCheckoutId);
                }
                $res = $this->_klarnaOrder!=NULL;
                if ($res) {
                    if ($this->_getLocationOrderId()) {
                        $this->_setKlarnaCheckoutId($this->_getLocationOrderId());
                    }
                }
                Mage::helper('klarna')->logKlarnaApi('initKlarnaOrder rest true');
                return $res;
            } catch (Exception $e) {
                // when checkout in Klarna was expired, then exception, so we just ignore and create new
                Mage::helper('klarna')->logKlarnaException($e);
            }
        }

        if ($createIfNotExists) {
            if ($checkoutId = $this->_createOrder()) {
                $this->_fetchOrder($checkoutId);
                $res = $this->_klarnaOrder!=NULL;
                if ($res) {
                    if ($this->_getLocationOrderId()) {
                        $this->_setKlarnaCheckoutId($this->_getLocationOrderId()); // $location
                    }
                }
                return $res;
            }
        }

        return false;
    }

    /*
     * Not happy with this, but I guess we can't solve it in other ways.
     *
     */
    public function getActualKlarnaOrder()
    {
        if ($this->_klarnaOrder) {
            return $this->_klarnaOrder;
        }
        return NULL;
    }
    
    public function getKlarnaCheckoutGui()
    {
        if ($this->_klarnaOrder) {
            return $this->_klarnaOrder->getHtmlSnippet();
        }

        return '';
    }

    public function getKlarnaCheckoutStatus()
    {
        if ($this->_klarnaOrder) {
            return $this->_klarnaOrder->getStatus();
        }

        return '';
    }

    public function loadQuote()
    {
        if ($this->_klarnaOrder) {
            /** @var $quote Mage_Sales_Model_Quote */
            $quote = Mage::getModel('sales/quote')->load($this->_getLocationOrderId(), 'klarna_checkout_id');
            if ($quote->getId()) {
                return $quote;
            }
        }
        return NULL;
    }

    public function initVarienOrder()
    {
        if ($this->_klarnaOrder) {
            $order = $this->_retrieveOrder($this->_klarnaOrder->getOrderId());
            $this->_klarnaOrder->setKlarnaReference($order->getKlarnaReference());
        }
        return $this->_klarnaOrder;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function updateKlarnaOrder($order)
    {
        if ($this->_klarnaOrder) {
            Mage::helper('klarna')->logKlarnaApi('updateKlarnaOrder rest order no: ' . $order->getIncrementId());
            $this->_acknowledgeOrder($this->_klarnaOrder->getOrderId());
            $this->_updateMerchantReferences($this->_klarnaOrder->getOrderId(), $order->getIncrementId());
            Mage::helper('klarna')->logKlarnaApi('updateKlarnaOrder rest success');
            return true;
        }

        return false;
    }

    protected function _setRequestList()
    {
        $default = array(
            "qty" => 0,
            "sku" => "",
            "name" => "",
            "price" => 0,
            "total_amount" => 0,
            "total_tax_amount" => 0,
            "tax" => 0,
            "discount" => 0,
            "quantity_unit" => 'pcs',
        );

        foreach ($this->_getTransport()->getGoodsList() as $array) {
            $values = array_merge($default, array_filter($array));
            $this->_request['order_lines'][] = array(
                'reference' => $values["sku"],
                'type' => 'physical',
                'name' => $values["name"],
                'unit_price' => round($values["price"] * 100),
                'quantity' => round($values["qty"]),
                'total_amount' => round($values["total_amount"] * 100),
                'tax_rate' => round($values["tax"] * 100),
                'total_tax_amount' => round($values["total_tax_amount"] * 100),
                'quantity_unit' => $values["quantity_unit"],
            );
        }
        foreach ($this->_getTransport()->getExtras() as $array) {
            $values = array_merge($default, array_filter($array));
            $this->_request['order_lines'][] = array(
                'reference' => $values["sku"],
                'type' => 'physical',
                'name' => $values["name"],
                'unit_price' => round($values["price"] * 100),
                'quantity' => round($values["qty"]),
                'total_amount' => round($values["total_amount"] * 100),
                'tax_rate' => round($values["tax"] * 100),
                'total_tax_amount' => round($values["total_tax_amount"] * 100),
                'quantity_unit' => $values["quantity_unit"],
            );
        }
    }
    
    /**
     * Set the goods list for Capture
     * Klarna seems to switch the order of the items in capture, so we simply add them backwards.
     *
     * @return void
     */
    public function setGoodsListCapture($amount)
    {
        $this->_request = array(
            'captured_amount' => round($amount * 100),
        );

        $this->_setRequestList();
    }
    
    /**
     * Set the goods list for Refund
     *
     * @return void
     */
    protected function _setGoodsListRefund($amount)
    {
        $this->_request = array(
            'refunded_amount' => round($amount * 100),
        );

        $this->_setRequestList();
    }

    public function setAddresses($billingAddress, $shippingAddress, $data)
    {
    }
}