<?php


namespace storfollo\adtools;

use ArrayAccess;
use Symfony\Component\Ldap;


abstract class Entry implements ArrayAccess
{
    /**
     * @var Ldap\Ldap
     */
    protected $ldap;
    /**
     * @var Ldap\Entry
     */
    protected $entry;

    protected $entryManager;
    /**
     * @var string LDAP objectClass
     */
    protected $objectClass;
    /**
     * @var string Distinguished name
     */
    public $dn;

    function __construct(Ldap\Ldap $ldap, Ldap\Entry $entry)
    {
        $this->ldap = $ldap;
        $this->entry = $entry;
        $this->entryManager = $this->ldap->getEntryManager();
        $this->objectClass = static::objectClass;
        $this->dn = $this->entry->getDn();
    }

    /**
     * Get entry from DN
     * @param Ldap\Ldap $ldap LDAP instance
     * @param string $dn DN
     * @return static
     * @throws exceptions\MultipleHitsException
     */
    public static function from_dn(Ldap\Ldap $ldap, string $dn)
    {
        return self::from_query($ldap, $dn, sprintf('(objectClass=%s)', static::objectClass));
    }

    /**
     * Get entry from attribute
     * @param Ldap\Ldap $ldap
     * @param string $dn
     * @param string $attribute
     * @param string $value
     * @return static
     */
    public static function from_attribute(Ldap\Ldap $ldap, string $dn, string $attribute, string $value)
    {
        $query = sprintf('(&(%s=%s)(objectClass=%s))',
            adtools_utils::ldap_query_escape($attribute),
            adtools_utils::ldap_query_escape($value),
            static::objectClass
        );
        return self::from_query($ldap, $dn, $query);
    }

    /**
     * Create object from LDAP query
     * @param Ldap\Ldap $ldap
     * @param string $dn
     * @param string $query
     * @return static
     */
    public static function from_query(Ldap\Ldap $ldap, string $dn, string $query)
    {
        $result = $ldap->query($dn, $query)->execute();
        if ($result->count() > 1)
            throw new exceptions\MultipleHitsException($query);
        elseif($result->count() == 0)
            throw new exceptions\NoHitsException($query);

        $entry = $result->toArray()[0];
        return new static($ldap, $entry);
    }

    /**
     * @param Ldap\Ldap $ldap LDAP instance
     * @param string $dn DN
     * @param array $attributes Attributes
     * @return static
     */
    public static function add(Ldap\Ldap $ldap, string $dn, array $attributes)
    {
        foreach ($attributes as $key => $attribute)
        {
            if (!is_array($attribute))
                $attributes[$key] = [$attribute];
        }
        $entry = new Ldap\Entry($dn, $attributes);
        $ldap->getEntryManager()->add($entry);
        return static::from_dn($ldap, $dn);
    }

    public function offsetExists($offset): bool
    {
        return $this->entry->hasAttribute($offset);
    }

    /**
     * @param mixed $offset
     * @return array|string|null
     */
    public function offsetGet($offset)
    {
        $value = $this->entry->getAttribute($offset);
        if (!empty($value) && count($value) == 1)
            return $value[0];
        else
            return $value;
    }

    public function offsetSet($offset, $value)
    {
        $this->entry->setAttribute($offset, $value);
        $this->entryManager->update($this->entry);
    }

    public function offsetUnset($offset)
    {
        $this->entry->removeAttribute($offset);
        $this->entryManager->update($this->entry);
    }

    function move(string $newParent): string
    {
        $this->entryManager->move($this->entry, $newParent);
        $cn = preg_replace('/CN=(.+?),[A-Z]{2}.+/i', '$1', $this->entry->getDn());
        return sprintf('CN=%s,%s', $cn, $newParent);
    }
}