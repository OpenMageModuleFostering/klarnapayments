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

class Vaimo_Klarna_Model_Klarna_Assign extends Vaimo_Klarna_Model_Klarna_Api
{
    protected $_postValues = array();

    public function __construct($klarnaApi = null, $payment = null)
    {
        parent::__construct($klarnaApi, $payment);
        $this->_setFunctionName('assign');
    }

    /**
     * Collect the post values that are relevant to the payment method
     *
     * @param array  $data    The post values to save
     * @param string $method  The payment method
     *
     * @return void
     */
    public function addPostvalues($data, $method = NULL)
    {
        foreach ($data as $key => $value) {
            if ($method) {
                $key = str_replace($method . "_", "", $key);
            }
            if (in_array($key,Mage::helper('klarna')->getKlarnaFields())) {
                $this->_postValues[$key] = $value;
            } else {
                $this->_logDebugInfo('Field ignored: ' . $key);
            }
        }
    }

    /*
     * This is required when using one step checkout, as it seems to post all fields for all klarna methods
     * This removes all fields containing names of the not selected klarna methods
     *
     * @param object  $data   Contains the data array containing the post values to clean
     * @param string $method  The payment method
     *
     * @return void
     */
    public function clearInactiveKlarnaMethodsPostvalues($dataObj, $method = NULL)
    {

        if ($method) {
            $methods = Mage::helper('klarna')->getSupportedMethods();
            $methodsToClear = array();
            foreach ($methods as $m) {
                if ($m!=$method) {
                    $methodsToClear[] = $m;
                }
            }
            $data = $dataObj->getData();
            foreach ($data as $key => $value) {
                foreach ($methodsToClear as $m) {
                    if (stristr($key, $m)!=false) {
                        unset($data[$key]);
                    }
                }
            }
            $dataObj->setData($data);
        }
    }

    public function unsPostvalue($key)
    {
        unset($this->_postValues[$key]);
    }

    /**
     * Set Magento additional info.
     *
     * Based on cleaned post values
     *
     * @param Mage_Payment_Model_Info $info   payment info instance
     *
     * @return void
     */
    public function updateAdditionalInformation($info)
    {
        foreach ($this->_postValues as $key => $value) {
            if ($value==='') {
                if ($info->getAdditionalInformation($key)) {
                    $info->unsAdditionalInformation($key);
                }
                continue;
            }
            $info->setAdditionalInformation($key, $value);
        }
    }

    /**
     * Get a usable email address
     *
     * @param string $customerSessionEmail email of current user
     *
     * @return string
     */
    public function getEmailValue($customerSessionEmail)
    {
        //Get the email address from the address object if its set
        $addressEmail = $this->getShippingAddress()->getEmail();
        if (strlen($addressEmail) > 0) {
            return $addressEmail;
        }

        //Otherwise we have to pick up the customers email from the session
        $sessionEmail = $customerSessionEmail;
        if (strlen($sessionEmail) > 0) {
            return $sessionEmail;
        }

        //For guests and new customers there wont be any email on the
        //customer object in the session or their shipping address, so we
        //have to fall back and get the email from their billing address.
        return $this->_billingAddress->getEmail();
    }

    /**
     * Update a Magento address with post values and save it
     * Even if they entered with two address lines, we update back to Magento only for first street line
     *
     * @param object $address The Magento address
     * @param string $specific_field To update only one field
     *
     * @return void
     */
    protected function _updateAddress($address, $specific_field = NULL)
    {
        if (array_key_exists("street", $this->_postValues)) {
            if ($specific_field==NULL || $specific_field=='street') {
                $street = $this->_postValues["street"];
                if (array_key_exists("house_number", $this->_postValues)) {
                    $street .=  " " . $this->_postValues["house_number"];
                }
                if (array_key_exists("house_extension", $this->_postValues)) {
                    $street .= " " . $this->_postValues["house_extension"];
                }
                $address->setStreet($this->_decode(trim($street)));
            }
        }

        if (array_key_exists("first_name", $this->_postValues)) {
            if ($specific_field==NULL || $specific_field=='first_name') {
                $address->setFirstname($this->_decode($this->_postValues["first_name"]));
            }
        }

        if (array_key_exists("last_name", $this->_postValues)) {
            if ($specific_field==NULL || $specific_field=='last_name') {
                $address->setLastname($this->_decode($this->_postValues["last_name"]));
            }
        }

        if (array_key_exists("zipcode", $this->_postValues)) {
            if ($specific_field==NULL || $specific_field=='zipcode') {
                $address->setPostcode($this->_decode($this->_postValues["zipcode"]));
            }
        }

        if (array_key_exists("city", $this->_postValues)) {
            if ($specific_field==NULL || $specific_field=='city') {
                $address->setCity($this->_decode($this->_postValues["city"]));
            }
        }

        if (array_key_exists("phonenumber", $this->_postValues)) {
            if ($specific_field==NULL || $specific_field=='phonenumber') {
                $address->setTelephone($this->_decode($this->_postValues["phonenumber"]));
            }
        }

        if ($specific_field==NULL || $specific_field=='company') {
            $address->setCompany($this->_getCompanyName($address, $this->_postValues));
        }

        $address->save();
    }

