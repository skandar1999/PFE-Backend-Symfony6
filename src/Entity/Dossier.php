<?php
namespace App\Entity;

use App\Repository\DossierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DossierRepository::class)]
class Dossier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToMany(targetEntity: File::class)]
    #[ORM\JoinTable(name: "dossier_files")]
    private Collection $files;
    
    #[ORM\Column(length: 255)]
    private ?string $namedossier = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $datedossier = null;


    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $status = true;
    
    #[ORM\Column]
    private ?bool $versionning = null;

    public function __construct()
    {
        $this->files = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
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

    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(File $file): self
    {
        if (!$this->files->contains($file)) {
            $this->files[] = $file;
        }
        
        return $this;
    }

    public function removeFile(File $file): self
    {
        $this->files->removeElement($file);

        return $this;
    }

    public function getNamedossier(): ?string
    {
        return $this->namedossier;
    }

    public function setNamedossier(string $namedossier): self
    {
        $this->namedossier = $namedossier;

        return $this;
    }

    public function getDatedossier(): ?\DateTimeInterface
    {
        return $this->datedossier;
    }

    public function setDatedossier(\DateTimeInterface $datedossier): self
    {
        $this->datedossier = $datedossier;

        return $this;
    }

    public function isVersionning(): ?bool
    {
        return $this->versionning;
    }

    public function setVersionning(bool $versionning): self
    {
        $this->versionning = $versionning;

        return $this;
    }

    public function getVersionning(): bool
    {
        return $this->versionning;
    }

    
    public function getStatus(): bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): self
    {
        $this->status = $status;

        return $this;
    }
   
}

