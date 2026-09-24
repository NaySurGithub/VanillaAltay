<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use customiesdevs\customies\block\BlockComponents;
use customiesdevs\customies\block\component\BlockComponent;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockToolType;
use pocketmine\block\BlockTypeInfo;
use pocketmine\math\AxisAlignedBB;
use Throwable;
use function count;

/**
 * A block defined by a behavior pack minecraft:block file. Every setting
 * comes from a plain definition array built by CustomBlockRegistrar, so the
 * block can be rebuilt on the async workers.
 *
 * @phpstan-type Definition array{
 *     identifier: string,
 *     name: string,
 *     hardness: float,
 *     blastResistance: float,
 *     lightLevel: int,
 *     lightFilter: int,
 *     frictionFactor: float,
 *     flammability: array{int, int}|null,
 *     collision: list<float>|null,
 *     components: array<string, mixed>,
 *     properties: list<array{string, list<bool|int|string>}>,
 *     permutations: list<array{string, array<string, mixed>}>,
 *     traits: array{cardinal: bool, yRotationOffset: int, facing: bool, blockFace: bool, verticalHalf: bool}
 * }
 */
class BehaviorBlock extends Block implements BlockComponents{

	/** @var array<string, BlockComponent> */
	private array $extraComponents = [];

	/**
	 * @phpstan-param Definition $definition
	 */
	public function __construct(
		BlockIdentifier $idInfo,
		BlockTypeInfo $typeInfo,
		protected array $definition
	){
		parent::__construct($idInfo, $definition["name"], $typeInfo);
	}

	/**
	 * @phpstan-param Definition $definition
	 */
	public static function create(int $typeId, array $definition) : Block{
		$typeInfo = new BlockTypeInfo(new BlockBreakInfo(
			$definition["hardness"],
			BlockToolType::NONE,
			0,
			$definition["blastResistance"]
		));
		if(count($definition["properties"]) > 0){
			return new BehaviorPermutableBlock(new BlockIdentifier($typeId), $typeInfo, $definition);
		}
		return new BehaviorBlock(new BlockIdentifier($typeId), $typeInfo, $definition);
	}

	public function getIdentifier() : string{
		return $this->definition["identifier"];
	}

	public function addComponent(BlockComponent $component) : void{
		$this->extraComponents[$component->getName()] = $component;
	}

	public function hasComponent(string $name) : bool{
		return isset($this->getComponents()[$name]);
	}

	/**
	 * @return array<string, BlockComponent>
	 */
	public function getComponents() : array{
		$components = [];
		foreach($this->definition["components"] as $name => $value){
			try{
				$component = BlockComponentMapper::map($name, $value);
			}catch(Throwable){
				$component = null;
			}
			if($component !== null){
				$components[$component->getName()] = $component;
			}
		}
		foreach($this->extraComponents as $name => $component){
			$components[$name] = $component;
		}
		return $components;
	}

	public function getLightLevel() : int{
		return $this->definition["lightLevel"];
	}

	public function getLightFilter() : int{
		return $this->definition["lightFilter"];
	}

	public function getFrictionFactor() : float{
		return $this->definition["frictionFactor"];
	}

	public function getFlameEncouragement() : int{
		return $this->definition["flammability"][0] ?? 0;
	}

	public function getFlammability() : int{
		return $this->definition["flammability"][1] ?? 0;
	}

	public function isSolid() : bool{
		return $this->definition["collision"] !== null;
	}

	protected function recalculateCollisionBoxes() : array{
		$box = $this->definition["collision"];
		if($box === null){
			return [];
		}
		return [new AxisAlignedBB($box[0], $box[1], $box[2], $box[3], $box[4], $box[5])];
	}
}
