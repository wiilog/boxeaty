<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 */
class User implements UserInterface {

    use Active;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $username = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $email = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $password = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $creationDate = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $lastLogin = null;

    /**
     * @ORM\ManyToOne(targetEntity=Role::class, inversedBy="users")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Role $role = null;

    /**
     * @ORM\OneToMany(targetEntity=BoxRecord::class, mappedBy="user")
     */
    private Collection $boxRecords;

    /**
     * @ORM\ManyToMany(targetEntity=Client::class, inversedBy="users")
     */
    private Collection $clients;

    /**
     * @ORM\OneToMany(targetEntity=Client::class, mappedBy="contact")
     */
    private Collection $contactOf;

    /**
     * @ORM\ManyToMany(targetEntity=Group::class, inversedBy="users")
     */
    private Collection $groups;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $resetToken = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $resetTokenExpiration = null;

    /**
     * @ORM\OneToMany(targetEntity=DepositTicket::class, mappedBy="orderUser")
     */
    private Collection $orderDepositTickets;

    /**
     * @ORM\OneToMany(targetEntity=DeliveryRound::class, mappedBy="deliverer")
     */
    private Collection $deliveryRounds;

    /**
     * @ORM\OneToMany(targetEntity=OrderStatusHistory::class, mappedBy="user")
     */
    private Collection $orderStatusHistories;

    /**
     * @ORM\OneToMany(targetEntity=ClientOrder::class, mappedBy="requester")
     */
    private Collection $clientOrders;

    /**
     * @ORM\OneToMany(targetEntity=CounterOrder::class, mappedBy="user")
     */
    private Collection $counterOrders;

