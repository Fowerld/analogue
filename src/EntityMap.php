<?php

namespace Analogue\ORM;

use Analogue\ORM\Exceptions\MappingException;
use Analogue\ORM\Relationships\BelongsTo;
use Analogue\ORM\Relationships\BelongsToMany;
use Analogue\ORM\Relationships\EmbedsMany;
use Analogue\ORM\Relationships\EmbedsOne;
use Analogue\ORM\Relationships\HasMany;
use Analogue\ORM\Relationships\HasManyThrough;
use Analogue\ORM\Relationships\HasOne;
use Analogue\ORM\Relationships\MorphMany;
use Analogue\ORM\Relationships\MorphOne;
use Analogue\ORM\Relationships\MorphTo;
use Analogue\ORM\Relationships\MorphToMany;
use Analogue\ORM\System\Manager;
use Analogue\ORM\System\Wrappers\Factory;
use Exception;
use ReflectionClass;

/**
 * The Entity Map defines the Mapping behaviour of an Entity,
 * including relationships.
 */
class EntityMap
{
    /**
     * The mapping driver to use with this entity.
     *
     * @var string
     */
    protected $driver = 'illuminate';

    /**
     * The Database Connection name for the model.
     *
     * @var string
     */
    protected $connection;

    /**
     * The table associated with the entity.
     *
     * @var string|null
     */
    protected $table = null;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Name of the entity's array property that should
     * contain the attributes.
     * If set to null, analogue will only hydrate object's properties.
     *
     * @var string|null
     */
    protected $arrayName = 'attributes';

    /**
     * Array containing the list of database columns to be mapped
     * in the attributes array of the entity.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Array containing the list of database columns to be mapped
     * to the entity's class properties.
     *
     * @var array
     */
    protected $properties = [];

    /**
     * The Custom Domain Class to use with this mapping.
     *
     * @var string|null
     */
    protected $class = null;

    /**
     * Embedded Value Objects.
     *
     * @var array
     */
    protected $embeddables = [];

    /**
     * Determine the relationships method used on the entity.
     * If not set, mapper will autodetect them.
     *
     * @var array
     */
    private $relationships = [];

    /**
     * Relationships that should be treated as collection.
     *
     * @var array
     */
    private $manyRelations = [];

    /**
     * Relationships that should be treated as single entity.
     *
     * @var array
     */
    private $singleRelations = [];

    /**
     * Relationships for which the key is stored in the Entity itself.
     *
     * @var array
     */
    private $localRelations = [];

    /**
     * List of local keys associated to local relation methods.
     *
     * @var array
     */
    private $localForeignKeys = [];

    /**
     * Relationships for which the key is stored in the Related Entity.
     *
     * @var array
     */
    private $foreignRelations = [];

    /**
     * Relationships which use a pivot record.
     *
     * @var array
     */
    private $pivotRelations = [];

    /**
     * Polymorphic relationships.
     *
     * @var array
     */
    private $polymorphicRelations = [];

    /**
     * Dynamic relationships.
     *
     * @var array
     */
    private $dynamicRelationships = [];

    /**
     * Targetted class for the relationship method. value is set to `null` for
     * polymorphic relations.
     *
     * @var array
     */
    private $relatedClasses = [];

    /**
     * Some relation methods like embedded objects, or HasOne and MorphOne,
     * will never have a proxy loaded on them.
     *
     * @var array
     */
    private $nonProxyRelationships = [];

    /**
     * Relation methods that are embedded objects.
     *
     * @var array
     */
    private $embeddedRelations = [];

    /**
     * The number of models to return for pagination.
     *
     * @var int
     */
    protected $perPage = 15;

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = [];

    /**
     * The class name to be used in polymorphic relations.
     *
     * @var string
     */
    protected $morphClass;

    /**
     * Sequence name, to be used with postgreSql
     * defaults to %table_name%_id_seq.
     *
     * @var string|null
     */
    protected $sequence = null;

    /**
     * Indicates if the entity should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    protected $createdAtColumn = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    protected $updatedAtColumn = 'updated_at';

    /**
     * Indicates if the entity uses softdeletes.
     *
     * @var bool
     */
    public $softDeletes = false;

    /**
     * The name of the "deleted at" column.
     *
     * @var string
     */
    protected $deletedAtColumn = 'deleted_at';

