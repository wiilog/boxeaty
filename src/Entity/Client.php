<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Utils\ActiveTrait;
use WiiCommon\Helper\Stream;

/**
 * @ORM\Entity(repositoryClass=ClientRepository::class)
 */
class Client {

    use ActiveTrait;

    public const BOXEATY = "BoxEaty";

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
     * @ORM\Column(type="string")
     */
    private ?string $latitude = null;

    /**
     * @ORM\Column(type="string")
     */
    private ?string $longitude = null;

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
    private Collection $locations;

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
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $mailNotificationOrderPreparation;

    /**
     * @ORM\OneToOne(targetEntity=ClientOrderInformation::class, inversedBy="client", cascade={"persist", "remove"})
     */
    private ?ClientOrderInformation $clientOrderInformation = null;

    /**
     * @ORM\OneToMany(targetEntity=CratePatternLine::class, mappedBy="client")
     */
    private Collection $cratePatternLines;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $prorateAmount;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $paymentModes;

    /**
     * @ORM\OneToMany(targetEntity=Collect::class, mappedBy="client")
     */
    private Collection $collects;

    public function __construct() {
        $this->users = new ArrayCollection();
        $this->locations = new ArrayCollection();
        $this->clients = new ArrayCollection();
        $this->boxes = new ArrayCollection();
        $this->depositTicketsClients = new ArrayCollection();
        $this->cratePatternLines = new ArrayCollection();
        $this->collects = new ArrayCollection();
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

    public function getLatitude(): ?string {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): self {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): self {
        $this->longitude = $longitude;
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
    public function getLocations(): Collection {
        return $this->locations;
    }

    public function addLocation(Location $kiosk): self {
        if (!$this->locations->contains($kiosk)) {
            $this->locations[] = $kiosk;
            $kiosk->setClient($this);
        }

        return $this;
    }

    public function removeLocation(Location $kiosk): self {
        if ($this->locations->removeElement($kiosk)) {
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
    public function getBoxes(): Collection {
        return $this->boxes;
    }

    public function addBox(Box $box): self {
        if (!$this->boxes->contains($box)) {
            $this->boxes[] = $box;
            $box->setOwner($this);
        }

        return $this;
    }

    public function removeBox(Box $box): self {
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
    public function getDepositTicketsClients(): Collection {
        return $this->depositTicketsClients;
    }

    public function addDepositTicketsClient(self $depositTicketsClient): self {
        if (!$this->depositTicketsClients->contains($depositTicketsClient)) {
            $this->depositTicketsClients[] = $depositTicketsClient;
        }

        return $this;
    }

    public function removeDepositTicketsClient(self $depositTicketsClient): self {
        $this->depositTicketsClients->removeElement($depositTicketsClient);

        return $this;
    }

    public function setDepositTicketClients($depositTicketsClients): self {
        $this->depositTicketsClients = new ArrayCollection($depositTicketsClients);
        return $this;
    }

    public function getOutLocation(): ?Location {
        return $this->outLocation;
    }

    public function setOutLocation(?Location $outLocation): self {
        $this->outLocation = $outLocation;

        return $this;
    }

    public function isMailNotificationOrderPreparation(): ?bool
    {
        return $this->mailNotificationOrderPreparation;
    }

    public function setMailNotificationOrderPreparation(?bool $mailNotificationOrderPreparation): self
    {
        $this->mailNotificationOrderPreparation = $mailNotificationOrderPreparation;

        return $this;
    }

    public function getClientOrderInformation(): ?ClientOrderInformation
    {
        if(!$this->clientOrderInformation) {
            $this->clientOrderInformation = new ClientOrderInformation();
        }

        return $this->clientOrderInformation;
    }

    public function setClientOrderInformation(?ClientOrderInformation $clientOrderInformation): self
    {
        $this->clientOrderInformation = $clientOrderInformation;

        return $this;
    }

    /**
     * @return Collection|CratePatternLine[]
     */
    public function getCratePatternLines(): Collection {
        return $this->cratePatternLines;
    }

    public function addCratePatternLine(CratePatternLine $cratePatternLine): self {
        if (!$this->cratePatternLines->contains($cratePatternLine)) {
            $this->cratePatternLines[] = $cratePatternLine;
            $cratePatternLine->setClient($this);
        }

        return $this;
    }

    public function removeCratePatternLine(CratePatternLine $cratePatternLine): self {
        if ($this->cratePatternLines->removeElement($cratePatternLine)) {
            if ($cratePatternLine->getClient() === $this) {
                $cratePatternLine->setClient(null);
            }
        }

        return $this;
    }

    public function setCratePatternLines(?array $cratePatternLines): self {
        foreach($this->getCratePatternLines()->toArray() as $cratePatternLine) {
            $this->removeCratePatternLine($cratePatternLine);
        }

        $this->cratePatternLines = new ArrayCollection();
        foreach($cratePatternLines as $cratePatternLine) {
            $this->addCratePatternLine($cratePatternLine);
        }

        return $this;
    }

    public function getProrateAmount(): ?int
    {
        return $this->prorateAmount;
    }

    public function setProrateAmount(?int $prorateAmount): self
    {
        $this->prorateAmount = $prorateAmount;

        return $this;
    }

    public function getPaymentModes(): ?string
    {
        return $this->paymentModes;
    }

    public function setPaymentModes(?string $paymentModes): self
    {
        $this->paymentModes = $paymentModes;

        return $this;
    }

    /**
     * @return float|null
     */
    public function getCratePatternAmount(): ?float
    {
        return Stream::from($this->getCratePatternLines())
            ->map(fn(CratePatternLine $cratePatternLine) => (
                $cratePatternLine->getQuantity()
                * (float) ($cratePatternLine->getCustomUnitPrice() ?: $cratePatternLine->getBoxType()->getPrice())
            ))
            ->sum();
    }

    /**
     * @return Collection|Collect[]
     */
    public function getCollects(): Collection {
        return $this->collects;
    }

    public function addCollect(Collect $collect): self {
        if (!$this->collects->contains($collect)) {
            $this->collects[] = $collect;
            $collect->setClient($this);
        }

        return $this;
    }

    public function removeCollect(Collect $collect): self {
        if ($this->collects->removeElement($collect)) {
            if ($collect->getClient() === $this) {
                $collect->setClient(null);
            }
        }

        return $this;
    }

    public function setCollects(?array $collects): self {
        foreach($this->getCollects()->toArray() as $collect) {
            $this->removeCollect($collect);
        }

        $this->collects = new ArrayCollection();
        foreach($collects as $collect) {
            $this->addCollect($collect);
        }

        return $this;
    }

}
