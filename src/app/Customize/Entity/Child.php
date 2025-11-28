<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Customize\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\AbstractEntity;
use Eccube\Entity\Customer;
use Eccube\Entity\Shipping;

/**
 * Child
 *
 * @ORM\Table(name="dtb_child")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discriminator_type", type="string", length=255)
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="Customize\Repository\ChildRepository")
 */
class Child extends AbstractEntity
{

    /*/1** */
    /* * Set from customer. */
    /* * */
    /* * @param \Eccube\Entity\Customer $Customer */
    /* * */
    /* * @return \Eccube\Entity\Child */
    /* *1/ */
    /*public function setFromCustomer(Customer $Customer) */
    /*{ */
    /*    $this */
    /*        ->setCustomer($Customer) */
    /*        ->setName01($Customer->getName01()) */
    /*        ->setName02($Customer->getName02()) */
    /*        ->setKana01($Customer->getKana01()) */
    /*        ->setKana02($Customer->getKana02()) */
    /*        ->setCompanyName($Customer->getCompanyName()) */
    /*        ->setPhoneNumber($Customer->getPhoneNumber()) */
    /*        ->setPostalCode($Customer->getPostalCode()) */
    /*        ->setPref($Customer->getPref()) */
    /*        ->setAddr01($Customer->getAddr01()) */
    /*        ->setAddr02($Customer->getAddr02()); */

    /*    return $this; */
    /*} */

    /*/1** */
    /* * Set from Shipping. */
    /* * */
    /* * @param \Eccube\Entity\Shipping $Shipping */
    /* * */
    /* * @return \Eccube\Entity\Child */
    /* *1/ */
    /*public function setFromShipping(Shipping $Shipping) */
    /*{ */
    /*    $this */
    /*        ->setName01($Shipping->getName01()) */
    /*        ->setName02($Shipping->getName02()) */
    /*        ->setKana01($Shipping->getKana01()) */
    /*        ->setKana02($Shipping->getKana02()) */
    /*        ->setCompanyName($Shipping->getCompanyName()) */
    /*        ->setPhoneNumber($Shipping->getPhoneNumber()) */
    /*        ->setPostalCode($Shipping->getPostalCode()) */
    /*        ->setPref($Shipping->getPref()) */
    /*        ->setAddr01($Shipping->getAddr01()) */
    /*        ->setAddr02($Shipping->getAddr02()); */

    /*    return $this; */
    /*} */

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string|null
     *
     * @ORM\Column(name="name01", type="string", length=255)
     */
    private $name01;

    /**
     * @var string|null
     *
     * @ORM\Column(name="name02", type="string", length=255)
     */
    private $name02;

    /**
     * @var string|null
     *
     * @ORM\Column(name="kana01", type="string", length=255, nullable=true)
     */
    private $kana01;

    /**
     * @var string|null
     *
     * @ORM\Column(name="kana02", type="string", length=255, nullable=true)
     */
    private $kana02;


    /**
     * @var \Customize\Entity\School
     *
     * @ORM\ManyToOne(targetEntity="Customize\Entity\School")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="school_id", referencedColumnName="id", nullable=false)
     * })
     */
    private $School;

    /**
     * @var string|null
     *
     * @ORM\Column(name="examinee_number", type="string", length=255, nullable=false)
     */
    private $examinee_number;

    /**
     * @var int
     *
     * @ORM\Column(name="enrollment_year", type="integer", nullable=false)
     */
    private $enrollment_year;

    /**
     * @var \Eccube\Entity\Customer
     *
     * @ORM\ManyToOne(targetEntity="Eccube\Entity\Customer", inversedBy="Childes")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="customer_id", referencedColumnName="id")
     * })
     */
    private $Customer;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="create_date", type="datetimetz")
     */
    private $create_date;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="update_date", type="datetimetz")
     */
    private $update_date;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name01.
     *
     * @param string|null $name01
     *
     * @return Child
     */
    public function setName01($name01 = null)
    {
        $this->name01 = $name01;

        return $this;
    }

    /**
     * Get name01.
     *
     * @return string|null
     */
    public function getName01()
    {
        return $this->name01;
    }

    /**
     * Set name02.
     *
     * @param string|null $name02
     *
     * @return Child
     */
    public function setName02($name02 = null)
    {
        $this->name02 = $name02;

        return $this;
    }

    /**
     * Get name02.
     *
     * @return string|null
     */
    public function getName02()
    {
        return $this->name02;
    }

    /**
     * Set kana01.
     *
     * @param string|null $kana01
     *
     * @return Child
     */
    public function setKana01($kana01 = null)
    {
        $this->kana01 = $kana01;

        return $this;
    }

    /**
     * Get kana01.
     *
     * @return string|null
     */
    public function getKana01()
    {
        return $this->kana01;
    }

    /**
     * Set kana02.
     *
     * @param string|null $kana02
     *
     * @return Child
     */
    public function setKana02($kana02 = null)
    {
        $this->kana02 = $kana02;

        return $this;
    }

    /**
     * Get kana02.
     *
     * @return string|null
     */
    public function getKana02()
    {
        return $this->kana02;
    }

    /**
     * Set examinee_number.
     *
     * @param string|null $examinee_number
     *
     * @return Child
     */
    public function setExamineeNumber($examinee_number = null)
    {
        $this->examinee_number = $examinee_number;

        return $this;
    }

    /**
     * Get examinee_number.
     *
     * @return string|null
     */
    public function getExamineeNumber()
    {
        return $this->examinee_number;
    }

    /**
     * Set enrollment_year.
     *
     * @param int|null $enrollment_year
     *
     * @return Child
     */
    public function setEnrollmentYear($enrollment_year = null)
    {
        $this->enrollment_year = $enrollment_year;

        return $this;
    }

    /**
     * Get enrollment_year.
     *
     * @return string|null
     */
    public function getEnrollmentYear()
    {
        return $this->enrollment_year;
    }

    /**
     * Set createDate.
     *
     * @param \DateTime $createDate
     *
     * @return Child
     */
    public function setCreateDate($createDate)
    {
        $this->create_date = $createDate;

        return $this;
    }

    /**
     * Get createDate.
     *
     * @return \DateTime
     */
    public function getCreateDate()
    {
        return $this->create_date;
    }

    /**
     * Set updateDate.
     *
     * @param \DateTime $updateDate
     *
     * @return Child
     */
    public function setUpdateDate($updateDate)
    {
        $this->update_date = $updateDate;

        return $this;
    }

    /**
     * Get updateDate.
     *
     * @return \DateTime
     */
    public function getUpdateDate()
    {
        return $this->update_date;
    }

    /**
     * Set customer.
     *
     * @param \Eccube\Entity\Customer|null $customer
     *
     * @return Child
     */
    public function setCustomer(Customer $customer = null)
    {
        $this->Customer = $customer;

        return $this;
    }

    /**
     * Get customer.
     *
     * @return \Eccube\Entity\Customer|null
     */
    public function getCustomer()
    {
        return $this->Customer;
    }

    /**
     * Set school.
     *
     * @param \Customize\Entity\School|null $school
     *
     * @return Child
     */
    public function setSchool(\Customize\Entity\School $school = null)
    {
        $this->School = $school;

        return $this;
    }

    /**
     * Get school.
     *
     * @return \Customize\Entity\School|null
     */
    public function getSchool()
    {
        return $this->School;
    }

    /**
     * Get Name for CSV.
     *
     * @return string|null
     */
    public function getCsvName()
    {
        return $this->name01.' '.$this->name02.'('.$this->examinee_number.')';
    }
}
