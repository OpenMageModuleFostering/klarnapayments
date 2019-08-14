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

class Vaimo_Klarna_Helper_Data extends Mage_Core_Helper_Abstract
{
    const KLARNA_METHOD_INVOICE = 'vaimo_klarna_invoice';
    const KLARNA_METHOD_ACCOUNT = 'vaimo_klarna_account';
    const KLARNA_METHOD_SPECIAL = 'vaimo_klarna_special';
    
    const KLARNA_STATUS_ACCEPTED = 'accepted';
    const KLARNA_STATUS_PENDING  = 'pending';
    const KLARNA_STATUS_DENIED   = 'denied';
    
    const KLARNA_INFO_FIELD_FEE                         = 'vaimo_klarna_fee';
    const KLARNA_INFO_FIELD_FEE_TAX                     = 'vaimo_klarna_fee_tax';
    const KLARNA_INFO_FIELD_BASE_FEE                    = 'vaimo_klarna_base_fee';
    const KLARNA_INFO_FIELD_BASE_FEE_TAX                = 'vaimo_klarna_base_fee_tax';
    const KLARNA_INFO_FIELD_FEE_CAPTURED_TRANSACTION_ID = 'klarna_fee_captured_transaction_id';
    const KLARNA_INFO_FIELD_FEE_REFUNDED                = 'klarna_fee_refunded';

    const KLARNA_INFO_FIELD_RESERVATION_STATUS  = 'klarna_reservation_status';
    const KLARNA_INFO_FIELD_RESERVATION_ID      = 'klarna_reservation_id';
    const KLARNA_INFO_FIELD_CANCELED_DATE       = 'klarna_reservation_canceled_date';
    const KLARNA_INFO_FIELD_INVOICE_LIST        = 'klarna_invoice_list';
    const KLARNA_INFO_FIELD_INVOICE_LIST_STATUS = 'invoice_status';
    const KLARNA_INFO_FIELD_INVOICE_LIST_ID     = 'invoice_id';
    const KLARNA_INFO_FIELD_HOST                = 'klarna_reservation_host';
    const KLARNA_INFO_FIELD_MERCHANT_ID         = 'merchant_id';

    const KLARNA_INFO_FIELD_PAYMENT_PLAN              = 'payment_plan';
    const KLARNA_INFO_FIELD_PAYMENT_PLAN_TYPE         = 'payment_plan_type';
    const KLARNA_INFO_FIELD_PAYMENT_PLAN_MONTHS       = 'payment_plan_months';
    const KLARNA_INFO_FIELD_PAYMENT_PLAN_START_FEE    = 'payment_plan_start_fee';
    const KLARNA_INFO_FIELD_PAYMENT_PLAN_INVOICE_FEE  = 'payment_plan_invoice_fee';
    const KLARNA_INFO_FIELD_PAYMENT_PLAN_TOTAL_COST   = 'payment_plan_total_cost';
    const KLARNA_INFO_FIELD_PAYMENT_PLAN_MONTHLY_COST = 'payment_plan_monthly_cost';
    const KLARNA_INFO_FIELD_PAYMENT_PLAN_DESCRIPTION  = 'payment_plan_description';

    const KLARNA_FORM_FIELD_PHONENUMBER = 'phonenumber';
    const KLARNA_FORM_FIELD_PNO         = 'pno';
    const KLARNA_FORM_FIELD_ADDRESS_ID  = 'address_id';
    const KLARNA_FORM_FIELD_DOB_YEAR    = 'dob_year';
    const KLARNA_FORM_FIELD_DOB_MONTH   = 'dob_month';
    const KLARNA_FORM_FIELD_DOB_DAY     = 'dob_day';
    const KLARNA_FORM_FIELD_CONSENT     = 'consent';
    const KLARNA_FORM_FIELD_GENDER      = 'gender';
    const KLARNA_FORM_FIELD_EMAIL       = 'email';
    
    const KLARNA_API_RESPONSE_STATUS         = 'response_status';
    const KLARNA_API_RESPONSE_TRANSACTION_ID = 'response_transaction_id';
    const KLARNA_API_RESPONSE_FEE_REFUNDED   = 'response_fee_refunded';
    const KLARNA_API_RESPONSE_FEE_CAPTURED   = 'response_fee_captured';

