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

class Vaimo_Klarna_Model_Klarna_Refund extends Vaimo_Klarna_Model_Klarna_Api
{
    public function __construct($klarnaApi = null, $payment = null)
    {
        parent::__construct($klarnaApi, $payment);
        $this->_setFunctionName('refund');
    }

    public function createItemList()
    {
        // The array that will hold the items that we are going to use
        $items = array();

        // Loop through the item collection
        foreach ($this->getCreditmemo()->getAllItems() as $item) {
            $ord_items = $this->getOrder()->getItemsCollection();
            foreach ($ord_items as $ord_item) {
                if ($ord_item->getId()==$item->getOrderItemId()) {
                    if (Mage::helper('klarna')->shouldItemBeIncluded($ord_item)) {
                        $items[] = $item;
                    }
                    break;
                }
            }
        }
        
        return $items;
    }
    
}
