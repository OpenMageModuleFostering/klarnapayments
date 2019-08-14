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

class Vaimo_Klarna_Model_Klarna_Tools_Address extends Vaimo_Klarna_Model_Klarna_Tools_Abstract
{
    /**
     * Split a string into an array consisting of Street, House Number and
     * House extension.
     *
     * @param string $address Address string to split
     *
     * @return array
     */
    protected static function _splitAddress($address)
    {
        // Get everything up to the first number with a regex
        $hasMatch = preg_match('/^[^0-9]*/', $address, $match);

        // If no matching is possible, return the supplied string as the street
        if (!$hasMatch) {
            return array($address, "", "");
        }

        // Remove the street from the address.
        $address = str_replace($match[0], "", $address);
        $street = trim($match[0]);

        // Nothing left to split, return
        if (strlen($address) == 0) {
            return array($street, "", "");
        }
        // Explode address to an array
        $addrArray = explode(" ", $address);

        // Shift the first element off the array, that is the house number
        $housenumber = array_shift($addrArray);

        // If the array is empty now, there is no extension.
        if (count($addrArray) == 0) {
            return array($street, $housenumber, "");
        }

        // Join together the remaining pieces as the extension.
        $extension = implode(" ", $addrArray);

        return array($street, $housenumber, $extension);
    }

    /**
     * Get the formatted street required for a Klarna Addr
     *
     * @param string $street The street to split
     * @param array  $split  An array determining the parts of the split
     *
     * @return array
     */
    protected function _splitStreet($street)
    {
        $split = $this->_getSplit();
        $result = array(
            'street' => '',
            'house_extension' => '',
            'house_number' => ''
        );
        $elements = $this->_splitAddress($street);
        $result['street'] = $elements[0];

        if (in_array('house_extension', $split)) {
            $result['house_extension'] = $elements[2];
        } else {
            $elements[1] .= ' ' . $elements[2];
        }

        if (in_array('house_number', $split)) {
            $result['house_number'] = $elements[1];
        } else {
            $result['street'] .= ' ' . $elements[1];
        }

        return array_map('trim', $result);
    }

    /**
     * Update a Magento address with an array containing address information
     *
     * @param array $addr  The addr to use
     *
     * @return void
     */
    protected function _updateShippingWithSelectedAddress($addr)
    {
        $selAddr = new Varien_Object($addr);
        $address = $this->getShippingAddress();
        $street = $selAddr->getStreet();

        if ($selAddr->getHouseNumber()) {
            $street .= " " . $selAddr->getHouseNumber();
        }
        if ($selAddr->getHouseExtension()) {
            $street .= " " . $selAddr->getHouseExtension();
        }

        // If it's a company purchase set company name.
        $company = $selAddr->getCompanyName();
        if ($company!="" && $this->isCompanyAllowed()) {
            $address->setCompany($company);
        } else {
            $address->setFirstname($selAddr->getFirstName())
                ->setLastname($selAddr->getLastName())
                ->setCompany('');
        }

        $address->setPostcode($selAddr->getZip())
            ->setStreet(trim($street))
            ->setCity($selAddr->getCity())
            ->save();
    }

    /**
     * Update a Magento address with another Magento address and save it.
     *
     * @return void
     */
    public function updateBillingAddress()
    {
        $this->getBillingAddress()->setFirstname($this->getShippingAddress()->getFirstname())
            ->setLastname($this->getShippingAddress()->getLastname())
            ->setPostcode($this->getShippingAddress()->getPostcode())
            ->setStreet($this->getShippingAddress()->getStreet())
            ->setCity($this->getShippingAddress()->getCity())
            ->setTelephone($this->getShippingAddress()->getTelephone())
            ->setCountry($this->getShippingAddress()->getCountry())
            ->setCompany($this->getShippingAddress()->getCompany())
            ->save();
    }

    /**
     * Get a unique key used to identify the given address
     *
     * The key is a hash of the lower bit ascii portion of company name,
     * first name, last name and street joined with pipes
     *
     * @param KlarnaAddr $klarnaAddr address
     *
     * @return string key for this address
     */
    public static function getAddressKey($klarnaAddr)
    {
        return hash(
            'crc32',
            preg_replace(
                '/[^\w]*/', '',
                $klarnaAddr->getCompanyName() . '|' .
                $klarnaAddr->getFirstName() . '|' .
                $klarnaAddr->getLastName() . '|' .
                $klarnaAddr->getStreet()
            )
        );
    }

}