    const KLARNA_LOGOTYPE_TYPE_INVOICE = 'invoice';
    const KLARNA_LOGOTYPE_TYPE_ACCOUNT = 'account';
    const KLARNA_LOGOTYPE_TYPE_BOTH    = 'unified';
    const KLARNA_LOGOTYPE_TYPE_BASIC   = 'basic';

    const KLARNA_LOGOTYPE_POSITION_FRONTEND = 'frontend';
    const KLARNA_LOGOTYPE_POSITION_PRODUCT  = 'product';
    const KLARNA_LOGOTYPE_POSITION_CHECKOUT = 'checkout';

    protected $_supportedMethods = array(
                                    Vaimo_Klarna_Helper_Data::KLARNA_METHOD_INVOICE,
                                    Vaimo_Klarna_Helper_Data::KLARNA_METHOD_ACCOUNT,
                                    Vaimo_Klarna_Helper_Data::KLARNA_METHOD_SPECIAL
                                    );

    protected $_klarnaFields = array(
        self::KLARNA_INFO_FIELD_FEE,
        self::KLARNA_INFO_FIELD_FEE_TAX,
        self::KLARNA_INFO_FIELD_BASE_FEE,
        self::KLARNA_INFO_FIELD_BASE_FEE_TAX,
        self::KLARNA_INFO_FIELD_FEE_CAPTURED_TRANSACTION_ID,
        self::KLARNA_INFO_FIELD_FEE_REFUNDED,

        self::KLARNA_INFO_FIELD_RESERVATION_STATUS,
        self::KLARNA_INFO_FIELD_RESERVATION_ID,
        self::KLARNA_INFO_FIELD_CANCELED_DATE,
        self::KLARNA_INFO_FIELD_INVOICE_LIST,
        self::KLARNA_INFO_FIELD_INVOICE_LIST_STATUS,
        self::KLARNA_INFO_FIELD_INVOICE_LIST_ID,
        self::KLARNA_INFO_FIELD_HOST,
        self::KLARNA_INFO_FIELD_MERCHANT_ID,

        self::KLARNA_INFO_FIELD_PAYMENT_PLAN,
        self::KLARNA_INFO_FIELD_PAYMENT_PLAN_TYPE,
        self::KLARNA_INFO_FIELD_PAYMENT_PLAN_MONTHS,
        self::KLARNA_INFO_FIELD_PAYMENT_PLAN_START_FEE,
        self::KLARNA_INFO_FIELD_PAYMENT_PLAN_INVOICE_FEE,
        self::KLARNA_INFO_FIELD_PAYMENT_PLAN_TOTAL_COST,
        self::KLARNA_INFO_FIELD_PAYMENT_PLAN_MONTHLY_COST,
        self::KLARNA_INFO_FIELD_PAYMENT_PLAN_DESCRIPTION,

        self::KLARNA_FORM_FIELD_PHONENUMBER,
        self::KLARNA_FORM_FIELD_PNO,
        self::KLARNA_FORM_FIELD_ADDRESS_ID,
        self::KLARNA_FORM_FIELD_DOB_YEAR,
        self::KLARNA_FORM_FIELD_DOB_MONTH,
        self::KLARNA_FORM_FIELD_DOB_DAY,
        self::KLARNA_FORM_FIELD_CONSENT,
        self::KLARNA_FORM_FIELD_GENDER,
        self::KLARNA_FORM_FIELD_EMAIL,
    
    );

    public function getSupportedMethods()
    {
        return $this->_supportedMethods;
    }

    public function getKlarnaFields()
    {
        return $this->_klarnaFields;
    }

    public function isMethodKlarna($method)
    {
        if (in_array($method, $this->getSupportedMethods())) {
            return true;
        }
        return false;
    }
    