    /**
     * Get company name if possible.
     *
     * @param object $address The Magento address
     *
     * @return string Company name or empty string.
     */
    private function _getCompanyName($address)
    {
        if ($this->isCompanyAllowed() === false) {
            return '';
        }

        if (array_key_exists('invoice_type', $this->_postValues)
            && $this->_postValues['invoice_type'] !== 'company'
        ) {
            return '';
        }

        // If there is a company name in the POST, update it on the address.
        if (array_key_exists('company_name', $this->_postValues)) {
            return $this->_decode($this->_postValues['company_name']);
        }

        // Otherwise keep what is on the address.
        return $address->getCompany();
    }

    /**
     * Update addresses with data from our checkout box
     *
     * @return void
     */
    public function updateAddress()
    {
        /*
         * getAddress is only allowed in Sweden, so this code is for Sweden only
         */
        if ($this->useGetAddresses()) {
            if (!isset($this->_postValues['pno']) || !isset($this->_postValues['address_id'])) {
                /*
                 * OneStepCheckout saves payment method upon load, which means an error message must not be produced
                 * in this function. Authorize will attempt to use the value and give an error message, which means
                 * it will be checked and reported anyway
                 */
                if (!Mage::helper('klarna')->isOneStepCheckout()) {
                    Mage::throwException(Mage::helper('klarna')->__(
                        'Unknown address, please specify correct personal id in the payment selection and press Fetch again, or use another payment method'
                        )
                    );
                }
            }
            if (isset($this->_postValues['pno']) && isset($this->_postValues['address_id'])) {
                $addr = $this->_getSelectedAddress($this->_postValues['pno'], $this->_postValues['address_id']);
                if ($addr!=NULL) {
                    /*
                     * This is not approved by Klarna, so address will be updated only when order is placed. This is NOT a bug.
                     */
                    // $this->_updateShippingWithSelectedAddress($addr);
                } else {
                    /*
                     * No error message here if using OneStepCheckout
                     */
                    if (!Mage::helper('klarna')->isOneStepCheckout()) {
                        Mage::throwException(Mage::helper('klarna')->__(
                            'Unknown address, please specify correct personal id in the payment selection and press Fetch again, or use another payment method'
                            )
                        );
                    }
                }
            }
        }

        /*
         *  Update the addresses with values from the checkout
         */
        $this->_updateAddress($this->getShippingAddress());
        $this->_updateAddress($this->getBillingAddress(), 'phonenumber');

    }

    public function getPostValues($key)
    {
        if ($key) {
            if (isset($this->_postValues[$key])) {
                return $this->_postValues[$key];
            } else {
                return NULL;
            }
        }
        return $this->_postValues;
    }

    public function setPaymentPlan()
    {
        $id = $this->getPostValues(Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN);
        $method = $this->getPostValues('method');
        if ($id) {
            $pclassArray = $this->_getSpecificPClass($id);
            if (!$pclassArray) {
                Mage::throwException(Mage::helper('klarna')->__('Unexpected error, pclass does not exist, please reload page and try again'));
            }
            $this->addPostvalues(array(
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_DESCRIPTION  => $pclassArray['description'],
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_MONTHLY_COST => $pclassArray['monthly_cost'],
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_TOTAL_COST   => $pclassArray['total_cost'],
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_INVOICE_FEE  => $pclassArray['invoicefee'],
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_START_FEE    => $pclassArray['startfee'],
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_MONTHS       => $pclassArray['months'],
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_TYPE         => $pclassArray['type'],
                        ));
        } else {
            if ($method==Vaimo_Klarna_Helper_Data::KLARNA_METHOD_ACCOUNT || $method==Vaimo_Klarna_Helper_Data::KLARNA_METHOD_SPECIAL ) {
                Mage::throwException(Mage::helper('klarna')->__('You must choose a payment plan'));
            }
            $this->addPostvalues(array(
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_DESCRIPTION  => '',
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_MONTHLY_COST => '',
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_TOTAL_COST   => '',
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_INVOICE_FEE  => '',
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_START_FEE    => '',
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_MONTHS       => '',
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN_TYPE         => '',
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_PAYMENT_PLAN              => '',
                        ));
        }
    }

    public function setPaymentFee($quote)
    {
        if ($quote->getVaimoKlarnaFee()) {
            $this->addPostvalues(array(
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_FEE  => $quote->getVaimoKlarnaFee(),
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_FEE_TAX => $quote->getVaimoKlarnaFeeTax(),
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_BASE_FEE  => $quote->getVaimoKlarnaBaseFee(),
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_BASE_FEE_TAX => $quote->getVaimoKlarnaBaseFeeTax(),
                        ));
        } else {
            $this->addPostvalues(array(
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_FEE  => '',
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_FEE_TAX => '',
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_BASE_FEE  => '',
                        Vaimo_Klarna_Helper_Data::KLARNA_INFO_FIELD_BASE_FEE_TAX => '',
                        ));
        }
    }

}
