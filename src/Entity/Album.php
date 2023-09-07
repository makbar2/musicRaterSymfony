<?php

namespace App\Entity;


use App\Interface\MusicInterface;
use App\Interface\ReviewHolderInterface;
use App\Interface\SubmissionInterface;
use App\Repository\AlbumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as JMS;

#[JMS\ExclusionPolicy("all")]
#[ORM\Entity(repositoryClass: AlbumRepository::class)]

class Album implements MusicInterface, ReviewHolderInterface, SubmissionInterface
{
    #[JMS\Groups(["user"])]
    #[ORM\Id]
    #[JMS\Expose]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[JMS\Groups(["user"])]
    #[JMS\Expose]
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[JMS\Groups(["user"])]
    #[JMS\Expose]
    #[ORM\Column(length: 255)]
    private ?string $picture = null;


    #[JMS\MaxDepth(1)]
    #[ORM\ManyToOne(inversedBy: 'albums')]
    #[ORM\JoinColumn(nullable: false)]
    #[JMS\Expose]
    private ?Artist $artist = null;

    #[JMS\Expose]
    #[JMS\Groups(["review"])]
    #[ORM\OneToMany(mappedBy: 'Album', targetEntity: Review::class, orphanRemoval: true)]
    private Collection $reviews;


    #[ORM\Column(options: ["default" => false] )]
    private ?bool $approved = false;


    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $releaseDate = null;

    #[JMS\Expose]
    #[ORM\OneToMany(mappedBy: 'album', targetEntity: Tracks::class, orphanRemoval: true)]
    private Collection $tracks;


    #[ORM\Column(length: 255)]
    private ?string $genre = null;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
        $this->tracks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPicture(): ?string
    {
        return $this->picture;
    }

    public function setPicture(string $picture): self
    {
        $this->picture = $picture;

        return $this;
    }



    public function getArtist(): ?Artist
    {
        return $this->artist;
    }

    public function setArtist(?Artist $artist): self
    {
        $this->artist = $artist;

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): self
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setAlbum($this);
        }

        return $this;
    }

    public function removeReview(Review $review): self
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getAlbum() === $this) {
                $review->setAlbum(null);
            }
        }

        return $this;
    }

    public function isApproved(): ?bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): self
    {
        $this->approved = $approved;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(\DateTimeInterface $releaseDate): self
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    /**
     * @return Collection<int, Tracks>
     */
    public function getTracks(): Collection
    {
        return $this->tracks;
    }

    public function addTrack(Tracks $track): self
    {
        if (!$this->tracks->contains($track)) {
            $this->tracks->add($track);
            $track->setAlbum($this);
        }

        return $this;
    }

    public function removeTrack(Tracks $track): self
    {
        if ($this->tracks->removeElement($track)) {
            // set the owning side to null (unless already changed)
            if ($track->getAlbum() === $this) {
                $track->setAlbum(null);
            }
        }

        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(string $genre): self
    {
        $this->genre = $genre;

        return $this;
    }
}