    public function getInvoiceLink($order, $transactionId)
    {
        $link = "";
        if ($order) {
            $payment = $order->getPayment();
            if ($payment) {
                $host = $payment->getAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_HOST);
                $domain = ($host === 'LIVE') ? 'online': 'testdrive';
                $link = "https://{$domain}.klarna.com/invoices/" . $transactionId . ".pdf";
            }
        }
        return $link;
    }

    public function shouldItemBeIncluded($item)
    {
        if ($item->getParentItemId()>0 && $item->getPriceInclTax()==0) return false;
        return true;
    }

    /**
     * Check if OneStepCheckout is activated or not
     * It also checks if OneStepCheckout is activated, but it's currently using
     * standard checkout
     *
     * @return bool
     */
    public function isOneStepCheckout($store = null)
    {
        $res = false;
        if (Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links', $store)) {
            $res = true;
            if (isset($_SERVER['REQUEST_URI'])) {
                if (stristr($_SERVER['REQUEST_URI'],'checkout/onepage')) {
                  $res = false;
                }
            }
        }
        return $res;
    }

    /*
     * Last minute change. We were showing logotype instead of title, but the implementation was not
     * as good as we wanted, so we reverted it and will make it a setting. This function will be the
     * base of that setting. If it returns false, we should show the logotype together with the title
     * otherwise just show the title.
     */
    public function showTitleAsTextOnly()
    {
        return true;
    }

    /**
     * Check if OneStepCheckout displays their prises with the tax included
     *
     * @return bool
     */
    public function isOneStepCheckoutTaxIncluded()
    {
        return (bool) Mage::getStoreConfig( 'onestepcheckout/general/display_tax_included' );
    }

    protected function _feePriceIncludesTax($store = null)
    {
        $config = Mage::getSingleton('klarna/tax_config');
        return $config->klarnaFeePriceIncludesTax($store);
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param null $store
     * @return mixed
     */
    protected function _getVaimoKlarnaFeeForMethod($quote, $store, $force = false)
    {
        /** @var Mage_Sales_Model_Quote_Payment $payment */
        $payment = $quote->getPayment();
        $method = $payment->getMethod();
        if (!$method && !$force) {
            return 0;
        }

        $fee = 0;
        if ($force || $method==Vaimo_Klarna_Helper_Data::KLARNA_METHOD_INVOICE) {
            $fee = Mage::getStoreConfig('payment/vaimo_klarna_invoice/invoice_fee', $store);
        }
        return $fee;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param $store
     * @return int
     */
    protected function _getVaimoKlarnaFee($quote, $store, $force = false, $inBaseCurrency = true)
    {
        $localFee = 0;
        $fee = $this->_getVaimoKlarnaFeeForMethod($quote, $store, $force);
        if ($fee) {
            if (!$inBaseCurrency && $store->getCurrentCurrency() != $store->getBaseCurrency()) {
                $rate = $store->getBaseCurrency()->getRate($store->getCurrentCurrency());
                $curKlarnaFee = $fee * $rate;
            } else {
                $curKlarnaFee = $fee;
            }
            $localFee = $store->roundPrice($curKlarnaFee);
        }
        return $localFee;
    }

    /**
     * Returns the label set for fee
     *
     * @param $store
     * @return string
     */
    public function getKlarnaFeeLabel($store = NULL)
    {
        return $this->__(Mage::getStoreConfig('payment/vaimo_klarna_invoice/invoice_fee_label', $store));
    }

    /**
     * Returns the tax class for invoice fee
     *
     * @param $store
     * @return string
     */
    public function getTaxClass($store)
    {
        $config = Mage::getSingleton('klarna/tax_config');
        return $config->getKlarnaFeeTaxClass($store);
    }

    /**
     * Returns the payment fee excluding VAT
     *
     * @param Mage_Sales_Model_Quote_Address $shippingAddress
     * @return float
     */
    public function getVaimoKlarnaFeeExclVat($shippingAddress)
    {
        $quote = $shippingAddress->getQuote();
        $store = $quote->getStore();
        $fee = $this->_getVaimoKlarnaFee($quote, $store);
        if ($fee && $this->_feePriceIncludesTax($store)) {
            $fee -= $this->getVaimoKlarnaFeeVat($shippingAddress);
        }
        return $fee;
    }

    /**
     * Returns the payment fee tax for the payment fee
     *
     * @param Mage_Sales_Model_Quote_Address $shippingAddress
     * @return float
     */
    public function getVaimoKlarnaFeeVat($shippingAddress)
    {
        $paymentTax = 0;
        $quote = $shippingAddress->getQuote();
        $store = $quote->getStore();
        $fee = $this->_getVaimoKlarnaFee($quote, $store);
        if ($fee) {
            $custTaxClassId = $quote->getCustomerTaxClassId();
            $taxCalculationModel = Mage::getSingleton('tax/calculation');
            $request = $taxCalculationModel->getRateRequest($shippingAddress, $quote->getBillingAddress(), $custTaxClassId, $store);
            $paymentTaxClass = $this->getTaxClass($store);
            $rate = $taxCalculationModel->getRate($request->setProductClassId($paymentTaxClass));
            if ($rate) {
                $paymentTax = $taxCalculationModel->calcTaxAmount($fee, $rate, $this->_feePriceIncludesTax($store), true);
            }
        }
        return $paymentTax;
    }

    /**
     * Returns the payment fee tax rate
     *
     * @param Mage_Sales_Model_Order $order
     * @return float
     */
    public function getVaimoKlarnaFeeVatRate($order)
    {
        $shippingAddress = $order->getShippingAddress();
        $store = $order->getStore();
        $custTaxClassId = $order->getCustomerTaxClassId();

        $taxCalculationModel = Mage::getSingleton('tax/calculation');
        $request = $taxCalculationModel->getRateRequest($shippingAddress, $order->getBillingAddress(), $custTaxClassId, $store);
        $paymentTaxClass = $this->getTaxClass($store);
        $rate = $taxCalculationModel->getRate($request->setProductClassId($paymentTaxClass));

        return $rate;
    }

    /**
     * Returns the payment fee including VAT, this function doesn't care about method or shipping address country
     * It's striclty for informational purpouses
     *
     * @return float
     */
    public function getVaimoKlarnaFeeInclVat($quote, $inBaseCurrency = true)
    {
        $shippingAddress = $quote->getShippingAddress();
        $store = $quote->getStore();
        $fee = $this->_getVaimoKlarnaFee($quote, $store, true, $inBaseCurrency);
        if ($fee && !$this->_feePriceIncludesTax($store)) {
            $custTaxClassId = $quote->getCustomerTaxClassId();
            $taxCalculationModel = Mage::getSingleton('tax/calculation');
            $request = $taxCalculationModel->getRateRequest($shippingAddress, $quote->getBillingAddress(), $custTaxClassId, $store);
            $paymentTaxClass = $this->getTaxClass($store);
            $rate = $taxCalculationModel->getRate($request->setProductClassId($paymentTaxClass));
            if ($rate) {
                $tax = $taxCalculationModel->calcTaxAmount($fee, $rate, $this->_feePriceIncludesTax($store), true);
                $fee += $tax;
            }

        }
        return $fee;
    }

    /*
     * The following functions shouldn't really need to exist...
     * Either I have done something wrong or the versions have changed how they work...
     *
     */
     
    /*
     * Add tax to grand total on invoice collect or not
     */
    public function collectInvoiceAddTaxToInvoice()
    {
        $currentVersion = Mage::getVersion();
        if ((version_compare($currentVersion, '1.10.0')>=0) && (version_compare($currentVersion, '1.12.0')<0)) {
            return false;
        } else {
            return true;
        }
    }
    
    /*
     * Call parent of quote collect or not
     */
    public function collectQuoteRunParentFunction()
    {
        return false; // Seems the code was wrong, this function is no longer required
        $currentVersion = Mage::getVersion();
        if (version_compare($currentVersion, '1.11.0')>=0) {
            return true;
        } else {
            return false;
        }
    }
    
    /*
     * Use extra tax in quote instead of adding to Tax, I don't know why this has to be
     * different in EE, but it clearly seems to be...
     */
    public function collectQuoteUseExtraTaxInCheckout()
    {
        return false; // Seems the code was wrong, this function is no longer required
        $currentVersion = Mage::getVersion();
        if (version_compare($currentVersion, '1.11.0')>=0) {
            return true;
        } else {
            return false;
        }
    }
}