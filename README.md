<div align="center">
  <h1>🌍 VanillaAltay 🌱</h1>
  <p>Vanilla Minecraft features that Altay does not have yet</p>
</div>

## 📖 What this is

Altay is server software, not a reimplementation of the game. Plenty of vanilla behaviour is missing or
simplified, and some of it is too large or too opinionated to live in the server itself. This plugin is where
those features go.

Every feature aims to match vanilla as closely as the server API allows. Where something is an approximation,
it says so instead of pretending otherwise.

## 📥 Installation

Drop the folder (or the compiled `.phar`) into your server's `plugins/` directory. No dependencies.

Requires Altay 5.x.

Configuration is generated in `plugin_data/VanillaAltay/config.yml`. It can disable the generator registration,
custom biome registration, caves, ores, ground cover, biome decoration, all structures or each structure
individually. The `/locate` and `/summon` commands and their client-side argument hints can also be disabled
independently.
Entities, AI, natural spawning, native entity overrides, spawn-egg overrides, mounts, owner combat and anger
propagation can also be switched independently. Spawn frequency, category caps and individual entity types are
configurable. Restart the server after changing it. Disabling custom biome registration while keeping generation
enabled is not recommended, because missing Altay biomes will fall back to the server's defaults.

## 🌎 Vanilla world generation

Registers a `vanilla_overworld` generator, built the way vanilla has generated the overworld since 1.18.

```
level-generator=vanilla_overworld
```

or, with a world manager:

```
/mw create myworld vanilla_overworld
```

### 🧠 How it works

The thing most custom generators get wrong is letting the biome decide the terrain height, which is what
produces square biome borders with cliffs between them. Vanilla stopped doing that in 1.18, and so does this.

Three independent low frequency fields describe the landscape. The height is derived from them, and **the
biome is only a label read off the same fields afterwards** — it never feeds back into the shape of the land.

| Field | Role |
| --- | --- |
| Continentalness | Oceans against continents, and how far above sea level the land sits |
| Erosion | How rough the land is; heavily eroded areas flatten into plains |
| Peaks and valleys | The relief itself, sharpened past a threshold so mountains stand out |
| Temperature, humidity | Biome choice only, never height |
| 3D density | Cliffs and overhangs, near the surface only |

Temperature and humidity are quantized into five levels each using vanilla's own band edges, then looked up in
a biome table. Those edges assume a noise centered on zero — reading them as a zero-to-one range is what turns
a world into permanent winter.

### ✅ What it does

- **Sea level 63**, as in vanilla.
- **Deepslate** fades in between y=0 and y=8 with vanilla's probability rule, rather than cutting sharply.
- **Bedrock** forms a ragged floor over the bottom layers instead of a flat slab.
- **Caves** in two families, like vanilla: wide rooms and thin winding tunnels. Both fields are sampled on a
  coarse grid and interpolated, because evaluating them per block costs more than the rest of the generator
  put together.
- **Ores** follow the vanilla vein sizes and height bands, including the split distributions — coal appears
  both high in the mountains and deep underground, iron in three separate bands.
- **Seventeen biomes**, eleven of which the plugin registers itself: Altay only ships thirteen, and everything
  else falls back to an unknown biome with no ground cover and no decoration.

Measured over 5000 sample points: ~37% ocean, then plains, forest, birch forest, dark forest, taiga, savanna,
jungle, and the rest below 3% each. Terrain height median 64, maximum 104, 45% below sea level.

### 🏛️ Structures

Structures sit on a grid of square regions. Each region draws one candidate chunk from the world seed, the
structure's own salt and the region coordinates, so two structures of a kind are never closer than their
minimum spacing nor further apart than their maximum, and any thread can answer "is there one here?" without
generating anything.

| Structure | Placement |
| --- | --- |
| Desert well | 1 in 500 per chunk, desert, below y=128 |
| Swamp hut | grid, 8–32 chunks apart |
| Monster room | underground, 8 attempts per chunk, needs a pocket with 1–5 openings |
| Jungle temple | grid, 8–32 chunks apart |
| Desert pyramid | grid, 8–32 chunks apart |
| Mineshaft | grid, 4–16 chunks apart, spans up to 14×14 chunks |

Anything bigger than a chunk implements `SpreadStructure`. Population may only touch the 3×3 chunks around the
chunk it is working on, so a mineshaft cannot be written in one go from its origin. Instead **every chunk
within reach rebuilds the same layout and writes only its own share of it**. That needs three things, and
getting any of them wrong produces a visible bug:

- the layout is seeded from its **origin**, not from the chunk building it, or the two disagree;
- each piece decorates from its **own derived seed**, or two chunks writing the same overlapping area place
  different blocks;
- every write is refused when its chunk is not loaded, or the server crashes on an unavailable chunk.

Layouts are cached per origin and each piece is skipped unless its bounding box meets the chunk being built —
without that test the cost near a mineshaft was 134 ms per chunk instead of 15.

### 🧭 Commands

`/locate biome <name> [radius] [teleport]` and `/locate structure <name> [radius] [teleport]`, both operator
only. Biome search walks a square spiral of chunks; structure search walks the placement regions directly. Both
are capped so a search can never hang the server, and only the biomes the generator actually produces are
offered. The client gets real argument hints.

`/summon <entity> [x y z]` creates any entity registered by VanillaAltay. The command is operator-only and must
be run by a player. Coordinates are optional; when omitted, the entity appears at the player's position. Absolute
and relative coordinates are supported:

```text
/summon minecraft:cow
/summon minecraft:warden 100 70 -25
/summon minecraft:phantom ~ ~10 ~
```

The `minecraft:` namespace may be omitted. Available identifiers are displayed by Bedrock's command suggestions
when `commands.summon.command-hints` is enabled.

### ⚠️ What is approximate

The noise fields play the same roles as Mojang's density functions, but this is not a transcription of them.
Vanilla runs a graph of spline-interpolated density functions with hundreds of hardcoded knots; reproducing it
exactly means porting that entire graph. **Worlds will not match a vanilla world of the same seed.** They look
and play like an overworld, not like *your* overworld.

Not implemented yet:

| Missing | Notes |
| --- | --- |
| Loot | Every chest is empty; there is no loot table system |
| Template structures | Villages, igloos, shipwrecks, outposts and ancient cities are built from Mojang's structure files, which are not available |
| Aquifers | Vanilla floods caves with local water tables and lava lakes below y=-54 |
| Surface rules | Badlands clay bands, swamp water, snow layers on cold peaks |
| Ravines | Canyon carving |
| Cave biomes | Lush caves, dripstone caves, deep dark |
| Ore veins | The large copper and iron veins introduced with 1.18 |

Some structure details are missing because the blocks themselves are absent from Altay: the jungle temple has
no pistons or dispensers, since neither block exists yet.

Generation currently costs around 75 ms per chunk, against roughly 15 ms for the generator shipped with the
server. The gap is in the noise sampling and is the next thing to work on.

## 🗺️ Roadmap

Other vanilla features are meant to land here over time. The generator is only the first one.
