<?php

namespace App\Entity;

use App\Entity\Task;
use App\Entity\User;
use App\Entity\Prop;
use App\Entity\Client;
use DateTimeInterface;
use App\Entity\Equipment;
use App\Entity\BillingLine;
use Doctrine\DBAL\Types\Types;
use App\Entity\OperatingSystem;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\InterventionReport;
use App\Repository\InterventionRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: InterventionRepository::class)]
#[ORM\Table(name: "tbl_intervention")]
class Intervention
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "datetime")]
    private DateTimeInterface $deposit_date;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $return_date = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $comment = null;

    #[ORM\ManyToOne(targetEntity: OperatingSystem::class, inversedBy: "interventions")]
    #[ORM\JoinColumn(nullable: false)]
    private ?OperatingSystem $operating_system = null;

    #[ORM\ManyToOne(targetEntity: Equipment::class, inversedBy: "interventions")]
    #[ORM\JoinColumn(nullable: false)]
    private ?Equipment $equipment = null;

    #[ORM\ManyToMany(targetEntity: Prop::class, inversedBy: "interventions")]
    #[ORM\JoinTable(name: "intervention_prop")]
    private Collection $props;

    #[ORM\ManyToMany(targetEntity: Task::class, inversedBy: "interventions")]
    private Collection $tasks;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: "interventions")]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\Column(type: "string", length: 255)]
    private string $status;

    #[ORM\OneToOne(targetEntity: InterventionReport::class, inversedBy: "intervention", cascade: ["persist", "remove"])]
    #[ORM\JoinColumn(nullable: false)]
    private InterventionReport $intervention_report;

    #[ORM\Column(type: "string", length: 255)]
    private string $total_price;

    #[ORM\OneToMany(targetEntity: BillingLine::class, mappedBy: "intervention")]
    private Collection $billing_lines;

    #[ORM\ManyToOne(inversedBy: 'intervention')]
    private ?User $user = null;

    // #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "interventions")]
    // #[ORM\JoinColumn(nullable: true)]
    // private ?User $user = null;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->props = new ArrayCollection();
        $this->setDepositDate(new \DateTime());
        $this->setStatus('En attente');
        $this->billing_lines = new ArrayCollection();
    }    

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDepositDate(): \DateTimeInterface
    {
        return $this->deposit_date;
    }

    public function setDepositDate(\DateTimeInterface $deposit_date): self
    {
        $this->deposit_date = $deposit_date;
        return $this;
    }

    public function getReturnDate(): ?\DateTimeInterface
    {
        return $this->return_date;
    }

    public function setReturnDate(?\DateTimeInterface $return_date): self
    {
        $this->return_date = $return_date;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getOperatingSystem(): ?OperatingSystem
    {
        return $this->operating_system;
    }

    public function setOperatingSystem(?OperatingSystem $operating_system): self
    {
        $this->operating_system = $operating_system;
        return $this;
    }

    public function getEquipment(): ?Equipment
    {
        return $this->equipment;
    }

    public function setEquipment(?Equipment $equipment): self
    {
        $this->equipment = $equipment;
        return $this;
    }

    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): self
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks[] = $task;
        }
        return $this;
    }

    public function removeTask(Task $task): self
    {
        if ($this->tasks->contains($task)) {
            $this->tasks->removeElement($task);
        }
        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getInterventionReport(): InterventionReport
    {
        return $this->intervention_report;
    }

    public function setInterventionReport(InterventionReport $intervention_report): self
    {
        $this->intervention_report = $intervention_report;
        return $this;
    }

    public function getTotalPrice(): string
    {
        return $this->total_price;
    }

    public function setTotalPrice(string $total_price): self
    {
        $this->total_price = $total_price;
        return $this;
    }

    public function getBillingLines(): Collection
    {
        return $this->billing_lines;
    }

    public function addBillingLine(BillingLine $billingLine): self
    {
        if (!$this->billing_lines->contains($billingLine)) {
            $this->billing_lines[] = $billingLine;
            $billingLine->setIntervention($this);
        }
        return $this;
    }

    public function removeBillingLine(BillingLine $billingLine): self
    {
        if ($this->billing_lines->contains($billingLine)) {
            $this->billing_lines->removeElement($billingLine);
            if ($billingLine->getIntervention() === $this) {
                $billingLine->setIntervention(null);
            }
        }
        return $this;
    }

    // public function getUser(): ?User
    // {
    //     return $this->user;
    // }

    // public function setUser(?User $user): self
    // {
    //     $this->user = $user;
    //     return $this;
    // }


    public function getProps(): Collection
    {
        return $this->props;
    }

    public function addProp(Prop $prop): self
    {
        if (!$this->props->contains($prop)) {
            $this->props[] = $prop;
        }
        return $this;
    }
    
    public function removeProp(Prop $prop): self
    {
        if ($this->props->contains($prop)) {
            $this->props->removeElement($prop);
        }
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }
}