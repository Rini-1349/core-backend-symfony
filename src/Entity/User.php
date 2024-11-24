<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Hateoas\Configuration\Annotation as Hateoas;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`users`')]
#[UniqueEntity(
    fields: ['email'],
    message: 'Un compte existe déjà avec cette adresse email.',
)]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getUser"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getUser", "simpleUser", "email"])]
    #[Assert\NotNull(message: "Vous devez renseigner une adresse email.")]
    #[Assert\Email( message: "\"{{ value }}\" n'est pas une adresse email valide.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "L'adresse email ne doit pas comporter plus de 255 caractères.",
    )]
    private ?string $email = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(length: 255)]
    #[Groups(["password"])]
    #[Assert\NotNull(message: "Vous devez renseigner un mot de passe.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le mot de passe ne doit pas comporter plus de 255 caractères.",
    )]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getUser", "simpleUser"])]
    #[Assert\NotNull(message: "Vous devez renseigner un nom.")]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: "Votre nom doit comporter au moins un caractère.",
        maxMessage: "Votre nom ne doit pas comporter plus de 255 caractères.",
    )]
    private ?string $lastname = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getUser", "simpleUser"])]
    #[Assert\NotNull(message: "Vous devez renseigner un prénom.")]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: "Votre prénom doit comporter au moins un caractère.",
        maxMessage: "Votre prénom ne doit pas comporter plus de 255 caractères.",
    )]
    private ?string $firstname = null;

    #[ORM\Column]
    #[Groups(["getUser", "simpleUser"])]
    private ?bool $isVerified = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(["getUser", "simpleUser"])]
    #[Assert\NotNull()]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(["getUser", "simpleUser"])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {

    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable("now", new \DateTimeZone('Europe/Paris'));
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable("now", new \DateTimeZone('Europe/Paris'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * The public representation of the user (e.g. a username, an email address, etc.)
     *
     * @see UserInterface
     */
    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * The public representation of the user (e.g. a username, an email address, etc.)
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        if (empty($roles)) {
            $roles = ['ROLE_USER'];
        }

        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getIsVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getUsername(): string {
        return $this->getUserIdentifier();
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
