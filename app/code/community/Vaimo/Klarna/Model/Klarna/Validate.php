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

class Vaimo_Klarna_Model_Klarna_Validate extends Vaimo_Klarna_Model_Klarna_Tools_Address
{
    protected $_postValues = array();

    public function __construct()
    {
        parent::__construct();
        $this->_setFunctionName('validate');
    }

    /**
     * Check that Date of birth has been supplied if required.
     *
     * @return bool
     */
    public function checkDateOfBirth()
    {
        try {
            $data = $this->getInfoInstance();
            if (!$data->getAdditionalInformation('dob_day') ||
                !$data->getAdditionalInformation('dob_month') ||
                !$data->getAdditionalInformation('dob_year')) {
                return false;
            }
            if ($data->getAdditionalInformation('dob_day') === "00" ||
                $data->getAdditionalInformation('dob_month') === "00" ||
                $data->getAdditionalInformation('dob_year') === "00" ) {
                return false;
            }
        } catch (Mage_Core_Exception $e) {
            $this->logKlarnaException($e);
            return false;
        }
        return true;
    }

    protected function _checkField($field)
    {
        try {
            $data = $this->getInfoInstance();
            if (!$data->getAdditionalInformation($field)) {
                return false;
            }
        } catch (Mage_Core_Exception $e) {
            $this->logKlarnaException($e);
            return false;
        }
        return true;
    }

    /**
     * Check that consent has been given if needed.
     *
     * @return bool
     */
    public function checkConsent()
    {
        try {
            $data = $this->getInfoInstance();
            if ((!$data->getAdditionalInformation("consent"))
                || ($data->getAdditionalInformation("consent") !== "consent")) {
                return false;
            }
        } catch (Mage_Core_Exception $e) {
            $this->logKlarnaException($e);
            return false;
        }
        return true;
    }

    /**
     * Check that gender has been selected
     *
     * @return bool
     */
    public function checkGender()
    {
        try {
            $data = $this->getInfoInstance();
            if (($data->getAdditionalInformation("gender")!=="0")
             && ($data->getAdditionalInformation("gender")!=="1")) {
                return false;
            }
        } catch (Mage_Core_Exception $e) {
            $this->logKlarnaException($e);
            return false;
        }
        return true;
    }

    /**
     * Make sure phonenumber is not blank.
     *
     * @return bool
     */
    public function checkPhone()
    {
        return $this->_checkField("phonenumber");
    }

    /**
     * Make sure pno is not blank.
     *
     * @return bool
     */
    public function checkPno()
    {
        return $this->_checkField("pno");
    }

    public function doBasicTests()
    {
        if ($this->checkPhone()==false) {
            Mage::throwException(Mage::helper('klarna')->__('Phonenumber must be properly entered'));
        }
        if ($this->needDateOfBirth()) {
            if ($this->checkDateOfBirth()==false) {
                Mage::throwException(Mage::helper('klarna')->__('Date of birth fields must be properly entered'));
            }
        } else {
            if ($this->checkPno()==false) {
                Mage::throwException(Mage::helper('klarna')->__('Personal ID must not be empty'));
            }
        }
        if ($this->needConsent()) {
            if ($this->checkConsent()==false) {
                Mage::throwException(Mage::helper('klarna')->__('You need to agree to the terms to be able to continue'));
            }
        }
        if ($this->needGender()) {
            if ($this->checkGender()==false) {
                Mage::throwException(Mage::helper('klarna')->__('You need to enter your gender to be able to continue'));
            }
        }
    }
}
