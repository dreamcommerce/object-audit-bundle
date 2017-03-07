# DreamCommerce Object Audit Bundle

This is a fork of the [SimpleThings EntityAudit](https://github.com/simplethings/EntityAudit) project.

## Installation (Standalone)

###Installing the lib/bundle

Simply run assuming you have installed composer.phar or composer binary:

``` bash
$ composer require dreamcommerce/object-audit-bundle
```

## Installation (In Symfony Application)

###Enable the bundle

Enable the bundle in the kernel:

``` php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        //...
            new DreamCommerce\Bundle\CommonBundle\DreamCommerceCommonBundle(),
            new DreamCommerce\Bundle\ObjectAuditBundle\DreamCommerceObjectAuditBundle(),
        //...
    );
    return $bundles;
}
```

###Configuration

You can configure the audited tables. 

#####app/config/config.yml
```yml
dream_commerce_object_audit:
    object_manager: default
    table_prefix: ''
    table_suffix: _audit
    revision_id_field_prefix: revision_
    revision_id_field_suffix: ''
    revision_type_field_name: revision_type
    revision_type_field_type: enumRevisionUInt8Type
```

###Creating new tables

Call the command below to see the new tables in the update schema queue.

```bash
./app/console doctrine:schema:update --dump-sql 
```

## Usage

### Define auditable entities
 
You need add `Auditable` annotation for the entities which you want to auditable.
  
```php
use Doctrine\ORM\Mapping as ORM;
use DreamCommerce\Component\ObjectAudit\Mapping\Annotation as Audit;

/**
 * @ORM\Entity()
 * @Audit\Auditable()
 */
class Page {
 //...
}
```

You can also ignore fields in an specific entity.
 
```php
class Page {

    /**
     * @ORM\Column(type="string")
     * @Audit\Ignore()
     */
    private $ignoreMe;

}
``` 

Or if you prefer XML:

```
<?xml version="1.0" encoding="UTF-8"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xmlns:dreamcommerce="https://dreamcommerce.com/schemas/orm/doctrine-object-audit-mapping"
                  xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping http://doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <entity name="Page">
        <dreamcommerce:auditable />
        
        <field name="ignoreMe" type="string">
            <dreamcommerce:ignore/>
        </field>
    </entity>
</doctrine-mapping>
```

Or YAML:

```
Page:
  type: entity
  dreamcommerce:
    auditable: true
  id:
    id:
      type: integer
      generator:
        strategy: AUTO
  fields:
    ignoreMe:
      type: string
      dreamcommerce:
        ignore: true
```