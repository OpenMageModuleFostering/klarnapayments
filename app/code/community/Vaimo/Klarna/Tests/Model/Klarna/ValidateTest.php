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

class Vaimo_Klarna_Tests_Model_Klarna_ValidateTest extends Vaimo_Klarna_Tests_TestCase
{
    public $additionalInformationCorrect = null;
    public $additionalInformationIncorrect = null;
    public $infoinstance = null;

    public function setUp()
    {
        //@todo: create mock function for getInfoInstance used in Vaimo_Klarna_Model_Klarna_Validate
        $this->infoinstance = new Varien_Object();
        $this->additionalInformationCorrect = array(
            'consent' => 'consent',
            'dob_day' => '01',
            'dob_month' => '01',
            'dob_year' => '01',
            'phonenumber' => '032110325',
            'pno' => '8303030303',
            'gender' => '1',
        );

        $this->additionalInformationIncorrect = array(
            'consent' => '',
            'dob_day' => '00',
            'dob_month' => '00',
            'dob_year' => '00',
            'phonenumber' => '',
            'pno' => '',
            'gender' => '',
        );
    }

    public function testCheckConsentShouldReturnTrue()
    {
        $this->infoinstance->setData('additional_information', $this->additionalInformationCorrect);
        $stub = $this->getMock('Vaimo_Klarna_Model_Klarna_Validate', array('getInfoInstance'));
        $stub->expects($this->any())
                ->method('getInfoInstance')
                ->will($this->returnValue($this->infoinstance));

        $expected = true;
        $this->assertEquals($expected, $stub->checkConsent());
    }

    public function testCheckConsentShouldReturnFalse()
    {
        $this->infoinstance->setData('additional_information', $this->additionalInformationIncorrect);
        $stub = $this->getMock('Vaimo_Klarna_Model_Klarna_Validate', array('getInfoInstance'));
        $stub->expects($this->any())
                ->method('getInfoInstance')
                ->will($this->returnValue($this->infoinstance));

        $expected = false;
        $this->assertEquals($expected, $stub->checkConsent);
    }

    public function testCheckDateOfBirthShouldReturnTrue(){
        $this->infoinstance->setData('additional_information', $this->additionalInformationCorrect);

        $stub = $this->getMock('Vaimo_Klarna_Model_Klarna_Validate', array('getInfoInstance'));
        $stub->expects($this->any())
                ->method('getInfoInstance')
                ->will($this->returnValue($this->infoinstance));

        $expected = true;
        $this->assertEquals($expected, $stub->checkDateOfBirth());
    }

    public function testCheckDateOfBirthShouldReturnFalse(){
        $this->infoinstance->setData('additional_information', $this->additionalInformationIncorrect);

        $stub = $this->getMock('Vaimo_Klarna_Model_Klarna_Validate', array('getInfoInstance'));
        $stub->expects($this->any())
                ->method('getInfoInstance')
                ->will($this->returnValue($this->infoinstance));


        $expected = false;
        $this->assertEquals($expected, $stub->checkDateOfBirth());
    }

    public function testCheckPnoShouldReturnTrue(){
        $this->infoinstance->setData('additional_information', $this->additionalInformationCorrect);

        $stub = $this->getMock('Vaimo_Klarna_Model_Klarna_Validate', array('getInfoInstance'));
        $stub->expects($this->any())
                ->method('getInfoInstance')
                ->will($this->returnValue($this->infoinstance));


        $expected = true;
        $this->assertEquals($expected, $stub->checkPno());
    }

    public function testCheckPnoShouldReturnFalse(){
        $this->infoinstance->setData('additional_information', $this->additionalInformationIncorrect);

        $stub = $this->getMock('Vaimo_Klarna_Model_Klarna_Validate', array('getInfoInstance'));
        $stub->expects($this->any())
                ->method('getInfoInstance')
                ->will($this->returnValue($this->infoinstance));


        $expected = false;
        $this->assertEquals($expected, $stub->checkPno());
    }

    public function testCheckPhoneShouldReturnTrue(){
        $this->infoinstance->setData('additional_information', $this->additionalInformationCorrect);

        $stub = $this->getMock('Vaimo_Klarna_Model_Klarna_Validate', array('getInfoInstance'));
        $stub->expects($this->any())
                ->method('getInfoInstance')
                ->will($this->returnValue($this->infoinstance));


        $expected = true;
        $this->assertEquals($expected, $stub->checkPhone());
    }

    public function testCheckPhoneShouldReturnFalse(){
        $this->infoinstance->setData('additional_information', $this->additionalInformationIncorrect);

        $stub = $this->getMock('Vaimo_Klarna_Model_Klarna_Validate', array('getInfoInstance'));
        $stub->expects($this->any())
                ->method('getInfoInstance')
                ->will($this->returnValue($this->infoinstance));


        $expected = false;
        $this->assertEquals($expected, $stub->checkPhone());
    }

    public function testCheckGenderShouldReturnTrue(){
        $this->infoinstance->setData('additional_information', $this->additionalInformationCorrect);

        $stub = $this->getMock('Vaimo_Klarna_Model_Klarna_Validate', array('getInfoInstance'));
        $stub->expects($this->any())
                ->method('getInfoInstance')
                ->will($this->returnValue($this->infoinstance));


        $expected = true;
        $this->assertEquals($expected, $stub->checkGender());
    }

    public function testCheckGenderShouldReturnFalse(){
        $this->infoinstance->setData('additional_information', $this->additionalInformationIncorrect);

        $stub = $this->getMock('Vaimo_Klarna_Model_Klarna_Validate', array('getInfoInstance'));
        $stub->expects($this->any())
                ->method('getInfoInstance')
                ->will($this->returnValue($this->infoinstance));


        $expected = false;
        $this->assertEquals($expected, $stub->checkGender());
    }
}