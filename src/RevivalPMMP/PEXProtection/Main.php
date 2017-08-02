<?php

namespace RevivalPMMP\PEXProtection;

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
    
	public $centers;
	public $tapping;
	
    public function onEnable() {
        $this->getServer()->getLogger()->info(TF::GREEN . "PEXProtector activated!");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
        $this->centers = new Config($this->getDataFolder() . "centers.yml", Config::YAML, [
        	"Disabled-Worlds" => []
        ]);
    }
    
    public function isCenterBlock($blockname) {
        if($this->centers->get($blockname) != null) {
            return true;
        }
    }
    
    public function isCenterBlockLocation($location) {
        foreach($this->centers->getAll() as $center) {
	    if(!$center === $this->centers->get("Disabled-Worlds")) {	
                if($center["x"] == $location->x && $center["y"] == $location->y && $center["z"] == $location->z && $location->getLevel()->getName() === $center["level"]) {
                    return true;
                }
	    }
        }
    }
    
    public function setCenterBlock($blockname, $location, $radius) {
        $this->centers->set($blockname, [
        "x" => $location->getX(),
        "y" => $location->getY(),
        "z" => $location->getZ(),
        "level" => $this->level->getName(),
        "radius" => $radius]);
        $this->centers->save();
    }
    
    public function inTapMode(Player $p): bool {
        if(isset($this->tapping[$p->getName()])) {
            return true;
        }
        return false;
    }
    
    public function onCreatureSpawn(CreatureSpawnEvent $event) {
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
    
    public function onCommand(CommandSender $p, Command $cmd, string $label, array $args) : bool {
        if($cmd->getName() === "pexprotection") {
            if($p->hasPermission("pexprotection.command")) {
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
                                    $this->level = $p->getLevel();
                                    $this->blockname = $args[1];
                                    $this->radius = $args[2];
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
	                    		$p->sendMessage(TF::GREEN . "Successfully added a world to disable monster spawning.");
			                    $worlds = $this->centers->get("Disabled-Worlds");
			                    $worlds[] = $args[1];
			                    $this->centers->set("Disabled-Worlds", $worlds);
			                    $this->centers->save();
		                    }
		                    return true;
	                    
                        case "delete":
                        case "del":
                        case "remove":
                        case "rem":
                        case "clear":
                            if($this->isCenterBlock($args[1])) {

                                $p->sendMessage(TF::AQUA . "Succesfully removed the protection center block " . $args[1] . "!");
                                $this->centers->remove($args[1]);
                                $this->centers->save();
                            } else {
                                $p->sendMessage(TF::RED . "That protection center block name does not exist.");
                            }
                            return true;
                    }
                } else {
                    $p->sendMessage(TF::RED . "Please provide a valid protection center block name!");
                }
            }
        }
    }
    
    public function onInteract(PlayerInteractEvent $ev) {
        $p = $ev->getPlayer();
        if($this->inTapMode($p)) {
            if(!$this->isCenterBlockLocation($ev->getBlock())) {
                $p->sendMessage(TF::GREEN . "Succesfully added a protection center block with radius" . $this->radius . "!");
                unset($this->tapping[$p->getName()]);
                $this->setCenterBlock($this->blockname, $ev->getBlock(), $this->radius, $this->level);
                $ev->setCancelled();
            } else {
                $p->sendMessage(TF::RED . "A protection center block at that location already exists!");
            }
        }
    }
}
