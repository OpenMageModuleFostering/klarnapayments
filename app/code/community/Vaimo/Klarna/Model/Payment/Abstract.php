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

class Vaimo_Klarna_Model_Payment_Abstract extends Mage_Payment_Model_Method_Abstract
{
    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_canVoid                 = true;
    protected $_canCancel               = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;
    protected $_canSaveCc               = false;
    protected $_canFetchTransactionInfo = true;

    public function getConfigData($field, $storeId = NULL)
    {
        if (!$storeId) $storeId = Mage::app()->getStore()->getId();
        return parent::getConfigData($field, $storeId);
    }

    public function canCapture()
    {
        return true;
    }

    public function canRefund()
    {
        return $this->canCapture();
    }

    public function canCapturePartial()
    {
        return $this->canCapture();
    }

    public function canRefundInvoicePartial()
    {
        return $this->canCapture();
    }

    public function canRefundPartialPerInvoice()
    {
        return $this->canCapture();
    }

    /*
     *
     * This returns blank because Klarna doesn't want the title in text
     * So we use the getMethodLabelAfterHtml function in Form to return
     * the image and title after the image
     * Perhaps there is a better way, but this worked.
     *
     */
    public function getTitle()
    {
        if (Mage::helper('klarna')->showTitleAsTextOnly()) {
            $klarnaAvailable = Mage::getModel('klarna/klarna_available');
            $klarnaAvailable->setQuote($this->getQuote(), $this->_code);
            return $klarnaAvailable->getMethodTitle();
        } else {
            return '';
        }
    }

    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    public function isAvailable( $quote = null )
    {
        $available = parent::isAvailable($quote);
        if(!$available) return false;

        try {
            $active = $this->getConfigData('active');

            if(!$active) return false;
            if(is_null($quote)) return false;

            $grandTotal = $quote->getGrandTotal();
            if(empty ($grandTotal) || $grandTotal <= 0) return false;

            $klarnaAvailable = Mage::getModel('klarna/klarna_available');
            $klarnaAvailable->setQuote($quote, $this->_code);
            if ($klarnaAvailable->isBelowAllowedHardcodedLimit($grandTotal) == false ) {
                return false;
            }

            $address = $quote->getShippingAddress();
            if ($address->getCountry() == null) {
                $address = $this->getDefaultAddress();
                if ($address == null) {
                    return false;
                }
            }
            if ($klarnaAvailable->isCountryAllowed()==false) {
                return false;
            }
        } catch (Mage_Core_Exception $e) {
            if ($klarnaAvailable) $klarnaAvailable->logKlarnaException($e);
            return false;
        }
        return true;
    }


    public function assignData( $data )
    {
        try {
            $klarnaAssign = Mage::getModel('klarna/klarna_assign');
            if (!($data instanceof Varien_Object)) {
                $data = new Varien_Object($data);
            }
            $info = $this->getInfoInstance();
            $quote = $info->getQuote();
            $klarnaAssign->setQuote($quote, $data->getMethod());
            $klarnaAssign->clearInactiveKlarnaMethodsPostvalues($data, $data->getMethod());
            $klarnaAssign->addPostvalues($data->getData(), $data->getMethod());
            $email = $klarnaAssign->getEmailValue(Mage::getSingleton('customer/session')->getCustomer()->getEmail());
            $klarnaAssign->addPostvalues(array('email' => $email)); // will replace email from checkout...
            $klarnaAssign->updateAddress();
            $klarnaAssign->setPaymentPlan();
            $klarnaAssign->setPaymentFee($quote);
            if ($klarnaAssign->getPostValues('consent')===NULL) {
                $klarnaAssign->addPostvalues(array('consent' => 'NO')); // If this is not set in post, set it to NO to mark as no consent was given.
            }
            if ($klarnaAssign->getPostValues('gender')===NULL) {
                $klarnaAssign->addPostvalues(array('gender' => '-1')); // If this is not set in post, set it to -1.
            }

            $klarnaAddr = $klarnaAssign->toKlarna($klarnaAssign->getShippingAddress());

            // These ifs were in a sense copied from old klarna module
            // Don't send in reference for non-company purchase.
            if (!$klarnaAddr->isCompany) {
                if ($klarnaAssign->getPostValues('reference')!==NULL) {
                    $klarnaAssign->unsPostvalue('reference');
                }
            } else {
                // This insane ifcase is for OneStepCheckout
                if ($klarnaAssign->getPostValues('reference')===NULL) {
                    $reference = $klarnaAddr->getFirstName() . " " . $klarnaAddr->getLastName();
                    $klarnaAssign->addPostvalues(array('reference' => $reference));
                }
            }

            $klarnaAssign->updateAdditionalInformation( $info );

        } catch (Mage_Core_Exception $e) {
            Mage::throwException($e->getMessage());
        }
        return $this;
    }

