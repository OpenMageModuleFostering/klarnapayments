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

class Vaimo_Klarna_Model_Klarna_Tools_Api extends Vaimo_Klarna_Model_Klarna_Tools_Address
{
    protected $_additionalInfo = NULL;
    protected $_goods_list = array();
    protected $_extras = array();

    protected $_pclasses = array();

    const FLAG_ITEM_NORMAL = "normal";
    const FLAG_ITEM_SHIPPING_FEE = "shipping";
    const FLAG_ITEM_HANDLING_FEE = "handling";

    const REFUND_METHOD_FULL = "full";
    const REFUND_METHOD_PART = "part";
    const REFUND_METHOD_AMOUNT = "amount";


    /**
     * Build the PClass URI
     *
     * @return array
     */
    protected function _getPCURI()
    {
        $mageConfig = Mage::getResourceModel('sales/order')->getReadConnection()->getConfig();
        return array(
            "user"      => $mageConfig['username'],
            "passwd"    => $mageConfig['password'],
            "dsn"       => $mageConfig['host'],
            "db"        => $mageConfig['dbname'],
            "table"     => "klarnapclasses"
        );
    }

    protected function _setAdditionalInformation($data, $value = NULL)
    {
        if (!$data) return;
        if ($value && !is_array($data)) {
            if ($this->_additionalInfo) {
                $this->_additionalInfo->setData($data, $value);
            } else {
                $this->_additionalInfo = new Varien_Object(array($data, $value));
            }
        } else {
            if ($this->_additionalInfo) {
                $this->_additionalInfo->setData($data);
            } else {
                $this->_additionalInfo = new Varien_Object($data);
            }
        }
    }
    
    protected function _unsetAdditionalInformation($field)
    {
        $this->_additionalInfo->unsetData($field);
    }
    
    protected function _getAdditionalInformation($field = '')
    {
        return $this->_additionalInfo->getData($field);
    }
        
    protected function _getGoodsList()
    {
        return $this->_goods_list;
    }

    protected function _getExtras()
    {
        return $this->_extras;
    }

    /**
     * Get the Personal Number associated to this purchase
     *
     * @return string
     */
    protected function _getPNO()
    {
        if ($this->needDateOfBirth()) {
            if ((array_key_exists("dob_day", $this->_getAdditionalInformation()))
                && (array_key_exists("dob_month", $this->_getAdditionalInformation()))
                && (array_key_exists("dob_year", $this->_getAdditionalInformation()))
            ) {
                return $this->_getAdditionalInformation("dob_day")
                    . $this->_getAdditionalInformation("dob_month")
                    . $this->_getAdditionalInformation("dob_year");
            }
        } elseif (array_key_exists("pno", $this->_getAdditionalInformation())
            && strlen($this->_getAdditionalInformation("pno")) > 0
        ) {
            return $this->_getAdditionalInformation("pno");
        }
        return "";
    }

    /**
     * Get the gender associated to this purchase
     *
     * @return null|int
     */
    protected function _getGender()
    {
        if ($this->needGender() && array_key_exists("gender", $this->_getAdditionalInformation())) {
            return $this->_getAdditionalInformation("gender");
        }
        return null;
    }

