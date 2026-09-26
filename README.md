<div align="center">

# 🌍 Vanilla-Altay

**The vanilla Minecraft: Bedrock Edition features your Altay server is missing.**

[![Release](https://img.shields.io/github/v/release/NaySurGithub/VanillaAltay?style=for-the-badge&color=8b5cf6)](https://github.com/NaySurGithub/VanillaAltay/releases/latest)
[![Altay](https://img.shields.io/badge/Altay-5.44-10b981?style=for-the-badge)](https://github.com/altayofficial/Altay)
[![PHP](https://img.shields.io/badge/PHP-8.2-777bb4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net)
[![License](https://img.shields.io/badge/license-GPL--3.0-f59e0b?style=for-the-badge)](LICENSE)

</div>

---

## 🔴 Redstone

Build real contraptions, from a simple door to a flying machine.

- **Wiring** · dust, torches, repeaters, comparators reading containers
- **Sources** · levers, buttons, pressure plates, observers, trapped chests
- **Pistons** · push and pull whole slime structures, launch entities
- **Machines** · dispensers, droppers, hoppers that lock when powered
- **Openables** · doors, trapdoors and fence gates, iron ones redstone only

## 🔥 Dimensions

The Nether and the End, generated like vanilla and reachable through portals.

- **The Nether** · all five biomes, bastion remnants, fortresses, ruined portals and fossils
- **The End** · the main island and its obsidian spikes with their crystals, outer islands, gateways, chorus forests and end cities
- **Portals** · light a nether portal anywhere in its frame, fill the twelve frames with eyes of ender to open the End
- **Dimension rules** · beds and respawn anchors explode where they should, water evaporates in the Nether, lava runs faster there

## 📦 Behavior packs

Drop any behavior pack in the `behavior_packs` folder next to `resource_packs` and it just works.

- **Custom items and blocks** · components, states, permutations, placement
- **Custom entities** · components, component groups, events and simple AI
- **Recipes and loot tables** · crafting, smelting, brewing, block and mob drops
- **Scripts** · `@minecraft/server` and `@minecraft/server-ui`, run by a JavaScript engine written in pure PHP, nothing to install

## 🧱 Dummies

Every vanilla block and item nothing else implements, read from the server's own data.

- **Blocks** · every state kept, vanilla hardness, tools, light and flammability
- **Items** · held, stored and saved like any other item
- **Creative inventory** · each one in its vanilla place and group

## 🚀 Installation

1. Download the latest `.phar` from the [releases](https://github.com/NaySurGithub/VanillaAltay/releases/latest).
2. Drop it in the `plugins` folder of your server, with [Customies](https://github.com/altayofficial/Customies) for custom content.
3. Start the server. Every module can be turned off in `plugin_data/Vanilla-Altay/config.yml`.

## 🧩 For developers

| Event | When |
|---|---|
| `redstone\event\PistonPushBlockEvent` | A piston is about to push blocks |
| `redstone\event\PistonPullBlockEvent` | A sticky piston is about to pull blocks |

Both can be cancelled.

## ❤️ Credits

The redstone module is based on [Cosmoverse/Redstone](https://github.com/Cosmoverse/Redstone) by Muqsit.

## 📄 License

Vanilla-Altay is licensed under the [GNU General Public License v3.0](LICENSE).