    public function validate()
    {
        parent::validate();
        try {
            $klarnaValidate = Mage::getModel('klarna/klarna_validate');
            $info = $this->getInfoInstance();
            if ($info->getQuote()) {
                // Validate is called while in checkout and immediately after place order is pushed
                $quote = $info->getQuote();
                $klarnaValidate->setInfoInstance($this->getInfoInstance());
                $klarnaValidate->setQuote($quote, $info->getMethod());
            } else {
                // Magento also calls validate when the quote has been changed into an order, then the quote doesn't exist and we do our tests against the order
                $order = $info->getOrder();
                $klarnaValidate->setInfoInstance($this->getInfoInstance());
                $klarnaValidate->setOrder($order);
            }

            // We cannot perform basic tests with OneStepCheckout because they try
            // to save the payment method as soon as the customer views the checkout
            if (Mage::helper('klarna')->isOneStepCheckout()) {
                return $this;
            }

            $klarnaValidate->doBasicTests();

        } catch (Mage_Core_Exception $e) {
            Mage::throwException($e->getMessage());
        }
        return $this;
    }

    /**
     * Authorize the purchase
     *
     * @param Varien_Object $payment Magento payment model
     * @param double $amount  The amount to authorize with
     *
     * @return Klarna_KlarnaPaymentModule_Model_Klarna_Shared
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        try {
            /*
             * Since we could not perform basic tests with OneStepCheckout at validate and assign functions
             * we do them here instead
             */
            if (Mage::helper('klarna')->isOneStepCheckout()) {
                $klarnaValidate = Mage::getModel('klarna/klarna_validate');
                $klarnaValidate->setInfoInstance($this->getInfoInstance());
                $klarnaValidate->setPayment($payment);
                $klarnaValidate->doBasicTests();
            }

            $klarnaAuthorize = Mage::getModel('klarna/klarna_authorize');
            $klarnaAuthorize->setPayment($payment);
            $klarnaAuthorize->updateAddress();

            if (Mage::helper('klarna')->isOneStepCheckout() && $klarnaAuthorize->shippingSameAsBilling()) {
                $klarnaAuthorize->updateBillingAddress();
            }
        
            $itemList = $klarnaAuthorize->createItemList();
            $payment->setKlarnaItemList($itemList);

