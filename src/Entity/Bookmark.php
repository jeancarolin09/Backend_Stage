<?php

// src/Entity/Bookmark.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\BookmarkRepository;

#[ORM\Entity(repositoryClass: BookmarkRepository::class)]
class Bookmark
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private $id;

    #[ORM\ManyToOne(targetEntity:User::class, inversedBy:"bookmarks")]
    #[ORM\JoinColumn(nullable:false)]
    private $user;

    #[ORM\ManyToOne(targetEntity:Event::class, inversedBy:"bookmarks")]
    #[ORM\JoinColumn(nullable:false)]
    private $event;

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getEvent(): ?Event { return $this->event; }
    public function setEvent(Event $event): self { $this->event = $event; return $this; }
}
