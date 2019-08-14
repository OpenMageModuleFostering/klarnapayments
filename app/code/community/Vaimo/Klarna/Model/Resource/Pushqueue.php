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

class Vaimo_Klarna_Model_Resource_Pushqueue extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('klarna/pushqueue', 'id');
    }

    public function loadByKlarnaOrderNumber(Vaimo_Klarna_Model_Pushqueue $pushqueue, $klarnaOrderNumber)
    {
        $adapter = $this->_getReadAdapter();
        $pushqueueTable   = $this->getTable('klarna/pushqueue');
        $bind    = array('klarna_order_number' => $klarnaOrderNumber);
        $select  = $adapter->select()
            ->from($pushqueueTable)
            ->where('klarna_order_number = :klarna_order_number');

        $pushQueueId = $adapter->fetchOne($select, $bind);
        if ($pushQueueId) {
            $this->load($pushqueue, $pushQueueId);
        } else {
            $pushqueue->setData(array());
        }

        return $this;
    }

}