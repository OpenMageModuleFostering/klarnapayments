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

class Vaimo_Klarna_Block_Klarnacheckout_Klarnacheckout extends Mage_Core_Block_Template
{
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    protected function _getKlarnaOrderHtml()
    {
        try {
            $klarna = Mage::getModel('klarna/klarnacheckout');
            $klarna->setQuote($this->getQuote(), Vaimo_Klarna_Helper_Data::KLARNA_METHOD_CHECKOUT);
            $html = $klarna->getKlarnaOrderHtml($klarna->getQuote()->getKlarnaCheckoutId(), true, true);
        } catch (Exception $e) {
            $quote = Mage::helper('klarna')->findQuote($klarna->getQuote()->getKlarnaCheckoutId());
            $orderCreated = false;
            if ($quote && $quote->getId()) {
                $order = Mage::getModel('sales/order')->load($quote->getId(), 'quote_id');
                if ($order->getId()) {
                    $orderCreated = true;
                }
            }
            if ($quote && ($quote->getId()!=$klarna->getQuote()->getId() || !$quote->getIsActive() || $orderCreated)) {
                if (!$quote->getIsActive()) {
                    Mage::helper('klarna')->logKlarnaApi('getKlarnaOrderHtml failed with checkout id: ' . $klarna->getQuote()->getKlarnaCheckoutId() . '. ' . $e->getMessage() . '. ' . 'Exiting since quote is inactive ' . $quote->getId());
                } elseif ($order->getId()) {
                    Mage::helper('klarna')->logKlarnaApi('getKlarnaOrderHtml failed with checkout id: ' . $klarna->getQuote()->getKlarnaCheckoutId() . '. ' . $e->getMessage() . '. ' . 'Exiting since quote has already created an order ' . $order->getIncrementId());
                } else {
                    Mage::helper('klarna')->logKlarnaApi('getKlarnaOrderHtml failed with checkout id: ' . $klarna->getQuote()->getKlarnaCheckoutId() . '. ' . $e->getMessage() . '. ' . 'Exiting since quote is wrong' . $quote->getId());
                }
                if ($quote->getIsActive()) {
                    $quote->setIsActive(false);
                    $quote->save();
                }
                Mage::helper('klarna')->logKlarnaException($e);
                Mage::throwException(Mage::helper('klarna')->__('Current cart is not active. Please try again'));
            } else {
                Mage::helper('klarna')->logKlarnaApi('getKlarnaOrderHtml failed with checkout id: ' . $klarna->getQuote()->getKlarnaCheckoutId() . '. Trying with null.');
                $klarna->getQuote()->setKlarnaCheckoutId(null);
                $html = $klarna->getKlarnaOrderHtml(null, true, true);
                Mage::helper('klarna')->logKlarnaApi('getKlarnaOrderHtml succeeded with null');
            }
        }
        return $html;
    }

    public function getKlarnaHtml()
    {
        try {
            $html = $this->_getKlarnaOrderHtml();
        } catch (Exception $e) {
            Mage::helper('klarna')->logKlarnaException($e);
            $html = $e->getMessage();
            if (!$html) {
                $extraInfo = '';
                if ($e->getInternalMessage()) {
                    if (stristr($e->getInternalMessage(), 'Bad format')) {
                        $extraInfo = ' (' . $e->getInternalMessage() . ')';
                    }
                }
                $html = Mage::helper('klarna')->__(
                    'Klarna Checkout is not responding properly. Please try again in a while or choose another payment method.' . 
                    $extraInfo
                );
            }
            $this->getCheckout()->addError($html);
            Mage::app()->getResponse()
                ->setRedirect(Mage::getBaseUrl() . Mage::helper('klarna')->getKCORedirectToCartUrl($this->getQuote()->getStoreId()))
                ->sendResponse();
            exit(0);
        }

        return $html;
    }
}