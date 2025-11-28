<?php
namespace Customize\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation as Eccube;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Eccube\Entity\Customer;
use Customize\Entity\Child;

/**
 * @Eccube\EntityExtension("Eccube\Entity\Customer")
 */

trait CustomerTrait
{

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="Customize\Entity\Child", mappedBy="Customer", cascade={"remove"})
     * @ORM\OrderBy({
     *     "id"="ASC"
     * })
     */
    private $Children;

    /**
     * Add child.
     *
     * @param \Customize\Entity\Child $child
     *
     * @return Customer
     */
    public function addChild(Child $child)
    {
        if (!$this->Children) {
            $this->CustomerAddresses = new \Doctrine\Common\Collections\ArrayCollection();
        }
        $this->Children[] = $child;

        return $this;
    }

    /**
     * Remove customerAddress.
     *
     * @param \Customize\Entity\Child $child
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeChild(Child $child)
    {
        if (!$this->Children) {
            $this->CustomerAddresses = new \Doctrine\Common\Collections\ArrayCollection();
        }
        return $this->Children->removeElement($child);
    }

    /**
     * Get children.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getChildren()
    {
        if (!$this->Children) {
            $this->CustomerAddresses = new \Doctrine\Common\Collections\ArrayCollection();
        }
        return $this->Children;
    }
}
