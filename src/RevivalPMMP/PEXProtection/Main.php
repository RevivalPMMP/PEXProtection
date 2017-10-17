<?php

namespace RevivalPMMP\PEXProtection;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use revivalpmmp\pureentities\event\CreatureSpawnEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Position;
use pocketmine\Player;

class Main extends PluginBase implements Listener {

	/** @var Config */
	public $centers;
	/** @var array */
	private $tapping = [];
	/** @var string */
	private $blockName = "";
	/** @var int */
	private $radius = 0;
	
	public function onEnable(): void {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getDataFolder());
		$this->centers = new Config($this->getDataFolder() . "centers.yml", Config::YAML, [
			"Disabled-Worlds" => []
		]);
	}

	/**
	* @param string $blockName
	*
	* @return bool
	*/
	public function isCenterBlock(string $blockName): bool {
		return $this->centers->exists($blockName);
	}

	/**
	* @param Position $position
	*
	* @return bool
	*/
	public function isCenterBlockLocation(Position $position): bool {
		foreach($this->centers->getAll() as $center) {
			if(!$center === $this->centers->get("Disabled-Worlds")) {
				if($center["x"] === $position->x && $center["y"] === $position->y && $center["z"] === $position->z && $position->getLevel()->getName() === $center["level"]) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	* @param string   $blockName
	* @param Position $position
	* @param int      $radius
	*/
	public function setCenterBlock(string $blockName, Position $position, int $radius): void {
		$this->centers->set($blockName, [
			"x" => $position->getX(),
			"y" => $position->getY(),
			"z" => $position->getZ(),
			"level" => $position->level->getName(),
			"radius" => $radius
		]);
		$this->centers->save();
	}

	/**
	* @param Player $player
	*
	* @return bool
	*/
	public function inTapMode(Player $player): bool {
		return isset($this->tapping[$player->getName()]);
	}

	/**
	* @param CreatureSpawnEvent $event
	*/
	public function onCreatureSpawn(CreatureSpawnEvent $event): void {
		if(in_array($event->getLevel()->getName(), $this->centers->get("Disabled-Worlds"))) {
			$event->setCancelled();
		}
		foreach($this->centers->getAll() as $center){
			if(!$center === $this->centers->get("Disabled-Worlds")) {
				$pos = new Position(
					$center["x"],
					$center["y"],
					$center["z"],
					$this->getServer()->getLevelByName($center["level"])
				);
				$entity = $event->getPosition();
				if(($entity->distance($pos) < $center["radius"] && $center["level"] === $event->getLevel()->getName()) || in_array($event->getLevel()->getName(), $this->centers->get("Disabled-Worlds"))) {
					$event->setCancelled();
				}
			}
		}
	}

	/**
	* @param CommandSender $sender
	* @param Command       $command
	* @param string        $commandLabel
	* @param array         $args
	*
	* @return bool
	*/
	public function onCommand(CommandSender $sender, Command $command, string $commandLabel, array $args): bool {
		if($sender->hasPermission("pexprotection.command")) {
			if(isset($args[1])) {

				switch($args[0]) {
					case "add":
					case "set":
					case "create":
					case "make":
						if(!($sender instanceof Player)) {
							$sender->sendMessage(TextFormat::RED . "You cannot execute this command using console.");
							return true;
						}
						if(!$this->isCenterBlock($args[1])) {
							if(isset($args[2])) {
								$sender->sendMessage(TF::AQUA . "Tap a block to add a protection center block with the name " . $args[1] . "!");
								$this->tapping[$sender->getName()] = $sender->getName();
								$this->blockName = $args[1];
								$this->radius = (int) $args[2];
							} else {
								$sender->sendMessage(TF::RED . "You have to define a radius for the protection center block!");
							}
						} else {
							$sender->sendMessage(TF::RED . "A protection center block with that name already exists");
						}
						return true;

					case "disableworld":
					case "world":
						$sender->sendMessage(TF::GREEN . "Successfully added a world to disable monster spawning.");
						$worlds = $this->centers->get("Disabled-Worlds");
						$worlds[] = $args[1];
						$this->centers->set("Disabled-Worlds", $worlds);
						$this->centers->save();
						return true;

					case "delete":
					case "del":
					case "remove":
					case "rem":
					case "clear":
						if($this->isCenterBlock($args[1])) {
							$sender->sendMessage(TF::AQUA . "Successfully removed the protection center block " . $args[1] . "!");
							$this->centers->remove($args[1]);
							$this->centers->save();
						} else {
							$sender->sendMessage(TF::RED . "That protection center block name does not exist.");
						}
						return true;
				}
			} else {
				$sender->sendMessage(TF::RED . "Please provide a valid protection center block name!");
				return true;
			}
		}
		return false;
	}

	/**
	* @param PlayerInteractEvent $ev
	*/
	public function onInteract(PlayerInteractEvent $ev): void {
		$player = $ev->getPlayer();
		if($this->inTapMode($player)) {
			if(!$this->isCenterBlockLocation($ev->getBlock())) {
				$player->sendMessage(TF::GREEN . "Successfully added a protection center block with radius" . $this->radius . "!");
				unset($this->tapping[$player->getName()]);

				$this->setCenterBlock($this->blockName, $ev->getBlock(), $this->radius);
				$this->blockName = "";
				$this->radius = 0;
				$ev->setCancelled();
			} else {
				$player->sendMessage(TF::RED . "A protection center block at that location already exists!");
			}
		}
	}
}
