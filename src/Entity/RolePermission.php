<?php

namespace App\Entity;

use App\Repository\RolePermissionRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: RolePermissionRepository::class)]
#[ORM\Table(name: '`role_permissions`')]
class RolePermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getRolePermissions"])]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    #[Groups(["getRolePermissions"])]
    private ?string $role = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getRolePermissions"])]
    private ?string $controller = null;

    #[ORM\Column(length: 50)]
    #[Groups(["getRolePermissions"])]
    private ?string $action = null;

    #[ORM\Column(options: ["default" => false])]
    #[Groups(["getRolePermissions"])]
    private ?bool $isAuthorized = null;

    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'rolePermissions')]
    #[ORM\JoinColumn(name: 'role', referencedColumnName: 'id', nullable: false)]
    private ?Role $roleDetails = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getController(): ?string
    {
        return $this->controller;
    }

    public function setController(string $controller): static
    {
        $this->controller = $controller;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getIsAuthorized(): ?bool
    {
        return $this->isAuthorized;
    }

    public function setIsAuthorized(bool $isAuthorized): static
    {
        $this->isAuthorized = $isAuthorized;

        return $this;
    }

    public function getRoleDetails(): ?Role
    {
        return $this->roleDetails;
    }

    public function setRoleDetails(Role $roleDetails): static
    {
        $this->roleDetails = $roleDetails;
        $this->role = $roleDetails->getId();

        return $this;
    }
}
