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

class Vaimo_Klarna_Model_Klarna_Authorize extends Vaimo_Klarna_Model_Klarna_Api
{
    public function __construct($klarnaApi = null, $payment = null)
    {
        parent::__construct($klarnaApi, $payment);
        $this->_setFunctionName('authorize');
    }

    public function createItemList()
    {
        // The array that will hold the items that we are going to use
        $items = array();

        // Loop through the item collection
        foreach ($this->getOrder()->getAllItems() as $item) {
            if (Mage::helper('klarna')->shouldItemBeIncluded($item)==false) continue;
            $items[] = $item;
        }
        
        return $items;
    }

    public function shouldOrderBeCanceled($status)
    {
        $cancel_denied = true; // $this->_getConfigData('denied_status'); // Decision to cancel denied always, not optional
        if ($status==Vaimo_Klarna_Helper_Data::KLARNA_STATUS_DENIED && $cancel_denied) {
            return true;
        }
        return false;
    }
    
    /**
     * Update addresses with data from our checkout box
     *
     * @return void
     */
    public function updateAddress()
    {
        //Update with the getAddress call for Swedish customers
        if ($this->useGetAddresses()) {
            $addr = $this->_getSelectedAddress($this->getPayment()->getAdditionalInformation('pno'), $this->getPayment()->getAdditionalInformation('address_id'));
            if ($addr!=NULL) {
                $this->_updateShippingWithSelectedAddress($addr);
            } else {
                Mage::throwException(Mage::helper('klarna')->__('Unknown address, please specify correct personal id in the payment selection and press Fetch again, or use another payment method'));
            }
        }

        //Check to see if the addresses must be same. If so overwrite billing
        //address with the shipping address.
        if ($this->shippingSameAsBilling()) {
            $this->updateBillingAddress();
        }
    }

}
