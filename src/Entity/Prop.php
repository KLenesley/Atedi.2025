<?php

namespace App\Entity;

use App\Entity\Intervention;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\PropRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: PropRepository::class)]
#[UniqueEntity(fields: ['title'], message: 'Il existe déjà un matériel avec ce nom')]
#[ORM\Table(name: 'tbl_prop')]
class Prop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $title;

    
    #[ORM\ManyToMany(targetEntity: Intervention::class, mappedBy: 'props')]     
    private Collection $interventions;

    public function __construct()
    {
        $this->interventions = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }


    public function getInterventions(): Collection
    {
        return $this->interventions;
    }
    
    public function addIntervention(Intervention $intervention): self
    {
        if (!$this->interventions->contains($intervention)) {
            $this->interventions[] = $intervention;
            $intervention->addProp($this); // Ajoute le Prop à la liste des Prop de l'intervention
        }
    
        return $this;
    }
    
    public function removeIntervention(Intervention $intervention): self
    {
        if ($this->interventions->contains($intervention)) {
            $this->interventions->removeElement($intervention);
            $intervention->removeProp($this); // Retire le Prop de la liste des Prop de l'intervention
        }
    
        return $this;
    }
}