    /**
     * The date format to use with the current database connection.
     *
     * @var string
     */
    protected $dateFormat;

    /**
     * Set this property to true if the entity should be instantiated
     * using the IoC Container.
     *
     * @var bool
     */
    protected $dependencyInjection = false;

    /**
     * Set the usage of inheritance, possible values are :
     * "single_table"
     * null.
     *
     * @var string | null
     */
    protected $inheritanceType = null;

    /**
     * Discriminator column name.
     *
     * @var string
     */
    protected $discriminatorColumn = 'type';

    /**
     * Allow using a string to define which entity type should be instantiated.
     * If not set, analogue will uses entity's FQDN.
     *
     * @var array
     */
    protected $discriminatorColumnMap = [];

    /**
     * Return Domain class attributes, useful when mapping to a Plain PHP Object.
     *
     * @return array
     */
    public function getAttributes() : array
    {
        return $this->attributes;
    }

    /**
     * Set the domain class attributes.
     *
     * @param array $attributeNames
     */
    public function setAttributes(array $attributeNames)
    {
        $this->attributes = $attributeNames;
    }

    /**
     * Return true if the Entity has an 'attributes' array property.
     *
     * @return bool
     */
    public function usesAttributesArray() : bool
    {
        if ($this->arrayName === null) {
            return false;
        }
        if ($this->attributes === null) {
            return false;
        }

        return true;
    }

    /**
     * Return the name of the Entity's attributes property.
     *
     * @return string|null
     */
    public function getAttributesArrayName()
    {
        return $this->arrayName;
    }

    /**
     * Get all the attribute names for the class, including relationships, embeddables and primary key.
     *
     * @return array
     */
    public function getCompiledAttributes() : array
    {
        $key = $this->getKeyName();

        $embeddables = array_keys($this->getEmbeddables());

        $relationships = $this->getRelationships();

        $attributes = $this->getAttributes();

        return array_merge([$key], $embeddables, $relationships, $attributes);
    }

    /**
     * Set the date format to use with the current database connection.
     *
     * @param string $format
     */
    public function setDateFormat($format)
    {
        $this->dateFormat = $format;
    }

    /**
     * Get the date format to use with the current database connection.
     *
     *  @return string
     */
    public function getDateFormat() : string
    {
        return $this->dateFormat;
    }

    /**
     * Set the Driver for this mapping.
     *
     * @param string $driver
     */
    public function setDriver($driver)
    {
        $this->driver = $driver;
    }

    /**
     * Get the Driver for this mapping.
     *
     * @return string
     */
    public function getDriver() : string
    {
        return $this->driver;
    }

    /**
     * Set the db connection to use on the table.
     *
     * @param $connection
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the Database connection the Entity is stored on.
     *
     * @return string | null
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get the table associated with the entity.
     *
     * @return string
     */
    public function getTable() : string
    {
        if (!is_null($this->table)) {
            return $this->table;
        }

        return str_replace('\\', '', snake_case(str_plural(class_basename($this->getClass()))));
    }

    /**
     * Set the database table name.
     *
     * @param string $table
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * Get the pgSql sequence name.
     *
     * @return string
     */
    public function getSequence()
    {
        if (!is_null($this->sequence)) {
            return $this->sequence;
        } else {
            return $this->getTable().'_id_seq';
        }
    }

    /**
     * Get the custom entity class.
     *
     * @return string namespaced class name
     */
    public function getClass() : string
    {
        return isset($this->class) ? $this->class : null;
    }

    /**
     * Set the custom entity class.
     *
     * @param string $class namespaced class name
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * Get the embedded Value Objects.
     *
     * @return array
     */
    public function getEmbeddables() : array
    {
        return $this->embeddables;
    }

    /**
     * Return attributes that should be mapped to class properties.
     *
     * @return array
     */
    public function getProperties() : array
    {
        return $this->properties;
    }

    /**
     * Return the array property in which will be mapped all attributes
     * that are not mapped to class properties.
     *
     * @return string
     */
    public function getAttributesPropertyName() : string
    {
    }

    /**
     * Set the embedded Value Objects.
     *
     * @param array $embeddables
     */
    public function setEmbeddables(array $embeddables)
    {
        $this->embeddables = $embeddables;
    }

