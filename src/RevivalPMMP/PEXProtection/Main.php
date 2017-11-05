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
	private $tapping;

	/** @var string */
	private $level;

	/** @var string */
    private $blockName;

    /** @var int */
    private $radius;

    /** @var bool */
    private $allMobs;

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->centers = new Config($this->getDataFolder() . "centers.yml", Config::YAML);
        $this->getServer()->getLogger()->info(TF::GREEN . "PEXProtector Enabled!");
    }

    /**
     * @param string $blockName
     * @return bool
     */
    public function isCenterBlock(string $blockName) {
        return $this->centers->exists($blockName);
    }

    /**
     * @param Block $location
     * @return bool
     */
    public function isCenterBlockLocation(Block $location) {
        foreach($this->centers->getAll() as $center) {
            if($center["xPos"] === $location->x
                && $center["yPos"] === $location->y
                && $center["zPos"] === $location->z
                && $location->getLevel()->getName() === $center["level"]) {
                return true;
            }
        }
        return false;
    }

    /**
     * Adds passed parameters to centers.yml file
     *
     * @param string $blockName
     * @param Block $location
     * @param int $radius
     * @param string $level
     * @param bool $allMobs
     */
    public function setCenterBlock(string $blockName, Block $location, int $radius, string $level, bool $allMobs) {
        $this->centers->set($blockName, array(
            "xPos" => $location->getX(),
            "yPos" => $location->getY(),
            "zPos" => $location->getZ(),
            "level" => $level,
            "radius" => $radius,
            "allmobs" => $allMobs
        ));
        $this->centers->save();
    }

    /**
     * @param Player $p
     * @return bool
     */
    public function inTapMode(Player $p): bool {
        if(isset($this->tapping[$p->getName()])) {
            return true;
        }
        return false;
    }

    /**
     * @param CreatureSpawnEvent $event
     */
    public function onCreatureSpawn(CreatureSpawnEvent $event) {
    	if(in_array($event->getLevel()->getName(), $this->getConfig()->get("Disabled-Worlds"))) {
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
            if(($entity->distance($pos) < $centerInfo["radius"] && $centerInfo["level"] === $event->getLevel()->getName()) || in_array($event->getLevel()->getName(), $this->getConfig()->get("Disabled-Worlds"))) {
                if(strcmp(strtolower($event->getType()), "monster") == 0 || $centerInfo["allmobs"]) {
                    $event->setCancelled();
                }
            }
	    }
    }

    /**
     * @param CommandSender $p
     * @param Command $cmd
     * @param string $label
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $p, Command $cmd, string $label, array $args) : bool {
        if($cmd->getName() === "pexprotection") {
            if($p->hasPermission("pexprotection.command") && $p instanceof Player) {
                if(isset($args[1])) {

                    switch($args[0]) {
                        case "add":
                        case "set":
                        case "create":
                        case "make":
                            if(!$this->isCenterBlock($args[1])) {
                                if(isset($args[2])) {
                                    $p->sendMessage(TF::AQUA . "Tap a block to add a protection center block with the name " . $args[1] . "!");
                                    $this->tapping = array();
                                    $this->tapping[$p->getName()] = $p->getName();
                                    $this->level = $p->getLevel()->getName();
                                    $this->blockName = $args[1];
                                    $this->radius = $args[2];
                                    if(isset($args[3])) {
                                        $this->allMobs = (strcmp(strtolower($args[3]), "true") == 0) ? true : false;
                                    } else {
                                        if ($this->getConfig()->exists("All-Mobs")) {
                                            $this->allMobs = $this->getConfig()->get("All-Mobs");
                                        } else {
                                            $this->allMobs = true;
                                        }
                                    }
                                } else {
                                    $p->sendMessage(TF::RED .  "You have to define a radius for the protection center block!");
                                }
                            } else {
                                $p->sendMessage(TF::RED . "A protection center block with that name already exists");
                            }
                            return true;
                        
	                    case "disableworld":
	                    case "world":
	                    	if(isset($args[1])) {
			                    $worlds = $this->getConfig()->get("Disabled-Worlds");
			                    $worlds[] = $args[1];
			                    $this->getConfig()->set("Disabled-Worlds", $worlds);
			                    $this->saveDefaultConfig();
                                $p->sendMessage(TF::GREEN . "Successfully added a world to disable monster spawning.");
		                    }
		                    return true;
	                    
                        case "delete":
                        case "del":
                        case "remove":
                        case "rem":
                        case "clear":
                            if($this->isCenterBlock($args[1])) {
                                $this->centers->remove($args[1]);
                                $this->centers->save();
                                $p->sendMessage(TF::AQUA . "Successfully removed the protection center block " . $args[1] . "!");
                            } else {
                                $p->sendMessage(TF::RED . "That protection center block name does not exist.");
                            }
                            return true;
                    }
                } else {
                    $p->sendMessage(TF::RED . "Please provide a valid protection center block name!");
                }
            } else {
                $this->getServer()->getLogger()->info(TF::RED . "PEXProtector commands must be used by players.");
                return true;
            }
        }
        return false;
    }

    /**
     * @param PlayerInteractEvent $ev
     */
    public function onInteract(PlayerInteractEvent $ev) {
        $p = $ev->getPlayer();
        if($this->inTapMode($p)) {
            if(!$this->isCenterBlockLocation($ev->getBlock())) {
                unset($this->tapping[$p->getName()]);
                $this->setCenterBlock($this->blockName, $ev->getBlock(), $this->radius, $this->level, $this->allMobs);
                $p->sendMessage(TF::GREEN . "Successfully added a protection center block with radius " . $this->radius . "!");
                unset($this->blockName);
                unset($this->radius);
                unset($this->level);
                unset($this->allMobs);
                $ev->setCancelled();
            } else {
                $p->sendMessage(TF::RED . "A protection center block at that location already exists!");
            }
        }
    }
}