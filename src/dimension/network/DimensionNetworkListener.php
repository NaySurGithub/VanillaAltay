<?php

declare(strict_types=1);

namespace dimension\network;

use dimension\Dimensions;
use pocketmine\block\RespawnAnchor;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPostChunkSendEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\SetSpawnPositionPacket;
use pocketmine\network\mcpe\protocol\SpawnParticleEffectPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\SpawnSettings;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use WeakMap;
use function array_key_first;
use function count;

/**
 * Makes the client believe it is in the dimension of the world it stands in.
 *
 * - StartGamePacket carries the dimension of the join world.
 * - Every world change is detected on the first packet the session sends
 *   afterwards (the core always sends the new world's time right after the
 *   switch, before any chunk of the new world). When the dimension differs,
 *   a ChangeDimensionPacket is queued ahead of it, the chunk cache of the new
 *   world is set to that dimension, and once the chunk under the player has
 *   been sent the server acknowledges the change (PlayerAction
 *   DIMENSION_CHANGE_ACK) so the client leaves its loading screen.
 * - Chunks of nether/end worlds are serialized for their height and carry
 *   their dimension id (see ChunkCacheDimension).
 * - SetSpawnPosition, particle effects and the death dimension metadata get
 *   the right dimension; dying in the nether or the end without a respawn
 *   anchor respawns at the overworld spawn.
 *
 * There is no transfer API: portals and any other code just call
 * Player::teleport() with a Position in the target world, which works for
 * every path that moves a player across worlds (commands, respawn, plugins).
 */
final class DimensionNetworkListener implements Listener{

	/** @var WeakMap<NetworkSession, int> */
	private WeakMap $clientDimensions;
	/** @var WeakMap<NetworkSession, int> */
	private WeakMap $clientWorlds;
	/** @var WeakMap<NetworkSession, true> */
	private WeakMap $pendingAcks;

	public function __construct(
		private PluginBase $plugin,
		private Dimensions $dimensions
	){
		$this->clientDimensions = new WeakMap();
		$this->clientWorlds = new WeakMap();
		$this->pendingAcks = new WeakMap();
	}

	/**
	 * @priority HIGHEST
	 */
	public function onDataPacketSend(DataPacketSendEvent $event) : void{
		$targets = $event->getTargets();
		foreach($targets as $session){
			$this->syncWorld($session);
		}

		$player = count($targets) === 1 ? $targets[array_key_first($targets)]->getPlayer() : null;
		foreach($event->getPackets() as $packet){
			if($packet instanceof StartGamePacket){
				$this->onStartGame($targets, $packet);
			}elseif($packet instanceof SetSpawnPositionPacket && $player !== null){
				$this->rewriteSpawnPosition($player, $packet);
			}elseif($packet instanceof SpawnParticleEffectPacket){
				$this->rewriteParticle($targets, $packet);
			}elseif($packet instanceof SetActorDataPacket || $packet instanceof AddPlayerPacket){
				$packet->metadata = $this->rewriteDeathDimension($packet->actorRuntimeId, $packet->metadata);
			}
		}
	}