    /**
     * Get the relationships to map on a custom domain
     * class.
     *
     * @return array
     */
    public function getRelationships() : array
    {
        return $this->relationships;
    }

    /**
     * Return all relationships that are not embedded objects.
     *
     * @return array
     */
    public function getNonEmbeddedRelationships() : array
    {
        return array_diff($this->relationships, $this->embeddedRelations);
    }

    /**
     * Get the relationships that will not have a proxy
     * set on them.
     *
     * @return array
     */
    public function getRelationshipsWithoutProxy() : array
    {
        return $this->nonProxyRelationships;
    }

    /**
     * Relationships of the Entity type.
     *
     * @return array
     */
    public function getSingleRelationships() : array
    {
        return $this->singleRelations;
    }

    /**
     * Relationships of type Collection.
     *
     * @return array
     */
    public function getManyRelationships() : array
    {
        return $this->manyRelations;
    }

    /**
     * Relationships with foreign key in the mapped entity record.
     *
     * @return array
     */
    public function getLocalRelationships() : array
    {
        return $this->localRelations;
    }

    /**
     * Return the local keys associated to the relationship.
     *
     * @param string $relation
     *
     * @return string | array | null
     */
    public function getLocalKeys($relation)
    {
        return isset($this->localForeignKeys[$relation]) ? $this->localForeignKeys[$relation] : null;
    }

    /**
     * Relationships with foreign key in the related Entity record.
     *
     * @return array
     */
    public function getForeignRelationships() : array
    {
        return $this->foreignRelations;
    }

    /**
     * Relationships which keys are stored in a pivot record.
     *
     * @return array
     */
    public function getPivotRelationships() : array
    {
        return $this->pivotRelations;
    }

    /**
     * Return an array containing all embedded relationships.
     *
     * @return array
     */
    public function getEmbeddedRelationships() : array
    {
        return $this->embeddedRelations;
    }

    /**
     * Return true if the relationship method is polymorphic.
     *
     * @param string $relation
     *
     * @return bool
     */
    public function isPolymorphic($relation) : bool
    {
        return in_array($relation, $this->polymorphicRelations);
    }

    /**
     * Get the targetted type for a relationship. Return null if polymorphic.
     *
     * @param string $relation
     *
     * @return string | null
     */
    public function getTargettedClass($relation)
    {
        if (!array_key_exists($relation, $this->relatedClasses)) {
            return;
        }

        return $this->relatedClasses[$relation];
    }

    /**
     * Add a Dynamic Relationship method at runtime. This has to be done
     * by hooking the 'initializing' event, before entityMap is initialized.
     *
     * @param string   $name         Relation name
     * @param \Closure $relationship
     *
     * @return void
     */
    public function addRelationshipMethod($name, \Closure $relationship)
    {
        $this->dynamicRelationships[$name] = $relationship;
    }

    /**
     * Get the dynamic relationship method names.
     *
     * @return array
     */
    public function getDynamicRelationships() : array
    {
        return array_keys($this->dynamicRelationships);
    }

    /**
     * Get the relationships that have to be eager loaded
     * on each request.
     *
     * @return array
     */
    public function getEagerloadedRelationships() : array
    {
        return $this->with;
    }

    /**
     * Get the primary key attribute for the entity.
     *
     * @return string
     */
    public function getKeyName() : string
    {
        return $this->primaryKey;
    }

    /**
     * Set the primary key for the entity.
     *
     * @param $key
     *
     * @return void
     */
    public function setKeyName($key)
    {
        $this->primaryKey = $key;
    }

    /**
     * Get the table qualified key name.
     *
     * @return string
     */
    public function getQualifiedKeyName() : string
    {
        return $this->getTable().'.'.$this->getKeyName();
    }

    /**
     * Get the number of models to return per page.
     *
     * @return int
     */
    public function getPerPage() : int
    {
        return $this->perPage;
    }

    /**
     * Set the number of models to return per page.
     *
     * @param int $perPage
     *
     * @return void
     */
    public function setPerPage($perPage)
    {
        $this->perPage = $perPage;
    }

    /**
     * Determine if the entity uses get.
     *
     * @return bool
     */
    public function usesTimestamps() : bool
    {
        return $this->timestamps;
    }

