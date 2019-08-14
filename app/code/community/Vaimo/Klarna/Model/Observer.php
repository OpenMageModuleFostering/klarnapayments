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


class Vaimo_Klarna_Model_Observer extends Mage_Core_Model_Abstract
{
    /*
     * Klarna requires the invoice details, lines etc, to perform capture
     * It's not known in the capture event, so I pick it up here
     * The item list is put onto the payment object, not stored, but it
     * will be available in the capture method
     *
     * @param Varien_Event_Observer $observer
     */
    public function prePaymentCapture($observer)
    {
        $payment = $observer->getEvent()->getPayment();
        $klarna = Mage::getModel('klarna/klarna');
        $klarna->setPayment($payment);

        if (!Mage::helper('klarna')->isMethodKlarna($payment->getMethod())) {
            return $this;
        }

        $invoice = $observer->getEvent()->getInvoice();
        $klarna->setInvoice($invoice);
        $itemList = $klarna->createItemListCapture();
        $payment->setKlarnaItemList($itemList);
    }

    /*
     * A cleanup function, if Klarna is selected, payment is saved and then
     * another method is selected and payment saved, then we cleanup the
     * additional information fields we set
     *
     * @param Varien_Event_Observer $observer
     */
    public function cleanAdditionalInformation($observer)
    {
        $payment = $observer->getEvent()->getPayment();
        if ($payment) {
            $data = $observer->getEvent()->getInput();
            $klarna = Mage::getModel('klarna/klarna');
            $klarna->setQuote($payment->getQuote());
            $klarna->clearInactiveKlarnaMethodsPostvalues($data,$data->getMethod());
        }
    }

    /*
     * We remove pno from quote, after the sales order was successfully placed
     *
     * @param Varien_Event_Observer $observer
     */
    public function cleanPnoFromQuote($observer)
    {
        $quote = $observer->getEvent()->getQuote();
        if ($quote) {
            if (!$quote->getIsActive()) {
                $payment = $quote->getPayment();
                if ($payment) {
                    if (Mage::helper('klarna')->isMethodKlarna($payment->getMethod())) {
                        if ($payment->getAdditionalInformation('pno')) {
                            $payment->unsAdditionalInformation('pno');
                        }
                    }
                }
            }
        }
    }


// KLARNA CHECKOUT FROM HERE

    /**
     * @return Mage_Checkout_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function customerAddressFormat(Varien_Event_Observer $observer)
    {
        $type = $observer->getEvent()->getType();

        if (strpos($type->getDefaultFormat(), 'care_of') === false) {
            $defaultFormat = explode("\n", $type->getDefaultFormat());

            if (is_array($defaultFormat)) {
                $result = array();

                foreach ($defaultFormat as $key => $value) {
                    $result[] = $value;
                    if ($key == 0) {
                        $result[] = '{{depend care_of}}c/o {{var care_of}}<br />{{/depend}}';
                    }
                }

                $type->setDefaultFormat(implode("\n", $result));
            }
        }
    }

    public function checkLaunchKlarnaCheckout($observer)
    {
        if (!$this->_getSession()->getUseOtherMethods()) {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $klarna = Mage::getModel('klarna/klarnacheckout');
            $klarna->setQuote($quote, Vaimo_Klarna_Helper_Data::KLARNA_METHOD_CHECKOUT);
            if ($klarna->getKlarnaCheckoutEnabled()) {
                $controllerAction = $observer->getControllerAction();
                $controllerAction->getResponse()
                    ->setRedirect(Mage::getUrl('checkout/klarna'))
                    ->sendResponse();
                exit;
            }
        }
    }
}
