<?php

namespace RevivalPMMP\PEXProtection;

use pocketmine\block\Block;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
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
	private $level;
	/** @var string */
	private $blockName = "";
	/** @var int */
	private $radius = 0;
	/** @var bool */
	private $allMobs;
	
	public function onEnable(): void {
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
	public function isCenterBlock(string $blockName): bool {
		return $this->centers->exists($blockName);
	}

	/**
	* @param Position $position
	*
	* @return bool
	*/
	public function isCenterBlockLocation(Block $location){
        foreach($this->centers->getAll() as $center){
            if($center["xPos"] === $location->x
                && $center["yPos"] === $location->y
                && $center["zPos"] === $location->z
                && $location->getLevel()->getName() === $center["level"]){
                return true;
            }
        }
        return false;
    }

	/**
     * Adds passed parameters to centers.yml file
     *
     * @param string $blockName
     * @param Block  $location
     * @param int    $radius
     * @param string $level
     * @param bool   $allMobs
     */
	public function setCenterBlock(string $blockName, Position $location, int $radius, string $level, bool $allMobs){
        $this->centers->set($blockName, array(
            "xPos" => $location->getX(),
            "yPos" => $location->getY(),
            "zPos" => $location->getZ(),
            "level" => $level,
            "radius" => $radius,
            "all-mobs" => $allMobs
        ));
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
	public function onCreatureSpawn(CreatureSpawnEvent $event){
	    if(!$event->isCancelled()){
            if(in_array($event->getLevel()->getName(), $this->getConfig()->get("Disabled-Worlds"))){
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
                if(($entity->distance($pos) < $centerInfo["radius"] && $centerInfo["level"] === $event->getLevel()->getName()) || in_array($event->getLevel()->getName(), $this->getConfig()->get("Disabled-Worlds"))){
                    if(strcmp(strtolower($event->getType()), "monster") == 0 || $centerInfo["all-mobs"]){
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
	public function onCommand(CommandSender $sender, Command $command, string $commandLabel, array $args): bool {
		if($sender->hasPermission("pexprotection.command")) {
			if(isset($args[1])) {

				switch($args[0]) {
					case "add":
					case "set":
					case "create":
					case "make":
						if(!($sender instanceof Player)) {
							$sender->sendMessage(TF::RED . "You cannot execute this command using console.");
							return true;
						}
						if(!$this->isCenterBlock($args[1])) {
							if(isset($args[2])) {
								$sender->sendMessage(TF::AQUA . "Tap a block to add a protection center block with the name " . $args[1] . "!");
								$this->tapping[$sender->getName()] = $sender->getName();
                                $this->level = $sender->getLevel()->getName();
								$this->blockName = $args[1];
								$this->radius = (int) $args[2];
                                if(isset($args[3])){
                                    $this->allMobs = (strcmp(strtolower($args[3]), "true") == 0) ? true : false;
                                } else {
                                    if($this->getConfig()->exists("All-Mobs")){
                                        $this->allMobs = $this->getConfig()->get("All-Mobs");
                                    } else {
                                        $this->allMobs = true;
                                    }
                                }
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
	public function onInteract(PlayerInteractEvent $ev){
        if(!$ev->isCancelled()){
            $p = $ev->getPlayer();
            if($this->inTapMode($p)){
                if(!$this->isCenterBlockLocation($ev->getBlock())){
                    unset($this->tapping[$p->getName()]);
                    $this->setCenterBlock($this->blockName, $ev->getBlock(), $this->radius, $this->level, $this->allMobs);
                    $p->sendMessage(TF::GREEN . "Successfully added a protection center block with radius " . $this->radius . "!");
                    unset($this->blockName);
                    unset($this->radius);
                    unset($this->level);
                    unset($this->allMobs);
                    $ev->setCancelled();
                }else{
                    $p->sendMessage(TF::RED . "A protection center block at that location already exists!");
                }
            }
        }
    }
}
