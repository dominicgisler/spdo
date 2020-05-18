<?php

namespace Gisler\Spdo;

use PDO;
use PDOException;
use ReflectionException;

/**
 * Class AbstractRepository
 * @package Gisler\Spdo
 */
abstract class AbstractRepository
{
    const ERR_NO_KEY = 'No primary key given';

    /**
     * @var PDO
     */
    protected $pdo = null;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var string
     */
    protected $class;

    /**
     * @param PDO $pdo
     * @param string $table
     * @param string $key
     * @param string $class
     */
    public function __construct(PDO $pdo, string $table, string $key, string $class)
    {
        $this->table = $table;
        $this->key = $key;
        $this->class = $class;
        $this->pdo = $pdo;
    }

    /**
     * @param AbstractModel $object
     * @return int|bool
     * @throws SpdoException|ReflectionException
     */
    public function save(AbstractModel &$object)
    {
        $this->validateObject($object);

        if (intval($object->{$this->key})) {
            return $this->update($object);
        } else {
            $object->{$this->key} = $this->insert($object);
            return $object->{$this->key};
        }
    }

    /**
     * @param AbstractModel $object
     * @return bool
     * @throws SpdoException
     */
    public function delete(AbstractModel $object): bool
    {
        $this->validateObject($object);

        $key = intval($object->{$this->key});
        if ($key <= 0) {
            throw new SpdoException(self::ERR_NO_KEY);
        }

        $pattern = 'DELETE FROM `%s` WHERE `%s` = :key';
        $stmt = $this->pdo->prepare(sprintf($pattern, $this->table, $this->key));
        $stmt->bindParam('key', $key, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * @param AbstractModel $object
     * @return int
     * @throws SpdoException|ReflectionException
     */
    public function insert(AbstractModel &$object): int
    {
        $this->validateObject($object);

        $item = $object->getArrayCopy();

        if (array_key_exists($this->key, $item)) {
            unset($item[$this->key]);
        }

        $stmt = $this->pdo->prepare(
            sprintf(
                'INSERT INTO `%s` (%s) VALUES (%s)',
                $this->table,
                $this->getInsertFields($item),
                $this->getInsertMarks($item)
            )
        );

        $stmt->execute(array_values($item));
        $object->{$this->key} = (int)$this->pdo->lastInsertId();

        return (int)$object->{$this->key};
    }

    /**
     * @param AbstractModel $object
     * @return bool
     * @throws SpdoException|ReflectionException
     */
    public function update(AbstractModel &$object): bool
    {
        $this->validateObject($object);

        $key = (int)$object->{$this->key};
        $item = $object->getArrayCopy();
        unset($item[$this->key]);

        $stmt = $this->pdo->prepare(
            sprintf(
                'UPDATE `%s` SET %s WHERE `%s` = ?',
                $this->table,
                $this->getUpdateString($item),
                $this->key
            )
        );

        $stmt->execute(array_merge(array_values($item), [$key]));

        return $key;
    }

    /**
     * @param Collection $col
     * @param bool $validateObjects
     * @return bool|PDOException
     * @throws SpdoException|ReflectionException
     */
    public function insertCollection(Collection $col, bool $validateObjects = false)
    {
        if ($col->count() == 0) {
            return true;
        }

        $names = [];
        $marks = [];
        $values = [];

        /** @var AbstractModel $object */
        foreach ($col as $object) {
            if ($validateObjects) {
                $this->validateObject($object);
            }

            $item = $object->getArrayCopy();

            if (array_key_exists($this->key, $item)) {
                unset($item[$this->key]);
            }

            if (sizeof($names) == 0) {
                $names = $item;
            }

            $marks[] = '(' . $this->getInsertMarks($item) . ')';

            foreach (array_values($item) as $value) {
                $values[] = $value;
            }
        }

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES %s',
            $this->table,
            $this->getInsertFields($names),
            implode(',', $marks)
        );

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            return $exception;
        }

        return true;
    }

    /**
     * @param Collection $col
     * @param bool $validateObjects
     * @throws ReflectionException|SpdoException
     */
    public function updateCollection(Collection $col, bool $validateObjects = false)
    {
        if ($col->count() == 0) {
            return;
        }

        $namesFull = [];
        $namesNoId = [];
        $marks = [];
        $values = [];

        /** @var AbstractModel $object */
        foreach ($col as $object) {
            if ($validateObjects) {
                $this->validateObject($object);
            }

            $item = $object->getArrayCopy();

            if (sizeof($namesFull) == 0) {
                $namesFull = $namesNoId = $item;
                if (array_key_exists($this->key, $namesNoId)) {
                    unset($namesNoId[$this->key]);
                }
            }

            $marks[] = '(' . $this->getInsertMarks($item) . ')';

            foreach (array_values($item) as $value) {
                $values[] = $value;
            }
        }

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES %s ON DUPLICATE KEY UPDATE %s',
            $this->table,
            $this->getInsertFields($namesFull),
            implode(',', $marks),
            $this->getOnDuplicate($namesNoId)
        );

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return;
    }

    /**
     * @return Collection
     */
    public function getAll(): Collection
    {
        return $this->get();
    }

    /**
     * @param array $params
     * @param array $fields
     * @return Collection
     */
    public function get(array $params = [], array $fields = []): Collection
    {
        $sql = sprintf(
            'SELECT %s FROM `%s`',
            sizeof($fields) ? $this->getSelectFields($fields) : '*',
            $this->table
        );

        if (sizeof($params)) {
            $sql = sprintf('%s WHERE %s', $sql, $this->getAndMarks($params));
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(sizeof($params) ? array_values($params) : null);

        return new Collection(
            $stmt->fetchAll(PDO::FETCH_CLASS, $this->class)
        );
    }

    /**
     * @param array $params
     * @param array $fields
     * @return AbstractModel|null
     */
    public function getObject(array $params = [], array $fields = [])
    {
        $col = $this->get($params, $fields);

        if ($col->count() > 0) {
            return $col->offsetGet(0);
        }

        return null;
    }

    /**
     * @param array $array
     * @return string
     */
    private function getSelectFields(array $array): string
    {
        return '`' . implode('`, `', $array) . '`';
    }

    /**
     * @param array $array
     * @return string
     */
    private function getInsertFields(array $array): string
    {
        return '`' . implode('`, `', array_keys($array)) . '`';
    }

    /**
     * @param array $array
     * @return string
     */
    private function getInsertMarks(array $array): string
    {
        return implode(',', array_fill(0, count($array), '?'));
    }

    /**
     * @param array $array
     * @return string
     */
    private function getUpdateString(array $array): string
    {
        return '`' . implode('` = ?, `', array_keys($array)) . '` = ?';
    }

    /**
     * @param array $array
     * @return string
     */
    private function getAndMarks(array $array): string
    {
        return '`' . implode('` = ? AND `', array_keys($array)) . '` = ?';
    }

    /**
     * @param array $array
     * @return string
     */
    private function getOnDuplicate(array $array): string
    {
        $ret = [];

        foreach (array_keys($array) as $key) {
            $ret[] = sprintf('`%s` = VALUES(`%s`)', $key, $key);
        }

        return implode(', ', $ret);
    }

    /**
     * @param AbstractModel $object
     * @throws SpdoException
     */
    private function validateObject(AbstractModel $object)
    {
        if (!($object instanceof AbstractModel)) {
            throw new SpdoException('Given object does not extend AbstractModel');
        }

        if (!($object instanceof $this->class)) {
            throw new SpdoException('Given object is not of type ' . (string)$this->class);
        }
    }
}
