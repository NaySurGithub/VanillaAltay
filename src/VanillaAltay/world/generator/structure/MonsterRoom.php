<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

final class MonsterRoom implements UndergroundStructure{

	public function getName() : string{
		return "monster_room";
	}

	public function getPlacement() : StructurePlacement{
		return new StructurePlacement(0, 0, 1, fn(int $biomeId) => true);
	}

	public function getMinY() : int{
		return -59;
	}

	public function getMaxY() : int{
		return 60;
	}

	public function getAttempts() : int{
		return 8;
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void{
		if(!$this->canPlaceAt($world, $random, $x, $y, $z)){
			return;
		}

		$builder = new StructureBuilder($world, $x, $y, $z);
		$cobblestone = VanillaBlocks::COBBLESTONE();
		$mossy = VanillaBlocks::MOSSY_COBBLESTONE();

		for($dx = -3; $dx <= 3; ++$dx){
			for($dy = -1; $dy <= 3; ++$dy){
				for($dz = -3; $dz <= 3; ++$dz){
					if($builder->get($dx, $dy, $dz)->getTypeId() === BlockTypeIds::AIR){
						continue;
					}

					$edge = $dx === -3 || $dx === 3 || $dz === -3 || $dz === 3 || $dy === -1 || $dy === 3;
					$builder->set($dx, $dy, $dz, $edge ? $cobblestone : VanillaBlocks::AIR());
				}
			}
		}

		for($dx = -2; $dx <= 2; ++$dx){
			for($dz = -2; $dz <= 2; ++$dz){
				$builder->set($dx, -1, $dz, $random->nextBoundedInt(4) === 0 ? $cobblestone : $mossy);
				$builder->set($dx, 3, $dz, VanillaBlocks::AIR());
			}
		}

		for($i = 0; $i < 2; ++$i){
			$chestX = $random->nextBoolean() ? -2 : 2;
			$chestZ = $random->nextBoundedInt(5) - 2;

			foreach(Facing::HORIZONTAL as $facing){
				[$dx, $dy, $dz] = Facing::OFFSET[$facing];
				if($builder->get($chestX + $dx, $dy, $chestZ + $dz)->isSolid()){
					$builder->set($chestX, 0, $chestZ, VanillaBlocks::CHEST()->setFacing(Facing::opposite($facing)));
					break;
				}
			}
		}

		$builder->set(0, 0, 0, VanillaBlocks::MONSTER_SPAWNER());
	}

	/**
	 * A dungeon only forms in a pocket that has a floor, a ceiling and between one and five openings in its walls,
	 * which is what makes it appear against caves rather than inside solid stone.
	 */
	private function canPlaceAt(ChunkManager $world, Random $random, int $x, int $y, int $z) : bool{
		for($attempt = 0; $attempt < 8; ++$attempt){
			$halfX = $random->nextBoundedInt(2) + 2;
			$halfZ = $random->nextBoundedInt(2) + 2;

			$openings = 0;
			$valid = true;

			for($dx = -$halfX - 1; $dx <= $halfX + 1 && $valid; ++$dx){
				for($dy = -1; $dy <= 4 && $valid; ++$dy){
					for($dz = -$halfZ - 1; $dz <= $halfZ + 1; ++$dz){
						$solid = $world->getBlockAt($x + $dx, $y + $dy, $z + $dz)->isSolid();

						if(($dy === -1 || $dy === 4) && !$solid){
							$valid = false;
							break;
						}

						if($dy === 0 && ($dx === -$halfX - 1 || $dx === $halfX + 1 || $dz === -$halfZ - 1 || $dz === $halfZ + 1) &&
							$world->getBlockAt($x + $dx, $y + $dy + 1, $z + $dz)->getTypeId() === BlockTypeIds::AIR){
							++$openings;
						}
					}
				}
			}

			if($valid && $openings >= 1 && $openings <= 5){
				return true;
			}
		}

		return false;
	}
}