    /**
     * Get the payment plan associated to this purchase
     *
     * @return int
     */
    protected function _getPaymentPlan()
    {
        if ((array_key_exists(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN, $this->_getAdditionalInformation()))
            && ($this->getOrder()->getPayment()->getMethod() !== "klarna_invoice")
        ) {
            return (int)$this->_getAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN);
        }
        return -1;
    }

    /**
     * Returns the tax rate
     *
     * @param int $taxClass The tax class to get the rate for
     *
     * @return double The tax rate
     */
    protected function _getTaxRate($taxClass)
    {
        // Load the customer so we can retrevice the correct tax class id
        $customer = Mage::getModel('customer/customer')->load($this->getOrder()->getCustomerId());
        $calculation = Mage::getSingleton('tax/calculation');
        $request = $calculation->getRateRequest(
            $this->getShippingAddress(),
            $this->getBillingAddress(),
            $customer->getTaxClassId(),
            $this->getOrder()->getStore()
        );
        return $calculation->getRate($request->setProductClassId($taxClass));
    }
    
    /**
     * Klarna supports three different types of refunds, full, part and amount
     * If any additional amount is specified, it will refund amount wise
     * If The entire amount is refunded, it will be a full refund
     * If above alternatives are false, it will refund part (meaning per item)
     * The exception to this is if you have discounts, then the orderline in Klarna
     * is the non-discounted one, followed by a total discount amount line.
     * Then it is impossible to refund part, then we need the amount refund.
     *
     * @param float $amount The amount to refund
     *
     * @return string One of the const methods defined above
     */
    protected function _decideRefundMethod($amount)
    {
        $res = self::REFUND_METHOD_PART;
        $remaining = $this->getOrder()->getTotalInvoiced() - $this->getOrder()->getTotalOnlineRefunded(); // - $this->getOrder()->getShippingRefunded();
        if (abs($remaining - $amount) < 0.00001) {
            $res = self::REFUND_METHOD_FULL;
        } else {
            if ($this->getCreditmemo()->getAdjustmentPositive()!=0 || $this->getCreditmemo()->getAdjustmentNegative()!=0) {
                $res = self::REFUND_METHOD_AMOUNT;
            } else {
                foreach ($this->_getExtras() as $extra) {
                    if (isset($extra['flags'])) {
                        switch ($extra['flags']) {
                            case self::FLAG_ITEM_HANDLING_FEE:
                                if ($this->getCreditmemo()->getVaimoKlarnaFeeRefund()>0) {
                                    if (isset($extra['original_price'])) {
                                        if ($extra['original_price']!=$extra['price']) { // If not full shipping refunded, it will use refund amount instead
                                            $res = self::REFUND_METHOD_AMOUNT;
                                        }
                                    }
                                }
                                break;
                            case self::FLAG_ITEM_SHIPPING_FEE;
                                if ($this->getCreditmemo()->getShippingAmount()>0) {
                                    if (isset($extra['original_price'])) {
                                        if ($extra['original_price']!=$extra['price']) { // If not full shipping refunded, it will use refund amount instead
                                            $res = self::REFUND_METHOD_AMOUNT;
                                        }
                                    }
                                }
                                break;
                            case self::FLAG_ITEM_NORMAL:
                                break;
                            default:
                                break;
                        }
                    }
                }
            }
            $discount_amount = 0;
            foreach ($this->getOrder()->getItemsCollection() as $item) {
                $discount_amount += $item->getDiscountAmount();
            }
            if ($discount_amount) {
                $res = self::REFUND_METHOD_AMOUNT;
            }
        }
        return $res;
    }

    protected function _checkBundles($items, &$item, $product)
    {
        if ($item->getProductType()==Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            if ($product->getPriceType()==Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC) {
                $item->setPriceInclTax(0);
            }
        }
    }

    /**
     * Create the goods list for Reservations
     *
     * @param array $items The items to add to the goods list
     *
     * @return void
     */
    protected function _createGoodsList($items = null)
    {
        if ($items === null) {
            $items = $this->getOrder()->getAllVisibleItems();
        }

        $taxRate = NULL;

        foreach ($items as $item) {

            if (Mage::helper('klarna')->shouldItemBeIncluded($item)==false) continue;

            //For handling the different activation
            $qty = $item->getQtyOrdered(); //Standard
            if (!isset($qty)) {
                $qty = $item->getQty(); //Advanced
            }
            $id = $item->getProductId();
            $product = Mage::getModel('catalog/product')->load($id);

            $this->_checkBundles($items, $item, $product);

            $taxRate = $this->_getTaxRate($product->getTaxClassId());

            $this->_goods_list[] =
                array(
                    "qty" => $qty,
                    "sku" => $item->getSku(),
                    "name" => $item->getName(),
                    "price" => $item->getPriceInclTax(),
                    "tax" => $taxRate,
                    "discount" => 0,
                    "flags" => self::FLAG_ITEM_NORMAL
                );
        }

        //Only add discounts and etc for unactivated orders
        if ($this->getOrder()->hasInvoices() <= 1) {
            $this->_addExtraFees($taxRate);
        }
    }

    /**
     * Create the goods list for Refunds
     *
     * @param array $items The items to add to the goods list
     *
     * @return void
     */
    protected function _createRefundGoodsList($items = null)
    {
        if ($items === null) {
            $this->_logKlarnaApi('_createRefundGoodsList got no items. Order: ' . $this->getOrder()->getIncrementId());
        }

        $taxRate = NULL;

        if ($items) {
            foreach ($items as $item) {
                $qty = $item->getQty();
                $id = $item->getProductId();
                $product = Mage::getModel('catalog/product')->load($id);

                $taxRate = $this->_getTaxRate($product->getTaxClassId());

                $this->_goods_list[] =
                    array(
                        "qty" => $qty,
                        "sku" => $item->getSku(),
                        "name" => $item->getName(),
                        "price" => $item->getPriceInclTax(),
                        "tax" => $taxRate,
                        "discount" => 0,
                        "flags" => self::FLAG_ITEM_NORMAL
                    );
            }
        }
        // Add same extra fees as original order, then remove the ones that should not be refunded
        $this->_addExtraFees($taxRate);
        foreach ($this->_getExtras() as $id => $extra) {
            if (isset($extra['flags'])) {
                switch ($extra['flags']) {
                    case self::FLAG_ITEM_HANDLING_FEE:
                        if ($this->getCreditmemo()->getVaimoKlarnaFeeRefund()>0) { // If not full invoice fee refunded, it will use refund amount instead
                            $this->_extras[$id]['original_price'] = $this->_extras[$id]['price'];
                            $this->_extras[$id]['price'] = $this->getCreditmemo()->getVaimoKlarnaFeeRefund();
                        } else {
                            unset($this->_extras[$id]);
                        }
                        break;
                    case self::FLAG_ITEM_SHIPPING_FEE;
                        if ($this->getCreditmemo()->getShippingAmount()>0) { // If not full shipping refunded, it will use refund amount instead
                            $this->_extras[$id]['original_price'] = $this->_extras[$id]['price'];
                            $this->_extras[$id]['price'] = $this->getCreditmemo()->getShippingAmount();
                        } else {
                            unset($this->_extras[$id]);
                        }
                        break;
                    case self::FLAG_ITEM_NORMAL:
                        unset($this->_extras[$id]);
                        break;
                    default:
                        unset($this->_extras[$id]);
                        break;
                }

            } else {
                unset($this->_extras[$id]);
            }
        }
    }

    /**
     * Returns the total handling fee included in extras
     *
     * @return decimal
     */
    protected function _feeAmountIncluded()
    {
        $res = 0;
        foreach ($this->_getExtras() as $extra) {
            if (isset($extra['flags'])) {
                if ($extra['flags']==self::FLAG_ITEM_HANDLING_FEE) {
                    $res = $res + $extra['price'];
                }
            }
        }
        return $res;
    }

    /**
     * Add all possible fees and discounts.
     *
     * @return void
     */
    protected function _addExtraFees($taxRate)
    {
        $this->_addInvoiceFee();

        $this->_addShippingFee();

        $this->_addGiftCard();

        $this->_addCustomerBalance();

        $this->_addRewardCurrency();

        $this->_addGiftWrapPrice();

        $this->_addGiftWrapItemPrice();

        $this->_addGwPrintedCardPrice();

        $this->_addDiscount($taxRate);

    }

    /**
     * Add the Gift Wrap Order price to the goods list
     *
     * @return void
     */
    protected function _addGiftWrapPrice()
    {
        if ($this->getOrder()->getGwPrice() <= 0) {
            return;
        }

        $price = $this->getOrder()->getGwPrice();
        $tax = $this->getOrder()->getGwTaxAmount();

        $sku = Mage::helper('klarna')->__('gw_order');

        $name = Mage::helper("enterprise_giftwrapping")->__("Gift Wrapping for Order");
        $this->_extras[] = array(
            "qty" => 1,
            "sku" => $sku,
            "name" => $name,
            "price" => $price + $tax,
        );
    }

    /**
     * Add the Gift Wrap Item price to the goods list
     *
     * @return void
     */
    protected function _addGiftWrapItemPrice()
    {
        if ($this->getOrder()->getGwItemsPrice() <= 0) {
            return;
        }

        $price = $this->getOrder()->getGwItemsPrice();
        $tax = $this->getOrder()->getGwItemsTaxAmount();

        $name = Mage::helper("enterprise_giftwrapping")->__("Gift Wrapping for Items");

        $sku = Mage::helper('klarna')->__('gw_items');

        $this->_extras[] = array(
            "qty" => 1,
            "sku" => $sku,
            "name" => $name,
            "price" => $price + $tax
        );
    }

    /**
     * Add the Gift Wrap Printed Card to the goods list
     *
     * @return void
     */
    protected function _addGwPrintedCardPrice()
    {
        if ($this->getOrder()->getGwPrintedCardPrice() <= 0) {
            return;
        }

        $price = $this->getOrder()->getGwPrintedCardPrice();
        $tax = $this->getOrder()->getGwPrintedCardTaxAmount();

        $name = Mage::helper("enterprise_giftwrapping")->__("Printed Card");

        $sku = Mage::helper('klarna')->__('gw_printed_card');

        $this->_extras[] = array(
            "qty" => 1,
            "sku" => $sku,
            "name" => $name,
            "price" => $price + $tax
        );
    }

    /**
     * Add the gift card amount to the goods list
     *
     * @return void
     */
    protected function _addGiftCard()
    {
        if ($this->getOrder()->getGiftCardsAmount() <= 0) {
            return;
        }

        $sku = Mage::helper('klarna')->__('gift_card');

        $this->_extras[] = array(
            "qty" => 1,
            "sku" => $sku,
            "name" => Mage::helper('klarna')->__('Gift Card'),
            "price" => ($this->getOrder()->getGiftCardsAmount() * -1)
        );
    }

    /**
     * Add the customer balance to the goods list
     *
     * @return void
     */
    protected function _addCustomerBalance()
    {
        if ($this->getOrder()->getCustomerBalanceAmount() <= 0) {
            return;
        }

        $sku = Mage::helper('klarna')->__('customer_balance');

        $this->_extras[] = array(
            "qty" => 1,
            "sku" => $sku,
            "name" => Mage::helper('klarna')->__("Customer Balance"),
            "price" => ($this->getOrder()->getCustomerBalanceAmount() * -1)
        );
    }

    /**
     * Add a reward currency amount to the goods list
     *
     * @return void
     */
    protected function _addRewardCurrency()
    {
        if ($this->getOrder()->getRewardCurrencyAmount() <= 0) {
            return;
        }

        $sku = Mage::helper('klarna')->__('reward_currency');

        $this->_extras[] = array(
            "qty" => 1,
            "sku" => $sku,
            "name" => Mage::helper('klarna')->__('Reward Currency'),
            "price" => ($this->getOrder()->getRewardCurrencyAmount() * -1)
        );
    }

    /**
     * Add the invoice fee to the goods list
     *
     * @return void
     */
    protected function _addInvoiceFee()
    {
        if ($this->getOrder()->getPayment()->getMethod() != Vaimo_Klarna_Helper_Data::KLARNA_METHOD_INVOICE) {
            return;
        }
        if ($this->_getAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_FEE)==0) {
            return;
        }

        $sku = Mage::helper('klarna')->__('invoice_fee');

        $this->_extras[] = array(
            "qty" => 1,
            "sku" => $sku,
            "name" => Mage::helper('klarna')->getKlarnaFeeLabel($this->getOrder()->getStore()),
            "price" => $this->_getAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_FEE) + $this->_getAdditionalInformation(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_FEE_TAX),
            "tax" => Mage::helper('klarna')->getVaimoKlarnaFeeVatRate($this->getOrder()),
            "flags" => self::FLAG_ITEM_HANDLING_FEE
        );
    }

    /**
     * Add the shipment fee to the goods list
     *
     * @return void
     */
    protected function _addShippingFee()
    {
        if ($this->getOrder()->getShippingInclTax() <= 0) {
            return;
        }
        $taxClass = Mage::getStoreConfig('tax/classes/shipping_tax_class');

        $sku = $this->getOrder()->getShippingMethod();

        if (!$sku) {
            $sku = Mage::helper('klarna')->__('discount');
        }

        $this->_extras[] = array(
            "qty" => 1,
            "sku" => $sku,
            "name" => $this->getOrder()->getShippingDescription(),
            "price" => $this->getOrder()->getShippingInclTax(),
            "tax" => $this->_getTaxRate($taxClass),
            "flags" => self::FLAG_ITEM_SHIPPING_FEE
        );
    }

    /**
     * Add the discount to the goods list
     *
     * @param $taxRate is the VAT rate of the LAST product in cart... Not perfect of course, but better than no VAT. It must be an official rate, can't be median
     * @return void
     */
    protected function _addDiscount($taxRate)
    {
        if ($this->getOrder()->getDiscountAmount() >= 0) {
            return;
        }
        // Instead of calculating discount from order etc, we now simply use the amounts we are adding to goods list
        
        //calculate grandtotal and subtotal with all possible fees and extra costs
        $subtotal = $this->getOrder()->getSubtotalInclTax();
		$grandtotal = $this->getOrder()->getGrandTotal();
		
		//if fee is added, add to subtotal
		//if discount is added, like cards and such, add to grand total
		foreach ($this->_extras as $extra) {
			if ($extra['price'] > 0) {
				$subtotal+= $extra['price'];
			} else if ($extra['price'] < 0) {
				$grandtotal+= $extra['price'];
			}
		}
        
        //now check what the actual discount incl vat is
        $amount = $grandtotal - $subtotal; //grand total is always incl tax

/*
        $amount = $this->getOrder()->getDiscountAmount();
        $applyAfter = Mage::helper('tax')->applyTaxAfterDiscount( $this->getOrder()->getStoreId() );
        $prodInclVat = Mage::helper('tax')->priceIncludesTax( $this->getOrder()->getStoreId() );
        if ($applyAfter == true) {
            //With this setting active the discount will not have the correct
            //value. We need to take each respective products rate and calculate
            //a new value.

            // The interesting part is that Magento changes how discounts are
            // added depending on if product prices are including VAT or not...
            if ($prodInclVat == false) {
                $amount = 0;
                foreach ($this->getOrder()->getAllVisibleItems() as $product) {
                    $rate = $product->getTaxPercent();
                    $newAmount = $product->getDiscountAmount() * (($rate / 100 ) + 1);
                    $amount -= $newAmount;
                }
                //If the discount also extends to shipping
                $shippingDiscount = $this->getOrder()->getShippingDiscountAmount() - 0;
                if ($shippingDiscount) {
                    $taxClass = Mage::getStoreConfig('tax/classes/shipping_tax_class');
                    $rate = $this->_getTaxRate($taxClass);
                    $newAmount = $shippingDiscount * (($rate / 100 ) + 1);
                    $amount -= $newAmount;
                }
            }
        }
*/

        $sku = $this->getOrder()->getDiscountDescription();

        if (!$sku) {
            $sku = Mage::helper('klarna')->__('discount');
        }

        $this->_extras[] = array(
            "qty" => 1,
            "sku" => $sku,
            "name" => Mage::helper('sales')->__('Discount (%s)', $sku),
            "price" => $amount,
            "tax" => $taxRate
        );
    }

    /**
     * Get the store information to use for fetching new PClasses
     *
     * @param storeIds a comma separated list of stores as a filter which ones to include
     *
     * @return array of store ids where Klarna is active
     */
    protected function _getKlarnaActiveStores()
    {
        $result = array();
        foreach (Mage::app()->getStores() as $store) {
            if (!$store->getConfig('payment/' . Vaimo_Klarna_Helper_Data::KLARNA_METHOD_ACCOUNT . '/active')
                && !$store->getConfig('payment/' . Vaimo_Klarna_Helper_Data::KLARNA_METHOD_SPECIAL . '/active')
            ) {
                continue;
            }
            $result[] = $store->getId();
        }
        return $result;
    }

    /*
     * We do not want to save things in the database that doesn't need to be there
     * Personal ID is also removed from database
     *
     * @return void
     */
    protected function _cleanAdditionalInfo()
    {
        if (array_key_exists("pno", $this->_getAdditionalInformation())) {
            $pno = $this->_getAdditionalInformation("pno");
            if (strlen($pno) > 0) {
                $this->getPayment()->unsAdditionalInformation("pno");
            }
            Mage::dispatchEvent( 'vaimo_klarna_pno_used_to_reserve', array(
                'store_id' => $this->getOrder()->getStoreId(),
                'order_id' => $this->getOrder()->getIncrementId(),
                'customer_id' => $this->getOrder()->getCustomerId(),
                'pno' => $pno
                ));
        }
        if (array_key_exists("consent", $this->_getAdditionalInformation())) {
            if ($this->_getAdditionalInformation("consent")=="NO") {
                $this->getPayment()->unsAdditionalInformation("consent");
            }
        }
        if (array_key_exists("gender", $this->_getAdditionalInformation())) {
            if ($this->_getAdditionalInformation("gender")=="-1") {
                $this->getPayment()->unsAdditionalInformation("gender");
            }
        }
    }

    /*
     * Whenever a refund, capture, reserve or cancel is performed, we send out an event
     * This can be listened to for financial reconciliation
     *
     * @return void
     */
    protected function _sendMethodEvent($eventcode, $amount)
    {
        Mage::dispatchEvent( $eventcode, array(
            'store_id' => $this->getOrder()->getStoreId(),
            'order_id' => $this->getOrder()->getIncrementId(),
            'method' => $this->getMethod(),
            'amount' => $amount
            ));
    }
}
