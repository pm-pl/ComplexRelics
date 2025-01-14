<?php

namespace DuoIncure\Relics;

use pocketmine\console\ConsoleCommandSender;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\Server;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HugeExplodeSeedParticle;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\StringTag;
use pocketmine\utils\TextFormat as TF;
use pocketmine\player\Player;
use function str_replace;
use function array_rand;
use function ucfirst;

class RelicFunctions {

	public const RELIC_TAG = "isRelic";

    public const TYPE_COMMON = "common";
    public const TYPE_RARE = "rare";
    public const TYPE_EPIC = "epic";
    public const TYPE_LEGENDARY = "legendary";

	private Main $plugin;
    /** @var mixed[]|string[] */
	private array $cfg;

	/**
	 * RelicFunctions constructor.
	 * @param Main $plugin
	 */
	public function __construct(Main $plugin){
		$this->plugin = $plugin;
		$this->cfg = $plugin->getConfig()->getAll();
	}

	/**
	 * @param string $type
	 * @param int $amount
	 * @return Item
	 */
	public function createRelic(string $type, int $amount): Item {
		$relic = VanillaItems::NETHER_STAR()->setCount($amount);
		$ucfName = ucfirst($type);
		$name = str_replace("&", "§", $this->cfg[$type]["name"] ?? "&6$ucfName Relic") ;
		$relic->setCustomName($name);
		$lore = str_replace("&", "§", $this->cfg[$type]["lore"] ?? "&7Right Click to claim your rewards!");
		$relic->setLore([$lore]);
		$nbt = $relic->getNamedTag();
		$nbt->setTag(self::RELIC_TAG, new StringTag($type));
		return $relic;
	}

	/**
	 * @param Player $player
	 * @param Item $relic
	 */
	public function giveRelicToPlayer(Player $player, Item $relic): void {
		$playerInventory = $player->getInventory();
		$playerX = $player->getPosition()->getX();
		$playerY = $player->getPosition()->getY();
		$playerZ = $player->getPosition()->getZ();
		$vector3Pos = new Vector3($playerX, $playerY, $playerZ);
		if($playerInventory->canAddItem($relic)) {
			$playerInventory->addItem($relic);
		} else {
			$player->getWorld()->dropItem($vector3Pos, $relic);
			$player->sendTip(TF::RED . "You found a relic but your inventory was full!");
		}
	}

    /**
     * @param Player $player
     * @param string $type
     * @param int $amount
     */
	public function giveRelic(Player $player, string $type, int $amount): void {
		$relic = $this->createRelic($type, $amount);
		$msgEnabled = $this->cfg["found-message-enabled"] ?? true;
		if($msgEnabled === true) {
			$this->sendCorrespondingMessage($player, $type);
		}
		$particlesEnabled = $this->cfg["particles-enabled"] ?? true;
		if($particlesEnabled === true) {
			$this->sendCorrespondingParticles($player, "found");
		}
		$this->giveRelicToPlayer($player, $relic);
	}

	/**
	 * @param Player $player
	 * @param Item $relic
	 * @param string $type
	 */
	public function giveRelicReward(Player $player, Item $relic, string $type): void {
		$rewardArray = $this->cfg[$type]["commands"];
		$chosenReward = $rewardArray[array_rand($rewardArray)];
		$commandToUse = str_replace("{player}", $player->getName(), $chosenReward);
		$relic->setCount($relic->getCount() - 1);
		$player->getInventory()->setItem($player->getInventory()->getHeldItemIndex(), $relic);
		$this->plugin->getServer()->dispatchCommand(new ConsoleCommandSender(Server::getInstance(), Server::getInstance()->getLanguage()), $commandToUse);
		$particlesEnabled = $this->cfg["particles-enabled"] ?? true;
		if($particlesEnabled === true) {
			$this->sendCorrespondingParticles($player, "open");
		}
	}

	/**
	 * @param Player $player
	 * @param string $type
	 */
	public function sendCorrespondingParticles(Player $player, string $type): void {
		$x = $player->getPosition()->getX();
		$y = $player->getPosition()->getY();
		$z = $player->getPosition()->getZ();
		$pos = new Vector3($x, $y, $z);
		switch ($type){
			case "found":
				$player->getWorld()->addParticle($pos, new HappyVillagerParticle(), [$player]);
				break;
			case "open":
				$player->getWorld()->addParticle($pos, new HugeExplodeSeedParticle(), [$player]);
				break;
		}
	}

	/**
	 * @param Player $player
	 * @param string $type
	 */
	public function sendCorrespondingMessage(Player $player, string $type): void {
		$msgForm = $this->cfg["message-type"] ?? "title";
		switch($msgForm){
			case "title":
				$title = str_replace("&", "§", $this->cfg[$type]["title"] ?? "&bCongrats!\n&7You found a $type relic!");
				$player->sendTitle($title);
				break;
			case "tip":
				$tip = str_replace("&", "§", $this->cfg[$type]["tip"] ?? "&bCongrats! &7You found a $type relic!");
				$player->sendTip($tip);
				break;
			case "message":
				$message = str_replace("&", "§", $this->cfg[$type]["message"] ?? "&bCongrats! &7You found a $type relic!");
				$player->sendMessage($message);
				break;
		}
	}
}