<?php

declare(strict_types=1);

namespace al5ina5\PocketShops;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\utils\Config;
use pocketmine\item\Item;
use onebone\economyapi\EconomyAPI;
use pocketmine\permission\Permission;

class Main extends PluginBase implements Listener{

	public function onLoad(){
		$this->getLogger()->info(TextFormat::WHITE . "I've been loaded!");
        @mkdir($this->getDataFolder());
    }

	public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    
        $defaultConfig = [
            "lang" => [
                "default_shop" => "shop",
                "onenable_message" => "Enabled",
                "ondisable_message" => "Disabled",
                "store_message" => "Hey! Each {item} will cost {price}. How many would you like to purchase? We'll take yours off your hands for {sell_price}.",
                "not_enough_money" => "You do not have enough money to purchase this item.",
                "not_enough_items" => "You do not have enough of that item in your inventory to sell.",
                "not_enough_storage" => "Not enough space in your inventory to deposit your items. Free up some space and come back!",
                "you_purchased" => "You purchased {quantity} {item} for {cost}.",
                "you_sold" => "You sold {quantity} {item} for {profit}.",
                "no_permission" => "You do not have permission to access this shop.",
                "no_shops_defined" => "No shops are defined in the shops.yml file. Please define at least one shop and this message will disappear forever. Please refer to documentation for information on how to define shops."
            ],
            "plugin_info" => [
                "author" => "Sebastian Alsina",
                "github" => "https://github.com/al5ina5/",
                "documentation" => "https://github.com/al5ina5/PocketShop/"
            ]
        ];
        
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML, $defaultConfig);
        $this->shops = new Config($this->getDataFolder() . "shops.yml", Config::YAML);

        foreach($this->shops as $shop => $info) {
            $perm = new Permission("shop.command.$shop", "Access to the '$shop' shop.");
            $this->getPlugin()->getServer()->getPluginManager()->addPermission($perm);
        }

		$this->getLogger()->info(TextFormat::YELLOW . TextFormat::BOLD . $this->config->get("lang")["onenable_message"]);
    }

	public function onDisable(){
		$this->getLogger()->info( TextFormat::YELLOW . TextFormat::BOLD . $this->config->get("lang")["ondisable_message"]);
    }
    
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) : bool {
		switch($command->getName()){
            case "shop":
                if (!isset($args[0])) {
                    $this->openShop($this->config->get("lang")["default_shop"], $sender->getPlayer());
                    return true;
                }

                $this->openShop($args[0], $sender->getPlayer());
                
                return true;
			default:
				return true;
		}
	}

    public function reloadShopAndConfig() : void {
        $this->shops = new Config($this->getDataFolder() . "shops.yml", Config::YAML);
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
    }

    public function openShop(string $shopName, Player $player) {
        $this->reloadShopAndConfig();

        if (count($this->shops->getAll()) <= 0) {
            $player->sendMessage($this->config->get("lang")["no_shops_defined"]);
            return true;
        }
        
        if (!isset($this->shops->getAll()[$shopName])) return;
        $shop = $this->shops->getAll()[$shopName];

        if (!$player->hasPermission("pocketshops.command.$shopName")) {
            if ($this->config->get("lang")["no_permission"] != "") $player->sendMessage($this->config->get("lang")["no_permission"]);
            return true;
        };

        $form = new SimpleForm(function(Player $player, $data) use ($shop) {
            if ($data === null) return;

            $this->openShopSection($shop, $data, $player);
        });

        $form->setTitle($shop["name"]);
        foreach ($shop["sections"] as $section => $info) $form->addButton($info["name"]);
        $form->sendToPlayer($player);
    }

    public function openShopSection($shop, int $sectionID, Player $player) {
        $section = $shop["sections"][$sectionID];

        $form = new SimpleForm(function(Player $player, $data) use ($section) {
            if ($data === null) return;

            $item = $this->parseShopItem($section["items"][$data]);

            if ($item["command"]) {
                $this->purchaseCommand($item["command"], $item["price"], $player);
                return;
            }

            $this->openItemOrder($section, $data, $player);
        });

        $form->setTitle($section["name"]);
        foreach ($section["items"] as $item => $info) {
            $parse = $this->parseShopItem($info);
            $form->addButton($parse["custom_name"] . " $" . $parse["price"]);
        }
        $form->sendToPlayer($player);
    }

    public function openItemOrder($section, int $itemID, $player) {
        $item = $this->parseShopItem($section["items"][$itemID]);

        $form = new CustomForm(function(Player $player, $data) use ($item) {
            if ($data === null) return;

            $quantity = 1;
            if ((int) $data[1] > 0) $quantity = (int) $data[1];

            $selling = false;
            if ($data[2] == "1") $selling = true;

            if ($selling) {
                $this->sellItem($item, $quantity, $player);
                return;
            }

            $this->purchaseItem($item, $quantity, $player);
        });

        $form->setTitle($item["custom_name"]);
        $form->addLabel(
            str_replace(["{item}", "{price}", "{sell_price}"], [$item["custom_name"], $item["price"], $item["sell_price"]], $this->config->get("lang")["store_message"])
            // "Each " . $item["custom_name"] . " will cost you $" . $item["price"] . ".\n" .
            // "How many would you like to purchase?\n" .
            // "We'll buy yours for $" . $item["sell_price"] . " a piece.\n\n"
        );
        $form->addInput("Quantity", "1");
        $form->addToggle("Buy - Sell", false);
        $form->addLabel(" ");
        $form->sendToPlayer($player);
    }

    public function purchaseCommand($command, $price, $player) {
        if (EconomyAPI::getInstance()->myMoney($player) < $price) {
            $player->sendMessage($this->config->get("lang")["not_enough_money"]);
            return false;
        }

        $player->sendMessage("You bought a commmand.");
        $this->getServer()->dispatchCommand(new ConsoleCommandSender(), str_replace("@p", $player->getName(), $command));
        EconomyAPI::getInstance()->reduceMoney($player, $price);
    }

    public function sellItem($item, $quantity, $player) {
        $itemObject = Item::get($item["id"], $item["dv"], $item["stack"] * $quantity);

        if (!$player->getInventory()->contains($itemObject)) {
            $player->sendMessage($this->config->get("lang")["not_enough_items"]);
            return;
        }

        $transactionValue = $item["sell_price"] * $quantity;

        $player->getInventory()->removeItem($itemObject);
        EconomyAPI::getInstance()->addMoney($player, $transactionValue);

        $player->sendMessage("You sold " . $item["stack"] * $quantity . " " . $item["name"] . " for " . $transactionValue . ".");
        $player->sendMessage(str_replace(["{quantity}", "{item}", "{profit}"], [$item["stack"] * $quantity, $item["name"], $transactionValue], $this->config->get("lang")["you_sold"]));

    }

    public function purchaseItem($item, $quantity, $player) {
        $itemObject = Item::get($item["id"], $item["dv"], $item["stack"] * $quantity);

        if (!$player->getInventory()->canAddItem($itemObject)) {
            $player->sendMessage($this->config->get("lang")["not_enough_storage"]);
            return;
        }

        $transactionValue = $item["price"] * $quantity;

        if (EconomyAPI::getInstance()->myMoney($player) < $transactionValue) {
            $player->sendMessage($this->config->get("lang")["not_enough_money"]);
            return;
        }

        $player->getInventory()->addItem($itemObject);
        EconomyAPI::getInstance()->reduceMoney($player, $transactionValue);

        
        $player->sendMessage(str_replace(["{quantity}", "{item}", "{cost}"], [$item["stack"] * $quantity, $item["name"], $transactionValue], $this->config->get("lang")["you_purchased"]));
    }

    public function parseShopItem($entry) {
        $item = [];

        $item["command"] = false; // cmd:The Alleviator:12000:citem alleviator @p
        if (explode(":", $entry)[0] == "cmd") {
            $item["command"] = explode(":", $entry)[3];
            $item["price"] = (int) explode(":", $entry)[2];
            $item["name"] = explode(":", $entry)[1];
            $item["custom_name"] = explode(":", $entry)[1];
            return $item;
        } else {
            $item["id"] = (int) explode(":", $entry)[0];
            $item["dv"] = (int) explode(":", $entry)[1];
            $item["stack"] = (int) explode(":", $entry)[2];
            $item["price"] = (int) explode(":", $entry)[3];
            $item["sell_price"] = (int) explode(":", $entry)[4];
    
            if ($item["sell_price"] == -1) {
                $item["sell_price"] = $item["price"] * 0.6;
            }
    
            $item["name"] = Item::get((int) explode(":", $entry)[0], (int) explode(":", $entry)[1])->getName();
    
            if (isset(explode(":", $entry)[5])) {
                $item["custom_name"] = explode(":", $entry)[5];  
            } else {
                $item["custom_name"] = Item::get((int) explode(":", $entry)[0], (int) explode(":", $entry)[1])->getName();
            }

            return $item;
        }
    }
}
