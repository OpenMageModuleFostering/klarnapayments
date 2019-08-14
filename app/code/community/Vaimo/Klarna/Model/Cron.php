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


class Vaimo_Klarna_Model_Cron extends Mage_Core_Model_Abstract
{
    public function statusUpdateOfPendingOrders()
    {
        $orders = Mage::getModel("sales/order")->getCollection()
            ->addFieldToFilter("state", array('eq' => Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW))
            ->addFieldToFilter("updated_at", array('gteq' => date("Y-m-d H:i:s", time() - 172800))); // 2 Days
        foreach ($orders as $order) {
            try {
                $payment = $order->getPayment();
                if (Mage::helper('klarna')->isMethodKlarna($payment->getMethod())) {
                    $payment->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_UPDATE, true);
                    $order->save();
                }
            } catch (Exception $e) {
                // Do nothing?
            }
        }
    }

    /**
     * Basically a copy of the KlarnaController pushAction
     * This will be unified later
     */
    public function treatPushQueue()
    {
        $collection = Mage::getModel('klarna/pushqueue')
            ->getCollection()
            ->applyRetryFilter(Vaimo_Klarna_Helper_Data::KLARNA_KCO_QUEUE_RETRY_ATTEMPTS);
        if ($collection->count()>0) {
            $helper = Mage::helper('klarna');
            $helper->setFunctionNameForLog('cron treatPushQueue');
            $helper->logKlarnaApi(Vaimo_Klarna_Helper_Data::KLARNA_LOG_START_TAG);
            foreach ($collection as $pushQueue) {
                $checkoutId = $pushQueue->getKlarnaOrderNumber();
                $quote = $helper->findQuote($checkoutId);
                if ($quote == null)
                    continue;

                /** @var Vaimo_Klarna_Model_Klarnacheckout $klarna */
                $klarna = Mage::getModel('klarna/klarnacheckout');
                $klarna->setQuote($quote, Vaimo_Klarna_Helper_Data::KLARNA_METHOD_CHECKOUT);
                if (substr($checkoutId, -1, 1) == '/') {
                    $checkoutId = substr($checkoutId, 0, strlen($checkoutId) - 1);
                }
                $helper->logKlarnaApi('pushAction checkout id: ' . $checkoutId);
                if (!$quote->getId()) {
                    $helper->logKlarnaApi('pushAction checkout quote not found!');
                }

                if ($checkoutId) {
                    try {
                        // createOrder returns the order if successful, otherwise an error string
                        $result = $klarna->createOrder($checkoutId);

                        if (is_array($result)) {
                            if ($result['status']=='success') {
                                $pushQueue->delete();
                                $helper->logKlarnaApi('Klarna cron order created successfully, order id: ' . $result['order']->getId());
                            } elseif ($result['status']=='fail') {
                                $pushQueue->delete();
                                $helper->logKlarnaApi($result['message']);
                            } else {
                                $pushQueue->setMessage($result['message']);
                                $attempt = $pushQueue->getRetryAttempts();
                                $pushQueue->setRetryAttempts($attempt + 1);
                                $pushQueue->save();
                                $helper->logKlarnaApi($result['message']);
                            }
                        } else {
                            $pushQueue->setMessage('Unkown error from createOrder');
                            $attempt = $pushQueue->getRetryAttempts();
                            $pushQueue->setRetryAttempts($attempt + 1);
                            $pushQueue->save();
                            $helper->logKlarnaApi('Unkown error from createOrder');
                        }

                    } catch (Exception $e) {
                        $pushQueue->setMessage($e->getMessage());
                        $attempt = $pushQueue->getRetryAttempts();
                        $pushQueue->setRetryAttempts($attempt + 1);
                        $pushQueue->save();
                        $helper->logKlarnaException($e);
                    }
                }
            }
            $helper->setFunctionNameForLog('cron treatPushQueue');
            $helper->logKlarnaApi(Vaimo_Klarna_Helper_Data::KLARNA_LOG_END_TAG);
        }
    }
}
