<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\WidgetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=WidgetRepository::class)
 * @UniqueEntity(fields="name", message="That name is taken!")
 */
class Widget
{
    /**
     * Unique identifier for the widget.
     *
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var string
     *
     * @Assert\NotBlank(message="Name field should not be blank")
     * @Assert\Length(max=20, maxMessage="Name can not be longer then {{ limit }} characters!")
     * @ORM\Column(type="string", length=20, unique=true)
     */
    private $name;

    /**
     * @Assert\Length(max=100, maxMessage="Description can not be longer then {{ limit }} characters!")
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $description;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