    /**
     * Determine if the entity uses soft deletes.
     *
     * @return bool
     */
    public function usesSoftDeletes() : bool
    {
        return $this->softDeletes;
    }

    /**
     * Get the 'created_at' column name.
     *
     * @return string
     */
    public function getCreatedAtColumn() : string
    {
        return $this->createdAtColumn;
    }

    /**
     * Get the 'updated_at' column name.
     *
     * @return string
     */
    public function getUpdatedAtColumn() : string
    {
        return $this->updatedAtColumn;
    }

    /**
     * Get the deleted_at column.
     *
     * @return string
     */
    public function getQualifiedDeletedAtColumn() : string
    {
        return $this->deletedAtColumn;
    }

    /**
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function getForeignKey() : string
    {
        return snake_case(class_basename($this->getClass())).'_id';
    }

    /**
     * Return the inheritance type used by the entity.
     *
     * @return string|null
     */
    public function getInheritanceType()
    {
        return $this->inheritanceType;
    }

    /**
     * Return the discriminator column name on the entity that's
     * used for table inheritance.
     *
     * @return string
     */
    public function getDiscriminatorColumn() : string
    {
        return $this->discriminatorColumn;
    }

    /**
     * Return the mapping of discriminator column values to
     * entity class names that are used for table inheritance.
     *
     * @return array
     */
    public function getDiscriminatorColumnMap() : array
    {
        return $this->discriminatorColumnMap;
    }

    /**
     * Return true if the entity should be instanciated using
     * the IoC Container.
     *
     * @return bool
     */
    public function useDependencyInjection() : bool
    {
        return $this->dependencyInjection;
    }

    /**
     * Add a single relation method name once.
     *
     * @param string $relation
     */
    protected function addSingleRelation($relation)
    {
        if (!in_array($relation, $this->singleRelations)) {
            $this->singleRelations[] = $relation;
        }
    }

    /**
     * Add a foreign relation method name once.
     *
     * @param string $relation
     */
    protected function addForeignRelation($relation)
    {
        if (!in_array($relation, $this->foreignRelations)) {
            $this->foreignRelations[] = $relation;
        }
    }

    /**
     * Add a polymorphic relation method name once.
     *
     * @param string $relation
     */
    protected function addPolymorphicRelation($relation)
    {
        if (!in_array($relation, $this->polymorphicRelations)) {
            $this->polymorphicRelations[] = $relation;
        }
    }

    /**
     * Add a non proxy relation method name once.
     *
     * @param string $relation
     */
    protected function addNonProxyRelation($relation)
    {
        if (!in_array($relation, $this->nonProxyRelationships)) {
            $this->nonProxyRelationships[] = $relation;
        }
    }

    /**
     * Add a local relation method name once.
     *
     * @param string $relation
     */
    protected function addLocalRelation($relation)
    {
        if (!in_array($relation, $this->localRelations)) {
            $this->localRelations[] = $relation;
        }
    }

    /**
     * Add a many relation method name once.
     *
     * @param string $relation
     */
    protected function addManyRelation($relation)
    {
        if (!in_array($relation, $this->manyRelations)) {
            $this->manyRelations[] = $relation;
        }
    }

    /**
     * Add a pivot relation method name once.
     *
     * @param string $relation
     */
    protected function addPivotRelation($relation)
    {
        if (!in_array($relation, $this->pivotRelations)) {
            $this->pivotRelations[] = $relation;
        }
    }

    /**
     * Add an embedded relation.
     *
     * @param string $relation
     */
    protected function addEmbeddedRelation($relation)
    {
        if (!in_array($relation, $this->embeddedRelations)) {
            $this->embeddedRelations[] = $relation;
        }
    }

    /**
     * Define an Embedded Object.
     *
     * @param mixed  $entity
     * @param string $related
     *
     * @return EmbedsOne
     */
    protected function embedsOne($parent, string $relatedClass, $relation = null) : EmbedsOne
    {
        if(is_null($relation)) {
            list(, $caller) = debug_backtrace(false);
            $relation = $caller['function'];
        }

        $this->addEmbeddedRelation($relation);
        $this->addNonProxyRelation($relation);

        return new EmbedsOne($parent, $relatedClass, $relation);
    }