    public function __construct() {
        $this->clients = new ArrayCollection();
        $this->boxRecords = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->orderDepositTickets = new ArrayCollection();
        $this->deliveryRounds = new ArrayCollection();
        $this->orderStatusHistories = new ArrayCollection();
        $this->clientOrders = new ArrayCollection();
        $this->counterOrders = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getUsername(): ?string {
        return $this->username;
    }

    public function setUsername(string $username): self {
        $this->username = $username;

        return $this;
    }

    public function getUserIdentifier(): string {
        return $this->getEmail();
    }

    public function getEmail(): ?string {
        return $this->email;
    }

    public function setEmail(string $email): self {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string {
        return $this->password;
    }

    public function setPassword(string $password): self {
        $this->password = $password;

        return $this;
    }

    public function getCreationDate(): ?DateTime {
        return $this->creationDate;
    }

    public function setCreationDate(DateTime $creationDate): self {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getLastLogin(): ?DateTime {
        return $this->lastLogin;
    }

    public function setLastLogin(DateTime $lastLogin): self {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    public function getRole(): ?Role {
        return $this->role;
    }

    public function setRole(?Role $role): self {
        $this->role = $role;

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
            $boxRecord->setUser($this);
        }

        return $this;
    }

    public function removeBoxRecord(BoxRecord $boxRecord): self {
        if ($this->boxRecords->removeElement($boxRecord)) {
            // set the owning side to null (unless already changed)
            if ($boxRecord->getUser() === $this) {
                $boxRecord->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Client[]
     */
    public function getClients(): Collection {
        return $this->clients;
    }

    public function addClient(Client $client): self {
        if (!$this->clients->contains($client)) {
            $this->clients[] = $client;
        }

        return $this;
    }

    public function removeClient(Client $client): self {
        $this->clients->removeElement($client);

        return $this;
    }

    public function setClients(array $clients): self {
        if ($this->clients) {
            foreach ($this->clients as $client) {
                $client->removeUser($this);
            }
        }

        $this->clients = new ArrayCollection($clients);
        foreach ($this->clients as $client) {
            $client->addUser($this);
        }

        return $this;
    }

    /**
     * @return Collection|Client[]
     */
    public function getContactOf(): Collection {
        return $this->contactOf;
    }

    public function addContactOf(Client $contactOf): self {
        if (!$this->contactOf->contains($contactOf)) {
            $this->contactOf[] = $contactOf;
            $contactOf->setContact($this);
        }

        return $this;
    }

    public function removeContactOf(Client $contactOf): self {
        if ($this->contactOf->removeElement($contactOf)) {
            if ($contactOf->getContact() === $this) {
                $contactOf->setContact(null);
            }
        }

        return $this;
    }

    public function setContactOf(?array $contactOfs): self {
        foreach ($this->getContactOf()->toArray() as $contactOf) {
            $this->removeContactOf($contactOf);
        }

        $this->contactOf = new ArrayCollection();
        foreach ($contactOfs as $contactOf) {
            $this->addContactOf($contactOf);
        }

        return $this;
    }

    /**
     * @return Collection|Group[]
     */
    public function getGroups(): Collection {
        return $this->groups;
    }

    public function addGroup(Group $group): self {
        if (!$this->groups->contains($group)) {
            $this->groups[] = $group;
        }

        return $this;
    }

    public function removeGroup(Group $group): self {
        $this->groups->removeElement($group);

        return $this;
    }

    public function setGroups(array $groups): self {
        if ($this->groups) {
            foreach ($this->groups as $group) {
                $group->removeUser($this);
            }
        }

        $this->groups = new ArrayCollection($groups);
        foreach ($this->groups as $group) {
            $group->addUser($this);
        }

        return $this;
    }

    public function getRoles() {
        return [];
    }

    public function getSalt() {
        return null;
    }

    public function eraseCredentials() {

    }

    public function getResetToken(): ?string {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): self {
        $this->resetToken = $resetToken;

        return $this;
    }

    public function getResetTokenExpiration(): ?DateTime {
        return $this->resetTokenExpiration;
    }

    public function setResetTokenExpiration(?DateTime $resetTokenExpiration): self {
        $this->resetTokenExpiration = $resetTokenExpiration;

        return $this;
    }

    public function getOrderDepositTickets(): Collection {
        return $this->orderDepositTickets;
    }

    public function addOrderDepositTicket(DepositTicket $depositTicket): self {
        if (!$this->orderDepositTickets->contains($depositTicket)) {
            $this->orderDepositTickets[] = $depositTicket;
            $depositTicket->setOrderUser($this);
        }

        return $this;
    }

    public function removeOrderDepositTicket(DepositTicket $depositTicket): self {
        if ($this->orderDepositTickets->removeElement($depositTicket)) {
            // set the owning side to null (unless already changed)
            if ($depositTicket->getOrderUser() === $this) {
                $depositTicket->setOrderUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|DeliveryRound[]
     */
    public function getDeliveryRounds(): Collection {
        return $this->deliveryRounds;
    }

    public function addDeliveryRound(DeliveryRound $deliveryRound): self {
        if (!$this->deliveryRounds->contains($deliveryRound)) {
            $this->deliveryRounds[] = $deliveryRound;
            $deliveryRound->setDeliverer($this);
        }

        return $this;
    }

    public function removeDeliveryRound(DeliveryRound $deliveryRound): self {
        if ($this->deliveryRounds->removeElement($deliveryRound)) {
            // set the owning side to null (unless already changed)
            if ($deliveryRound->getDeliverer() === $this) {
                $deliveryRound->setDeliverer(null);
            }
        }

        return $this;
    }

    public function setDeliveryRounds(?array $deliveryRounds): self {
        foreach ($this->getDeliveryRounds()->toArray() as $deliveryRound) {
            $this->removeDeliveryRound($deliveryRound);
        }

        $this->deliveryRounds = new ArrayCollection();
        foreach ($deliveryRounds as $deliveryRound) {
            $this->addDeliveryRound($deliveryRound);
        }

        return $this;
    }

    /**
     * @return Collection|OrderStatusHistory[]
     */
    public function getOrderStatusHistories(): Collection {
        return $this->orderStatusHistories;
    }

    public function addOrderStatusHistory(OrderStatusHistory $orderStatusHistory): self {
        if (!$this->orderStatusHistories->contains($orderStatusHistory)) {
            $this->orderStatusHistories[] = $orderStatusHistory;
            $orderStatusHistory->setUser($this);
        }

        return $this;
    }

    public function removeOrderStatusHistory(OrderStatusHistory $orderStatusHistory): self {
        if ($this->orderStatusHistories->removeElement($orderStatusHistory)) {
            // set the owning side to null (unless already changed)
            if ($orderStatusHistory->getUser() === $this) {
                $orderStatusHistory->setUser(null);
            }
        }

        return $this;
    }

    public function setOrderStatusHistories(?array $orderStatusHistories): self {
        foreach ($this->getOrderStatusHistories()->toArray() as $orderStatusHistory) {
            $this->removeOrderStatusHistory($orderStatusHistory);
        }

        $this->orderStatusHistories = new ArrayCollection();
        foreach ($orderStatusHistories as $orderStatusHistory) {
            $this->addOrderStatusHistory($orderStatusHistory);
        }

        return $this;
    }

    /**
     * @return Collection|ClientOrder[]
     */
    public function getClientOrders(): ?ClientOrder {
        return $this->clientOrders;
    }

    public function addClientOrder(ClientOrder $order): self {
        if (!$this->clientOrders->contains($order)) {
            $this->clientOrders[] = $order;
            $order->setRequester($this);
        }

        return $this;
    }

    public function removeClientOrder(ClientOrder $order): self {
        if ($this->clientOrders->removeElement($order)) {
            if ($order->getRequester() === $this) {
                $order->setRequester(null);
            }
        }

        return $this;
    }

    public function setClientOrders(?array $clientOrders): self {
        foreach ($this->getClientOrders()->toArray() as $order) {
            $this->removeClientOrder($order);
        }

        $this->clientOrders = new ArrayCollection();
        foreach ($clientOrders as $order) {
            $this->addClientOrder($order);
        }

        return $this;
    }

    /**
     * @return Collection|CounterOrder[]
     */
    public function getCounterOrders(): ?CounterOrder {
        return $this->counterOrders;
    }

    public function addCounterOrder(CounterOrder $order): self {
        if (!$this->counterOrders->contains($order)) {
            $this->counterOrders[] = $order;
            $order->setUser($this);
        }

        return $this;
    }

    public function removeCounterOrder(CounterOrder $order): self {
        if ($this->counterOrders->removeElement($order)) {
            if ($order->getUser() === $this) {
                $order->setUser(null);
            }
        }

        return $this;
    }

    public function setCounterOrders(?array $counterOrders): self {
        foreach ($this->getCounterOrders()->toArray() as $order) {
            $this->removeCounterOrder($order);
        }

        $this->counterOrders = new ArrayCollection();
        foreach ($counterOrders as $order) {
            $this->addCounterOrder($order);
        }

        return $this;
    }

}
