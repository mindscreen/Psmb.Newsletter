<?php
namespace Psmb\Newsletter\Domain\Model;

use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;

/**
 * Subscriber
 *
 * @Flow\Entity
 */
class Subscriber
{

    /**
     * @var string
     * @ORM\Column(length=80)
     * @Flow\Identity
     * @Flow\Validate(type="EmailAddress")
     * @Flow\Validate(type="NotEmpty")
     * @Flow\Validate(type="StringLength", options={"minimum"=1, "maximum"=80})
     */
    protected $email;

    /**
     * @var string
     * @Flow\Validate(type="Text")
     * @Flow\Validate(type="StringLength", options={"minimum"=1, "maximum"=80})
     * @ORM\Column(length=80)
     */
    protected $name;

    /**
     * @var array
     * @Flow\Validate(type="NotEmpty")
     */
    protected $subscriptions;

    /**
     * @var array
     */
    protected $metadata;

    /**
     * @return array|null
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param array $metadata
     */
    public function setMetadata(array $metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * @return string
     */
    public function getPersistenceObjectIdentifier(): string
    {
        return $this->Persistence_Object_Identifier;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return void
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return void
     */
    public function setEmail(string $email)
    {
        $this->email = $email;
    }

    /**
     * @return array
     */
    public function getSubscriptions(): array
    {
        return $this->subscriptions;
    }

    /**
     * @param array $subscriptions
     * @return void
     */
    public function setSubscriptions(array $subscriptions)
    {
        $this->subscriptions = $subscriptions;
    }

}
