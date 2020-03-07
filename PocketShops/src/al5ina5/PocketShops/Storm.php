<?php

declare(strict_types=1);

namespace al5ina5\Shop;

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
	}

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);

        $this->shops = new Config($this->getDataFolder() . "shops.yml", Config::YAML);
        foreach($this->shops as $shop => $info) {
            $perm = new Permission("shop.command.$shop", "Access to the '$shop' shop.");
            $this->getPlugin()->getServer()->getPluginManager()->addPermission($perm);
        }

		$this->getLogger()->info(TextFormat::DARK_GREEN . "I've been enabled!");
    }

	public function onDisable(){
		$this->getLogger()->info(TextFormat::DARK_RED . "I've been disabled!");
    }

    public function reloadShop() : void {
        $this->shops = new Config($this->getDataFolder() . "shops.yml", Config::YAML);
    }

    public function openShop(string $shopName, Player $player) {
        $this->reloadShop();
        
        $shop = $this->shops->getAll()[$shopName];

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

            $this->openItemOrder($section, $data, $player);
        });

        $form->setTitle($section["name"]);
        foreach ($section["items"] as $item => $info) $form->addButton($this->parseShopItem($info, "custom_name"));
        $form->sendToPlayer($player);
    }

    public function openItemOrder($section, int $itemID, $player) {
        $item = $this->parseShopItemTwo($section["items"][$itemID]);

        print_r($item);

        $form = new CustomForm(function(Player $player, $data) use ($section) {
            if ($data === null) return;

            print_r($data);

        });

        $form->setTitle($item["name"]);
        $form->addLabel(
            "How many would you like to purchase?\n" .
            "We'll buy yours for $" . $item["sell_price"] . "a piece.\n\n"
        );
        $form->addInput("Quantity", "1");
        $form->addToggle("Buy - Sell", false);
        $form->addLabel(" ");
        $form->sendToPlayer($player);
    }

    public function parseShopItemTwo($entry) {
        $item = [];

        $item["id"] = (int) explode(":", $entry)[0];
        $item["dv"] = (int) explode(":", $entry)[1];
        $item["stack"] = (int) explode(":", $entry)[2];
        $item["price"] = (int) explode(":", $entry)[3];
        $item["sell_price"] = 
        (int) explode(":", $entry)[4];

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

    public function parseShopItem($entry, $type) {
        switch ($type) { // "306:0:1:130:-1:CustomName"
            case "id": return (int) explode(":", $entry)[0];
            case "dv": return (int) explode(":", $entry)[1];
            case "stack": return (int) explode(":", $entry)[2];
            case "price": return (int) explode(":", $entry)[3];
            case "sell_price": return (int) explode(":", $entry)[4];
            case "name": return Item::get((int) explode(":", $entry)[0], (int) explode(":", $entry)[1])->getName();
            case "custom_name":
                if (isset(explode(":", $entry)[5])) {
                    return explode(":", $entry)[5];  
                } else {
                    return Item::get((int) explode(":", $entry)[0], (int) explode(":", $entry)[1])->getName();
                }
            default:
                return false;
        }
    }
    
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) : bool {

		switch($command->getName()){
            case "sebb":
                $this->openShop("ores", $sender->getPlayer());
                
                return true;
            case "shop":
                if (!isset($args[0])) return true; // Abort if the user didn't enter a shop name. Usage: /shop [name]

                $player = $sender->getPlayer(); // Get the 'Player' instance of the user who ran the command.

                if (!isset($this->shops->getAll()[$args[0]])) { // If the shop does not exist, we'll send a message an abort.
                    $player->sendMessage("That shop does not exist.");
                    return true;
                }

                if (!$sender->hasPermission("shop.command.$args[0]")) { // Abort if the player does not have permissions for this.
                    // $player->sendMessage("You don't have permission to use this command.");
                    return true;
                }

                $shop = $this->shops->getAll()[$args[0]]; // Get the details of the shop that the player is targetting.

                $shopForm = new SimpleForm(function(Player $player, $data) use ($shop) {
                    if ($data === null) return;

                    $section = $shop["sections"][$data];
                    
                    $sectionForm = new SimpleForm(function(Player $player, $data) use ($section) {
                        if ($data === null) return;

                        if (explode(":", $section["items"][$data])[0] == "cmd") {
                            if (EconomyAPI::getInstance()->myMoney($player) < (int) explode(":", $section["items"][$data])[2]) {
                                $player->sendMessage("You don't have enough money to purchase this.");
                                return false;
                            }

                            $commandToRun = explode(":", $section["items"][$data])[3];
                            $this->getServer()->dispatchCommand(new ConsoleCommandSender(), str_replace("@p", $player->getName(), $commandToRun));
                            EconomyAPI::getInstance()->reduceMoney($player, (int) explode(":", $section["items"][$data])[2]);

                            return false;
                        }

                        $items = $section["items"];

                        $item = [];
                        $item["id"] = (int) explode(":", $section["items"][$data])[0];
                        $item["meta"] = (int) explode(":", $section["items"][$data])[1];
                        $item["name"] = Item::get($item["id"], $item["meta"])->getName();
                        $item["stack"] = (int) explode(":", $section["items"][$data])[2];
                        $item["price"] = (int) explode(":", $section["items"][$data])[3];
                        $item["sell_price"] = (int) explode(":", $section["items"][$data])[4];
                        if ($item["sell_price"] == -1) $item["sell_price"] = (int) $item["price"] * 0.6;

                        if (isset(explode(":", $section["items"][$data])[5])) {
                            $item["custom_name"] = explode(":", $section["items"][$data])[5];
                        }
                        
                        print_r($item);

                        $orderForm = new CustomForm(function(Player $player, $data) use ($item) {
                            print_r($data);

                            if ($data === null) return;

                            $quantity = (int) $data[1];
                            if ($quantity == 0) $quantity = 1;

                            $sell = false;
                            if ($data[2] == 1) $sell = true;

                            if (!is_numeric($quantity) && $quantity > 0) {
                                $player->sendMessage("You must enter a numeric amount to purchase.");
                                return;
                            }

                            $itemObject = Item::get($item["id"], $item["meta"], $item["stack"] * $quantity);

                            if (isset($item["custom_name"])) $itemObject->setCustomName($item["custom_name"]);

                            // if (!$sell) {
                            //     $player->getInventory()->addItem($itemObject);
                            // } else {
                            //     $player->getInventory()->removeItem($itemObject);
                            // }

                            if ($sell) {
                                if (!$player->getInventory()->contains($itemObject)) {
                                    $player->sendMessage("You do not have enough of that item to sell.");
                                    return;
                                }

                                $finalPrice = $item["sell_price"] * $quantity;

                                $player->getInventory()->removeItem($itemObject);
                                EconomyAPI::getInstance()->addMoney($player, $finalPrice);

                                $player->sendMessage("You sold " . $item["stack"] * $quantity . " " . $item["name"] . " for " . $finalPrice . ".");
                            } else {
                                if (!$player->getInventory()->canAddItem($itemObject)) {
                                    $player->sendMessage("Not enough space in your inventory to deposit your items. Free up some space and come back!");
                                    return;
                                }

                                $finalPrice = $item["price"] * $quantity;

                                if (EconomyAPI::getInstance()->myMoney($player) < $finalPrice) {
                                    $player->sendMessage("You don't have enough money to purchase this.");
                                    return;
                                }

                                $player->getInventory()->addItem($itemObject);
                                EconomyAPI::getInstance()->reduceMoney($player, $item["price"] * $item["stack"]);

                                $player->sendMessage("You purchased " . $item["stack"] * $quantity . " " . $item["name"] . " for " . $finalPrice . ".");
                            }
                        });

                        $orderForm->setTitle("Confirm Order");
                        $orderForm->addLabel(
                            "How many would you like to purchase?\n" .
                            "We'll buy yours for $" . $item["sell_price"] . "/" . $item["stack"] . ".\n\n"
                            );
                        $orderForm->addInput("Quantity", "1");
                        $orderForm->addToggle("Buy - Sell", false);
                        $orderForm->addLabel(" ");

                        $orderForm->sendToPlayer($player);
                    });

                    $sectionForm->setTitle($section["name"]);
                    
                    foreach ($section["items"] as $item) {
                        if (explode(":", $item)[0] == "cmd") {
                            $sectionForm->addButton("§l" . explode(":", $item)[1] . "§r§7 $" . (int) (int)explode(":", $item)[2]);

                            continue;
                        } else if (isset(explode(":", $item)[5])) {
                            $sectionForm->addButton("§l" . explode(":", $item)[5] . "§r§7 $" . (int) (int)explode(":", $item)[3]);
                        } else {
                            $sectionForm->addButton("§l" . Item::get((int)explode(":", $item)[0], (int)explode(":", $item)[1])->getName() . "§r§7 $" . (int) (int)explode(":", $item)[3]);
                        }
                    }

                    $sectionForm->sendToPlayer($player);
                });

                $shopForm->setTitle($shop["name"]);
                foreach ($shop["sections"] as $section => $info) $shopForm->addButton($info["name"]);
                $shopForm->sendToPlayer($player);

				return true;
			default:
				return false;
		}
	}

}
