<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types; // Ajout pour Types::DATETIME_IMMUTABLE
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DateTimeImmutable;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')] // Bonne pratique pour éviter les mots-clés SQL
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    // Le champ 'password' est obligatoire pour PasswordAuthenticatedUserInterface
    #[ORM\Column]
    private ?string $password = null;

    // Rôles
    #[ORM\Column(type: Types::JSON)] // Utilisation du Type::JSON
    private array $roles = [];

    // Photo de profil
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $profilePicture = null;

    // Statut de vérification
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isVerified = false;

    // ⭐ NOUVEAU/MODIFIÉ : Code à 6 chiffres pour l'OTP
    #[ORM\Column(type: Types::STRING, length: 6, nullable: true)]
    private ?string $verificationCode = null;

    // ⭐ NOUVEAU : Date d'expiration du code (ex: 10 minutes)
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $codeExpiresAt = null;

    #[ORM\Column(type: 'boolean')]
    private $isOnline = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $lastActivity;

    #[ORM\OneToMany(mappedBy:"user", targetEntity:Bookmark::class, cascade:["persist", "remove"], orphanRemoval:true)]
    private Collection $bookmarks;

    public function __construct()
     {
      $this->bookmarks = new ArrayCollection();
    }
        /* --------------------------
     * | GETTERS & SETTERS (Core) |
     * -------------------------- */
    
    public function getId(): ?int
    {
        return $this->id;
    }

    // ... (getName, setName, getEmail, setEmail, getPassword, setPassword)
    // ... (MÉTHODES INCHANGÉES POUR LA CLARTÉ)

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /* ----------------------------
     * | GETTERS & SETTERS (Roles) |
     * ---------------------------- */

    public function getRoles(): array
    {
        $roles = $this->roles;
        // garantit que chaque utilisateur a au moins ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function eraseCredentials(): void
    {
        // Nettoyer les données sensibles temporaires si nécessaire
    }
    
    /* -------------------------------------
     * | GETTERS & SETTERS (Profile/Status) |
     * ------------------------------------- */

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): self
    {
        $this->profilePicture = $profilePicture;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    /* --------------------------------------
     * | GETTERS & SETTERS (OTP Verification) |
     * -------------------------------------- */

    public function getVerificationCode(): ?string
    {
        return $this->verificationCode;
    }

    public function setVerificationCode(?string $verificationCode): self
    {
        $this->verificationCode = $verificationCode;
        return $this;
    }

    public function getCodeExpiresAt(): ?DateTimeImmutable
    {
        return $this->codeExpiresAt;
    }

    public function setCodeExpiresAt(?DateTimeImmutable $codeExpiresAt): self
    {
        $this->codeExpiresAt = $codeExpiresAt;
        return $this;
    }
    public function getIsOnline(): bool
{
    return $this->isOnline;
}

public function setIsOnline(bool $isOnline): self
{
    $this->isOnline = $isOnline;
    return $this;
}
public function getLastActivity(): ?\DateTimeInterface
{
    return $this->lastActivity;
}

public function setLastActivity(\DateTimeInterface $lastActivity): self
{
    $this->lastActivity = $lastActivity;
    return $this;
}


    public function getBookmarks(): Collection { return $this->bookmarks; }
    public function addBookmark(Bookmark $bookmark): self {
    if (!$this->bookmarks->contains($bookmark)) {
        $this->bookmarks->add($bookmark);
        $bookmark->setUser($this);
    }
    return $this;
}
    public function removeBookmark(Bookmark $bookmark): self {
    if ($this->bookmarks->removeElement($bookmark)) {
        if ($bookmark->getUser() === $this) $bookmark->setUser(null);
    }
    return $this;
}

}