            $result = $klarnaAuthorize->reserve($amount);
            $transactionStatus = $result[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS];
            $transactionId = $result[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID];
        } catch (Mage_Core_Exception $e) {
            Mage::throwException($e->getMessage());
        }

        $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_RESERVATION_ID, $transactionId );
        $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_RESERVATION_STATUS, $transactionStatus );
        $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_HOST, $this->getConfigData("host") );
        $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_MERCHANT_ID, $this->getConfigData("merchant_id") );

        if ($transactionStatus==Vaimo_Klarna_Helper_Data::KLARNA_STATUS_PENDING) {
            $payment->setIsTransactionPending(true);
        }

        $payment->setTransactionId($transactionId)
                ->setIsTransactionClosed(0);
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        try {
            $klarnaCapture = Mage::getModel('klarna/klarna_capture');
            $klarnaCapture->setPayment($payment);
            $result = $klarnaCapture->capture($amount);
            $transactionStatus = $result[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS];
            $transactionId = $result[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID];
            $feeAmountCaptured = $result[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_FEE_CAPTURED];
        } catch (Mage_Core_Exception $e) {
            Mage::throwException($e->getMessage());
        }
        $invoices = $payment->getAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_INVOICE_LIST);
        if (!is_array($invoices)) {
            $invoices = array();
        }
        $invoices[] = array(
                           Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_INVOICE_LIST_STATUS => $transactionStatus,
                           Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_INVOICE_LIST_ID => $transactionId
                           );
        $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_INVOICE_LIST, $invoices);

        if ($feeAmountCaptured) {
            $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_FEE_CAPTURED_TRANSACTION_ID, $transactionId);
            $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_FEE_REFUNDED, 0);
        }

        $payment->setTransactionId($transactionId)
                ->setIsTransactionClosed(0);
        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        try {
            $klarnaRefund = Mage::getModel('klarna/klarna_refund');
            $klarnaRefund->setPayment($payment);

            $itemList = $klarnaRefund->createItemList();
            $payment->setKlarnaItemList($itemList);

            $klarnaRefund->setInfoInstance($this->getInfoInstance());
            $result = $klarnaRefund->refund($amount);
            $transactionStatus = $result[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS]; // Always OK...
            $transactionId = $result[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_TRANSACTION_ID];
            $klarnaFeeRefunded = $result[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_FEE_REFUNDED];
            if ($klarnaFeeRefunded) {
                if ($payment->getAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_FEE_REFUNDED)) {
                    $klarnaFeeRefunded = $klarnaFeeRefunded + $payment->getAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_FEE_REFUNDED);
                }
                $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_FEE_REFUNDED, $klarnaFeeRefunded);
            }
            $id = date('His');
            if (!$id) $id = 1;
        } catch (Mage_Core_Exception $e) {
            Mage::throwException($e->getMessage());
        }

        $payment->setTransactionId($transactionId . '-' . $id . '-refund')
                ->setIsTransactionClosed(1);
        return $this;
    }
    
    public function cancel(Varien_Object $payment)
    {
        try {
            $klarnaCancel = Mage::getModel('klarna/klarna_cancel');
            $klarnaCancel->setPayment($payment);
            $result = $klarnaCancel->cancel();
            $transactionStatus = $result[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS];
        } catch (Mage_Core_Exception $e) {
            Mage::throwException($e->getMessage());
        }
        if (!$transactionStatus) {
            Mage::throwException(Mage::helper('klarna')->__('Klarna was not able to cancel the reservation'));
        }
        $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_CANCELED_DATE, date("Y-m-d") );

        $payment->setIsTransactionClosed(1);
        return $this;
    }

    public function void(Varien_Object $payment)
    {
        return $this->cancel($payment);
    }

    /**
     * Fetch transaction details info
     *
     * To ask for update on a pending order, see if we get denied or accepted
     *
     * @param Mage_Payment_Model_Info $payment
     * @param string $transactionId
     * @return array
     */
    public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId)
    {
        $klarnaAuthorize = Mage::getModel('klarna/klarna_authorize');
        $klarnaAuthorize->setPayment($payment);
        $status = $payment->getAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_RESERVATION_STATUS);
        $result = $klarnaAuthorize->checkStatus();
        $transactionStatus = $result[Vaimo_Klarna_Helper_Data::KLARNA_API_RESPONSE_STATUS];
        if ($transactionStatus!=$status) {
            $payment->setAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_RESERVATION_STATUS, $transactionStatus );
        }
        if ($transactionStatus==Vaimo_Klarna_Helper_Data::KLARNA_STATUS_ACCEPTED) {
            $payment->setIsTransactionApproved(true);
        } elseif ($transactionStatus==Vaimo_Klarna_Helper_Data::KLARNA_STATUS_DENIED) {
            $payment->setIsTransactionDenied(true);
        }
        return parent::fetchTransactionInfo($payment, $transactionId);
    }
}
