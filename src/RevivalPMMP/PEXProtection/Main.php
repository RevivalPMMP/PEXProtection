<?php
declare(strict_types=1);
namespace RevivalPMMP\PEXProtection;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use revivalpmmp\pureentities\event\CreatureSpawnEvent;

class Main extends PluginBase implements Listener {

	/** @var Config $centers */
	public $centers;
	/** @var string[] $tapping */
	private $tapping = [];
	/** @var string $level */
	private $level;
	/** @var string $blockName */
	private $blockName;
	/** @var int $radius */
	private $radius;
	/** @var bool $allMobs */
	private $allMobs = true;

	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->saveDefaultConfig();
		$this->centers = new Config($this->getDataFolder() . "centers.yml", Config::YAML);
		$this->getServer()->getLogger()->info(TF::GREEN . "PEXProtector Enabled!");
	}

	/**
	 * @param string $blockName
	 *
	 * @return bool
	 */
	public function isCenterBlock(string $blockName) : bool{
		return $this->centers->exists($blockName);
	}

	/**
	 * @param Position $position
	 *
	 * @return bool
	 */
	public function isCenterBlockLocation(Position $position){
		foreach($this->centers->getAll() as $center){
			if($center["xPos"] === $position->x
				and $center["yPos"] === $position->y
				and $center["zPos"] === $position->z
				and $position->getLevel()->getName() === $center["level"]){
				return true;
			}
		}

		return false;
	}

	/**
	 * Adds passed parameters to centers.yml file
	 *
	 * @param string   $blockName
	 * @param Position $position
	 * @param int      $radius
	 * @param string   $level
	 * @param bool     $allMobs
	 *
	 * @return bool
	 */
	public function setCenterBlock(string $blockName, Position $position, int $radius, string $level, bool $allMobs) : bool{
		$this->centers->set($blockName, [
			"xPos" => $position->x,
			"yPos" => $position->y,
			"zPos" => $position->z,
			"level" => $level,
			"radius" => $radius,
			"all-mobs" => $allMobs
		]);

		return $this->centers->save(true);
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function inTapMode(Player $player) : bool{
		return isset($this->tapping[$player->getName()]);
	}

	/**
	 * @param CreatureSpawnEvent $event
	 */
	public function onCreatureSpawn(CreatureSpawnEvent $event){
		if(!$event->isCancelled()){
			if(in_array($event->getLevel()->getName(), $this->getConfig()->get("Disabled-Worlds", []))){
				$event->setCancelled();
			}
			foreach($this->centers->getAll() as $areaCenter => $centerInfo){
				$pos = new Position(
					$centerInfo["xPos"],
					$centerInfo["yPos"],
					$centerInfo["zPos"],
					$this->getServer()->getLevelByName($centerInfo["level"])
				);
				$entity = $event->getPosition();
				if(($entity->distance($pos) < $centerInfo["radius"] and $centerInfo["level"] === $event->getLevel()->getName()) or in_array($event->getLevel()->getName(), $this->getConfig()->get("Disabled-Worlds", []))){
					if(strcmp(strtolower($event->getType()), "monster") === 0 or $centerInfo["all-mobs"]){
						$event->setCancelled();
					}
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
	public function onCommand(CommandSender $sender, Command $command, string $commandLabel, array $args) : bool{
		if(isset($args[1])){
			switch($args[0]){
				case "add":
				case "set":
				case "create":
				case "make":
					if(!($sender instanceof Player)){
						$sender->sendMessage(TF::RED . "You cannot execute this command using console.");

						return true;
					}
					if(!$this->isCenterBlock($args[1])){
						if(isset($args[2])){
							$sender->sendMessage(TF::AQUA . "Tap a block to add a protection center block with the name " . $args[1] . "!");
							$this->tapping[$sender->getName()] = $sender->getName();
							$this->level = $sender->getLevel()->getFolderName();
							$this->blockName = $args[1];
							$this->radius = (int) $args[2];
							if(isset($args[3])){
								$v = trim($args[3]);
								switch(strtolower($v)){
									case "on":
									case "true":
									case "yes":
										$v = true;
										break;
									case "off":
									case "false":
									case "no":
										$v = false;
										break;
								}
								$this->allMobs = $v;
							}else{
								$this->allMobs = $this->getConfig()->get("All-Mobs", true);
							}
						}else{
							$sender->sendMessage(TF::RED . "You have to define a radius for the protection center block!");
						}
					}else{
						$sender->sendMessage(TF::RED . "A protection center block with that name already exists");
					}

					return true;
					break;
				case "disableworld":
				case "world":
					$sender->sendMessage(TF::GREEN . "Successfully added a world to disable monster spawning.");
					$worlds = $this->getConfig()->get("Disabled-Worlds");
					$worlds[] = $args[1];
					$this->getConfig()->set("Disabled-Worlds", $worlds);
					$this->getConfig()->save(true);

					return true;
					break;
				case "delete":
				case "del":
				case "remove":
				case "rem":
				case "clear":
					if($this->isCenterBlock($args[1])){
						$sender->sendMessage(TF::AQUA . "Successfully removed the protection center block " . $args[1] . "!");
						$this->centers->remove($args[1]);
						$this->centers->save(true);
					}else{
						$sender->sendMessage(TF::RED . "That protection center block name does not exist.");
					}

					return true;
					break;
				default:
					return false;
			}
		}else{
			$sender->sendMessage(TF::RED . "Please provide a valid protection center block name!");

			return true;
		}
	}

	/**
	 * @priority LOW
	 * @ignoreCancelled true
	 *
	 * @param PlayerInteractEvent $ev
	 */
	public function onInteract(PlayerInteractEvent $ev) : void{
		if(!$ev->isCancelled()){
			$p = $ev->getPlayer();
			if($this->inTapMode($p)){
				if(!$this->isCenterBlockLocation($ev->getBlock())){
					$ev->setCancelled();
					unset($this->tapping[$p->getName()]);
					if($this->setCenterBlock($this->blockName, $ev->getBlock(), $this->radius, $this->level, $this->allMobs)){
						$p->sendMessage(TF::GREEN . "Successfully added a protection center block with radius " . $this->radius . "!");
					}
					unset($this->blockName);
					unset($this->radius);
					unset($this->level);
					unset($this->allMobs);
				}else{
					$p->sendMessage(TF::RED . "A protection center block at that location already exists!");
				}
			}
		}
	}
}
