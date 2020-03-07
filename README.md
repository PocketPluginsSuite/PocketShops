# PocketShops [![Chat](https://img.shields.io/badge/chat-on%20discord-7289da.svg)](https://invite.gg/underforums)
PocketShops is the self-proclaimed **best** shop plugin for PocketMine.

PocketShops aims to be an extremely powerful solution to create shops on PocketMine servers, while maintaining an ease-of-use that allows anyone to quickly get shop working in just a few minutes. This plugin has built-in support for [Onebone's EconomyAPI (PocketMine Economy Solution)](https://github.com/onebone/EconomyS) and uses [jojoe77777's libFormAPI](https://github.com/jojoe77777/FormAPI) to create in-game menu's (GUI) for shops.

# Installation
Installation is simple. You have 2 options.
1. Download the latest .phar of PocketShops on Poggit (coming soon) and place it into your `/plugins/` folder.
2. Clone this repository into your `/plugins/` folder. You must have [PocketMine's DevTools plugin](https://poggit.pmmp.io/p/DevTools) installed on your server.

# Setup
In an effort to be simple and non-intrusive, the PocketShops **does not** have any shops configured by default.

### Example `shop.yml` Configuration
The following examples shop can be opened by running `/shop food`. The player must have the `pocketshops.shop.food` permission. Simply copy and pase the configuration below into your `shops.yml` file and the shop will be ready to use instantly; there's no need to reload the server.
```
food:
  name: Groovy Food Shop
  sections:
    0:
      name: Cheap Meals
      items:
      - "297:0:1:6:-1" # [item-id]:[data-value]:[stack]:[price][sell-price]:[custom-name]
      - "350:0:1:8:-1"
    2:
      name: Special Meals
      items:
      - "cmd:Test Command (Salute Joe):0:tell @p Salute @p!" # cmd:[product-name]:[price]:[command]
      - "297:0:1:6:-1"
      - "350:0:1:12:-1"
      - "320:0:1:8:-1"
```

### Permissions
PocketShops only has 1 permission node: `pocketshops.command.[shop-name]`

### Configuring Your Shop's Items
Items are defined in the following format:
```
- "297:0:1:6:-1"
```
```
- "[item-id]:[data-value]:[stack]:[price][sell-price]:[custom-name]"
```

### Selling a Command
PocketShops supports selling commands. This expands the possibilities of shop items to a near limitless extent. Combined with my PocketItems plugin (coming soon), you can easily sell 'custom' items.
```
- "cmd:Test Command (Salute Joe):10:tell @p Salute @p!" # cmd:[product-name]:[price]:[command]
```
```
- "cmd:[product-name]:[price]:[command]"
```

# Support
Need support? [Join us on Discord.](https://invite.gg/underforums/) [![Chat](https://img.shields.io/badge/chat-on%20discord-7289da.svg)](https://invite.gg/underforums)