<?php

declare(strict_types=1);

namespace dummy\block;

use pocketmine\block\Block;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeInfo;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

/**
 * A vanilla block the server does not implement. It keeps every vanilla state
 * so worlds are saved untouched, and uses the vanilla hardness, light and
 * flammability, but has no behaviour of its own.
 */
class DummyBlock extends Block{

	private int $stateIndex = 0;

	public function __construct(
		BlockIdentifier $idInfo,
		string $name,
		BlockTypeInfo $typeInfo,
		private DummyBlockType $type
	){
		parent::__construct($idInfo, $name, $typeInfo);
	}

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		if($this->type->getStateCount() > 1){
			$w->boundedIntAuto(0, $this->type->getStateCount() - 1, $this->stateIndex);
		}
	}

	public function getDummyType() : DummyBlockType{
		return $this->type;
	}

	public function getStateIndex() : int{
		return $this->stateIndex;
	}

	/**
	 * @return $this
	 */
	public function setStateIndex(int $stateIndex) : self{
		$this->stateIndex = $stateIndex;
		return $this;
	}

	public function getStateData() : BlockStateData{
		return $this->type->getState($this->stateIndex);
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->stateIndex = $this->type->orient($this->stateIndex, $face, $player?->getHorizontalFacing());
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function getLightLevel() : int{
		return $this->type->lightLevel;
	}

	public function isTransparent() : bool{
		return $this->type->transparent;
	}

	public function isSolid() : bool{
		return !$this->type->passable;
	}

	public function getFrictionFactor() : float{
		return $this->type->friction;
	}

	public function getFlameEncouragement() : int{
		return $this->type->flameEncouragement;
	}

	public function getFlammability() : int{
		return $this->type->flammability;
	}

	protected function recalculateCollisionBoxes() : array{
		if($this->type->passable){
			return [];
		}
		return parent::recalculateCollisionBoxes();
	}
}
