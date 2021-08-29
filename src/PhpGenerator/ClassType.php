<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\PhpGenerator;

use Nette;


/**
 * Class/Interface/Trait/Enum description.
 *
 * @property Method[] $methods
 * @property Property[] $properties
 */
final class ClassType
{
	use Nette\SmartObject;
	use Traits\CommentAware;
	use Traits\AttributeAware;

	public const
		TYPE_CLASS = 'class',
		TYPE_INTERFACE = 'interface',
		TYPE_TRAIT = 'trait',
		TYPE_ENUM = 'enum';

	public const
		VISIBILITY_PUBLIC = 'public',
		VISIBILITY_PROTECTED = 'protected',
		VISIBILITY_PRIVATE = 'private';

	private ?PhpNamespace $namespace;

	private ?string $name;

	/** class|interface|trait */
	private string $type = self::TYPE_CLASS;

	private bool $final = false;

	private bool $abstract = false;

	/** @var string|string[] */
	private $extends = [];

	/** @var string[] */
	private array $implements = [];

	/** @var array[] */
	private array $traits = [];

	/** @var Constant[] name => Constant */
	private array $consts = [];

	/** @var Property[] name => Property */
	private array $properties = [];

	/** @var Method[] name => Method */
	private array $methods = [];

	/** @var EnumCase[] name => EnumCase */
	private $cases = [];


	public static function class(string $name = null, PhpNamespace $namespace = null): self
	{
		return new self($name, $namespace);
	}


	public static function interface(string $name = null, PhpNamespace $namespace = null): self
	{
		return (new self($name, $namespace))->setType(self::TYPE_INTERFACE);
	}


	public static function trait(string $name = null, PhpNamespace $namespace = null): self
	{
		return (new self($name, $namespace))->setType(self::TYPE_TRAIT);
	}


	public static function enum(string $name = null, PhpNamespace $namespace = null): self
	{
		return (new self($name, $namespace))->setType(self::TYPE_ENUM);
	}


	public static function from(string|object $class): self
	{
		return (new Factory)->fromClassReflection(new \ReflectionClass($class));
	}


	public static function withBodiesFrom(string|object $class): self
	{
		return (new Factory)->fromClassReflection(new \ReflectionClass($class), true);
	}


	public function __construct(string $name = null, PhpNamespace $namespace = null)
	{
		$this->setName($name);
		$this->namespace = $namespace;
	}


	public function __toString(): string
	{
		return (new Printer)->printClass($this, $this->namespace);
	}


	/** @deprecated  an object can be in multiple namespaces */
	public function getNamespace(): ?PhpNamespace
	{
		return $this->namespace;
	}


	public function setName(?string $name): static
	{
		if ($name !== null && !Helpers::isIdentifier($name)) {
			throw new Nette\InvalidArgumentException("Value '$name' is not valid class name.");
		}
		$this->name = $name;
		return $this;
	}


	public function getName(): ?string
	{
		return $this->name;
	}


	/** @deprecated */
	public function setClass(): static
	{
		$this->type = self::TYPE_CLASS;
		return $this;
	}


	public function isClass(): bool
	{
		return $this->type === self::TYPE_CLASS;
	}


	public function setInterface(): static
	{
		$this->type = self::TYPE_INTERFACE;
		return $this;
	}


	public function isInterface(): bool
	{
		return $this->type === self::TYPE_INTERFACE;
	}


	public function setTrait(): static
	{
		$this->type = self::TYPE_TRAIT;
		return $this;
	}


	public function isTrait(): bool
	{
		return $this->type === self::TYPE_TRAIT;
	}


	public function isEnum(): bool
	{
		return $this->type === self::TYPE_ENUM;
	}


	public function setType(string $type): static
	{
		if (!in_array($type, [self::TYPE_CLASS, self::TYPE_INTERFACE, self::TYPE_TRAIT, self::TYPE_ENUM], true)) {
			throw new Nette\InvalidArgumentException('Argument must be class|interface|trait|enum.');
		}
		$this->type = $type;
		return $this;
	}


	public function getType(): string
	{
		return $this->type;
	}


	public function setFinal(bool $state = true): static
	{
		$this->final = $state;
		return $this;
	}


	public function isFinal(): bool
	{
		return $this->final;
	}


	public function setAbstract(bool $state = true): static
	{
		$this->abstract = $state;
		return $this;
	}


	public function isAbstract(): bool
	{
		return $this->abstract;
	}


	/**
	 * @param  string|string[]  $names
	 */
	public function setExtends(string|array $names): static
	{
		$this->validateNames((array) $names);
		$this->extends = $names;
		return $this;
	}


	/** @return string|string[] */
	public function getExtends(): string|array
	{
		return $this->extends;
	}


	public function addExtend(string $name): static
	{
		$this->validateNames([$name]);
		$this->extends = (array) $this->extends;
		$this->extends[] = $name;
		return $this;
	}


	/**
	 * @param  string[]  $names
	 */
	public function setImplements(array $names): static
	{
		$this->validateNames($names);
		$this->implements = $names;
		return $this;
	}


	/** @return string[] */
	public function getImplements(): array
	{
		return $this->implements;
	}


	public function addImplement(string $name): static
	{
		$this->validateNames([$name]);
		$this->implements[] = $name;
		return $this;
	}


	public function removeImplement(string $name): static
	{
		$key = array_search($name, $this->implements, true);
		if ($key !== false) {
			unset($this->implements[$key]);
		}
		return $this;
	}


	/**
	 * @param  string[]  $names
	 */
	public function setTraits(array $names): static
	{
		$this->validateNames($names);
		$this->traits = array_fill_keys($names, []);
		return $this;
	}