    /**
     * Define an Embedded Collection.
     *
     * @param mixed  $entity
     * @param string $related
     *
     * @return EmbedsOne
     */
    protected function embedsMany($parent, string $relatedClass, $relation = null) : EmbedsMany
    {
        if(is_null($relation)) {
            list(, $caller) = debug_backtrace(false);
            $relation = $caller['function'];
        }

        $this->addEmbeddedRelation($relation);
        $this->addNonProxyRelation($relation);

        return new EmbedsMany($parent, $relatedClass, $relation);
    }

    /**
     * Define a one-to-one relationship.
     *
     * @param mixed  $entity
     * @param string $related    entity class
     * @param string $foreignKey
     * @param string $localKey
     *
     * @throws MappingException
     *
     * @return \Analogue\ORM\Relationships\HasOne
     */
    protected function hasOne($entity, $related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $relatedMapper = Manager::getInstance()->mapper($related);

        $relatedMap = $relatedMapper->getEntityMap();

        $localKey = $localKey ?: $this->getKeyName();

        // Add the relation to the definition in map
        list(, $caller) = debug_backtrace(false);
        $relation = $caller['function'];
        $this->relatedClasses[$relation] = $related;

        $this->addSingleRelation($relation);
        $this->addForeignRelation($relation);
        $this->addNonProxyRelation($relation);

        // This relationship will always be eager loaded, as proxying it would
        // mean having an object that doesn't actually exists.
        if (!in_array($relation, $this->with)) {
            $this->with[] = $relation;
        }

        return new HasOne($relatedMapper, $entity, /*$relatedMap->getTable().'.'*/$foreignKey, $localKey);
    }