	/**
	 * @priority HIGHEST
	 */
	public function onDataPacketReceive(DataPacketReceiveEvent $event) : void{
		$packet = $event->getPacket();
		if($packet instanceof PlayerActionPacket && $packet->action === PlayerAction::DIMENSION_CHANGE_ACK){
			$event->cancel();
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onPostChunkSend(PlayerPostChunkSendEvent $event) : void{
		$player = $event->getPlayer();
		$session = $player->getNetworkSession();
		if(!isset($this->pendingAcks[$session])){
			return;
		}
		$position = $player->getPosition();
		if($event->getChunkX() !== ($position->getFloorX() >> Chunk::COORD_BIT_SIZE) || $event->getChunkZ() !== ($position->getFloorZ() >> Chunk::COORD_BIT_SIZE)){
			return;
		}
		unset($this->pendingAcks[$session]);
		$blockPosition = BlockPosition::fromVector3($position);
		$session->sendDataPacket(PlayerActionPacket::create($player->getId(), PlayerAction::DIMENSION_CHANGE_ACK, $blockPosition, $blockPosition, 0));
	}

	/**
	 * @priority HIGH
	 */
	public function onRespawn(PlayerRespawnEvent $event) : void{
		$position = $event->getRespawnPosition();
		if(!$position->isValid()){
			return;
		}
		$dimension = $this->dimensions->getDimension($position->getWorld());
		if($dimension === DimensionIds::OVERWORLD){
			return;
		}
		if($dimension === DimensionIds::NETHER && $position->getWorld()->getBlock($position) instanceof RespawnAnchor){
			return;
		}
		$overworld = $this->plugin->getServer()->getWorldManager()->getDefaultWorld();
		if($overworld !== null){
			$event->setRespawnPosition($overworld->getSpawnLocation());
		}
	}

	/**
	 * @param NetworkSession[] $targets
	 */
	private function onStartGame(array $targets, StartGamePacket $packet) : void{
		foreach($targets as $session){
			$player = $session->getPlayer();
			if($player === null || !$player->getLocation()->isValid()){
				continue;
			}
			$world = $player->getWorld();
			$dimension = $this->dimensions->getDimension($world);
			$this->clientDimensions[$session] = $dimension;
			$this->clientWorlds[$session] = $world->getId();
			ChunkCacheDimension::apply($world, $session->getCompressor(), $dimension);

			$settings = $packet->levelSettings->spawnSettings;
			$packet->levelSettings->spawnSettings = new SpawnSettings($settings->getBiomeType(), $settings->getBiomeName(), $dimension);
		}
	}

	/**
	 * Detects that the session's player moved to another world since the
	 * last packet and switches the client's dimension when needed.
	 */
	private function syncWorld(NetworkSession $session) : void{
		if(!isset($this->clientWorlds[$session])){
			return;
		}
		$player = $session->getPlayer();
		if($player === null || !$player->getLocation()->isValid()){
			return;
		}
		$world = $player->getWorld();
		if($this->clientWorlds[$session] === $world->getId()){
			return;
		}
		$this->clientWorlds[$session] = $world->getId();
		$dimension = $this->dimensions->getDimension($world);
		ChunkCacheDimension::apply($world, $session->getCompressor(), $dimension);
		if($this->clientDimensions[$session] === $dimension){
			return;
		}
		$this->clientDimensions[$session] = $dimension;
		$this->pendingAcks[$session] = true;
		$session->sendDataPacket(ChangeDimensionPacket::create($dimension, $player->getPosition(), false, null));
	}

	private function rewriteSpawnPosition(Player $player, SetSpawnPositionPacket $packet) : void{
		if($packet->spawnType === SetSpawnPositionPacket::TYPE_WORLD_SPAWN){
			$packet->dimension = $this->dimensions->getDimension($player->getWorld());
			return;
		}
		$spawn = $player->getSpawn();
		if($spawn instanceof Position && $spawn->isValid()){
			$packet->dimension = $this->dimensions->getDimension($spawn->getWorld());
		}
	}

	/**
	 * @param NetworkSession[] $targets
	 */
	private function rewriteParticle(array $targets, SpawnParticleEffectPacket $packet) : void{
		foreach($targets as $session){
			$player = $session->getPlayer();
			if($player !== null && $player->getLocation()->isValid()){
				$packet->dimensionId = $this->dimensions->getDimension($player->getWorld());
				return;
			}
		}
	}

	/**
	 * @param MetadataProperty[] $metadata
	 * @phpstan-param array<int, MetadataProperty> $metadata
	 *
	 * @return MetadataProperty[]
	 * @phpstan-return array<int, MetadataProperty>
	 */
	private function rewriteDeathDimension(int $actorRuntimeId, array $metadata) : array{
		if(!isset($metadata[EntityMetadataProperties::PLAYER_DEATH_DIMENSION])){
			return $metadata;
		}
		foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
			if($player->getId() !== $actorRuntimeId){
				continue;
			}
			$deathPosition = $player->getDeathPosition();
			if($deathPosition !== null && $deathPosition->isValid()){
				$metadata[EntityMetadataProperties::PLAYER_DEATH_DIMENSION] = new IntMetadataProperty($this->dimensions->getDimension($deathPosition->getWorld()));
			}
			break;
		}
		return $metadata;
	}
}
