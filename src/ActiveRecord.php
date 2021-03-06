<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Granula;

use Doctrine\DBAL\Query\QueryBuilder;
use Granula\Mapper\ResultMapper;
use Granula\Meta\Accessor;
use Granula\Meta\SqlGenerator;
use Granula\Type\EntityType;

trait ActiveRecord
{
    /**
     * @param string $sql
     * @param array $params
     * @param array $types
     * @param callable $map
     * @return \Generator
     */
    public static function query($sql, $params = [], $types = [], $map = null)
    {
        $em = EntityManager::getInstance();
        $conn = $em->getConnection();

        // Use mapper for current meta class.
        if (null === $map) {
            $class = get_called_class();

            $mapper = new ResultMapper();
            $mapper->setRootEntity($class::meta(), false);

            $map = function ($result) use ($mapper, $conn) {
                return $mapper->map($result, $conn->getDatabasePlatform());
            };
        } elseif ($map instanceof ResultMapper) {
            $mapper = $map;
            $map = function ($result) use ($mapper, $conn) {
                return $mapper->map($result, $conn->getDatabasePlatform());
            };
        }

        $st = Query::create($sql)->params($params)->types($types)->run($conn);

        while ($result = $st->fetch()) {
            yield $map($result);
        }
    }

    /**
     *  Update row.
     */
    public function save()
    {
        $this->insertOrUpdate(false);
    }

    /**
     * Insert new row.
     */
    public function create()
    {
        $this->insertOrUpdate(true);
    }

    /**
     * @param bool $isNewEntity Insert or update row.
     */
    private function insertOrUpdate($isNewEntity)
    {
        $meta = self::meta();
        $em = EntityManager::getInstance();
        $conn = $em->getConnection();
        $primary = $meta->getPrimaryField()->getName();

        $data = [];
        foreach ($meta->getFields() as $name => $field) {
            $data[$name] = $field->getType()->convertToDatabaseValue($this->$name, $conn->getDatabasePlatform());
        }

        if ($isNewEntity) {
            $conn->insert($meta->getTable(), $data);
            $this->$primary = $conn->lastInsertId();
        } else {
            $conn->update($meta->getTable(), $data, [$primary => $this->$primary]);
        }
    }

    /**
     * Delete current entity.
     */
    public function delete()
    {
        $meta = self::meta();
        $em = EntityManager::getInstance();
        $em->getConnection()->delete(
            $meta->getTable(),
            [$meta->getPrimaryField()->getName() => Accessor::create($meta)->getPrimary($this)]
        );
    }


    /**
     * @return \Generator
     */
    public static function all()
    {
        $meta = self::meta();
        $qb = self::createQueryBuilder();

        $qb
            ->select($meta->getSelect())
            ->from($meta->getTable(), $meta->getAlias());

        return self::query($qb->getSQL());
    }

    /**
     * @param integer $id
     * @return mixed|null
     */
    public static function find($id)
    {
        if (null === $id) {
            return null;
        }

        $em = EntityManager::getInstance();
        $meta = self::meta();
        $entity = $em->find($meta->getClass(), $id);

        if (null === $entity) {

            $sqlGenerator = new SqlGenerator($meta);
            $mapper = new ResultMapper();
            $qb = self::createQueryBuilder();

            $qb
                ->select($sqlGenerator->getSelect())
                ->from($meta->getTable(), $meta->getAlias())
                ->where($qb->expr()->eq(
                    $sqlGenerator->getPrimaryFieldNameWithAlias(),
                    '?'
                ))
                ->setMaxResults(1);

            $mapper->setRootEntity($meta);

            foreach ($meta->getFieldsWhatHasEntities() as $field) {
                $class = $field->getEntityClass();
                /** @var $entityMeta Meta */
                $entityMeta = $class::meta();
                $entitySqlGenerator = new SqlGenerator($entityMeta);
                $alias = $field->getName();

                $qb->addSelect($entitySqlGenerator->getSelect($alias));
                $qb->leftJoin($meta->getAlias(), $entityMeta->getTable(), $alias,
                    $qb->expr()->eq(
                        $meta->getAlias() . '.' . $field->getName(),
                        $entitySqlGenerator->getPrimaryFieldNameWithAlias($alias)
                    )
                );

                $mapper->addJoinedEntity($field, $entityMeta, $alias);
            }

            $result = self::query($qb->getSQL(), [$id], [\PDO::PARAM_INT], $mapper);
            $entity = $result->valid() ? $result->current() : null;
            $em->persist($entity);
        }

        return $entity;
    }

    /**
     * @return QueryBuilder
     */
    public static function createQueryBuilder()
    {
        $em = EntityManager::getInstance();
        return $em->getConnection()->createQueryBuilder();
    }

    /**
     * @return Meta
     */
    public static function meta()
    {
        $em = EntityManager::getInstance();
        return $em->getMetaForClass(get_called_class());
    }

    /**
     * @param $id
     * @return Lazy
     */
    public static function lazy($id)
    {
        return new Lazy(get_called_class(), $id);
    }

    /**
     * @param $field
     */
    public function load($field)
    {
        $lazy = $this->$field;

        if ($lazy instanceof Lazy) {
            $this->$field = $lazy->load();
        }
    }
}