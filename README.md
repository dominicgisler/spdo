# Simple PDO Objects

Simple PDO based CRUD functions.

## Examples

### Define model

```php
<?php

use \Gisler\Spdo\AbstractModel;

class Person extends AbstractModel
{
    /**
     * @var int
     */
    public $person_id;

    /**
     * @var string
     */
    public $name;
}
```

### Define Repository

```php
<?php

use \Gisler\Spdo\AbstractRepository;

class PersonRepository extends AbstractRepository
{
    /**
     * PersonRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(
            new PDO('mysql:host=localhost;dbname=database', 'username', 'password'),
            'person',
            'person_id',
            Person::class
        );
    }
}
```

### CRUD objects

```php
<?php

$repo = new PersonRepository();

// get
$col = $repo->getAll(); // get all entities
$col = $repo->get(['name' => 'Max']); // get entities with name="Max"
$person = $repo->getObject(['person_id' => 1]); // get single entity where person_id=1

// save (insert / update)
$repo->save(new Person(['name' => 'Max Muster'])); // inserts a new entity
$repo->save(new Person(['person_id' => 1, 'name' => 'Max Muster'])); // updates entity with person_id=1

// delete
$repo->delete(new Person(['person_id' => 1, 'name' => 'Max Muster'])); // delete entity with person_id=1
```