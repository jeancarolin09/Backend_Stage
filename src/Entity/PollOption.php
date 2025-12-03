<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity]
class PollOption
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $text;

    #[ORM\Column(type: 'integer')]
    private int $votes = 0;

    #[ORM\ManyToOne(targetEntity: Poll::class, inversedBy: 'options')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Poll $poll = null;

    // ✅ Voici la relation manquante
    #[ORM\OneToMany(mappedBy: 'option', targetEntity: Vote::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $votesList;

    public function __construct()
    {
        $this->votesList = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function getVotes(): int
    {
        return $this->votes;
    }

    public function setVotes(int $votes): self
    {
        $this->votes = $votes;
        return $this;
    }

    public function getPoll(): ?Poll
    {
        return $this->poll;
    }

    public function setPoll(?Poll $poll): self
    {
        $this->poll = $poll;
        return $this;
    }

    // ✅ Méthodes pour gérer les votes liés
    public function getVotesList(): Collection
    {
        return $this->votesList;
    }

    public function addVote(Vote $vote): self
    {
        if (!$this->votesList->contains($vote)) {
            $this->votesList->add($vote);
            $vote->setOption($this);
        }
        return $this;
    }

    public function removeVote(Vote $vote): self
    {
        if ($this->votesList->removeElement($vote)) {
            if ($vote->getOption() === $this) {
                $vote->setOption(null);
            }
        }
        return $this;
    }
}
