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

class Vaimo_Klarna_Model_Quote_Total extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    public function __construct()
    {
        $this->setCode('vaimo_klarna_fee');
    }

    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $currentVersion = Mage::getVersion();
        if (version_compare($currentVersion, '1.13.0')>0) {
            // This line is for some wierd reason required when running in EE 1.13... It shouldn't be... or?
            parent::collect($address);
        }

        if ($address->getAddressType() != "shipping") {
          return $this;
        }

        $address->setVaimoKlarnaFee(0);
        $address->setVaimoKlarnaFeeTax(0);
        $address->setVaimoKlarnaBaseFee(0);
        $address->setVaimoKlarnaBaseFeeTax(0);

        if ($address->getQuote()->getId() == NULL) {
          return $this;
        }

        $collection = $address->getQuote()->getPaymentsCollection();
        if ($collection->count() <= 0 || $address->getQuote()->getPayment()->getMethod() == null) {
            return $this;
        }

        if (!Mage::helper('klarna')->isMethodKlarna($address->getQuote()->getPayment()->getMethod())) {
            return $this;
        }

        $items = $address->getAllItems();
        if (!count($items)) {
            return $this;
        }

        $klarnaFee = Mage::helper('klarna')->getVaimoKlarnaFeeExclVat($address);

        if (!$klarnaFee > 0 ) {
            return $this;
        }

        $quote = $address->getQuote();
        $store = $quote->getStore();
        
        $baseKlarnaFee = $store->convertPrice($klarnaFee, false);

        $address->setVaimoKlarnaBaseFee($baseKlarnaFee);
        $address->setVaimoKlarnaFee($klarnaFee);

        $address->setBaseGrandTotal($address->getBaseGrandTotal() + $baseKlarnaFee);
        $address->setGrandTotal($address->getGrandTotal() + $klarnaFee);

        $quote->setVaimoKlarnaFee($klarnaFee);
        $quote->setVaimoKlarnaBaseFee($baseKlarnaFee);

        return $this;
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $amount = $address->getVaimoKlarnaFee();

        if ($amount!=0) {
            $quote = $address->getQuote();
            $address->addTotal(array(
                'code' => $this->getCode(),
                'title' => Mage::helper('klarna')->getKlarnaFeeLabel($quote->getStore()),
                'value' => $amount,
            ));
        }
        return $this;
    }

}
