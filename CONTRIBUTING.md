# Contributing to Vanilla-Altay

Thanks for helping bring vanilla Bedrock features to Altay. This page explains how the project is laid out and what a pull request needs to be merged.

## 🧭 Before you start

- Open an issue first for anything bigger than a bug fix, so we can agree on the approach.
- Vanilla Bedrock is the reference. A behaviour is correct when it matches the game, not when it matches another server.
- One pull request, one change. Unrelated fixes go in their own pull request.

## 🗂️ Project layout

| Folder | Content |
|---|---|
| `src/vanillaaltay` | Plugin entry point, reads the config once and starts the modules |
| `src/redstone` | Redstone module |
| `src/dimension` | Nether and End: generators, client dimension switch, portals, dimension rules |
| `src/behaviorpack` | Behavior pack loader: items, blocks, entities, recipes, loot tables, scripts |
| `resources` | Default `config.yml` and data files |

A new module lives in its own folder under `src`, gets its own section in `config.yml` and can be turned off there.

## 🛠️ Setup

1. Clone the repository into the `plugins` folder of an Altay server built from the `master` branch.
2. Install [DevTools](https://github.com/pmmp/DevTools) to load the plugin from source.
3. Install [Customies](https://github.com/altayofficial/Customies) to test custom items, blocks and entities.

## ✍️ Code rules

- **PHP 8.2.** No `private(set)`, property hooks, interface properties, typed class constants or other newer syntax.
- **Style.** Tabs, `function name() : type{`, `}elseif(`, one class per file, `use function` for global functions.
- **English** for every identifier, log line and docblock.
- **No comments inside function bodies** and none at the end of a line. Docblocks above classes and methods are welcome when they explain behaviour or a contract.
- **Name things after what they do.** Never name a class, a file or a docblock after another project or say where code was taken from.
- **Verify every API.** Check that each Altay class, method and constant you use exists on `master`. Never guess.
- **No new dependency.** The plugin must run on a stock Altay server, on any operating system, with nothing else to install.
- **Threads.** Generators and populators run on worker threads: they must not touch the server, a world or shared state.
- **Config.** Read it once when the plugin loads, never while the server is running.

## 🧪 Testing

There is no automated test suite. Describe in your pull request what you tested in game and how, with the Altay version and the Bedrock client version you used.

## 📝 Commits

Use [Conventional Commits](https://www.conventionalcommits.org), in lowercase and in the imperative:

```
feat: add powered rails
fix: stop hoppers from pulling out of locked containers
docs: explain the dimensions config
```

## 🚀 Releases

Every push to `main` builds the phar and publishes it as the `latest` release. The version in `plugin.yml` is only changed by the maintainer.

## 📄 License

By contributing, you agree that your work is released under the [GNU General Public License v3.0](LICENSE).
