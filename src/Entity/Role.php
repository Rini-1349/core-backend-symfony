<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use JMS\Serializer\Annotation\Groups;

#[ORM\Table(name: '`roles`')]
#[UniqueEntity(
    fields: ['id'],
    message: 'Un rôle existe déjà avec ce nom.',
)]
#[ORM\Entity(repositoryClass: RoleRepository::class)]
class Role
{
    #[ORM\Id]
    #[ORM\Column(length: 20)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[Assert\NotBlank(message: "L'identifiant du rôle est obligatoire.")]
    #[Assert\Length(
        max: 20,
        maxMessage: "L'identifiant du rôle ne doit pas dépasser 20 caractères."
    )]
    #[Groups(["getRole", "getRolePermissions"])]
    private ?string $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\Length(
        max: 255,
        maxMessage: "L'adresse email ne doit pas comporter plus de 255 caractères.",
    )]
    #[Groups(["getRole", "getRoleDescription", "getRolePermissions"])]
    private ?string $description = null;

    #[ORM\OneToMany(targetEntity: RolePermission::class, mappedBy: 'roleDetails')]
    #[Groups(["getRolePermissions"])]
    private Collection $rolePermissions;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

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

    public function getRolePermissions(): Collection
    {
        return $this->rolePermissions;
    }
}
