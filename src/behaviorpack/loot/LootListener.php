<?php

declare(strict_types=1);

namespace behaviorpack\loot;

use pocketmine\block\Block;
use pocketmine\entity\Human;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\Listener;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\player\Player;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use Throwable;

/**
 * Replaces the drops of the blocks and entities whose behavior pack
 * definition declares a loot table.
 */
final class LootListener implements Listener{

	/**
	 * @param array<string, string> $blockTables  block identifier => loot table path
	 * @param array<string, string> $entityTables entity identifier => loot table path
	 */
	public function __construct(
		private array $blockTables,
		private array $entityTables
	){
	}

	/**
	 * @priority HIGH
	 */
	public function onBlockBreak(BlockBreakEvent $event) : void{
		$block = $event->getBlock();
		$identifier = self::blockIdentifier($block);
		if($identifier === null || !isset($this->blockTables[$identifier])){
			return;
		}
		$player = $event->getPlayer();
		if(!$player->hasFiniteResources()){
			$event->setDrops([]);
			return;
		}
		$tool = $event->getItem();
		if($tool->hasEnchantment(VanillaEnchantments::SILK_TOUCH())){
			$event->setDrops([$block->asItem()]);
			return;
		}
		$table = LootTableRegistry::get($this->blockTables[$identifier]);
		if($table === null){
			return;
		}
		$event->setDrops($table->roll(new LootContext(null, $player, $tool, LootItems::lootingLevel($tool))));
	}

	/**
	 * @priority HIGH
	 */
	public function onEntityDeath(EntityDeathEvent $event) : void{
		$entity = $event->getEntity();
		if($entity instanceof Player){
			return;
		}
		$path = $this->entityTables[$entity::getNetworkTypeId()] ?? null;
		if($path === null){
			return;
		}
		$table = LootTableRegistry::get($path);
		if($table === null){
			return;
		}
		$killer = null;
		$cause = $entity->getLastDamageCause();
		if($cause instanceof EntityDamageByEntityEvent){
			$killer = $cause->getDamager();
		}
		$tool = $killer instanceof Human ? $killer->getInventory()->getItemInHand() : null;
		$event->setDrops($table->roll(new LootContext($entity, $killer, $tool, LootItems::lootingLevel($tool))));
	}

	private static function blockIdentifier(Block $block) : ?string{
		try{
			return GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->getName();
		}catch(Throwable){
			return null;
		}
	}
}
