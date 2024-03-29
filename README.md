# BoxEaty

## Installing

Copy the `.env` file as `.env.local` and replace `DATABASE_URL` with the correct URL for your local database server.

```sh
composer install
php bin/console doctrine:schema:update --force
php bin/console doctrine:fixtures:load --group=fixtures --append
yarn install
yarn watch
```

## Security checker

The Local PHP Security Checker is a command line tool that checks if your PHP application depends on PHP packages with
known security vulnerabilities. It uses the Security Advisories Database behind the scenes. Download a binary from
the [releases page on Github](https://github.com/fabpot/local-php-security-checker/releases), rename it to
local-php-security-checker and make it executable. From the application directory, run the binary without argument or
flags to check for known vulnerabilities :

```
local-php-security-checker
```

## Best practices

### Relations

#### OneToOne

```php
use Doctrine\ORM\Mapping as ORM;

class Entity {

    /**
     * @ORM\OneToOne(targetEntity=Example::class, inversedBy="entity")
     */
    private ?Example $example = null;

    public function getExample(): ?Example {
        return $this->example;
    }
    
    public function setExample(?Example $example): self {
        if($this->example && $this->example->getEntity() !== $this) {
            $oldExample = $this->example;
            $this->example = null;
            $oldExample->setEntity(null);
        }
        $this->example = $example;
        if($this->example && $this->example->getEntity() !== $this) {
            $this->example->setEntity($this);
        }
    
        return $this;
    }
    
}
```

#### ManyToOne

```php
use Doctrine\ORM\Mapping as ORM;

class Entity {

    /**
     * @ORM\ManyToOne(targetEntity=Example::class, inversedBy="entities")
     */
    private ?Example $example = null;

    public function getExample(): ?Example {
        return $this->example;
    }
    
    public function setExample(?Example $example): self {
        if($this->example && $this->example !== $example) {
            $this->example->removeEntity($this);
        }
        $this->example = $example;
        if($example) {
            $example->addEntity($this);
        }
    
        return $this;
    }
    
}
```

#### OneToMany

```php
use Doctrine\ORM\Mapping as ORM;

class Entity {

    /**
     * @ORM\OneToMany(targetEntity=Example::class, mappedBy="entity")
     */
    private Collection $examples;
    
    public function __construct() {
        $this->examples = new ArrayCollection();
    }

    /**
     * @return Collection|Example[]
     */
    public function getExamples(): Collection {
        return $this->examples;
    }

    public function addExample(Example $example): self {
        if (!$this->examples->contains($example)) {
            $this->examples[] = $example;
            $example->setEntity($this);
        }

        return $this;
    }

    public function removeExample(Example $example): self {
        if ($this->examples->removeElement($example)) {
            if ($example->getEntity() === $this) {
                $example->setEntity(null);
            }
        }

        return $this;
    }

    public function setExamples(?array $examples): self {
        foreach($this->getExamples()->toArray() as $example) {
            $this->removeExample($example);
        }

        $this->examples = new ArrayCollection();
        foreach($examples as $example) {
            $this->addExample($example);
        }
        
        return $this;
    }
}
```

### ManyToMany

```php
use Doctrine\ORM\Mapping as ORM;

class Entity {

    /**
     * @ORM\ManyToMany(targetEntity=Example::class, mappedBy="entities")
     */
    private Collection $examples;

    /**
     * @return Collection|Example[]
     */
    public function getExamples(): Collection {
        return $this->examples;
    }

    public function addExample(Example $example): self {
        if (!$this->examples->contains($example)) {
            $this->examples[] = $example;
            $example->addEntity($this);
        }

        return $this;
    }

    public function removeExample(Example $example): self {
        if ($this->examples->removeElement($example)) {
            $example->removeEntity($this);
        }

        return $this;
    }

    public function setExamples(?array $examples): self {
        foreach($this->getExamples()->toArray() as $example) {
            $this->removeExample($example);
        }

        $this->examples = new ArrayCollection();
        foreach($examples as $example) {
            $this->addExample($example);
        }
        
        return $this;
    }
    
}
```
