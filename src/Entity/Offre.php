<?php

namespace App\Entity;

use App\Entity\Enum\TypeOffre;
use App\Entity\Entreprise;
use App\Entity\Candidature;
use App\Repository\OffreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OffreRepository::class)]
class Offre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire')]
    private ?string $titre = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Expression(
        "value !== null || this.getTypeOffre() !== 'stage_ete' && this.getTypeOffre() !== 'stage_pfe'",
        message: 'La durée est obligatoire pour les stages'
    )]
    #[Assert\Range(
        min: 1,
        max: 12,
        notInRangeMessage: 'La durée doit être comprise entre {{ min }} et {{ max }} mois',
        groups: ['stage_duration']
    )]
    private ?int $duree = null;

    #[ORM\Column(type: 'string', enumType: TypeOffre::class)]
    #[Assert\NotBlank(message: 'Le type d\'offre est obligatoire')]
    private ?TypeOffre $typeOffre = null;

    #[ORM\ManyToOne(inversedBy: 'offres')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Entreprise $entreprise = null;

    #[ORM\OneToMany(mappedBy: 'offre', targetEntity: Candidature::class, orphanRemoval: true)]
    private Collection $candidatures;

    public function __construct()
    {
        $this->candidatures = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDuree(): ?string
    {
        return $this->duree;
    }

    public function setDuree(string $duree): static
    {
        $this->duree = $duree;

        return $this;
    }

    public function getTypeOffre(): ?TypeOffre
    {
        return $this->typeOffre;
    }

    public function setTypeOffre(TypeOffre $typeOffre): static
    {
        $this->typeOffre = $typeOffre;

        return $this;
    }

    public function getEntreprise(): ?Entreprise
    {
        return $this->entreprise;
    }

    public function setEntreprise(?Entreprise $entreprise): static
    {
        $this->entreprise = $entreprise;

        return $this;
    }

    /**
     * @return Collection<int, Candidature>
     */
    public function getCandidatures(): Collection
    {
        return $this->candidatures;
    }

    public function addCandidature(Candidature $candidature): static
    {
        if (!$this->candidatures->contains($candidature)) {
            $this->candidatures->add($candidature);
            $candidature->setOffre($this);
        }

        return $this;
    }

    public function removeCandidature(Candidature $candidature): static
    {
        if ($this->candidatures->removeElement($candidature)) {
            // set the owning side to null (unless already changed)
            if ($candidature->getOffre() === $this) {
                $candidature->setOffre(null);
            }
        }

        return $this;
    }
}
