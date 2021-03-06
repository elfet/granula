<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Granula\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Granula\Lazy;

class EntityType extends Type
{
    const name = 'entity';

    private $entityClassName;

    private $entityPrimaryFieldName;

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return $platform->getIntegerTypeDeclarationSQL($fieldDeclaration);
    }

    public function getName()
    {
        return self::name;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (null === $value) {
            return null;
        } elseif ($value instanceof Lazy) {
            return $value->getId();
        } else {
            $entity = new \ReflectionObject($value);
            $property = $entity->getProperty($this->entityPrimaryFieldName);
            $property->setAccessible(true);
            return $property->getValue($value);
        }
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return new Lazy($this->entityClassName, (int)$value);
    }

    /**
     * @param mixed $entityClassName
     */
    public function setEntityClassName($entityClassName)
    {
        $this->entityClassName = $entityClassName;
        $this->entityPrimaryFieldName = $entityClassName::meta()->getPrimaryField()->getName();
    }
}