	/** @return string[] */
	public function getTraits(): array
	{
		return array_keys($this->traits);
	}


	/** @internal */
	public function getTraitResolutions(): array
	{
		return $this->traits;
	}


	public function addTrait(string $name, array $resolutions = []): static
	{
		$this->validateNames([$name]);
		$this->traits[$name] = $resolutions;
		return $this;
	}


	public function removeTrait(string $name): static
	{
		unset($this->traits[$name]);
		return $this;
	}


	public function addMember(Method|Property|Constant|EnumCase $member): static
	{
		if ($member instanceof Method) {
			$this->methods[$member->getName()] = $member;

		} elseif ($member instanceof Property) {
			$this->properties[$member->getName()] = $member;

		} elseif ($member instanceof Constant) {
			$this->consts[$member->getName()] = $member;

		} elseif ($member instanceof EnumCase) {
			$this->cases[$member->getName()] = $member;
		}

		return $this;
	}


	/**
	 * @param  Constant[]|mixed[]  $consts
	 */
	public function setConstants(array $consts): static
	{
		$this->consts = [];
		foreach ($consts as $k => $v) {
			$const = $v instanceof Constant
				? $v
				: (new Constant($k))->setValue($v);
			$this->consts[$const->getName()] = $const;
		}
		return $this;
	}


	/** @return Constant[] */
	public function getConstants(): array
	{
		return $this->consts;
	}


	public function addConstant(string $name, $value): Constant
	{
		return $this->consts[$name] = (new Constant($name))->setValue($value);
	}


	public function removeConstant(string $name): static
	{
		unset($this->consts[$name]);
		return $this;
	}


	/**
	 * Sets cases to enum
	 * @param  EnumCase[]  $consts
	 */
	public function setCases(array $cases): static
	{
		(function (EnumCase ...$cases) {})(...$cases);
		$this->cases = [];
		foreach ($cases as $case) {
			$this->cases[$case->getName()] = $case;
		}
		return $this;
	}


	/** @return EnumCase[] */
	public function getCases(): array
	{
		return $this->cases;
	}


	/** Adds case to enum */
	public function addCase(string $name, string|int $value = null): EnumCase
	{
		return $this->cases[$name] = (new EnumCase($name))->setValue($value);
	}


	public function removeCase(string $name): static
	{
		unset($this->cases[$name]);
		return $this;
	}


	/**
	 * @param  Property[]  $props
	 */
	public function setProperties(array $props): static
	{
		$this->properties = [];
		foreach ($props as $v) {
			if (!$v instanceof Property) {
				throw new Nette\InvalidArgumentException('Argument must be Nette\PhpGenerator\Property[].');
			}
			$this->properties[$v->getName()] = $v;
		}
		return $this;
	}


	/** @return Property[] */
	public function getProperties(): array
	{
		return $this->properties;
	}


	public function getProperty(string $name): Property
	{
		if (!isset($this->properties[$name])) {
			throw new Nette\InvalidArgumentException("Property '$name' not found.");
		}
		return $this->properties[$name];
	}


	/**
	 * @param  string  $name  without $
	 */
	public function addProperty(string $name, $value = null): Property
	{
		return $this->properties[$name] = func_num_args() > 1
			? (new Property($name))->setValue($value)
			: new Property($name);
	}


	/**
	 * @param  string  $name without $
	 */
	public function removeProperty(string $name): static
	{
		unset($this->properties[$name]);
		return $this;
	}


	public function hasProperty(string $name): bool
	{
		return isset($this->properties[$name]);
	}


	/**
	 * @param  Method[]  $methods
	 */
	public function setMethods(array $methods): static
	{
		$this->methods = [];
		foreach ($methods as $v) {
			if (!$v instanceof Method) {
				throw new Nette\InvalidArgumentException('Argument must be Nette\PhpGenerator\Method[].');
			}
			$this->methods[$v->getName()] = $v;
		}
		return $this;
	}


	/** @return Method[] */
	public function getMethods(): array
	{
		return $this->methods;
	}


	public function getMethod(string $name): Method
	{
		if (!isset($this->methods[$name])) {
			throw new Nette\InvalidArgumentException("Method '$name' not found.");
		}
		return $this->methods[$name];
	}


	public function addMethod(string $name): Method
	{
		$method = new Method($name);
		if (!$this->isInterface()) {
			$method->setPublic();
		}
		return $this->methods[$name] = $method;
	}


	public function removeMethod(string $name): static
	{
		unset($this->methods[$name]);
		return $this;
	}


	public function hasMethod(string $name): bool
	{
		return isset($this->methods[$name]);
	}


	/** @throws Nette\InvalidStateException */
	public function validate(): void
	{
		if ($this->abstract && $this->final) {
			throw new Nette\InvalidStateException('Class cannot be abstract and final.');

		} elseif ($this->isEnum() && ($this->abstract || $this->final || $this->extends || $this->properties)) {
			throw new Nette\InvalidStateException('Enum cannot be abstract or final or extends class or have properties.');

		} elseif (!$this->name && ($this->abstract || $this->final)) {
			throw new Nette\InvalidStateException('Anonymous class cannot be abstract or final.');
		}
	}


	private function validateNames(array $names): void
	{
		foreach ($names as $name) {
			if (!Helpers::isNamespaceIdentifier($name, true)) {
				throw new Nette\InvalidArgumentException("Value '$name' is not valid class name.");
			}
		}
	}


	public function __clone()
	{
		$clone = fn($item) => clone $item;
		$this->cases = array_map($clone, $this->cases);
		$this->consts = array_map($clone, $this->consts);
		$this->properties = array_map($clone, $this->properties);
		$this->methods = array_map($clone, $this->methods);
	}
}
