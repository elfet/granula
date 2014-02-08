<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Granula;

use Granula\Meta\Field;
use Granula\Meta\Index;
use Granula\Type\EntityType;

class Meta
{
    /**
     * @var string
     */
    private $class;

    /**
     * @var string
     */
    private $alias;

    /**
     * @var string
     */
    private $table;

    /**
     * @var Field[]
     */
    private $fields = [];

    /**
     * @var Index[]
     */
    private $indexes = [];

    /**
     * @var Field[]
     */
    private $fieldsWhatHasEntities = [];

    public function __construct($class)
    {
        $this->class = $class;
    }

    public function table($table)
    {
        $this->table = $table;
        $this->alias = $this->table[0];
    }

    public function alias($alias)
    {
        $this->alias = $alias;
    }

    public function field($name, $type)
    {
        $this->fields[$name] = new Field($name, $type);

        if ($type === EntityType::name) {
            $this->fieldsWhatHasEntities[$name] = $this->fields[$name];
        }

        return $this->fields[$name];
    }

    public function index($columns, $name)
    {
        return $this->indexes[$name] = new Index($columns, $name);
    }

    public function getClass()
    {
        return $this->class;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getPrimaryField()
    {
        foreach ($this->fields as $field) {
            if ($field->isPrimary()) {
                return $field;
            }
        }

        throw new \RuntimeException('Entity does not contain primary field.');
    }

    /**
     * @return Field[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return Index[]
     */
    public function getIndexes()
    {
        return $this->indexes;
    }

    /**
     * @return Field[]
     */
    public function getFieldsWhatHasEntities()
    {
        return $this->fieldsWhatHasEntities;
    }

    /**
     * @return string
     */
    public function getPrimaryFieldNameWithAlias()
    {
        return $this->getAlias() . '.' . $this->getPrimaryField()->getName();
    }

    public function getSelect($prefix = '')
    {
        $select = [];

        foreach ($this->fields as $field) {
            $select[] = $this->getAlias()
                . '.'
                . $field->getName()
                . ' AS '
                . $prefix
                . $this->getAlias()
                . '_'
                . $field->getName();
        }

        return $select;
    }

    public function getJoinSelect(Field $field)
    {
        return $this->getSelect($field->getName() . '_');
    }
}