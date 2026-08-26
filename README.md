<div align="center">
  <h1>VanillaAltay</h1>
  <p>Vanilla Minecraft features for Altay</p>
</div>

VanillaAltay adds missing or simplified vanilla gameplay to Altay while staying configurable and compatible with the server API.

## Features

- Vanilla entities with AI, combat, drops and interactions
- Natural spawning with per-category limits
- Breeding, taming and rideable animals
- Aquatic, flying, hostile and passive mobs
- Projectiles and special attacks
- `/summon` with entity suggestions and relative coordinates
- `/locate` for supported biomes and structures
- Optional overworld generator
- Independent configuration switches for every major system

## Requirements

- Altay 5.x
- PHP 8.1 or newer

No additional plugin dependency is required.

## Installation

1. Download `VanillaAltay.phar` from the latest release.
2. Place it in the server's `plugins/` directory.
3. Restart the server.
4. Edit `plugin_data/VanillaAltay/config.yml` if needed.

## Commands

All commands require operator permission.

```text
/summon <entity> [x y z]
/locate biome <name> [radius] [teleport]
/locate structure <name> [radius] [teleport]
```

Examples:

```text
/summon minecraft:cow
/summon minecraft:warden 100 70 -25
/summon minecraft:phantom ~ ~10 ~
/locate biome plains 1000
/locate structure village 2000 true
```

The `minecraft:` namespace is optional for `/summon`.

## Configuration

The generated configuration can independently control:

- World generation, custom biomes and generation features
- Every supported structure
- `/locate`, `/summon` and their client hints
- Entity registration, AI and natural spawning
- Spawn interval, category caps and disabled entity types
- Spawn eggs, mounts, owner combat and anger propagation
- Startup entity self-test

Disable the generator:

```yaml
generation:
  enabled: false
```

Disable command hints without disabling commands:

```yaml
commands:
  locate:
    command-hints: false
  summon:
    command-hints: false
```

Disable individual natural spawns:

```yaml
entities:
  spawning:
    disabled-types:
      - minecraft:phantom
      - minecraft:bat
```

See [`resources/config.yml`](resources/config.yml) for every option.

## World generation

Select `vanilla_overworld` to use the optional generator:

```text
level-generator=vanilla_overworld
```

It provides overworld terrain, biomes, caves, ores, decoration and configurable structures. Generated worlds do not match Java or Bedrock seeds exactly.

## Current limitations

- Some complex mob behaviours are approximations due to Altay API limitations.
- Villager trading and several advanced boss phases are incomplete.
- Generated structure loot is limited.
- Aquifers, ravines and cave biomes are not implemented.

## Development

Code style is defined in `.php-cs-fixer.php`.

Report issues at [altayofficial/Altay](https://github.com/altayofficial/Altay/issues).