    /**
     * Define a polymorphic one-to-one relationship.
     *
     * @param mixed       $entity
     * @param string      $related
     * @param string      $name
     * @param string|null $type
     * @param string|null $id
     * @param string|null $localKey
     *
     * @throws MappingException
     *
     * @return \Analogue\ORM\Relationships\MorphOne
     */
    protected function morphOne($entity, $related, $name, $type = null, $id = null, $localKey = null)
    {
        list($type, $id) = $this->getMorphs($name, $type, $id);

        $localKey = $localKey ?: $this->getKeyName();

        $relatedMapper = Manager::getInstance()->mapper($related);

        //$table = $relatedMapper->getEntityMap()->getTable();

        // Add the relation to the definition in map
        list(, $caller) = debug_backtrace(false);
        $relation = $caller['function'];
        $this->relatedClasses[$relation] = $related;

        $this->addSingleRelation($relation);
        $this->addForeignRelation($relation);
        $this->addNonProxyRelation($relation);

        // This relationship will always be eager loaded, as proxying it would
        // mean having an object that doesn't actually exists.
        if (!in_array($relation, $this->with)) {
            $this->with[] = $relation;
        }

        return new MorphOne($relatedMapper, $entity, /*$table.'.'.*/$type, /*$table.'.'.*/$id, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param mixed       $entity
     * @param string      $related
     * @param string|null $foreignKey
     * @param string|null $otherKey
     * @param string|null $relation
     *
     * @throws MappingException
     *
     * @return \Analogue\ORM\Relationships\BelongsTo
     */
    protected function belongsTo($entity, $related, $foreignKey = null, $otherKey = null)
    {
        // Add the relation to the definition in map
        list(, $caller) = debug_backtrace(false);
        $relation = $caller['function'];
        $this->relatedClasses[$relation] = $related;

        $this->addSingleRelation($relation);
        $this->addLocalRelation($relation);

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the relationship function, which
        // when combined with an "_id" should conventionally match the columns.
        if (is_null($foreignKey)) {
            $foreignKey = snake_case($relation).'_id';
        }

        $this->localForeignKeys[$relation] = $foreignKey;

        $relatedMapper = Manager::getInstance()->mapper($related);

        $otherKey = $otherKey ?: $relatedMapper->getEntityMap()->getKeyName();

        return new BelongsTo($relatedMapper, $entity, $foreignKey, $otherKey, $relation);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param mixed       $entity
     * @param string|null $name
     * @param string|null $type
     * @param string|null $id
     *
     * @throws MappingException
     *
     * @return \Analogue\ORM\Relationships\MorphTo
     */
    protected function morphTo($entity, $name = null, $type = null, $id = null)
    {
        // If no name is provided, we will use the backtrace to get the function name
        // since that is most likely the name of the polymorphic interface. We can
        // use that to get both the class and foreign key that will be utilized.
        if (is_null($name)) {
            list(, $caller) = debug_backtrace(false);

            $name = snake_case($caller['function']);
        }
        $this->addSingleRelation($name);
        $this->addLocalRelation($name);
        $this->addPolymorphicRelation($name);

        $this->relatedClass[$name] = null;

        list($type, $id) = $this->getMorphs($name, $type, $id);

        // Store the foreign key in the entity map.
        // We might want to store the (key, type) as we might need it
        // to build a MorphTo proxy
        $this->localForeignKeys[$name] = [
            'id'   => $id,
            'type' => $type,
        ];

        $mapper = Manager::getInstance()->mapper(get_class($entity));

        // If the type value is null it is probably safe to assume we're eager loading
        // the relationship. When that is the case we will pass in a dummy query as
        // there are multiple types in the morph and we can't use single queries.
        $factory = new Factory();
        $wrapper = $factory->make($entity);

        if (is_null($class = $wrapper->getEntityAttribute($type))) {
            return new MorphTo(
                $mapper, $entity, $id, null, $type, $name
            );
        }

        // If we are not eager loading the relationship we will essentially treat this
        // as a belongs-to style relationship since morph-to extends that class and
        // we will pass in the appropriate values so that it behaves as expected.
        else {
            $class = Manager::getInstance()->getInverseMorphMap($class);
            $relatedMapper = Manager::getInstance()->mapper($class);

            $foreignKey = $relatedMapper->getEntityMap()->getKeyName();

            return new MorphTo(
                $relatedMapper, $entity, $id, $foreignKey, $type, $name
            );
        }
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param mixed       $entity
     * @param string      $related
     * @param string|null $foreignKey
     * @param string|null $localKey
     *
     * @throws MappingException
     *
     * @return \Analogue\ORM\Relationships\HasMany
     */
    protected function hasMany($entity, $related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $relatedMapper = Manager::getInstance()->mapper($related);

        $localKey = $localKey ?: $this->getKeyName();

        // Add the relation to the definition in map
        list(, $caller) = debug_backtrace(false);
        $relation = $caller['function'];
        $this->relatedClasses[$relation] = $related;

        $this->addManyRelation($relation);
        $this->addForeignRelation($relation);

        return new HasMany($relatedMapper, $entity, $foreignKey, $localKey);
    }

    /**
     * Define a has-many-through relationship.
     *
     * @param mixed       $entity
     * @param string      $related
     * @param string      $through
     * @param string|null $firstKey
     * @param string|null $secondKey
     *
     * @throws MappingException
     *
     * @return \Analogue\ORM\Relationships\HasManyThrough
     */
    protected function hasManyThrough($entity, $related, $through, $firstKey = null, $secondKey = null)
    {
        $relatedMapper = Manager::getInstance()->mapper($related);
        $throughMapper = Manager::getInstance()->mapper($through);

        $firstKey = $firstKey ?: $this->getForeignKey();

        $throughMap = $throughMapper->getEntityMap();

        $secondKey = $secondKey ?: $throughMap->getForeignKey();

        // Add the relation to the definition in map
        list(, $caller) = debug_backtrace(false);
        $relation = $caller['function'];
        $this->relatedClasses[$relation] = $related;

        $this->addManyRelation($relation);
        $this->addForeignRelation($relation);

        return new HasManyThrough($relatedMapper, $entity, $throughMap, $firstKey, $secondKey);
    }

    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @param mixed       $entity
     * @param string      $related
     * @param string      $name
     * @param string|null $type
     * @param string|null $id
     * @param string|null $localKey
     *
     * @return \Analogue\ORM\Relationships\MorphMany
     */
    protected function morphMany($entity, $related, $name, $type = null, $id = null, $localKey = null)
    {
        // Here we will gather up the morph type and ID for the relationship so that we
        // can properly query the intermediate table of a relation. Finally, we will
        // get the table and create the relationship instances for the developers.
        list($type, $id) = $this->getMorphs($name, $type, $id);

        $relatedMapper = Manager::getInstance()->mapper($related);

        $table = $relatedMapper->getEntityMap()->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        // Add the relation to the definition in map
        list(, $caller) = debug_backtrace(false);
        $relation = $caller['function'];
        $this->relatedClasses[$relation] = $related;

        $this->addManyRelation($relation);
        $this->addForeignRelation($relation);

        return new MorphMany($relatedMapper, $entity, /*$table.'.'.*/$type, /*$table.'.'.*/$id, $localKey);
    }

    /**
     * Define a many-to-many relationship.
     *
     * @param mixed       $entity
     * @param string      $relatedClass
     * @param string|null $table
     * @param string|null $foreignKey
     * @param string|null $otherKey
     * @param string|null $relation
     *
     * @throws MappingException
     *
     * @return \Analogue\ORM\Relationships\BelongsToMany
     */
    protected function belongsToMany($entity, $related, $table = null, $foreignKey = null, $otherKey = null)
    {
        // Add the relation to the definition in map
        list(, $caller) = debug_backtrace(false);
        $relation = $caller['function'];
        $this->relatedClasses[$relation] = $related;

        $this->addManyRelation($relation);
        $this->addForeignRelation($relation);
        $this->addPivotRelation($relation);

        // First, we'll need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $relatedMapper = Manager::getInstance()->mapper($related);

        $relatedMap = $relatedMapper->getEntityMap();

        $otherKey = $otherKey ?: $relatedMap->getForeignKey();

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($table)) {
            $table = $this->joiningTable($relatedMap);
        }

        return new BelongsToMany($relatedMapper, $entity, $table, $foreignKey, $otherKey, $relation);
    }

    /**
     * Define a polymorphic many-to-many relationship.
     *
     * @param mixed       $entity
     * @param string      $related
     * @param string      $name
     * @param string|null $table
     * @param string|null $foreignKey
     * @param string|null $otherKey
     * @param bool        $inverse
     *
     * @throws MappingException
     *
     * @return \Analogue\ORM\Relationships\MorphToMany
     */
    protected function morphToMany($entity, $related, $name, $table = null, $foreignKey = null, $otherKey = null, $inverse = false)
    {
        // Add the relation to the definition in map
        list(, $caller) = debug_backtrace(false);
        $relation = $caller['function'];
        $this->relatedClasses[$relation] = $related;

        $this->addManyRelation($relation);
        $this->addForeignRelation($relation);
        $this->addPivotRelation($relation);

        // First, we will need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we will make the query
        // instances, as well as the relationship instances we need for these.
        $foreignKey = $foreignKey ?: $name.'_id';

        $relatedMapper = Manager::getInstance()->mapper($related);

        $otherKey = $otherKey ?: $relatedMapper->getEntityMap()->getForeignKey();

        $table = $table ?: str_plural($name);

        return new MorphToMany($relatedMapper, $entity, $name, $table, $foreignKey, $otherKey, $caller, $inverse);
    }

    /**
     * Define a polymorphic, inverse many-to-many relationship.
     *
     * @param mixed       $entity
     * @param string      $related
     * @param string      $name
     * @param string|null $table
     * @param string|null $foreignKey
     * @param string|null $otherKey
     *
     * @throws MappingException
     *
     * @return \Analogue\ORM\Relationships\MorphToMany
     */
    protected function morphedByMany($entity, $related, $name, $table = null, $foreignKey = null, $otherKey = null)
    {
        // Add the relation to the definition in map
        list(, $caller) = debug_backtrace(false);
        $relation = $caller['function'];
        $this->relatedClasses[$relation] = $related;

        $this->addManyRelation($relation);
        $this->addForeignRelation($relation);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        // For the inverse of the polymorphic many-to-many relations, we will change
        // the way we determine the foreign and other keys, as it is the opposite
        // of the morph-to-many method since we're figuring out these inverses.
        $otherKey = $otherKey ?: $name.'_id';

        return $this->morphToMany($entity, $related, $name, $table, $foreignKey, $otherKey, true);
    }

    /**
     * Get the joining table name for a many-to-many relation.
     *
     * @param EntityMap $relatedMap
     *
     * @return string
     */
    public function joiningTable($relatedMap)
    {
        // The joining table name, by convention, is simply the snake cased models
        // sorted alphabetically and concatenated with an underscore, so we can
        // just sort the models and join them together to get the table name.
        $base = $this->getTable();

        $related = $relatedMap->getTable();

        $tables = [$related, $base];

        // Now that we have the model names in an array we can just sort them and
        // use the implode function to join them together with an underscores,
        // which is typically used by convention within the database system.
        sort($tables);

        return strtolower(implode('_', $tables));
    }

    /**
     * Get the polymorphic relationship columns.
     *
     * @param string $name
     * @param string $type
     * @param string $id
     *
     * @return string[]
     */
    protected function getMorphs($name, $type, $id)
    {
        $type = $type ?: $name.'_type';

        $id = $id ?: $name.'_id';

        return [$type, $id];
    }

    /**
     * Get the class name for polymorphic relations.
     *
     * @return string
     */
    public function getMorphClass()
    {
        $morphClass = Manager::getInstance()->getMorphMap($this->getClass());

        return $this->morphClass ?: $morphClass;
    }

    /**
     * Create a new Entity Collection instance.
     *
     * @param array $entities
     *
     * @return \Analogue\ORM\EntityCollection
     */
    public function newCollection(array $entities = [])
    {
        $collection = new EntityCollection($entities, $this);

        return $collection->keyBy($this->getKeyName());
    }

    /**
     * Process EntityMap parsing at initialization time.
     *
     * @return void
     */
    public function initialize()
    {
        $userMethods = $this->getCustomMethods();

        // Parse EntityMap for method based relationship
        if (count($userMethods) > 0) {
            $this->relationships = $this->parseMethodsForRelationship($userMethods);
        }

        // Parse EntityMap for dynamic relationships
        if (count($this->dynamicRelationships) > 0) {
            $this->relationships = $this->relationships + $this->getDynamicRelationships();
        }
    }

    /**
     * Parse every relationships on the EntityMap and sort
     * them by type.
     *
     * @return void
     */
    public function boot()
    {
        if (count($this->relationships > 0)) {
            $this->sortRelationshipsByType();
        }
    }

    /**
     * Get Methods that has been added in the child class.
     *
     * @return array
     */
    protected function getCustomMethods()
    {
        $mapMethods = get_class_methods($this);

        $parentsMethods = get_class_methods('Analogue\ORM\EntityMap');

        return array_diff($mapMethods, $parentsMethods);
    }

    /**
     * Parse user's class methods for relationships.
     *
     * @param array $customMethods
     *
     * @return array
     */
    protected function parseMethodsForRelationship(array $customMethods)
    {
        $relationships = [];

        $class = new ReflectionClass(get_class($this));

        // Get the mapped Entity class, as we will detect relationships
        // methods by testing that the first argument is type-hinted to
        // the same class as the mapped Entity.
        $entityClass = $this->getClass();

        foreach ($customMethods as $methodName) {
            $method = $class->getMethod($methodName);

            if ($method->getNumberOfParameters() > 0) {
                $params = $method->getParameters();

                if ($params[0]->getClass() && ($params[0]->getClass()->name == $entityClass || is_subclass_of($entityClass, $params[0]->getClass()->name))) {
                    $relationships[] = $methodName;
                }
            }
        }

        return $relationships;
    }

    /**
     * Sort Relationships methods by type.
     *
     * TODO : replace this by direclty setting these value
     * in the corresponding methods, so we won't need
     * the correpondancy tabble
     *
     * @return void
     */
    protected function sortRelationshipsByType()
    {
        $entityClass = $this->getClass();

        // Instantiate a dummy entity which we will pass to relationship methods.
        $entity = unserialize(sprintf('O:%d:"%s":0:{}', strlen($entityClass), $entityClass));

        foreach ($this->relationships as $relation) {
            $this->$relation($entity);
        }
    }

    /**
     * Override this method for custom entity instantiation.
     *
     * @return null
     */
    public function activator()
    {
    }

    /**
     * Magic call to dynamic relationships.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws Exception
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (!array_key_exists($method, $this->dynamicRelationships)) {
            throw new Exception(get_class($this)." has no method $method");
        }

        // Add $this to parameters so the closure can call relationship method on the map.
        $parameters[] = $this;

        return  call_user_func_array([$this->dynamicRelationships[$method], $parameters]);
    }
}
