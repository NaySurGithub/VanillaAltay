<?php

declare(strict_types=1);

namespace behaviorpack\script\ddui;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ClientboundDataStorePacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * The data store packet of the data driven screens. A change carries a whole
 * nested value, which the protocol library cannot encode, so the entries are
 * written here: ["change", property, value] or ["update", property, path, value].
 */
final class DduiDataStorePacket extends ClientboundDataStorePacket{

	private const STORE = "minecraft";

	/** @var list<array{0: string, 1: string, 2: mixed, 3?: mixed}> */
	private array $entries = [];

	/**
	 * @param list<array{0: string, 1: string, 2: mixed, 3?: mixed}> $entries
	 */
	public static function entries(array $entries) : self{
		$result = new self;
		$result->entries = $entries;
		return $result;
	}

	protected function encodePayload(ByteBufferWriter $out) : void{
		VarInt::writeUnsignedInt($out, count($this->entries));
		foreach($this->entries as $entry){
			if($entry[0] === "change"){
				VarInt::writeUnsignedInt($out, 1);
				CommonTypes::putString($out, self::STORE);
				CommonTypes::putString($out, $entry[1]);
				LE::writeUnsignedInt($out, 1);
				self::writeValue($out, $entry[2]);
				continue;
			}
			VarInt::writeUnsignedInt($out, 0);
			CommonTypes::putString($out, self::STORE);
			CommonTypes::putString($out, $entry[1]);
			CommonTypes::putString($out, (string) $entry[2]);
			$value = $entry[3] ?? "";
			if(is_bool($value)){
				VarInt::writeUnsignedInt($out, 1);
				CommonTypes::putBool($out, $value);
			}elseif(is_int($value) || is_float($value)){
				VarInt::writeUnsignedInt($out, 0);
				LE::writeDouble($out, (float) $value);
			}else{
				VarInt::writeUnsignedInt($out, 2);
				CommonTypes::putString($out, (string) $value);
			}
			LE::writeUnsignedInt($out, 1);
			LE::writeUnsignedInt($out, 1);
		}
	}

	private static function writeValue(ByteBufferWriter $out, mixed $value) : void{
		if(is_bool($value)){
			LE::writeUnsignedInt($out, 1);
			CommonTypes::putBool($out, $value);
			return;
		}
		if(is_int($value) || is_float($value)){
			LE::writeUnsignedInt($out, 2);
			LE::writeSignedLong($out, (int) $value);
			return;
		}
		if(is_string($value)){
			LE::writeUnsignedInt($out, 4);
			CommonTypes::putString($out, $value);
			return;
		}
		if(is_array($value)){
			LE::writeUnsignedInt($out, 6);
			VarInt::writeUnsignedInt($out, count($value));
			foreach($value as $key => $item){
				CommonTypes::putString($out, (string) $key);
				self::writeValue($out, $item);
			}
			return;
		}
		LE::writeUnsignedInt($out, 0);
	}
}
