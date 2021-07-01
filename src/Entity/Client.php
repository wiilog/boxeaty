<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ClientRepository::class)
 */
class Client {

    use Active;

    public const INACTIVE = 0;
    public const ACTIVE = 1;

    public const DEFAULT_TICKET_VALIDITY = 1;

    public const NAMES = [
        self::ACTIVE => 'actif',
        self::INACTIVE => 'inactif'
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="text")
     */
    private ?string $address = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $phoneNumber = null;

    /**
     * @ORM\ManyToOne(targetEntity=Group::class, inversedBy="clients")
     * @ORM\JoinColumn(name="`group`", nullable=false)
     */
    private ?Group $group = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="contactOf")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?User $contact = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $isMultiSite = null;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="clients")
     */
    private ?Client $linkedMultiSite = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $depositTicketValidity = null;

    /**
     * @ORM\OneToOne(targetEntity=Location::class)
     */
    private ?Location $outLocation = null;

    /**
     * @ORM\ManyToMany(targetEntity=User::class, mappedBy="clients")
     */
    private Collection $users;

    /**
     * @ORM\OneToMany(targetEntity=Location::class, mappedBy="client")
     */
    private Collection $kiosks;

    /**
     * @ORM\OneToMany(targetEntity=Client::class, mappedBy="linkedMultiSite")
     */
    private Collection $clients;

    /**
     * @ORM\OneToMany(targetEntity=Box::class, mappedBy="owner")
     */
    private Collection $boxes;

    /**
     * @ORM\OneToMany(targetEntity=BoxRecord::class, mappedBy="client")
     */
    private Collection $boxRecords;

    /**
     * @ORM\ManyToMany(targetEntity=Client::class)
     * @ORM\JoinTable(name="deposit_tickets_clients")
     */
    private Collection $depositTicketsClients;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $paymentMode;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $deliveryMode;

    /**
     * @ORM\OneToMany(targetEntity=Depository::class, mappedBy="client")
     */
    private Collection $depositories;

    /**
     * @ORM\Column(type="float")
     */
    private ?float $distance;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $tokens;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $closedParcOrder;

    /**
     * @ORM\Column(type="float")
     */
    private ?float $servicePrice;

    /**
     * @ORM\Column(type="float")
     */
    private ?float $deliveryPriceWorkingDay;

    /**
     * @ORM\Column(type="float")
     */
    private ?float $deliveryPriceOffDay;

    /**
     * @ORM\ManyToMany(targetEntity=OrderType::class, inversedBy="clients")
     */
    private Collection $orderType;

    public function __construct() {
        $this->users = new ArrayCollection();
        $this->kiosks = new ArrayCollection();
        $this->clients = new ArrayCollection();
        $this->boxes = new ArrayCollection();
        $this->depositTicketsClients = new ArrayCollection();
        $this->depositories = new ArrayCollection();
        $this->orderType = new ArrayCollection();
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

    public function getAddress(): ?string {
        return $this->address;
    }

    public function setAddress(string $address): self {
        $this->address = $address;

        return $this;
    }

    public function getGroup(): ?Group {
        return $this->group;
    }

    public function setGroup(?Group $group): self {
        $this->group = $group;

        return $this;
    }

    public function getPhoneNumber(): ?string {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): self {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getContact(): ?User {
        return $this->contact;
    }

    public function setContact(?User $contact): self {
        $this->contact = $contact;

        return $this;
    }

    public function isMultiSite(): ?bool {
        return $this->isMultiSite;
    }

    public function setIsMultiSite(bool $isMultiSite): self {
        $this->isMultiSite = $isMultiSite;

        return $this;
    }

    public function getLinkedMultiSite(): ?self {
        return $this->linkedMultiSite;
    }

    public function setLinkedMultiSite(?self $linkedMultiSite): self {
        $this->linkedMultiSite = $linkedMultiSite;

        return $this;
    }

    public function getDepositTicketValidity(): ?int {
        return $this->depositTicketValidity ?? self::DEFAULT_TICKET_VALIDITY;
    }

    public function setDepositTicketValidity(?int $depositTicketValidity): self {
        $this->depositTicketValidity = $depositTicketValidity;
        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getUsers(): Collection {
        return $this->users;
    }

    public function addUser(User $user): self {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->addClient($this);
        }

        return $this;
    }

    public function removeUser(User $user): self {
        if ($this->users->removeElement($user)) {
            $user->removeClient($this);
        }

        return $this;
    }

    /**
     * @return Collection|Location[]
     */
    public function getKiosks(): Collection {
        return $this->kiosks;
    }

    public function addKiosk(Location $kiosk): self {
        if (!$this->kiosks->contains($kiosk)) {
            $this->kiosks[] = $kiosk;
            $kiosk->setClient($this);
        }

        return $this;
    }

    public function removeKiosk(Location $kiosk): self {
        if ($this->kiosks->removeElement($kiosk)) {
            // set the owning side to null (unless already changed)
            if ($kiosk->getClient() === $this) {
                $kiosk->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|self[]
     */
    public function getClients(): Collection {
        return $this->clients;
    }

    public function addClient(self $client): self {
        if (!$this->clients->contains($client)) {
            $this->clients[] = $client;
            $client->setLinkedMultiSite($this);
        }

        return $this;
    }

    public function removeClient(self $client): self {
        if ($this->clients->removeElement($client)) {
            // set the owning side to null (unless already changed)
            if ($client->getLinkedMultiSite() === $this) {
                $client->setLinkedMultiSite(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Box[]
     */
    public function getBoxes(): Collection
    {
        return $this->boxes;
    }

    public function addBox(Box $box): self
    {
        if (!$this->boxes->contains($box)) {
            $this->boxes[] = $box;
            $box->setOwner($this);
        }

        return $this;
    }

    public function removeBox(Box $box): self
    {
        if ($this->boxes->removeElement($box)) {
            // set the owning side to null (unless already changed)
            if ($box->getOwner() === $this) {
                $box->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|BoxRecord[]
     */
    public function getBoxRecords(): Collection {
        return $this->boxRecords;
    }

    public function addBoxRecord(BoxRecord $boxRecord): self {
        if (!$this->boxRecords->contains($boxRecord)) {
            $this->boxRecords[] = $boxRecord;
            $boxRecord->setClient($this);
        }

        return $this;
    }

    public function removeBoxRecord(BoxRecord $boxRecord): self {
        if ($this->boxRecords->removeElement($boxRecord)) {
            // set the owning side to null (unless already changed)
            if ($boxRecord->getClient() === $this) {
                $boxRecord->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|self[]
     */
    public function getDepositTicketsClients(): Collection
    {
        return $this->depositTicketsClients;
    }

    public function addDepositTicketsClient(self $depositTicketsClient): self
    {
        if (!$this->depositTicketsClients->contains($depositTicketsClient)) {
            $this->depositTicketsClients[] = $depositTicketsClient;
        }

        return $this;
    }

    public function removeDepositTicketsClient(self $depositTicketsClient): self
    {
        $this->depositTicketsClients->removeElement($depositTicketsClient);

        return $this;
    }

    public function setDepositTicketClients($depositTicketsClients): self
    {
        $this->depositTicketsClients = new ArrayCollection($depositTicketsClients);
        return $this;
    }

    public function getOutLocation(): ?Location
    {
        return $this->outLocation;
    }

    public function setOutLocation(?Location $outLocation): self
    {
        $this->outLocation = $outLocation;

        return $this;
    }

    public function getPaymentMode(): ?string
    {
        return $this->paymentMode;
    }

    public function setPaymentMode(string $paymentMode): self
    {
        $this->paymentMode = $paymentMode;

        return $this;
    }

    public function getDeliveryMode(): ?int
    {
        return $this->deliveryMode;
    }

    public function setDeliveryMode(int $deliveryMode): self
    {
        $this->deliveryMode = $deliveryMode;

        return $this;
    }

    /**
     * @return Collection|Depository[]
     */
    public function getDepositories(): Collection
    {
        return $this->depositories;
    }

    public function addDepository(Depository $depository): self
    {
        if (!$this->depositories->contains($depository)) {
            $this->depositories[] = $depository;
            $depository->setClient($this);
        }

        return $this;
    }

    public function removeDepository(Depository $depository): self
    {
        if ($this->depositories->removeElement($depository)) {
            // set the owning side to null (unless already changed)
            if ($depository->getClient() === $this) {
                $depository->setClient(null);
            }
        }

        return $this;
    }

    public function setDepositories(?array $depositories): self {
        foreach($this->getDepositories()->toArray() as $depositorie) {
            $this->removeDepository($depositorie);
        }

        $this->depositories = new ArrayCollection();
        foreach($depositories as $depositorie) {
            $this->addDepository($depositorie);
        }

        return $this;
    }

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function setDistance(float $distance): self
    {
        $this->distance = $distance;

        return $this;
    }

    public function getTokens(): ?int
    {
        return $this->tokens;
    }

    public function setTokens(int $tokens): self
    {
        $this->tokens = $tokens;

        return $this;
    }

    public function getClosedParcOrder(): ?bool
    {
        return $this->closedParcOrder;
    }

    public function setClosedParcOrder(bool $closedParcOrder): self
    {
        $this->closedParcOrder = $closedParcOrder;

        return $this;
    }

    public function getServicePrice(): ?float
    {
        return $this->servicePrice;
    }

    public function setServicePrice(float $servicePrice): self
    {
        $this->servicePrice = $servicePrice;

        return $this;
    }

    public function getDeliveryPriceWorkingDay(): ?float
    {
        return $this->deliveryPriceWorkingDay;
    }

    public function setDeliveryPriceWorkingDay(float $deliveryPriceWorkingDay): self
    {
        $this->deliveryPriceWorkingDay = $deliveryPriceWorkingDay;

        return $this;
    }

    public function getDeliveryPriceOffDay(): ?float
    {
        return $this->deliveryPriceOffDay;
    }

    public function setDeliveryPriceOffDay(float $deliveryPriceOffDay): self
    {
        $this->deliveryPriceOffDay = $deliveryPriceOffDay;

        return $this;
    }

    /**
     * @return Collection|OrderType[]
     */
    public function getOrderType(): Collection
    {
        return $this->orderType;
    }

    public function addOrderType(OrderType $orderType): self
    {
        if (!$this->orderType->contains($orderType)) {
            $this->orderType[] = $orderType;
            $orderType->addClient($this);
        }

        return $this;
    }

    public function removeOrderType(OrderType $orderType): self
    {
        if($this->orderType->removeElement($orderType)){
            $orderType->removeClient($this);
        }

        return $this;
    }

    public function setOrderType(?array $orderType): self {
        foreach($this->getOrderType()->toArray() as $orderTyp) {
            $this->removeOrderType($orderTyp);
        }

        $this->orderType = new ArrayCollection();
        foreach($orderType as $orderTyp) {
            $this->addOrderType($orderTyp);
        }

        return $this;
    }

}
