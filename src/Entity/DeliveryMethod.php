<?php

namespace App\Entity;

use App\Repository\DeliveryMethodRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DeliveryMethodRepository::class)
 */
class DeliveryMethod {

    public const BIKE = 0;
    public const LIGHT_TRUCK = 1;
    public const HEAVY_TRUCK = 2;

    public const TRANSPORT_TYPES = [
        self::BIKE => "bike",
        self::LIGHT_TRUCK => "light-truck",
        self::HEAVY_TRUCK => "heavy-truck",
    ];
    const DEFAULT_DATATABLE_ORDER = [["name", "asc"]] ;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $icon = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $deleted = false;

    /**
     * @ORM\OneToMany(targetEntity=ClientOrderInformation::class, mappedBy="deliveryMethod")
     */
    private $clientOrderInformation;

    public function __construct()
    {
        $this->clientOrderInformation = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;

        return $this;
    }

    public function getIcon(): ?string {
        return $this->icon;
    }

    public function setIcon(string $icon): self {
        $this->icon = $icon;

        return $this;
    }

    public function getDeleted(): ?bool {
        return $this->deleted;
    }

    public function setDeleted(?bool $deleted): self {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * @return Collection|ClientOrderInformation[]
     */
    public function getClientOrderInformation(): Collection
    {
        return $this->clientOrderInformation;
    }

    public function addClientOrderInformation(ClientOrderInformation $clientOrderInformation): self
    {
        if (!$this->clientOrderInformation->contains($clientOrderInformation)) {
            $this->clientOrderInformation[] = $clientOrderInformation;
            $clientOrderInformation->setDeliveryMethod($this);
        }

        return $this;
    }

    public function removeClientOrderInformation(ClientOrderInformation $clientOrderInformation): self
    {
        if ($this->clientOrderInformation->removeElement($clientOrderInformation)) {
            // set the owning side to null (unless already changed)
            if ($clientOrderInformation->getDeliveryMethod() === $this) {
                $clientOrderInformation->setDeliveryMethod(null);
            }
        }

        return $this;
    }

}
