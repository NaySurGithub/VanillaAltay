# Vanilla-Altay

An Altay plugin that brings missing vanilla mechanics to your server, one module at a time.

## Modules

### Redstone

| Category | Blocks |
|---|---|
| Wiring | Redstone dust, redstone torch, repeater, comparator (reads container contents) |
| Power sources | Lever, buttons, pressure plates (wooden, stone, weighted), block of redstone, observer, trapped chest |
| Powered blocks | Redstone lamp, doors (including copper), trapdoors (wooden, iron, copper), fence gates, TNT, redstone ore |
| Hopper | Transfers items between containers, picks up dropped items, locked while powered |
| Pistons | Piston, sticky piston, slime block structures, with push and pull events for other plugins |
| Dispenser | Armor, buckets, projectiles, spawn eggs, plain drops |
| Dropper | Inserts into the container it faces, drops the item otherwise |

### Behavior packs

Drop behavior packs in the `behavior_packs/` folder of the server, next to `resource_packs/`. Extracted packs, `.mcpack`, `.mcaddon` and `.zip` archives are all accepted.

| Content | Loaded |
|---|---|
| `items/` | Custom items |
| `blocks/` | Custom blocks, with states and permutations |
| `entities/` | Custom entities: components, component groups, events, basic AI, spawn eggs |
| `recipes/` | Shaped, shapeless, furnace, blast furnace, smoker, campfire and brewing recipes |
| `loot_tables/` | Block and entity drops |
| `scripts/` | `@minecraft/server` and `@minecraft/server-ui` scripts |

The matching resource packs go in `resource_packs/` as usual.

## Requirements

- Altay 5.44 or newer
- PHP 8.2 or newer
- [Customies](https://github.com/altayofficial/Customies) for custom items, blocks and entities

## Installation

Download the latest `.phar` from the releases and drop it in the `plugins/` folder of your server.

To run from source, place this folder in `plugins/` with DevTools installed.

## Configuration

`plugin_data/Vanilla-Altay/config.yml`

| Key | Default | Effect |
|---|---|---|
| `redstone.enabled` | `true` | Redstone module |
| `behavior-packs.enabled` | `true` | Behavior packs module |
| `behavior-packs.folder` | `behavior_packs` | Packs folder, relative to the server folder |
| `behavior-packs.items-and-blocks` | `true` | Custom items and blocks |
| `behavior-packs.entities` | `true` | Custom entities |
| `behavior-packs.recipes` | `true` | Recipes |
| `behavior-packs.loot-tables` | `true` | Loot tables |
| `behavior-packs.scripts` | `true` | Scripts |
| `behavior-packs.script-timeout-ms` | `2000` | Time a script may run before its pack is disabled |

## Events

| Event | Fired when |
|---|---|
| `redstone\event\PistonPushBlockEvent` | A piston is about to push blocks. Cancellable. |
| `redstone\event\PistonPullBlockEvent` | A sticky piston is about to pull blocks. Cancellable. |

## Credits

The redstone module is based on [Cosmoverse/Redstone](https://github.com/Cosmoverse/Redstone) by Muqsit.

## License

Vanilla-Altay is licensed under the [GNU General Public License v3.0](LICENSE).
