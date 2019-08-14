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
        $klarnaCapture = Mage::getModel('klarna/klarna_capture');
        $payment = $observer->getEvent()->getPayment();
        $klarnaCapture->setPayment($payment);

        if (!$klarnaCapture->supportedMethod()) {
            return $this;
        }

        $invoice = $observer->getEvent()->getInvoice();
        $klarnaCapture->setInvoice($invoice);
        $itemList = $klarnaCapture->createItemList();
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
            $klarnaAssign = Mage::getModel('klarna/klarna_assign');
            $klarnaAssign->setQuote($payment->getQuote());
            $klarnaAssign->clearInactiveKlarnaMethodsPostvalues($data,$data->getMethod());
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

}
