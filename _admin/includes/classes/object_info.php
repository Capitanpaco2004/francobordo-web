<?php
class objectInfo
{
    public array $properties = [];

    public function __construct(array $object_array)
    {
        $this->addProperties($object_array);
    }

    public function &__get(string $key)
    {
        if (!array_key_exists($key, $this->properties)) {
            $this->properties[$key] = null;
        }
        return $this->properties[$key];
    }

    public function __set(string $key, $value): void
    {
        $this->properties[$key] = $value;
    }

    public function __isset(string $key)
    {
        return array_key_exists($key, $this->properties);
    }

    public function __unset(string $key)
    {
        unset($this->properties[$key]);
    }

    public function addProperties(array $object_array)
    {
        foreach ($object_array as $key => $value) {
            $this->properties[$key] = tep_db_prepare_input($value);
        }
    }
}
