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

class Vaimo_Klarna_Model_Quote_Tax extends Mage_Sales_Model_Quote_Address_Total_Tax
{
    public function __construct()
    {
        $this->setCode('vaimo_klarna_fee_tax');
    }

    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        if (Mage::helper('klarna')->collectQuoteRunParentFunction()) {
//            parent::collect($address);
        }

        if ($address->getQuote()->getId() == NULL) {
          return $this;
        }
        
        if ($address->getAddressType() != "shipping") {
          return $this;
        }

        if (!$address->getVaimoKlarnaFee()) {
          return $this;
        }

        if (!Mage::helper('klarna')->isMethodKlarna($address->getQuote()->getPayment()->getMethod())) {
            return $this;
        }

        $items = $address->getAllItems();
        if (!count($items)) {
            return $this;
        }

        $quote = $address->getQuote();
        $custTaxClassId = $quote->getCustomerTaxClassId();
        $store = $quote->getStore();
        $taxCalculationModel = Mage::getSingleton('tax/calculation');
        $request = $taxCalculationModel->getRateRequest($address, $quote->getBillingAddress(), $custTaxClassId, $store);
        $klarnaFeeTaxClass = Mage::helper('klarna')->getTaxClass($store);

        $klarnaFeeTax      = 0;
        $klarnaFeeBaseTax  = 0;

        if ($klarnaFeeTaxClass) {
            if ($rate = $taxCalculationModel->getRate($request->setProductClassId($klarnaFeeTaxClass))) {

                $klarnaFeeTax = $taxCalculationModel->calcTaxAmount($address->getVaimoKlarnaFee(), $rate, false, true);
                $klarnaFeeBaseTax = $taxCalculationModel->calcTaxAmount($address->getVaimoKlarnaBaseFee(), $rate, false, true);
                
                if (Mage::helper('klarna')->collectQuoteUseExtraTaxInCheckout()) {
                    $address->setExtraTaxAmount($address->getExtraTaxAmount() + $klarnaFeeTax);
                    $address->setBaseExtraTaxAmount($address->getBaseExtraTaxAmount() + $klarnaFeeBaseTax);
                } else {
                    $address->setTaxAmount($address->getTaxAmount() + $klarnaFeeTax);
                    $address->setBaseTaxAmount($address->getBaseTaxAmount() + $klarnaFeeBaseTax);

                    $address->setGrandTotal($address->getGrandTotal() + $klarnaFeeTax);
                    $address->setBaseGrandTotal($address->getBaseGrandTotal() + $klarnaFeeBaseTax);
                }
            }
        }
        
        $address->setVaimoKlarnaFeeTax($klarnaFeeTax);
        $address->setVaimoKlarnaBaseFeeTax($klarnaFeeBaseTax);

        $quote->setVaimoKlarnaFeeTax($klarnaFeeTax);
        $quote->setVaimoKlarnaBaseFeeTax($klarnaFeeBaseTax);

        return $this;
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $store = $address->getQuote()->getStore();

        if (Mage::getSingleton('tax/config')->displayCartSubtotalBoth($store) || Mage::getSingleton('tax/config')->displayCartSubtotalInclTax($store)) {
            if ($address->getSubtotalInclTax() > 0) {
                $subtotalInclTax = $address->getSubtotalInclTax();
            } else {
                $subtotalInclTax = $address->getSubtotal()+$address->getTaxAmount()-$address->getShippingTaxAmount()-$address->getVaimoKlarnaFeeTax();
            }

            $address->addTotal(array(
                'code'      => 'subtotal',
                'title'     => Mage::helper('sales')->__('Subtotal'),
                'value'     => $subtotalInclTax,
                'value_incl_tax' => $subtotalInclTax,
                'value_excl_tax' => $address->getSubtotal(),
            ));
        }
        return $this;
    }

}
